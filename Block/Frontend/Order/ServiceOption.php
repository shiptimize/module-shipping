<?php
namespace Shiptimize\Shipping\Block\Frontend\Order;

class ServiceOption extends \Magento\Framework\View\Element\Template
{

    private $scopeConfig;
    private $storeManager;
    private $shiptimize;

    /** 
     * @param \Magento\Framework\View\Element\Template\Context $context,
     * @param array $data,
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
     * @param \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize,
     * @param \Shiptimize\Shipping\Model\ShiptimizeOrderMagento $shiptimizeOrder,
     * @param \Magento\Framework\Registry $registry
     */  
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        array $data,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize,
        \Shiptimize\Shipping\Model\ShiptimizeOrderMagento $shiptimizeOrder,
        \Magento\Framework\Registry $registry
    )
    {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
        $this->shiptimize = $shiptimize;
        $this->shiptimizeOrder = $shiptimizeOrder;

        $this->coreRegistry = $registry;
    }
 
    /**
     * @return boolean
     */
    public function displayCheckout()
    {
        return $this->scopeConfig->getValue('shipping/shiptimizeshipping/checkoutenabled');
    }

    public function translate($text)
    {
        return $this->shiptimize->__($text);
    }

    public function getPickupPoint()
    {
        $order = $this->coreRegistry->registry('current_order');
        
        $this->shiptimizeOrder->bootstrap($order->getId());
        $html = '';
        $meta = $this->shiptimizeOrder->getOrderMeta();

        if ($meta) {
            $html = "<div class='block'><div  class='box'><strong class='box-title'><span>";
            $html .= $this->shiptimize->__("Selected Pickup");
            $html .= "</span></strong><div class='box-content'>";
            $html .= $meta['shiptimize_pickup_label'];

            $html .="</div>";
        }

        return $html;
    }
}
