# Mbm_MagoMcp — MCP server for Mago Assistant

Exposes [Mago Assistant](https://askmago.com)'s chat tools as a [Model Context
Protocol](https://modelcontextprotocol.io) server at `/mcp/mago`, so any MCP client (Hermes,
Claude Desktop, an editor, a CI job, etc.) can call the same skills the admin chat panel uses —
without an admin session, a browser, or the panel itself.

## Requirements

- Magento >= 2.4.9 or Mage-OS >= 3.0
- [`mago-assistant/mago`](https://github.com/mago-assistant/mago) installed and enabled

## Installation

```bash
composer require mbm/module-mago-mcp
bin/magento module:enable Mbm_MagoMcp
bin/magento setup:upgrade
```

## Configuration

`Stores > Configuration > Mago Assistant > MCP Server`

| Field | Purpose |
|---|---|
| Enabled | Master switch for the `/mcp/mago` route. Disabled by default. |
| Allow Write Tools | Global switch on top of each admin's own write grant — when off, no write action may be called over MCP by anyone. Disabled by default. |

## Issuing tokens

Every admin who wants to use the MCP server needs their own token, from `Mago Assistant > MCP
Users`:

1. Pick the admin user and an optional label (e.g. "laptop", "CI job").
2. Click **Generate Token**. The token is shown **once**, in the success message — if you lose
   it there's no recovery, just generate a new one.
3. Give that token to the MCP client as a Bearer token — see below.
4. **Revoke** disables a token immediately without deleting it (useful for a temporary pause);
   **Delete** removes it entirely.

## Connecting a client

Point the client at `<your-base-url>/mcp/mago` with the token as a Bearer header — for example,
in a Hermes `config.yaml`:

```yaml
mcp_servers:
  mago:
    url: https://your-store.example/mcp/mago
    headers:
      Authorization: Bearer <token-from-the-grid>
    timeout: 60
```

## Permissions

A token only ever unlocks what its own admin user could already do in the chat panel, nothing
more — the same permission grants set under `Mago Assistant > Skills & Permissions`, plus the
admin's own Magento role, apply identically here. Write actions additionally need the module's
"Allow Write Tools" switch on, and every write call must be confirmed before it actually runs.

## License

Proprietary — Made by Mouses.
