<?php

namespace SolidBase\GoogleDriveImporter\Service\Preprocessor;

use Psr\Log\LoggerInterface;
use SolidBase\GoogleDriveImporter\Api\DataProcessorInterface;

class AttributePreprocessor implements DataProcessorInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(array $data): array
    {
        $this->logger->info('Attribute preprocessor started');

        $attributeData = [];

        foreach ($data as $key => $item) {
            $idx = $key + 1;
            $this->logger->info("Processing row {$idx} of " . \count($data));

            if (!isset($attributeData[$item['attribute_code']])) {
                $attributeData[$item['attribute_code']] = [
                    'name' => $item['name'],
                ];
                if ((bool) $item['uses_source'] === true || $item['uses_source'] === 'true') {
                    $attributeData[$item['attribute_code']]['options'] = [];
                }
            }

            if (!empty($item['option_value'])) {
                $attributeData[$item['attribute_code']]['options'][] = $item['option_value'];
            }
        }

        $this->logger->info('Attribute preprocessor finished');

        return $attributeData;
    }
}
