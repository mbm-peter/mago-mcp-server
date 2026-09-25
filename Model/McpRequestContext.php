<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Model;

/**
 * Holds the admin_user_id the current MCP request's Bearer token resolved
 * to, for the duration of that request. Set by Controller\Mago\Index before
 * dispatching into McpService; read by AuthorizationPlugin so the base
 * module's own session-bound ACL checks (AbstractSkill::execute) resolve
 * against that admin instead of a non-existent admin session.
 *
 * Safe as a shared singleton: one PHP-FPM worker serves one HTTP request at
 * a time, so there is no cross-request bleed. Outside an MCP request (i.e.
 * normal admin session traffic) this stays null and AuthorizationPlugin is
 * a complete no-op.
 */
class McpRequestContext
{
    private ?int $adminUserId = null;

    public function setAdminUserId(?int $adminUserId): void
    {
        $this->adminUserId = $adminUserId;
    }

    public function getAdminUserId(): ?int
    {
        return $this->adminUserId;
    }
}
