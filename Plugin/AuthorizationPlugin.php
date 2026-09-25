<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Plugin;

use Magento\Framework\Authorization;
use Mbm\MagoMcp\Model\AdminAclResolver;
use Mbm\MagoMcp\Model\McpRequestContext;

/**
 * MagoAssistant's AbstractSkill::execute() does its own native-Magento-ACL
 * check on top of ToolRegistry's permission checker, via the shared,
 * session-bound Authorization::isAllowed(). That has no admin session to
 * resolve a role from on the MCP route, so it always denies -- this plugin
 * redirects that one check to AdminAclResolver for the specific admin the
 * current MCP request's Bearer token resolved to (McpRequestContext), and
 * is a complete no-op for every other (real admin session) request.
 */
class AuthorizationPlugin
{
    public function __construct(
        private readonly McpRequestContext $mcpRequestContext,
        private readonly AdminAclResolver $aclResolver
    ) {
    }

    public function aroundIsAllowed(
        Authorization $subject,
        callable $proceed,
        $resource,
        $privilege = null
    ): bool {
        $adminUserId = $this->mcpRequestContext->getAdminUserId();
        if ($adminUserId === null) {
            return $proceed($resource, $privilege);
        }

        return $this->aclResolver->isAllowed($adminUserId, (string) $resource);
    }
}
