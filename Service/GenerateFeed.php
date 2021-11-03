<?php
/*
 * @package      Webcode_Glami
 *
 * @author       Webcode, Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2021 GLAMI Inspigroup s.r.o.
 * @license      See LICENSE.txt for license details.
 */

namespace HotDeals\Feed\Service;

use HotDeals\Feed\Helper\Data as Helper;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Data\Collection;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
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
     * @var \Magento\InventorySalesApi\Api\StockResolverInterface
     */
    private $stockResolver;
    /**
     * @var \Magento\InventorySalesApi\Api\AreProductsSalableInterface
     */
    private $areProductsSalable;
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
     * @var array
     */
    private array $stockId;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private Json $json;

    /**
     * @var \Magento\Catalog\Model\Product\Visibility
     */
    private \Magento\Catalog\Model\Product\Visibility $productVisibility;

    /**
     * Product Feed constructor.
     *
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ProductStatus $productStatus
     * @param \Magento\Catalog\Model\Product\Visibility $productVisibility
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     * @param \Magento\Catalog\Helper\ImageFactory $imageFactory
     * @param \Magento\InventorySalesApi\Api\StockResolverInterface $stockResolver
     * @param \Magento\InventorySalesApi\Api\AreProductsSalableInterface $areProductsSalable
     * @param \Magento\Framework\Filesystem $filesystem
     * @param File $file
     * @param \Magento\Framework\Serialize\Serializer\Json $json
     * @param Helper $helper
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ProductCollectionFactory $productCollectionFactory,
        ProductStatus $productStatus,
        \Magento\Catalog\Model\Product\Visibility $productVisibility,
        CategoryFactory $categoryFactory,
        ImageFactory $imageFactory,
        StockResolverInterface $stockResolver,
        AreProductsSalableInterface $areProductsSalable,
        Filesystem $filesystem,
        File $file,
        Json $json,
        Helper $helper
    ) {
        $this->storeManager = $storeManager;
        $this->productCollection = $productCollectionFactory;
        $this->productStatus = $productStatus;
        $this->productVisibility = $productVisibility;
        $this->category = $categoryFactory;
        $this->image = $imageFactory;
        $this->stockResolver = $stockResolver;
        $this->areProductsSalable = $areProductsSalable;
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
     * @return array
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
            if ($this->isProductAvailable($store, $product->getSku())
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

        dump($data);
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
            //->addAttributeToSelect([ProductInterface::NAME, $this->helper->getManufacturerAttribute()])
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
     * @param StoreInterface $store
     * @param string $sku
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function isProductAvailable(StoreInterface $store, string $sku): bool
    {
        $stockId = $this->getStockIdByStore($store);
        foreach ($this->areProductsSalable->execute([$sku], $stockId) as $product) {
            if ($product->getSku() === $sku) {
                return $product->isSalable();
            }
        }

        return false;
    }

    /**
     * @param StoreInterface $store
     *
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getStockIdByStore(StoreInterface $store): int
    {
        $storeId = $store->getId();
        if (!isset($this->stockId[$storeId])) {
            $websiteCode = $this->storeManager->getWebsite($store->getWebsiteId())->getCode();
            $stock = $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode);
            $this->stockId[$storeId] = (int)$stock->getStockId();
        }

        return (int)$this->stockId[$storeId];
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

            return round($discount);
        }

        return 0;
    }

    private function getFinalPrice(ProductInterface $product): float
    {
        return (float)$product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
    }

    private function getPrice(ProductInterface $product): float
    {
        return (float)$product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
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
