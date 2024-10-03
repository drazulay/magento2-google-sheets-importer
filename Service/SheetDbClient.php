<?php

namespace SolidBase\GoogleDriveImporter\Service;

use Psr\Log\LoggerInterface;
use SolidBase\GoogleDriveImporter\Api\StageManagementInterface;
use SolidBase\GoogleDriveImporter\Helper\Credentials;

class SheetDbClient
{
    private Credentials $credentialsHelper;
    private LoggerInterface $logger;

    public function __construct(Credentials $credentialsHelper, LoggerInterface $logger)
    {
        $this->credentialsHelper = $credentialsHelper;
        $this->logger = $logger;
    }

    public function getSpreadsheetValues(string $sheetName = StageManagementInterface::STAGE_IMPORT_ATTRIBUTES, array $parameters = []): array
    {
        $url = "https://sheetdb.io/api/v1/" . $this->credentialsHelper->getApiKey();
        $parameters['sheet'] = $sheetName;
        $url .= '?' . http_build_query($parameters);
        $response = file_get_contents($url);

        return json_decode($response, true);
    }
}
