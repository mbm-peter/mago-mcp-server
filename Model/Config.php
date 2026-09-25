<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Config helper for Mbm_MagoMcp.
 */
class Config
{
    private const XML_PATH_ENABLED = 'mago_mcp/general/enabled';
    private const XML_PATH_WRITE_ACCESS = 'mago_mcp/general/write_access';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isWriteAccessAllowed(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->isSetFlag(
            self::XML_PATH_WRITE_ACCESS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
