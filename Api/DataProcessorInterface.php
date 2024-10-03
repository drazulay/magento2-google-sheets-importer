<?php

namespace SolidBase\GoogleSheetsImporter\Api;

interface DataProcessorInterface
{
    public function process(array $data): array;
}
