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
 * POST mago_mcp/tokens/delete
 * Body: id
 */
class Delete extends Action implements HttpPostActionInterface
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

        $this->tokenRepository->delete($entityId);
        $this->messageManager->addSuccessMessage(__('Token deleted.'));

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
