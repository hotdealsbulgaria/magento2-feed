<?php
/*
 * Copyright (c) 2021. HotDeals Ltd.
 */

namespace HotDeals\Feed\Service;

use HotDeals\Feed\Helper\Data as Helper;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Data\Collection;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.ShortVariable)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class GenerateFeed
{
    public const IMAGE_WIDTH = 1000;

    public const IMAGE_HEIGHT = 1000;

    /**
     * Categories Collection
     *
     * @var \Magento\Catalog\Model\CategoryFactory
     */
    protected $category;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \HotDeals\Feed\Helper\Data
     */
    private $helper;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    private $productCollection;

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Source\Status
     */
    private $productStatus;

    /**
     * @var \Magento\Framework\Filesystem
     */
    private Filesystem $filesystem;

    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    private $file;

    /**
     * @var ProgressBar
     */
    private ProgressBar $progressBar;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private Json $json;

    /**
     * @var \Magento\Catalog\Model\Product\Visibility
     */
    private Visibility $productVisibility;

    /**
     * Product Feed constructor.
     *
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param Helper $helper
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ProductStatus $productStatus
     * @param \Magento\Catalog\Model\Product\Visibility $productVisibility
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     * @param \Magento\Framework\Filesystem $filesystem
     * @param File $file
     * @param \Magento\Framework\Serialize\Serializer\Json $json
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        StockRegistryInterface $stockRegistry,
        ProductCollectionFactory $productCollectionFactory,
        ProductStatus $productStatus,
        Visibility $productVisibility,
        CategoryFactory $categoryFactory,
        Filesystem $filesystem,
        File $file,
        Json $json,
        Helper $helper
    ) {
        $this->storeManager = $storeManager;
        $this->productCollection = $productCollectionFactory;
        $this->productStatus = $productStatus;
        $this->productVisibility = $productVisibility;
        $this->stockRegistry = $stockRegistry;
        $this->category = $categoryFactory;
        $this->filesystem = $filesystem;
        $this->file = $file;
        $this->json = $json;
        $this->helper = $helper;
    }

    /**
     * @param \Symfony\Component\Console\Helper\ProgressBar $progressBar
     */
    public function setProgressBar(ProgressBar $progressBar): void
    {
        $this->progressBar = $progressBar;
    }

    /**
     * @param null $storeCode
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Exception
     */
    public function execute($storeCode = null): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            /* @phpstan-ignore-next-line */
            if (($storeCode === null || $store->getCode() === $storeCode)
                && $store->getIsActive()
                && $this->helper->isActive($store->getId())
            ) {
                try {
                    $this->generateFeed($store);
                } catch (FileSystemException $e) {
                    $this->helper->logger($e->getMessage(), LogLevel::CRITICAL);
                }
            }
        }
    }

    /**
     * Genereate feed for every store.
     *
     * @param StoreInterface $store
     *
     * @return void
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Exception
     */
    protected function generateFeed(StoreInterface $store): void
    {
        $productsCollection = $this->getProductsCollection($store->getId());
        if ($this->hasProgressBar()) {
            $this->progressBar->setMaxSteps($productsCollection->getSize());
        }

        $data = [];
        /** @var ProductInterface $product */
        foreach ($productsCollection as $product) {
            if ($this->stockRegistry->getStockStatusBySku($product->getSku())
                && $discountAmount = $this->getDiscountPercent($product)
            ) {
                $data[] = [
                    'id' => (int)$product->getId(),
                    'code' => $product->getSku(),
                    'category' => $this->getProductCategoryPath($product, $store),
                    'brand' => (string)$product->getAttributeText($this->helper->getManufacturerAttribute()),
                    'old_price' => $this->getPrice($product),
                    'new_price' => $this->getFinalPrice($product),
                    'discount' => $discountAmount,
                    'image' => $this->getProductImage($product),
                    'url' => $product->getProductUrl(),
                    'name' => $product->getName()
                ];
            }

            if ($this->hasProgressBar()) {
                $this->progressBar->advance();
            }
        }

        $dir = $this->helper->getFeedPath();
        $this->file->checkAndCreateFolder($dir, 0755);

        try {
            $media = $this->filesystem->getDirectoryWrite(DirectoryList::PUB);
            $media->writeFile($this->helper->getFeedPath(true), $this->json->serialize($data));
        } catch (\Exception $e) {
            $this->helper->logger($e->getMessage());
        }
    }

    /**
     * Get Products Collection
     *
     * @param int $store
     *
     * @return object
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     */
    protected function getProductsCollection(int $store = 0): object
    {
        $collection = $this->productCollection->create();
        $collection->setStore($store)
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()])
            ->setVisibility($this->productVisibility->getVisibleInSiteIds())
            ->addMediaGalleryData()
            ->addAttributeToFilter('is_saleable', 1)
            ->addFinalPrice();

        return $collection;
    }

    /**
     * @return bool
     */
    public function hasProgressBar(): bool
    {
        return $this->progressBar instanceof ProgressBar;
    }

    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     *
     * @return float
     */
    private function getDiscountPercent(ProductInterface $product): float
    {
        $finalPrice = $this->getFinalPrice($product);
        $regularPrice = $this->getPrice($product);

        if ($regularPrice > $finalPrice) {
            $discount = ($regularPrice - $finalPrice)/$regularPrice*100;

            return round($discount, 2);
        }

        return 0;
    }

    private function getFinalPrice(ProductInterface $product): float
    {
        return round($product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue(), 2);
    }

    private function getPrice(ProductInterface $product): float
    {
        return round($product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue(), 2);
    }

    /**
     * Product Category Path
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param \Magento\Store\Api\Data\StoreInterface $store
     *
     * @return string
     */
    private function getProductCategoryPath(ProductInterface $product, StoreInterface $store): string
    {
        $categories = [];
        try {
            $collection = $product->getCategoryCollection()
                ->setStoreId($store)
                ->addAttributeToSelect('path')
                ->addAttributeToFilter('is_active', ['eq' => 1]);

            foreach ($collection as $cat) {
                $pathIds = explode('/', $cat->getPath());
                $cats = $this->category->create()
                    ->getCollection()
                    ->setStoreId($store)
                    ->addAttributeToSelect('name')
                    ->addAttributeToSelect('is_active')
                    ->addFieldToFilter('entity_id', ['in' => $pathIds])
                    ->addFieldToFilter('level', ['gt' => 0]);

                foreach ($cats as $c) {
                    $level = $c->getLevel();
                    if (isset($oldLevel) && $level >= $oldLevel) {
                        $categories[$c->getId()] = trim($c->getName());
                    } elseif (isset($oldLevel)) {
                        break;
                    }
                    $oldLevel = $level;
                }
            }
        } catch (\Exception $e) {
            $this->helper->logger($e->getMessage());
        }

        return implode(' | ', $categories);
    }

    /**
     * Get Product Base Image Url
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     *
     * @return string
     */
    private function getProductImage(ProductInterface $product): ?string
    {
        $images = $product->getMediaGalleryImages();
        if ($images instanceof Collection) {
            foreach ($images as $image) {
                if ($product->getImage() === $image->getFile()) {
                    return $image->getUrl();
                }
            }
        }

        return null;
    }
}
