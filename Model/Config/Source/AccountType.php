<?php

namespace SolidBase\GoogleDriveImporter\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class AccountType implements OptionSourceInterface
{
    const SERVICE_ACCOUNT = 'service_account';
    const OAUTH2 = 'oauth2';

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => static::OAUTH2, 'label' => __('OAuth2')],
            ['value' => static::SERVICE_ACCOUNT, 'label' => __('Service Account')],
        ];
    }
}
