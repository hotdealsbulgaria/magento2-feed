<?php
/*
 * @package      Webcode_Feed
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2021 Webcode Ltd. (https://webcode.bg/)
 * @license      Visit https://webcode.bg/license/ for license details.
 */

namespace HotDeals\Feed\Model\Config\Source;

use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Attribute
{
    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Repository
     */
    protected $attributeRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @param AttributeRepository $attributeRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        AttributeRepository $attributeRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->attributeRepository   = $attributeRepository;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [['value' => '', 'label' => '']];
        $attributes = $this->attributeRepository->getList($this->searchCriteriaBuilder->create())->getItems();
        foreach ($attributes as $attribute) {
            if ((int)$attribute->getIsUserDefined() === 1) {
                $options[] = [
                    'value' => $attribute->getAttributeCode(),
                    'label' => $attribute->getDefaultFrontendLabel()
                ];
            }
        }

        return $options;
    }
}
