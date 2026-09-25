<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;

/**
 * "Generate token" form shown above the MCP Users grid.
 */
class TokenGenerate extends Template
{
    public function __construct(
        Context $context,
        private readonly ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAdminUsers(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $userTable = $this->resourceConnection->getTableName('admin_user');

        $select = $connection->select()
            ->from($userTable, ['user_id', 'username', 'firstname', 'lastname'])
            ->where('is_active = ?', 1)
            ->order('username ASC');

        return $connection->fetchAll($select);
    }

    public function getGenerateUrl(): string
    {
        return $this->getUrl('mago_mcp/tokens/generate');
    }
}
