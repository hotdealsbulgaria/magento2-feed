<?php

namespace HotDeals\Feed\Block\Adminhtml\System\Config\Form\Field;

use HotDeals\Feed\Helper\Data;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class FeedUrl extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var \HotDeals\Feed\Helper\Data
     */
    private $helper;

    /**
     * @param Context $context
     * @param \HotDeals\Feed\Helper\Data $helper
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        Context $context,
        Data $helper,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        $this->helper = $helper;
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * Render scope label
     *
     * @param AbstractElement $element
     *
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _renderScopeLabel(AbstractElement $element): ?string
    {
        return null;
    }

    /**
     * Retrieve element HTML markup
     *
     * @param AbstractElement $element
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->helper->getFeedUrl();
    }
}
