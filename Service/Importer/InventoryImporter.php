<?php

namespace SolidBase\GoogleDriveImporter\Service\Importer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Inventory\Model\SourceItemFactory;
use Magento\Inventory\Model\SourceItem\Command\GetSourceItemsBySku;
use Magento\Inventory\Model\SourceItem\Command\SourceItemsSave;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Psr\Log\LoggerInterface;
use SolidBase\GoogleDriveImporter\Api\DataProcessorInterface;

class InventoryImporter implements DataProcessorInterface
{
    private LoggerInterface $logger;
    private GetSourceItemsBySku $sourceItemsBySku;
    private SourceItemsSave $sourceItemsSave;
    private SourceItemFactory $sourceItemFactory;
    private ProductRepositoryInterface $productRepository;
    private IndexerRegistry $indexerRegistry;

    public function __construct(
        LoggerInterface $logger,
        GetSourceItemsBySku $sourceItemsBySku,
        SourceItemsSave $sourceItemsSave,
        SourceItemFactory $sourceItemFactory,
        ProductRepositoryInterface $productRepository,
        IndexerRegistry $indexerRegistry
    ) {
        $this->logger = $logger;
        $this->sourceItemsBySku = $sourceItemsBySku;
        $this->sourceItemsSave = $sourceItemsSave;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->productRepository = $productRepository;
        $this->indexerRegistry = $indexerRegistry;
    }

    public function process(array $data): array
    {
        $sourceItemsToSave = [];
        $sourceItemsData = $data['source_items'];
        $parentChildMapping = $data['parent_child_mapping'];

        unset($data['source_items']);
        unset($data['parent_child_mapping']);

        $totalRows = \count($data);
        foreach ($data as $idx => $row) {
            $sku = $row['sku'];
            $idx++;

            $sourceItemData = $sourceItemsData[$sku];
            if (isset($parentChildMapping[$sku])) {
                $childSkus = $parentChildMapping[$sku];
                $sourceItemData['quantity'] = \array_reduce($childSkus, function ($carry, $childSku) use ($sourceItemsData) {
                    return $carry + $sourceItemsData[$childSku]['quantity'];
                }, 0);;
                $sourceItemData['status'] = $sourceItemData['quantity'] > 0;
            }

            /** @var SourceItemInterface $sourceItem */
            $inventorySourceItems = $this->sourceItemsBySku->execute($sku);
            if (empty($inventorySourceItems)) {
                $sourceItem = $this->sourceItemFactory->create();
                $sourceItem->setSourceCode($sourceItemData['source_code']);
                $inventorySourceItems[] = $sourceItem;
            }

            foreach ($inventorySourceItems as $sourceItem) {
                if ($sourceItem->getSourceCode() === $sourceItemData['source_code']) {
                    $sourceItem->setQuantity($sourceItemData['quantity']);
                    $sourceItem->setSku($sku);
                    $sourceItem->setSourceCode($sourceItemData['source_code']);
                    $sourceItem->setStatus($sourceItemData['status']);
                }
                $sourceItemsToSave[] = $sourceItem;
            }

            $this->logger->info(
                __('[%idx/%total] Queued save for source item [source code: "%source_code", qty: "%qty", status: "%status"] for product with SKU=%sku', [
                    'idx' => $idx,
                    'total' => $totalRows,
                    'sku' => $sku,
                    'source_code' => $sourceItemData['source_code'],
                    'qty' => $sourceItemData['quantity'],
                    'status' => $sourceItemData['status'],
                ])
            );
        }

        try {
            $this->sourceItemsSave->execute($sourceItemsToSave);
            $this->logger->info(__('Saved %count source items', ['count' => \count($sourceItemsToSave)]));

            $skus = \array_map(fn ($sourceItem) => $sourceItem->getSku(), $sourceItemsToSave);
            foreach (['inventory', 'cataloginventory_stock'] as $indexerId) {
                $this->indexerRegistry->get($indexerId)->reindexList($skus);
                $this->logger->info(__('Reindexed %indexer index', ['indexer' => $indexerId]));
            }
        } catch (\Exception $e) {
            $this->logger->error(__($e->getMessage()));
        }

        return $data;
    }
}
