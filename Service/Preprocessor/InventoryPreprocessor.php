<?php

namespace SolidBase\GoogleSheetsImporter\Service\Preprocessor;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Psr\Log\LoggerInterface;
use SolidBase\GoogleSheetsImporter\Api\DataProcessorInterface;

class InventoryPreprocessor implements DataProcessorInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(array $data): array
    {
        $this->logger->info('Inventory preprocessor started');

        $totalRows = \count($data);
        $data['skip_rows'] = [];
        $data['source_items'] = [];
        $data['parent_child_mapping'] = [];
        foreach($data as $idx => $row) {
            if ($idx === 'skip_rows' || $idx === 'source_items' | $idx === 'parent_child_mapping') {
                continue;
            }

            $idx++;
            $sku = $row['sku'];

            if (!\is_numeric($row['stock_qty']) || (float) $row['stock_qty'] < 0) {
                $data['skip_rows'][] = $idx;
                $this->logger->warning(
                    __('[%idx/%total] - Value for "stock_qty" ("%value") either below zero or not numeric for product with SKU=%sku -- skipping import', [
                        'idx' => $idx,
                        'total' => $totalRows,
                        'value' => $row['stock_qty'],
                        'sku' => $sku,
                    ])
                );
                continue;
            }

            if (empty($row['stock_source_code'])) {
                $row['stock_source_code'] = 'default';
            }

            if (!\is_string($row['stock_source_code'])) {
                $data['skip_rows'][] = $idx;
                $this->logger->warning(
                    __('[%idx/%total] - Value for "source_code" ("%value") either below zero or not numeric for product with SKU=%sku -- skipping import', [
                        'idx' => $idx,
                        'total' => $totalRows,
                        'value' => $row['stock_qty'],
                        'sku' => $sku,
                    ])
                );
                continue;
            }

            $sourceItem = [
                'sku' => $sku,
                'source_code' => $row['stock_source_code'],
                'quantity' => (float) $row['stock_qty'],
                'status' => (int) $row['stock_qty'] > 0
                    ? SourceItemInterface::STATUS_IN_STOCK
                    : SourceItemInterface::STATUS_OUT_OF_STOCK,
            ];
            $data['source_items'][$sku] = $sourceItem;

            $this->logger->info(
                __('[%idx/%total] - Preprocessed source item for product with SKU=%sku (source code: "%stock_source", qty: "%qty", is_in_stock: "%is_in_stock")',
                    [
                        'idx' => $idx,
                        'total' => $totalRows,
                        'sku' => $sku,
                        'stock_source' => $sourceItem['source_code'],
                        'qty' => $sourceItem['quantity'],
                        'is_in_stock' => $sourceItem['status'],
                    ]
                )
            );
        }

        $this->logger->info('Inventory preprocessor finished');

        return $data;
    }
}
