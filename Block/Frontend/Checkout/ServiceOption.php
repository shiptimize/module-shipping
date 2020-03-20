<?php

namespace Shiptimize\Shipping\Block\Frontend\Checkout;

class ServiceOption extends \Magento\Framework\View\Element\Template
{

    private $scopeConfig;
    private $storeManager;
    private $shiptimize;

    /** 
     * @param \Magento\Framework\View\Element\Template\Context $context,
     * @param array $data,
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager,
     * @param \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize
     */ 
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        array $data,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize
    )
    {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->shiptimize = $shiptimize;
    }
 
    /**
     * @return boolean
     */
    public function displayCheckout()
    {
        return $this->scopeConfig->getValue('shipping/shiptimizeshipping/checkoutenabled');
    }

    public function getAjaxPickupPointsUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl().'shiptimize/api/getPickupPoints';
    }

    public function getAjaxSavePickupUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl().'shiptimize/checkout/savePickupPoint';
    }

    /**
     *
     */
    public function getCarriers()
    {
        $config_carriers = $this->scopeConfig->getValue('shipping/shiptimizeshipping/carriers');

        if (!$config_carriers) {
            return array();
        }

        $shiptimize_carriers = array();
        $carriers = json_decode($config_carriers);

        foreach ($carriers as $c) {
            array_push(
                $shiptimize_carriers,
                (object)array(
                    'Name' => $c->Name,
                    'HasPickup' => $c->HasPickup,
                    'Id' => $c->Id,
                    'ClassName' => $c->ClassName
                )
            );
        }
        return $shiptimize_carriers;
    }

    public function getGMapsKey()
    {
        return $this->scopeConfig->getValue('shipping/shiptimizeshipping/gmapskey');
    }

    public function translate($text)
    {
        return $this->shiptimize->__($text);
    }
    /**
     * @return string the public path to the icon folder
     */
    public function getIconFolder()
    {
        return $this->getViewFileUrl('Shiptimize_Shipping::images/').'/markers/';
    }

    public function getLeafletUrl()
    {
        return $this->getViewFileUrl('Shiptimize_Shipping::js/leaflet.js');
    }
}
