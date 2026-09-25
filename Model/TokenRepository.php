<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Math\Random;

/**
 * Per-admin-user MCP Bearer tokens.
 *
 * Only a SHA-256 hash of each token is stored; the plaintext is returned once,
 * at generation time, and never persisted or retrievable again (same
 * mechanism as Magento's own integration/webapi tokens).
 */
class TokenRepository
{
    private const TOKEN_BYTES = 32;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Random $random
    ) {
    }

    /**
     * Generate a new token for an admin user, returning the plaintext once.
     */
    public function generate(int $adminUserId, string $label = ''): string
    {
        $token = bin2hex($this->random->getRandomBytes(self::TOKEN_BYTES));

        $connection = $this->resourceConnection->getConnection();
        $connection->insert($this->resourceConnection->getTableName('mago_mcp_token'), [
            'admin_user_id' => $adminUserId,
            'token_hash' => $this->hash($token),
            'label' => $label !== '' ? $label : null,
            'is_active' => 1,
        ]);

        return $token;
    }

    /**
     * Resolve a presented Bearer token to the admin_user_id it was issued to,
     * or null when the token is unknown, revoked, or belongs to a disabled
     * admin account. Updates last_used_at on success.
     */
    public function resolveAdminUserId(string $token): ?int
    {
        if ($token === '') {
            return null;
        }

        $connection = $this->resourceConnection->getConnection();
        $tokenTable = $this->resourceConnection->getTableName('mago_mcp_token');
        $adminUserTable = $this->resourceConnection->getTableName('admin_user');

        $select = $connection->select()
            ->from(['t' => $tokenTable], ['entity_id', 'admin_user_id'])
            ->join(['u' => $adminUserTable], 'u.user_id = t.admin_user_id', [])
            ->where('t.token_hash = ?', $this->hash($token))
            ->where('t.is_active = ?', 1)
            ->where('u.is_active = ?', 1);

        $row = $connection->fetchRow($select);
        if (!$row) {
            return null;
        }

        $connection->update(
            $tokenTable,
            ['last_used_at' => new \Magento\Framework\DB\Sql\Expression('NOW()')],
            ['entity_id = ?' => $row['entity_id']]
        );

        return (int) $row['admin_user_id'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tokenTable = $this->resourceConnection->getTableName('mago_mcp_token');
        $adminUserTable = $this->resourceConnection->getTableName('admin_user');

        $select = $connection->select()
            ->from(['t' => $tokenTable])
            ->join(['u' => $adminUserTable], 'u.user_id = t.admin_user_id', ['username', 'firstname', 'lastname'])
            ->order('t.created_at DESC');

        return $connection->fetchAll($select);
    }

    public function revoke(int $entityId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->update(
            $this->resourceConnection->getTableName('mago_mcp_token'),
            ['is_active' => 0],
            ['entity_id = ?' => $entityId]
        );
    }

    public function delete(int $entityId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->delete(
            $this->resourceConnection->getTableName('mago_mcp_token'),
            ['entity_id = ?' => $entityId]
        );
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
