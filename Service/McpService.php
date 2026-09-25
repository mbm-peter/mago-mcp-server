<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Service;

use MagoAssistant\Mago\Api\Tool\IrreversibleToolInterface;
use MagoAssistant\Mago\Api\Tool\ToolInterface;
use MagoAssistant\Mago\Service\Tool\ToolRegistry;
use Mbm\MagoMcp\Model\Config;

/**
 * Minimal JSON-RPC 2.0 handler implementing the Model Context Protocol
 * "tools" surface over Mago Assistant's own ToolRegistry, so the same
 * skills the admin chat panel uses are reachable outside the admin
 * session (e.g. from an editor's or agent's MCP client) at /mcp/mago.
 *
 * Supported methods: initialize, notifications/initialized, tools/list, tools/call.
 *
 * Each request is scoped to the admin user whose Bearer token was presented
 * (resolved by the controller via TokenRepository and passed into handle()).
 * ToolRegistry is wired here with McpPermissionChecker (etc/di.xml), so the
 * same per-user mago_skill_permission grants and ACL fallback the admin chat
 * panel honors for that admin apply identically over MCP — a token only ever
 * unlocks what its own admin user could already do. The module's own
 * "Allow Write Tools" toggle is an additional, global gate on top: write
 * actions require both the calling admin's own write grant AND the toggle.
 */
class McpService
{
    private const PROTOCOL_VERSION = '2024-11-05';
    private const SERVER_NAME = 'mago-mcp';

    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly Config $config
    ) {
    }

    /**
     * Handle one JSON-RPC request (or notification) and return the response
     * payload, or null when no response is required (a notification).
     *
     * @param array<string, mixed> $request
     * @param int $adminUserId The admin user the caller's Bearer token resolved to
     * @return array<string, mixed>|null
     */
    public function handle(array $request, int $adminUserId): ?array
    {
        $id = $request['id'] ?? null;
        $method = (string) ($request['method'] ?? '');
        $params = is_array($request['params'] ?? null) ? $request['params'] : [];

        try {
            $result = match ($method) {
                'initialize' => $this->initialize(),
                'tools/list' => $this->toolsList($adminUserId),
                'tools/call' => $this->toolsCall($params, $adminUserId),
                'ping' => [],
                'notifications/initialized' => null,
                default => throw new \RuntimeException("Method not found: {$method}", -32601),
            };
        } catch (\Throwable $e) {
            if ($id === null) {
                // Notifications never get a response, error or otherwise.
                return null;
            }
            $code = $e->getCode() ?: -32000;
            return $this->errorResponse($id, (int) $code, $e->getMessage());
        }

        if ($id === null) {
            return null;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function initialize(): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'version' => '1.0.0',
            ],
            'capabilities' => [
                'tools' => new \stdClass(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolsList(int $adminUserId): array
    {
        $tools = [];
        foreach ($this->toolRegistry->getToolDefinitions($adminUserId) as $definition) {
            $tools[] = [
                'name' => $definition['name'],
                'description' => $definition['description'],
                'inputSchema' => $definition['parameters'],
            ];
        }
        return ['tools' => $tools];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function toolsCall(array $params, int $adminUserId): array
    {
        $name = (string) ($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        // getTool() (not getToolByName()) filters by this admin's own enabled/available
        // tools, so a token can never reach a tool its admin user has no grant for at all.
        $tool = $this->toolRegistry->getTool($name, $adminUserId);
        if ($tool === null) {
            throw new \RuntimeException("Unknown or unavailable tool: {$name}", -32602);
        }

        $isReadOnly = $tool->isReadOnlyAction($arguments);

        if (!$isReadOnly && !$this->config->isWriteAccessAllowed()) {
            return $this->toolErrorContent(
                'Write tools are disabled for the MCP server. Enable "Allow Write Tools" under '
                . 'Stores > Configuration > Mago Assistant > MCP Server to allow this call.'
            );
        }

        if (!$this->toolRegistry->isCallAllowed($tool, $arguments, $adminUserId)) {
            return $this->toolErrorContent(
                'Your admin account does not have permission to perform this action. '
                . 'Ask an administrator to grant it under Mago Assistant > Skills & Permissions.'
            );
        }

        // Writes require an explicit confirmation round-trip: the client must resend the same
        // call with arguments.confirm = true after showing the impacts to its own user. This
        // mirrors the admin chat panel's confirm-before-write UX, enforced server-side here
        // because MCP has no admin session or panel to render that prompt itself.
        $confirmed = (bool) ($arguments['confirm'] ?? false);
        if (!$isReadOnly && !$confirmed) {
            unset($arguments['confirm']);
            return $this->confirmationRequiredContent($tool, $arguments, $adminUserId);
        }
        unset($arguments['confirm']);

        $arguments['_admin_user_id'] = $adminUserId;

        try {
            $result = $tool->execute($arguments);
        } catch (\Throwable $e) {
            return $this->toolErrorContent($e->getMessage());
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ],
            'isError' => isset($result['error']),
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function confirmationRequiredContent(ToolInterface $tool, array $arguments, int $adminUserId): array
    {
        $impacts = $tool instanceof IrreversibleToolInterface
            ? $tool->getImpacts($arguments, $adminUserId)
            : [];

        $payload = [
            'requiresConfirmation' => true,
            'message' => 'This call would write data. Re-call the same tool with the same '
                . 'arguments plus "confirm": true to proceed.',
            'impacts' => $impacts,
        ];

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ],
            'isError' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolErrorContent(string $message): array
    {
        return [
            'content' => [
                ['type' => 'text', 'text' => $message],
            ],
            'isError' => true,
        ];
    }

    /**
     * @param int|string $id
     * @return array<string, mixed>
     */
    private function errorResponse(int|string $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
