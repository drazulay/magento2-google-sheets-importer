<?php

namespace SolidBase\GoogleDriveImporter\Service;

use Google\Service\Exception;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use SolidBase\GoogleDriveImporter\Api\StageManagementInterface;
use SolidBase\GoogleDriveImporter\Service\Preprocessor\AttributePreprocessorFactory;
use SolidBase\GoogleDriveImporter\Service\Preprocessor\CategoryPreprocessorFactory;
use SolidBase\GoogleDriveImporter\Service\Preprocessor\InventoryPreprocessorFactory;
use SolidBase\GoogleDriveImporter\Service\Preprocessor\ProductPreprocessorFactory;
use SolidBase\GoogleDriveImporter\Service\Importer\AttributeImporterFactory;
use SolidBase\GoogleDriveImporter\Service\Importer\CategoryImporterFactory;
use SolidBase\GoogleDriveImporter\Service\Importer\ProductImporterFactory;
use SolidBase\GoogleDriveImporter\Service\Importer\InventoryImporterFactory;

class Importer
{
    private array $data = [];
    private array $skipRows = [];
    private array $skipSkus = [];
    private LoggerInterface $logger;
    private SheetDbClient $sheets;
    private AttributePreprocessorFactory $attributePreprocessorFactory;
    private CategoryPreprocessorFactory $categoryPreprocessorFactory;
    private ProductPreprocessorFactory $productPreprocessorFactory;
    private InventoryPreprocessorFactory $inventoryPreprocessorFactory;
    private AttributeImporterFactory $attributeImporterFactory;
    private CategoryImporterFactory $categoryImporterFactory;
    private ProductImporterFactory $productImporterFactory;
    private InventoryImporterFactory $inventoryImporterFactory;
    private StageManagementInterface $stageManagement;
    private Manager $cacheManager;

    public function __construct(
        LoggerInterface $logger,
        SheetDbClient $sheets,
        AttributePreprocessorFactory $attributeFactory,
        CategoryPreprocessorFactory $categoryFactory,
        ProductPreprocessorFactory $productFactory,
        InventoryPreprocessorFactory $inventoryFactory,
        AttributeImporterFactory $attributeImporterFactory,
        CategoryImporterFactory $categoryImporterFactory,
        ProductImporterFactory $productImporterFactory,
        InventoryImporterFactory $inventoryImporterFactory,
        StageManagementInterface $stageManagement,
        Manager $cacheManager
    ) {
        $this->logger = $logger;
        $this->sheets = $sheets;
        $this->attributePreprocessorFactory = $attributeFactory;
        $this->categoryPreprocessorFactory = $categoryFactory;
        $this->productPreprocessorFactory = $productFactory;
        $this->inventoryPreprocessorFactory = $inventoryFactory;
        $this->attributeImporterFactory = $attributeImporterFactory;
        $this->categoryImporterFactory = $categoryImporterFactory;
        $this->productImporterFactory = $productImporterFactory;
        $this->inventoryImporterFactory = $inventoryImporterFactory;
        $this->stageManagement = $stageManagement;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @throws Exception
     * @throws FileSystemException
     * @throws LocalizedException
     * @throws \Exception
     * @throws \Google\Exception
     */
    public function run(): self
    {
        $this->fetchData();
        $this->preprocess();
        $this->import();
        $this->flushCaches();

        $this->logger->info(__('Import completed'));

        return $this;
    }

    protected function flushCaches(): void
    {
        foreach ([
//                'config',
//                'layout',
                'block_html',
                'collections',
                'reflection',
                'db_ddl',
                'eav',
//                'config_integration',
//                'config_integration_api',
                'full_page',
//                'config_webservice'
        ] as $type) {
            $this->cacheManager->flush([$type]);
            $this->logger->info(__('Cache %cache flushed', ['cache' => $type]));
        }
    }

    protected function fetchData(): void
    {
        $this->stageManagement->setStage(StageManagementInterface::STAGE_PREPARE);
        foreach(StageManagementInterface::STAGES as $sheet) {
            if ($sheet === StageManagementInterface::STAGE_PREPARE) {
                continue;
            }

            $data = $this->sheets->getSpreadsheetValues($sheet);
            $this->data[$sheet] = $data;
        }

        $this->logger->info('Data fetched');
    }

    protected function preprocess(): void
    {
        foreach (StageManagementInterface::STAGES as $stage) {
            switch ($stage) {
                case StageManagementInterface::STAGE_PREPARE:
                    break;
                case StageManagementInterface::STAGE_IMPORT_ATTRIBUTES:
                    try {
                        $data = $this->attributePreprocessorFactory->create()->process(
                            $this->data[StageManagementInterface::STAGE_IMPORT_ATTRIBUTES]
                        );
                    } catch (\Exception $e) {
                        $this->logger->error(
                            $e->getMessage()
                        );
                        throw $e;
                    }
                    break;
                case StageManagementInterface::STAGE_IMPORT_CATEGORIES:
                    try {
                        $data = $this->categoryPreprocessorFactory->create()->process(
                            $this->data[StageManagementInterface::STAGE_IMPORT_CATEGORIES]
                        );
                    } catch (\Exception $e) {
                        $this->logger->error(
                            $e->getMessage()
                        );
                        throw $e;
                    }
                    break;
                case StageManagementInterface::STAGE_IMPORT_PRODUCTS:
                    try {
                        $data = $this->productPreprocessorFactory->create()->process(
                            $this->data[StageManagementInterface::STAGE_IMPORT_PRODUCTS]
                        );
                    } catch (\Exception $e) {
                        $this->logger->error(
                            $e->getMessage()
                        );
                        throw $e;
                    }
                    break;
                case StageManagementInterface::STAGE_IMPORT_INVENTORY:
                    try {
                        $data = $this->inventoryPreprocessorFactory->create()->process(
                            $this->data[StageManagementInterface::STAGE_IMPORT_INVENTORY]
                        );
                    } catch (\Exception $e) {
                        $this->logger->error(
                            $e->getMessage()
                        );
                        throw $e;
                    }
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid stage: ' . $stage);
            }

            if (isset($data['skip_rows'])) {
                $this->skipRows = \array_merge($this->skipRows, $data['skip_rows']);
                unset($data['skip_rows']);
            }
            if (isset($data['skip_skus'])) {
                $this->skipSkus = \array_merge($this->skipSkus, $data['skip_skus']);
                unset($data['skip_skus']);
            }

            if ($stage !== StageManagementInterface::STAGE_PREPARE) {
                $this->data[$stage] = $data;
            }
        }

        if (!empty($this->skipRows)) {
            $this->logger->warning(
                __('Products at the following rows had data errors and will not be imported: %skus', [
                    'skus' => \implode(
                        ', ',
                        $this->skipRows
                    )
                ])
            );
        }

        if (!empty($this->skipSkus)) {
            $this->logger->warning(
                __('Products with the following SKUs had data errors and will not be imported: %skus', [
                    'skus' => \implode(
                        ', ',
                        $this->skipSkus
                    )
                ])
            );
        }

        foreach ($this->data as $stage => $data) {
            $newStageData = [];
            foreach ($this->data[$stage] as $idx => $row) {
                if (!\in_array($idx, $this->skipRows, true)) {
                    $newStageData[$idx] = $row;
                    continue;
                }
                if (!\in_array($row['sku'], $this->skipSkus, true)) {
                    $newStageData[$idx] = $row;
                }
            }

            $this->data[$stage] = $newStageData;
        }

        $this->logger->info('Data pre-processed');
    }

    /**
     * @throws \Exception
     */
    protected function import(): void
    {
        foreach (StageManagementInterface::STAGES as $stage) {
            if ($stage !== StageManagementInterface::STAGE_PREPARE) {
                $this->stageManagement->setStage($stage);
            }
            $parentChildMapping = null;

            switch ($stage) {
                case StageManagementInterface::STAGE_PREPARE:
                    break;
                case StageManagementInterface::STAGE_IMPORT_ATTRIBUTES:
                    try {
                        $this->attributeImporterFactory->create()->process($this->data[$stage]);
                    } catch (\Exception $e) {
                        $this->logger->error(
                            $e->getMessage()
                        );
                        throw $e;
                    }
                    break;
                case StageManagementInterface::STAGE_IMPORT_CATEGORIES:
                    try {
                        $this->categoryImporterFactory->create()->process($this->data[$stage]);
                    } catch (\Exception $e) {
                        $this->logger->error(
                            $e->getMessage()
                        );
                        throw $e;
                    }
                    break;
                case StageManagementInterface::STAGE_IMPORT_PRODUCTS:
                    try {
                        $parentChildMapping = $this->productImporterFactory->create()->process($this->data[$stage]);
                        $nextStage = $this->stageManagement->getNextStage($stage);
                        if (null !== $parentChildMapping) {
                            $this->data[$nextStage]['parent_child_mapping'] = $parentChildMapping;
                        }
                    } catch (\Exception $e) {
                        $this->logger->error(
                            $e->getMessage()
                        );
                        throw $e;
                    }
                    break;
                case StageManagementInterface::STAGE_IMPORT_INVENTORY:
                    try {
                        $this->inventoryImporterFactory->create()->process($this->data[$stage]);
                    } catch (\Exception $e) {
                        $this->logger->error(
                            $e->getMessage()
                        );
                        throw $e;
                    }
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid stage');
            }
        }
    }
}
