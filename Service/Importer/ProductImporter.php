<?php

namespace SolidBase\GoogleSheetsImporter\Service\Importer;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\CategoryLinkRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\AttributeSetFinder;
use Magento\Catalog\Model\Product\LinkFactory;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Api\LinkManagementInterface;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\AttributeSetManagementInterface;
use Magento\Eav\Model\AttributeSetManagement;
use Magento\Eav\Model\AttributeSetRepository;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Filesystem;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use SolidBase\GoogleSheetsImporter\Api\DataProcessorInterface;
use SolidBase\GoogleSheetsImporter\Model\Config\Source\Visibility;

class ProductImporter implements DataProcessorInterface
{
    private const FILE_BASEDIR = 'googledriveimporter';

    private const SKIP_ATTRIBUTES = [
        'attribute_ids',
        'attribute_set',
        'child_of_sku',
        'group_price',
        'is_recurring',
        'msrp_enabled',
        'recurring_profile',
        'store_name',
        'store_name',
        'super_attribute_code',
        'image',
        'small_image',
        'thumbnail',
    ];

    private const GALLERY_ATTRIBUTES = [
        'image',
        'small_image',
        'thumbnail',
    ];

    private array $products = [];
    private array $optionMapping = [];
    private array $parentChildMapping = [];
    private array $categoryIds = [];
    private array $galleryData = [];
    private array $productLinks = [];
    private array $categoryIdsToReindex = [];
    private array $productIdsToReindex = [];
    private string|null $currentParentSku = null;
    private string|null $currentChildSku = null;
    private int $currentIndex = 0;
    private int $totalRows = 0;
    private LoggerInterface $logger;
    private ProductRepositoryInterface $productRepository;
    private ProductFactory $productFactory;
    private StoreManagerInterface $storeManager;
    private Filesystem $filesystem;
    private AttributeSetRepository $attributeSetRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private FilterBuilder $filterBuilder;
    private LinkFactory $productLinkFactory;
    private AttributeRepositoryInterface $attributeRepository;
    private Attribute $attributeModel;
    private LinkManagementInterface $linkManagement;
    private Visibility $visibilitySource;
    private Factory $optionsFactory;
    private Type $productType;
    private EavSetup $eavSetup;
    private CategoryLinkManagementInterface $categoryLinkManagement;
    private CategoryLinkRepositoryInterface $categoryLinkRepository;
    private IndexerRegistry $indexerRegistry;

    public function __construct(
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        ProductFactory $productFactory,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        AttributeSetRepository $attributeSetRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        LinkFactory $productLinkFactory,
        AttributeRepositoryInterface $attributeRepository,
        Attribute $attributeModel,
        LinkManagementInterface $linkManagement,
        Visibility $visibilitySource,
        Factory $optionsFactory,
        Type $productType,
        EavSetup $eavSetup,
        CategoryLinkManagementInterface $categoryLinkManagement,
        CategoryLinkRepositoryInterface $categoryLinkRepository,
        IndexerRegistry $indexerRegistry
    ) {
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->productLinkFactory = $productLinkFactory;
        $this->attributeRepository = $attributeRepository;
        $this->attributeModel = $attributeModel;
        $this->linkManagement = $linkManagement;
        $this->visibilitySource = $visibilitySource;
        $this->optionsFactory = $optionsFactory;
        $this->productType = $productType;
        $this->eavSetup = $eavSetup;
        $this->categoryLinkManagement = $categoryLinkManagement;
        $this->categoryLinkRepository = $categoryLinkRepository;
        $this->indexerRegistry = $indexerRegistry;
    }

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws StateException
     * @throws InputException
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function process(array $data): array
    {
        $this->totalRows = count($data);
        $this->galleryData = [];
        $this->productLinks = [];
        $this->categoryIdsToReindex = [];
        $this->productIdsToReindex = [];

        $productEntityTypeId = $this->eavSetup->getEntityTypeId(Product::ENTITY);

        foreach ($data as $idx => $row) {
            $this->currentIndex = $idx + 1;

            $this->logger->info(
                __('[%idx/%total] Importing data for product with SKU=%sku', [
                    'idx' => $this->currentIndex,
                    'total' => $this->totalRows,
                    'sku' => $row['sku'],
                ])
            );

            foreach($row as $key => $value) {
                if (\in_array($key, self::SKIP_ATTRIBUTES, true)) {
                    continue;
                }

                $attribute = null;
                try {
                    $attribute = $this->attributeRepository->get(Product::ENTITY, $key);

                    $source = $attribute->getSourceModel();
                    if (null !== $source) {
                        $options = $attribute->getOptions();
                        $optionValue = null;
                        foreach ($options as $option) {
                            if ($option->getLabel() === $value) {
                                $optionValue = $option->getValue();
                                if (!isset($this->options[$key])) {
                                    $this->optionMapping[$key] = [];
                                }
                                $this->optionMapping[$key][$optionValue] = $value;
                                break;
                            }
                        }
                        if (null !== $optionValue) {
                            $row[$key] = $optionValue;
                        }
                    }
                } catch (NoSuchEntityException $e) {
                    $this->logger->warning(
                        __('[%idx/%total] - Attribute with code "%code" does not exist', [
                            'idx' => $this->currentIndex,
                            'total' => $this->totalRows,
                            'code' => $key
                        ])
                    );
                }
            }

            $storeId = $this->storeManager->getStore($row['store_name'] ?? null)->getId();
            unset($row['store_name']);

            $sku = $row['sku'];

            try {
                $product = $this->productRepository->get($sku, false, $storeId, true);

                $this->logger->info(
                    __('[%idx/%total] Product with SKU=%sku already exists, updating instead.', [
                        'idx' => $this->currentIndex,
                        'total' => $this->totalRows,
                        'sku' => $sku,
                    ])
                );
            } catch (NoSuchEntityException $e) {
                $product = $this->productFactory->create()->setStoreId($storeId);
            }

            if (empty($row['child_of_sku'])) {
                $product->setTypeId('configurable');
                $this->currentParentSku = $sku;
                if (!isset($this->parentChildMapping[$this->currentParentSku])) {
                    $this->parentChildMapping[$this->currentParentSku] = [];
                }
            } else {
                $this->currentChildSku = $sku;
                $this->parentChildMapping[$this->currentParentSku][] = $this->currentChildSku;
            }

            if (empty($row['visibility'])) {
                $row['visibility'] = 'not_individually';
            }
            $product->setVisibility(
                $this->visibilitySource->translateSpreadsheetValue($row['visibility'])
            );

            foreach ($row as $key => $value) {
                if ($key === 'child_of_sku' || $key === 'super_attribute_code') {
                    if (!empty($row['super_attribute_code'])) {
                        if (!isset($this->productLinks[$row['child_of_sku']])) {
                            $this->productLinks[$row['child_of_sku']] = [];
                        }

                        $attribute = $this->attributeRepository->get(Product::ENTITY, $row['super_attribute_code']);
                        $attributeLabel = $row[$row['super_attribute_code']];

                        $this->productLinks[$row['child_of_sku']][$sku] = [
                            'attribute_id' => $attribute->getAttributeId(),
                            'attribute_code' => $row['super_attribute_code'],
                            'attribute_label' => $attributeLabel,
                        ];
                    }
                    unset($row['child_of_sku'], $row['super_attribute_code']);
                }

                if ($key === 'category_ids') {
                    $this->categoryIds[$sku] = \array_unique(
                        \array_merge(
                            \array_map(fn ($id) => (int) $id, $product->getCategoryIds()),
                            \array_map(fn ($id) => (int) $id, \explode(',', $value))
                        )
                    );
                    \sort($this->categoryIds[$sku]);

                    $this->logger->info(
                        __('[%idx/%total] Assigning product with SKU=%sku to categories with IDs=%category_ids', [
                            'idx' => $this->currentIndex,
                            'total' => $this->totalRows,
                            'sku' => $sku,
                            'category_ids' => \implode(',', $this->categoryIds[$sku]),
                        ])
                    );
                }

                if (!isset($this->galleryData[$sku])) {
                    $this->galleryData[$sku] = [];
                }
                if (\in_array($key, self::GALLERY_ATTRIBUTES, true)) {
                    $this->galleryData[$sku][$key] = $value;
                    unset($row[$key]);
                }

                if ($key === 'attribute_set') {
                    $searchCriteria = $this->searchCriteriaBuilder->addFilter(
                        $this->filterBuilder->setField('attribute_set_name')->setValue($value)->create()
                    )->create();

                    $attributeSets = $this->attributeSetRepository->getList($searchCriteria)->getItems();
                    foreach($attributeSets as $attributeSet) {
                        if ($attributeSet->getEntityTypeId() === $productEntityTypeId) {
                            $row['attribute_set_id'] = $attributeSet->getAttributeSetId();
                        }
                    }
                    unset($row['attribute_set']);
                }
            }

            $product->setData(
                \array_filter($row, fn ($value) => !empty($value))
            );

            $this->products[$sku] = $product;
            if (null === $product->getId()) {
                $this->productRepository->save($product);
            }

            $this->productIdsToReindex[] = $product->getId();
            $this->categoryIdsToReindex = \array_unique(
                \array_merge(
                    $this->categoryIdsToReindex,
                    \explode(',', $product->getCategoryIds()[0])
                )
            );
        }

        $this->assignToCategories();
        $this->importProductLinks();
        $this->importGalleryImages();
        $this->saveProducts();

        $this->reindex();

        return $this->parentChildMapping;
    }

    protected function reindex(): void
    {
        foreach ([
                     'catalog_category_product' => $this->categoryIdsToReindex,
                     'catalog_product_category' => $this->productIdsToReindex,
                 ] as $indexerId => $ids) {
            $this->indexerRegistry->get($indexerId)->reindexList($ids);

            $this->logger->info(__('Reindexed %indexer index', ['indexer' => $indexerId]));
        }
    }

    protected function assignToCategories(): void
    {
        foreach ($this->categoryIds as $sku => $categoryIds) {
            try {
                $this->products[$sku]->setCategoryIds($categoryIds);
                $this->categoryLinkManagement->assignProductToCategories($sku, $categoryIds);

                $this->logger->info(
                    __('[%idx/%total] Assigned product to categories for SKU=%sku', [
                        'idx' => $this->currentIndex,
                        'total' => $this->totalRows,
                        'sku' => $sku
                    ])
                );
            } catch (\Exception $e) {
                $this->logger->error(
                    __('[%idx/%total] Could assign product to categories for SKU=%sku: %err', [
                        'idx' => $this->currentIndex,
                        'total' => $this->totalRows,
                        'sku' => $sku,
                        'err' => $e->getMessage()
                    ])
                );
            }
        }
    }

    protected function saveProducts(): void
    {
        $savedCount = 0;
        foreach ($this->products as $product) {
            try {
                $this->productRepository->save($product);
                $savedCount++;
            } catch (\Exception $e) {
                $this->logger->error(
                    __('[%idx/%total] Could not save product: %err', [
                        'idx' => $this->currentIndex,
                        'total' => $this->totalRows,
                        'err' => $e->getMessage()
                    ])
                );
            }
        }

        $this->logger->info(__('Saved %count products', ['count' => $savedCount]));
    }

    /**
     * @throws NoSuchEntityException
     * @throws StateException
     * @throws CouldNotSaveException
     * @throws InputException
     */
    protected function importProductLinks(): void
    {
        foreach ($this->productLinks as $parentSku => $productLink) {
            try {
                $associatedProductIds = [];
                foreach (\array_keys($productLink) as $sku) {
                    $child = $this->products[$sku];
                    $associatedProductIds[] = $child->getId();
                }

                foreach ($productLink as $sku => $link) {
                    $optionLabel = null;
                    $optionValue = null;

                    $attributeOptions = $this->optionMapping[$link['attribute_code']];
                    foreach ($attributeOptions as $optionValue => $optionLabel) {
                        if ($optionLabel === $link['attribute_label']) {
                            break;
                        }
                    }

                    if (null !== $optionLabel) {
                        $parent = $this->products[$parentSku];

                        $attributeValues[] = [
                            'attribute_id' => $link['attribute_id'],
                            'label' => $optionLabel,
                            'value_index' => $optionValue,
                        ];

                        $configurableAttributesData = [
                            [
                                'attribute_id' => $link['attribute_id'],
                                'code' => $link['attribute_code'],
                                'label' => $link['attribute_label'],
                                'position' => '0',
                                'values' => $attributeValues,
                            ],
                        ];

                        $configurableOptions = $this->optionsFactory->create($configurableAttributesData);

                        $extensionConfigurableAttributes = $parent->getExtensionAttributes();
                        $extensionConfigurableAttributes->setConfigurableProductOptions($configurableOptions);
                        $extensionConfigurableAttributes->setConfigurableProductLinks($associatedProductIds);
                        $parent->setExtensionAttributes($extensionConfigurableAttributes);

                        $this->products[$parentSku] = $parent;

                        $this->logger->info(
                            __('[%idx/%total] Linked simple product (SKU: %simple_sku) to configurable product (SKU=%config_sku) on "%attribute_code"="%attribute_label"', [
                                'idx' => $this->currentIndex,
                                'total' => $this->totalRows,
                                'simple_sku' => $sku,
                                'config_sku' => $parentSku,
                                'attribute_code' => $link['attribute_code'],
                                'attribute_label' => $link['attribute_label'],
                            ])
                        );
                    }
                }

                $this->logger->info(__('[%idx/%total] Imported product links for product with SKU=%sku', [
                    'idx' => $this->currentIndex,
                    'total' => $this->totalRows,
                    'sku' => $sku,
                ]));
            } catch (\Exception $e) {
                $this->logger->error(
                    __('[%idx/%total] Could not import product links for SKU=%sku: %err', [
                        'idx' => $this->currentIndex,
                        'total' => $this->totalRows,
                        'sku' => $sku,
                        'err' => $e->getMessage()
                    ])
                );
            }
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws StateException
     * @throws FileSystemException
     * @throws InputException
     */
    protected function importGalleryImages(): void
    {
        foreach ($this->galleryData as $sku => $images) {
            $product = $this->products[$sku];

            try {
                $product->setMediaGalleryEntries([]);
                foreach ($images as $type => $uri) {
                    $product->addImageToMediaGallery(
                        $this->copyToGalleryPath($sku, $type, $uri),
                        [$type],
                        true,
                        false
                    );
                }

                $this->logger->info(
                    __('[%idx/%total] Imported gallery images for product with SKU="%sku"', [
                        'idx' => $this->currentIndex,
                        'total' => $this->totalRows,
                        'sku' => $sku
                    ])
                );
            } catch (\Exception $e) {
                $this->logger->error(
                    __('[%idx/%total] Could not import gallery images for product with SKU=%sku: %err', [
                        'idx' => $this->currentIndex,
                        'total' => $this->totalRows,
                        'sku' => $sku,
                        'err' => $e->getMessage()
                    ])
                );
            }
        }
    }

    /**
     * @throws FileSystemException
     */
    protected function copyToGalleryPath(string $sku, string $type, string $uri): bool|string
    {
        $dir = $this->filesystem->getDirectoryWrite(
            DirectoryList::MEDIA
        );

        $imageDir = $dir->getAbsolutePath(self::FILE_BASEDIR . DIRECTORY_SEPARATOR . 'images'
            . DIRECTORY_SEPARATOR . $sku . DIRECTORY_SEPARATOR . $type);
        $dir->create($imageDir);

        $filePath = $imageDir . DIRECTORY_SEPARATOR . \basename($uri);

        $isFileChanged = false;
        if (\str_starts_with($uri, 'http')) {
            $dir->writeFile(
                $filePath,
                \file_get_contents($uri)
            );

            $isFileChanged = true;
        }

        if ($dir->isFile($uri)) {
            $dir->copyFile(
                $uri,
                $filePath
            );

            $dir->delete($uri);

            $isFileChanged = true;
        }

        if (!$isFileChanged) {
            $this->logger->error(
                __('SKU "%sku": Could not download or copy image from: "%uri" with type "%type"', [
                    'uri' => $uri,
                    'type' => $type,
                    'sku' => $sku,
                ])
            );
            return false;
        }

        $this->logger->info(
            __('Fetching image: %uri --> %path', [
                'uri' => $uri,
                'path' => $filePath,
            ])
        );

        return $filePath;
    }
}
