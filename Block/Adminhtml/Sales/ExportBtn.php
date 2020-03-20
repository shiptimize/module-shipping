<?php

namespace Shiptimize\Shipping\Block\Adminhtml\Sales;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\AuthorizationInterface;

class ExportBtn implements ButtonProviderInterface
{
    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @var \Magento\Backend\Block\Widget\Context
     */
    private $context;

    /**
     * CustomButton constructor.
     *
     * @param \Magento\Framework\AuthorizationInterface $authorization
     * @param Context $context
     * @param \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize
     */
    public function __construct(
        \Magento\Framework\AuthorizationInterface $authorization,
        \Magento\Backend\Block\Widget\Context $context,
        \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize
    ) {
        $this->authorization = $authorization;
        $this->context = $context;
        $this->shiptimize = $shiptimize;
    }

    /**
     * @return array
     */
    public function getButtonData()
    {
        if (!$this->authorization->isAllowed('Magento_Cms::save')) {
            return [];
        }

        return [
            'label' => $this->shiptimize->__('Export Preset Orders'),
            'on_click' => sprintf("location.href = '%s';", $this->getBackUrl()),
            'class' => 'primary',
            'sort_order' => 10
        ];
    }

    /**
     * Get URL for back (reset) button
     *
     * @return string
     */
    public function getBackUrl()
    {
        return $this->context->getUrlBuilder()->getUrl('shiptimize/shipping/exportAll', []);
    }
}

