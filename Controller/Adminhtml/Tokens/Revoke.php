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
 * POST mago_mcp/tokens/revoke
 * Body: id
 */
class Revoke extends Action implements HttpPostActionInterface
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
        $entityId = (int) $this->getRequest()->getParam('id');
        if (!$entityId) {
            $this->messageManager->addErrorMessage(__('Token not found.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $this->tokenRepository->revoke($entityId);
        $this->messageManager->addSuccessMessage(__('Token revoked.'));

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
