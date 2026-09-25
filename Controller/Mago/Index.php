<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Controller\Mago;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Mbm\MagoMcp\Model\Config;
use Mbm\MagoMcp\Model\TokenRepository;
use Mbm\MagoMcp\Service\McpService;

/**
 * POST /mcp/mago
 *
 * JSON-RPC 2.0 endpoint implementing the Model Context Protocol "tools"
 * surface for Mago Assistant's skills, reachable without an admin session.
 *
 * Authenticated with a per-admin-user Bearer token (Stores > Configuration >
 * Mago Assistant > MCP Server > MCP Users, or Mago Assistant > MCP Users
 * grid), not Magento admin cookies/ACL — every request is scoped to whichever
 * admin user the token was issued to (see McpService/McpPermissionChecker),
 * so multiple admins can each hold their own token with their own tool
 * permissions. This is a plain frontend route, deliberately exempt from
 * admin/form-key CSRF since there is no admin session to protect.
 */
class Index extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly TokenRepository $tokenRepository,
        private readonly McpService $mcpService,
        private readonly JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setHttpResponseCode(404)->setData([
                'jsonrpc' => '2.0',
                'error' => ['code' => -32001, 'message' => 'MCP server is disabled'],
            ]);
        }

        $adminUserId = $this->resolveAdminUserId();
        if ($adminUserId === null) {
            return $result->setHttpResponseCode(401)->setData([
                'jsonrpc' => '2.0',
                'error' => ['code' => -32002, 'message' => 'Unauthorized'],
            ]);
        }

        $body = json_decode((string) $this->getRequest()->getContent(), true);
        if (!is_array($body)) {
            return $result->setHttpResponseCode(400)->setData([
                'jsonrpc' => '2.0',
                'error' => ['code' => -32700, 'message' => 'Parse error'],
            ]);
        }

        // Batches (a JSON array of requests) are valid JSON-RPC; handle single
        // requests as the common case and batches by mapping over each entry.
        $isBatch = array_is_list($body) && isset($body[0]) && is_array($body[0]);
        $requests = $isBatch ? $body : [$body];

        $responses = [];
        foreach ($requests as $request) {
            $response = $this->mcpService->handle(is_array($request) ? $request : [], $adminUserId);
            if ($response !== null) {
                $responses[] = $response;
            }
        }

        if ($responses === []) {
            // All notifications: JSON-RPC requires no HTTP body.
            return $result->setHttpResponseCode(204)->setData([]);
        }

        return $result->setData($isBatch ? $responses : $responses[0]);
    }

    private function resolveAdminUserId(): ?int
    {
        $header = (string) $this->getRequest()->getHeader('Authorization');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return $this->tokenRepository->resolveAdminUserId(substr($header, 7));
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
