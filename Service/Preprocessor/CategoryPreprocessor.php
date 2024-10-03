<?php

namespace SolidBase\GoogleDriveImporter\Service\Preprocessor;

use Psr\Log\LoggerInterface;
use SolidBase\GoogleDriveImporter\Api\DataProcessorInterface;

class CategoryPreprocessor implements DataProcessorInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(array $data): array
    {
        $this->logger->info('Category preprocessor started');

        $this->logger->info('Category preprocessor finished');

        return $data;
    }
}
