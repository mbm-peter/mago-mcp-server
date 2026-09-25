<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Model;

use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Authorization\Model\Role;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Acl\Builder as AclBuilder;

/**
 * Resolves whether a given admin user's role grants a Magento ACL resource,
 * without an admin session/cookie to hang off of.
 *
 * Core's own \Magento\Framework\Authorization\Policy\Acl and AclRetriever both
 * assume a "current" role coming from a logged-in session (RoleLocatorInterface
 * or CurrentRoleContext bound to the request). On the MCP route there is no
 * admin session at all — the caller is identified purely by the Bearer token
 * resolved to an admin_user_id — so this looks the user's role up directly
 * from admin_user -> authorization_role and checks it against the ACL tree
 * built by AclBuilder, the same tree core's policy classes consult.
 */
class AdminAclResolver
{
    /** @var array<int, int|false> admin_user_id => role_id (false = no role found) */
    private array $roleIdByUser = [];

    public function __construct(
        private readonly RoleCollectionFactory $roleCollectionFactory,
        private readonly AclBuilder $aclBuilder
    ) {
    }

    public function isAllowed(int $adminUserId, string $resource): bool
    {
        $roleId = $this->getRoleId($adminUserId);
        if ($roleId === null) {
            return false;
        }

        try {
            $acl = $this->aclBuilder->getAcl();
            if (!$acl->hasResource($resource)) {
                return false;
            }
            return $acl->isAllowed((string) $roleId, $resource);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getRoleId(int $adminUserId): ?int
    {
        if (!array_key_exists($adminUserId, $this->roleIdByUser)) {
            $roleCollection = $this->roleCollectionFactory->create();
            /** @var Role $role */
            $role = $roleCollection->setUserFilter($adminUserId, UserContextInterface::USER_TYPE_ADMIN)
                ->getFirstItem();
            $this->roleIdByUser[$adminUserId] = $role->getId() ? (int) $role->getId() : false;
        }

        $roleId = $this->roleIdByUser[$adminUserId];
        return $roleId === false ? null : $roleId;
    }
}
