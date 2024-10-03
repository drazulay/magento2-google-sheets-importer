<?php

namespace SolidBase\GoogleDriveImporter\Api;

interface StageManagementInterface
{
    public const STAGE_PREPARE = 'prepare';
    public const STAGE_IMPORT_ATTRIBUTES = 'import_attributes';
    public const STAGE_IMPORT_CATEGORIES = 'import_categories';
    public const STAGE_IMPORT_PRODUCTS = 'import_products';
    public const STAGE_IMPORT_INVENTORY = 'import_inventory';
    public const STAGES = [
        self::STAGE_PREPARE,
        self::STAGE_IMPORT_ATTRIBUTES,
        self::STAGE_IMPORT_CATEGORIES,
        self::STAGE_IMPORT_PRODUCTS,
        self::STAGE_IMPORT_INVENTORY,
    ];

    public function getNextStage(string $currentStage): ?string;

    public function getStage(): string;

    public function setStage(string $stage): StageManagementInterface;
}
