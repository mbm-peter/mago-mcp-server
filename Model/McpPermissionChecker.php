<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Model;

use Magento\Framework\App\ResourceConnection;
use MagoAssistant\Mago\Service\Skills\PermissionChecker;

/**
 * Per-admin-user permission checks for the MCP route.
 *
 * Extends MagoAssistant's own PermissionChecker so ToolRegistry's concrete
 * type-hint is satisfied and per-user overrides in mago_skill_permission
 * (set via Mago Assistant > Skills & Permissions, same grid the admin chat
 * panel reads) apply identically here. Only the ACL *fallback* differs: the
 * parent's fallback calls AuthorizationInterface::isAllowed(), which resolves
 * the "current" role off the admin session/cookie — there is none on the MCP
 * route, so it would always deny. AdminAclResolver replaces that fallback
 * with a direct role lookup for the specific admin_user_id the Bearer token
 * resolved to, so the fallback still reflects that admin's real role-based
 * ACL grant instead of always failing closed.
 */
class McpPermissionChecker extends PermissionChecker
{
    /** @var array<int, array<string, string>> */
    private array $permissionsByUser = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly AdminAclResolver $aclResolver
    ) {
        // Deliberately does not call parent::__construct(): the parent's
        // AuthorizationInterface dependency is session-bound and unused here.
    }

    public function isAllowed(int $adminUserId, string $skillName, string $action = 'read'): bool
    {
        $permission = $this->getPermission($adminUserId, $skillName);

        if ($permission !== null) {
            return match ($permission) {
                'write' => true,
                'read' => $action === 'read',
                default => false,
            };
        }

        $resource = $action === 'write'
            ? 'MagoAssistant_Mago::assistant_write'
            : 'MagoAssistant_Mago::assistant_read';

        return $this->aclResolver->isAllowed($adminUserId, $resource);
    }

    private function getPermission(int $adminUserId, string $skillName): ?string
    {
        if (!isset($this->permissionsByUser[$adminUserId])) {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('mago_skill_permission');

            $this->permissionsByUser[$adminUserId] = $connection->fetchPairs(
                $connection->select()
                    ->from($table, ['skill_name', 'permission'])
                    ->where('admin_user_id = ?', $adminUserId)
            );
        }

        return $this->permissionsByUser[$adminUserId][$skillName] ?? null;
    }
}
