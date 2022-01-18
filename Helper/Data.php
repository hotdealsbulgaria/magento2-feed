<?php
/*
 * Copyright (c) 2021. HotDeals Ltd.
 */

namespace HotDeals\Feed\Helper;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LogLevel;

/**
 * Class Helper Data
 */
class Data extends AbstractHelper
{
    /**
     * Module Name for settings.
     */
    public const MODULE_NAME = 'hotdeals';

    /**
     * Path to module active flag.
     */
    public const XML_PATH_SYNC_ENABLED = 'general/enabled';

    /**
     * Feeds Directory Name
     */
    public const FEED_DIR = 'feed';

    /**
     * Directory separator
     */

    public const DS = '/';

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    private $directoryList;

    /**
     * Data constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Filesystem\DirectoryList $directoryList
     * @param Context $context
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        DirectoryList $directoryList,
        Context $context
    ) {
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;
        parent::__construct($context);
    }

    /**
     * Check is module enabled for selected store.
     *
     * @param int|bool $storeId
     *
     * @return bool
     * @throws Exception
     */
    public function isActive($storeId = null): bool
    {
        if (!$storeId) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        return (bool)$this->getConfigData(self::XML_PATH_SYNC_ENABLED, $storeId);
    }

    /**
     * Get Config Data
     *
     * @param string $field
     * @param int|bool $storeId
     *
     * @return string
     * @throws Exception
     */
    public function getConfigData(string $field, $storeId = null): ?string
    {
        if (!$storeId) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        $field = self::MODULE_NAME . DIRECTORY_SEPARATOR . $field;

        return $this->scopeConfig->getValue($field, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param bool $withFilename
     *
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getFeedPath(bool $withFilename = false): string
    {
        return $this->directoryList->getPath(DirectoryList::PUB) . self::DS . self::FEED_DIR . self::DS .
               ($withFilename === true ? $this->getFeedFilename() : '');
    }

    /**
     * @return string
     */
    private function getFeedFilename(): ?string
    {
        return 'hd' . ($this->getCurrentStore() ? '-' . $this->getCurrentStore()->getCode() : '') . '.json';
    }

    /**
     * @return string
     */
    public function getFeedUrl(): ?string
    {
        if ($store = $this->getCurrentStore()) {
            /* @phpstan-ignore-next-line */
            $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            $baseUrl = str_replace(UrlInterface::URL_TYPE_MEDIA . '/', '', $baseUrl);

            return $baseUrl . $this->getFeedFilename();
        }

        return null;
    }

    /**
     * Get Current Magento Store
     *
     * @return StoreInterface|null
     */
    public function getCurrentStore(): ?StoreInterface
    {
        try {
            return $this->storeManager->getStore();
        } catch (NoSuchEntityException $e) {
            $this->logger($e->getMessage());
        }

        return $this->storeManager->getDefaultStoreView();
    }

    /**
     * @throws \Exception
     */
    public function getManufacturerAttribute(): ?string
    {
        return $this->getConfigData('general/manufacturer');
    }

    /**
     * @param string $message
     * @param string $type
     *
     * @return void
     */
    public function logger(string $message, string $type = LogLevel::ALERT): void
    {
        dump($message);
        $this->_logger->log($type, $message, ['module' => self::MODULE_NAME]);
    }
}
