<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Config helper for Mbm_MagoMcp.
 */
class Config
{
    private const XML_PATH_ENABLED = 'mago_mcp/general/enabled';
    private const XML_PATH_API_KEY = 'mago_mcp/general/api_key';
    private const XML_PATH_WRITE_ACCESS = 'mago_mcp/general/write_access';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
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

    public function getApiKey(?int $storeId = null): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value !== '' ? $this->encryptor->decrypt($value) : '';
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
