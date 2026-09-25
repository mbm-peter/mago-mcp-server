# Mbm_MagoMcp — MCP server for Mago Assistant

Exposes [Mago Assistant](https://askmago.com)'s chat tools as a [Model Context
Protocol](https://modelcontextprotocol.io) server at `/mcp/mago`, so any MCP client (Hermes,
Claude Desktop, an editor, a CI job, etc.) can call the same skills the admin chat panel uses —
without an admin session, a browser, or the panel itself.

## Requirements

- Magento >= 2.4.9 or Mage-OS >= 3.0
- [`mago-assistant/mago`](https://github.com/mago-assistant/mago) installed and enabled — this
  module wraps its `ToolRegistry`, it does not reimplement any skill

## Installation

```bash
composer require mbm/module-mago-mcp
bin/magento module:enable Mbm_MagoMcp
bin/magento setup:upgrade
```

## Configuration

`Stores > Configuration > Mago Assistant > MCP Server`

| Field | Config path | Purpose |
|---|---|---|
| Enabled | `mago_mcp/general/enabled` | Master switch for the `/mcp/mago` route. Disabled by default. |
| Allow Write Tools | `mago_mcp/general/write_access` | Global gate on top of each admin's own write grant — when off, **no** write action may be called over MCP by anyone. Disabled by default. |

## Issuing tokens

Every admin who wants to use the MCP server needs their own token, from `Mago Assistant > MCP
Users`:

1. Pick the admin user and an optional label (e.g. "laptop", "CI job").
2. Click **Generate Token**. The plaintext token is shown **once**, in the success message — only
   its SHA-256 hash is stored, so if you lose it there's no recovery, just generate a new one.
3. Give that token to the MCP client as a Bearer token — see [Connecting a client](#connecting-a-client) below.
4. **Revoke** disables a token immediately without deleting its row (useful for a temporary
   pause); **Delete** removes it entirely.

## Connecting a client

Point the client at `<your-base-url>/mcp/mago` over HTTP(S) with the token as a Bearer header —
for example, in a Hermes `config.yaml`:

```yaml
mcp_servers:
  mago:
    url: https://your-store.example/mcp/mago
    headers:
      Authorization: Bearer <token-from-the-grid>
    timeout: 60
```

The endpoint implements JSON-RPC 2.0 `initialize`, `tools/list`, `tools/call`,
`notifications/initialized` and `ping` — standard MCP, nothing custom.

## Permission model

This is the core design point of this module: **a token only ever unlocks what its own admin
user could already do in the chat panel**, nothing more.

- `tools/list` and `tools/call` are both scoped to the `admin_user_id` the presented token
  resolved to. A token for an admin with no grant on a skill never sees or can call that skill.
- Per-skill overrides set under `Mago Assistant > Skills & Permissions` (the same grid the chat
  panel reads) apply identically here.
- Where a skill has no explicit override, the check falls back to that admin's actual Magento
  ACL role (`MagoAssistant_Mago::assistant_read` / `assistant_write`, and each skill's own native
  ACL resource, e.g. `Magento_Catalog::products` for creating a product) — resolved directly from
  their role, since there is no admin session/cookie on this route to read a "current user" from.
- A write call additionally requires the module's own **Allow Write Tools** toggle to be on, on
  top of the calling admin's own write grant. Either being off blocks the call.
- Every write call must be confirmed: the first call returns `requiresConfirmation: true` with
  any impacts; the client resends the same call with `arguments.confirm: true` to actually run
  it. This mirrors the chat panel's confirm-before-write UX, enforced server-side here because
  MCP has no panel to show that prompt itself.

## Architecture

```
Controller/Mago/Index          POST /mcp/mago — resolves the Bearer token to an admin_user_id
                                (TokenRepository), sets it on McpRequestContext for the
                                request, dispatches into McpService.
Service/McpService              JSON-RPC 2.0 <-> MagoAssistant\Mago\Service\Tool\ToolRegistry.
Model/TokenRepository            Per-admin tokens: only a SHA-256 hash is stored; plaintext is
                                 returned once, at generation.
Model/McpPermissionChecker       Extends the base module's own PermissionChecker; same
                                 mago_skill_permission grants, but its ACL fallback uses
                                 AdminAclResolver instead of a session-bound authorization check.
Model/AdminAclResolver           Looks an admin's role up directly (admin_user -> authorization_role)
                                 and checks it against the ACL tree, with no session required.
Model/McpRequestContext          Per-request holder for "which admin issued this MCP call" —
                                 read by AuthorizationPlugin.
Plugin/AuthorizationPlugin       Some base-module skills (e.g. product_management) make their
                                 own native-ACL check via the shared, session-bound
                                 Magento\Framework\Authorization, independent of ToolRegistry.
                                 This plugin redirects that one check to AdminAclResolver when
                                 McpRequestContext is set, and is a no-op otherwise.
etc/di.xml                       Wires a dedicated ToolRegistry virtualType with
                                 McpPermissionChecker (instead of the base module's own,
                                 session-bound PermissionChecker) plus the plugin above.
Controller/Adminhtml/Tokens/*    Admin grid: generate / revoke / delete tokens.
```

## Security notes

- Tokens are stored as a SHA-256 hash only (`mago_mcp_token.token_hash`); the plaintext is
  displayed exactly once, at generation time, in the "MCP Users" grid's success message.
- The route is deliberately outside `adminhtml` (no admin session, no admin cookie) and CSRF-exempt,
  since it is a Bearer-token API, not a browser form post — same posture as Magento's own REST API.
- Deleting or deactivating an admin user (`admin_user.is_active = 0`) immediately invalidates that
  admin's tokens too — `TokenRepository::resolveAdminUserId` requires both the token and the admin
  account to be active.
- There is no rate limiting or IP allowlisting built in; put this behind whatever network controls
  you'd put in front of any other Bearer-token API.

## License

Proprietary — Made by Mouses.
