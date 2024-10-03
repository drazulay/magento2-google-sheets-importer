<?php

namespace SolidBase\GoogleDriveImporter\Api;

interface DataProcessorInterface
{
    public function process(array $data): array;
}
