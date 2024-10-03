<?php

namespace SolidBase\GoogleSheetsImporter\Model;

use Psr\Log\LoggerInterface;
use SolidBase\GoogleSheetsImporter\Api\StageManagementInterface;

class StageManagement implements StageManagementInterface
{
    private $stage;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->stage = static::STAGE_PREPARE;
        $this->logger = $logger;
    }

    public function getNextStage(string $currentStage): ?string
    {
        $index = 0;
        foreach (static::STAGES as $index => $stage) {
            if ($stage === $currentStage) {
                break;
            }
        }
        return static::STAGES[$index + 1];
    }

    public function getStage(): string
    {
        return $this->stage;
    }

    public function setStage(string $stage): StageManagementInterface
    {
        $this->logger->info('Stage is set to "' . \str_replace('import_', '', $stage) . '"');
        $this->stage = $stage;
        return $this;
    }
}
