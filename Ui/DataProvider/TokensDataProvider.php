<?php
/**
 * Copyright © Made by Mouses
 */
declare(strict_types=1);

namespace Mbm\MagoMcp\Ui\DataProvider;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\DocumentFactory;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Mbm\MagoMcp\Model\TokenRepository;

class TokensDataProvider extends DataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        private readonly TokenRepository $tokenRepository,
        private readonly SearchResultFactory $searchResultFactory,
        private readonly DocumentFactory $documentFactory,
        private readonly AttributeValueFactory $attributeValueFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getRows(): array
    {
        $rows = [];
        foreach ($this->tokenRepository->getAll() as $row) {
            $rows[] = [
                'entity_id' => $row['entity_id'],
                'admin_user_id' => $row['admin_user_id'],
                'admin_username' => trim($row['firstname'] . ' ' . $row['lastname'])
                    . ' (' . $row['username'] . ')',
                'label' => $row['label'],
                'is_active' => (int) $row['is_active'],
                'created_at' => $row['created_at'],
                'last_used_at' => $row['last_used_at'],
            ];
        }
        return $rows;
    }

    public function getSearchResult(): SearchResultInterface
    {
        $documents = [];
        foreach ($this->getRows() as $row) {
            $doc = $this->documentFactory->create();
            $doc->setId($row['entity_id']);
            $attributes = [];
            foreach ($row as $key => $value) {
                $attr = $this->attributeValueFactory->create();
                $attr->setAttributeCode($key);
                $attr->setValue($value);
                $attributes[$key] = $attr;
            }
            $doc->setCustomAttributes($attributes);
            $documents[] = $doc;
        }

        $searchResult = $this->searchResultFactory->create();
        $searchResult->setItems($documents);
        $searchResult->setTotalCount(count($documents));
        $searchResult->setSearchCriteria($this->getSearchCriteria());

        return $searchResult;
    }

    public function getData(): array
    {
        $rows = $this->getRows();
        return [
            'totalRecords' => count($rows),
            'items' => $rows,
        ];
    }
}
