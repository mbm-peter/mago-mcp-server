<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Controller\Adminhtml\Tokens;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Mbm\MagoMcp\Model\TokenRepository;

/**
 * POST mago_mcp/tokens/generate
 * Body: admin_user_id, label (optional)
 *
 * The generated plaintext token is shown exactly once, in the success
 * message, and is never stored or retrievable again — only its SHA-256 hash
 * is persisted (see TokenRepository).
 */
class Generate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Mbm_MagoMcp::tokens_manage';

    public function __construct(
        Context $context,
        private readonly TokenRepository $tokenRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $adminUserId = (int) $this->getRequest()->getParam('admin_user_id');
        $label = trim((string) $this->getRequest()->getParam('label', ''));

        if (!$adminUserId) {
            $this->messageManager->addErrorMessage(__('Select an admin user.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        try {
            $token = $this->tokenRepository->generate($adminUserId, $label);
            $this->messageManager->addSuccessMessage(
                __(
                    'Token generated. Copy it now — it will not be shown again: %1',
                    $token
                )
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Error generating token: %1', $e->getMessage())
            );
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
