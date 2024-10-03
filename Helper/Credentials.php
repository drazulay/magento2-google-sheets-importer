<?php

namespace SolidBase\GoogleDriveImporter\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;

class Credentials extends AbstractHelper
{
    const AUTH_PROVIDER_X509_CERT_URL = 'https://www.googleapis.com/oauth2/v1/certs';

    const CLIENT_X509_CERT_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/drazulay%40mythic-reach-437416-n1.iam.gserviceaccount.com';

    const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    const AUTH_URI = 'https://accounts.google.com/o/oauth2/auth';

    const UNIVERSE_DOMAIN = 'googleapis.com';

    private StoreManagerInterface $storeManager;
    private EncryptorInterface $encryptor;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->encryptor = $encryptor;
    }

    public function getAccountType(): string
    {
        return $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/account_type');
    }

    public function getProjectId(): string
    {
        return $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/project_id');
    }

    public function getClientId(): string
    {
        return $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/client_id');
    }

    public function getClientEmail(): string
    {
        return $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/client_email');
    }

    public function getClientSecret(): string
    {
        return $this->encryptor->decrypt(
            $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/client_secret')
        );
    }

    public function getClientX509CertUrl(): string
    {
        return $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/client_x509_cert_url')
            ?? static::CLIENT_X509_CERT_URL;
    }

    public function getUniverseDomain(): string
    {
        return $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/universe_domain')
            ?? static::UNIVERSE_DOMAIN;
    }

    public function getPrivateKeyId(): string
    {
        return $this->encryptor->decrypt(
            $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/private_key_id')
        );
    }

    public function getPrivateKey(): string
    {
        return $this->encryptor->decrypt(
            $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/private_key')
        );
    }

    public function getAuthUri(): string
    {
        return $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/auth_uri')
            ?? static::AUTH_URI;
    }

    public function getTokenUri(): string
    {
        return $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/token_uri')
            ?? static::TOKEN_URI;
    }

    public function getAuthProviderX509CertUrl(): string
    {
        return $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/auth_provider_x509_cert_url')
            ?? static::AUTH_PROVIDER_X509_CERT_URL;
    }

    public function getSpreadsheetId(): string
    {
        return $this->scopeConfig->getValue('solidbase_googledriveimporter/google_drive/spreadsheet_id');
    }

    public function getApiKey(): string
    {
        return $this->encryptor->decrypt(
            $this->scopeConfig->getValue('solidbase_googledriveimporter/sheetdb/api_key')
        );
    }

    public function getCredentials(): array
    {
        return [
            'type' => $this->getAccountType(),
            'project_id' => $this->getProjectId(),
            'private_key_id' => $this->getPrivateKeyId(),
            'private_key' => $this->getPrivateKey(),
            'client_secret' => $this->getClientSecret(),
            'client_email' => $this->getClientEmail(),
            'client_id' => $this->getClientId(),
            'auth_uri' => $this->getAuthUri(),
            'token_uri' => $this->getTokenUri(),
            'auth_provider_x509_cert_url' => $this->getAuthProviderX509CertUrl(),
            'client_x509_cert_url' => $this->getClientX509CertUrl(),
            'universe_domain' => $this->getUniverseDomain(),
        ];
    }
}
