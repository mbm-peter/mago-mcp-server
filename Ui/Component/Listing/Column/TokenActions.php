<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class TokenActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (!isset($item['entity_id'])) {
                    continue;
                }

                $actions = [];
                if ((int) $item['is_active'] === 1) {
                    $actions['revoke'] = [
                        'href' => $this->urlBuilder->getUrl('mago_mcp/tokens/revoke', ['id' => $item['entity_id']]),
                        'label' => __('Revoke'),
                        'confirm' => [
                            'title' => __('Revoke Token'),
                            'message' => __('Are you sure you want to revoke this token? It will stop working immediately.'),
                        ],
                    ];
                }
                $actions['delete'] = [
                    'href' => $this->urlBuilder->getUrl('mago_mcp/tokens/delete', ['id' => $item['entity_id']]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete Token'),
                        'message' => __('Are you sure you want to delete this token record?'),
                    ],
                ];

                $item[$this->getData('name')] = $actions;
            }
        }

        return $dataSource;
    }
}
