<?php

namespace SolidBase\GoogleDriveImporter\Service\Preprocessor;

use Psr\Log\LoggerInterface;
use SolidBase\GoogleDriveImporter\Api\DataProcessorInterface;

class ProductPreprocessor implements DataProcessorInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(array $data): array
    {
        $this->logger->info('Product preprocessor started');

        $totalRows = \count($data);
        $data['skip_rows'] = [];
        foreach ($data as $idx => $row) {
            if ($idx === 'skip_rows') {
                continue;
            }

            $idx++;

            if (empty($row['sku'])) {
                $data['skip_rows'][] = $idx;
                $this->logger->warning(
                    __('[%idx/%total] Empty SKU -- skipping import', ['idx' => $idx, 'total' => $totalRows])
                );
                continue;
            }

            if (empty($row['name'])) {
                $data['skip_rows'][] = $idx;
                $this->logger->warning(
                    __('Row %row: empty name -- skipping import', ['row' => $idx])
                );
            }
        }

        $this->logger->info('Product preprocessor finished');

        return $data;
    }
}
