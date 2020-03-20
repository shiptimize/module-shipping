<?php
namespace Shiptimize\Shipping\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Shiptimize\Shipping\Model\ShiptimizeMagento;

/**
 * This class observes the shipping settings
 */
class ShippingSettingsObserver implements ObserverInterface
{

    private $scopeConfig;
    private $shiptimize;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->shiptimize = $shiptimize;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->shiptimize->refreshToken();
    }
}
