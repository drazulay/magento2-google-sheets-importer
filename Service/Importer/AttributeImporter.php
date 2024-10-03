<?php

namespace SolidBase\GoogleSheetsImporter\Service\Importer;

use Psr\Log\LoggerInterface;
use SolidBase\GoogleSheetsImporter\Api\DataProcessorInterface;

class AttributeImporter implements DataProcessorInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(array $data): array
    {
        $skipSkus = $data['skip_skus'] ?? [];

        return $data;
    }
}
