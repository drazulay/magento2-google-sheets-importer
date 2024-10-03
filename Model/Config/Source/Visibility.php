<?php

namespace SolidBase\GoogleSheetsImporter\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Visibility implements OptionSourceInterface
{

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        return [
            ['value' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE, 'label' => 'Not individually'],
            ['value' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH, 'label' => 'Both'],
            ['value' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG, 'label' => 'Catalog'],
            ['value' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_SEARCH, 'label' => 'Search'],
        ];
    }

    public function translateSpreadsheetValue(string $key)
    {
        $options = [
            'not_individually' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE,
            'both' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH,
            'catalog' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG,
            'search' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_SEARCH,
        ];

        return $options[$key];
    }
}
