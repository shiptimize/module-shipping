<?php
namespace Shiptimize\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use \Shiptimize\Shippping\Model\TableRatesModel;

class ShiptimizeTableRates extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements \Magento\Shipping\Model\Carrier\CarrierInterface
{
    protected $_code = 'ShiptimizeTableRates';

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger, 
        \Shiptimize\Shipping\Model\TableRatesModel $tableRates,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        array $data = []
    )
    {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data); 
        $this->tableRates = $tableRates;
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory  = $rateMethodFactory; 
    }
    /**
     * Custom Shipping Rates Collector
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        
        if (!$this->isActive()) {
            return false;
        }
        
        $country = $request->getDestCountryId();
        $region = $request->getDestRegionCode();
        $zipcode = $request->getDestPostcode();

        $weight = $request->getPackageWeight();

        $freeBoxes = 0;
        $nitems = 0;

        if ($request->getAllItems()) {
            $items = $request->getAllItems();

            foreach ($items as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    foreach ($item->getChildren() as $child) {
                        if ($child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                            $freeBoxes += $item->getQty() * $child->getQty();
                        } else if (!$child->getFreeShipping()) {
                            $nitems += $item->getQty();
                        }
                    }
                } elseif ($item->getFreeShipping()) {
                    $freeBoxes += $item->getQty();
                } else {
                    $nitems += $item->getQty();
                }
            }
        }

        $orderprice = $request->getPackageValue();
        //error_log("Country: $country, region:$region, postalCode $zipcode weight: $weight nItems: $nitems orderPrice: $orderprice ");

        $rates = $this->tableRates->getRates($country, $region, $zipcode,  $weight, $orderprice, $nitems);
        
        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();
        
        $global_name = $this->getConfigData('title');
         
        //error_log("Matching rates ". var_export($rates, true));

        foreach ($rates as $rate) {
            /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
            $method = $this->rateMethodFactory->create(); 

            $method->setCarrier($this->_code);
            $method->setMethod($rate->carrier_id . '_' . ($rate->has_pickup ? 'pickup_' : '' ) . $rate->id); // this identifies this method

            $carrierTitle = $global_name; 

            if (!$global_name) { 
                $carrierTitle = $rate->display_name; 
            }
            else {
                $method->setMethodTitle( $rate->display_name );
            }
          
            $method->setCarrierTitle($carrierTitle); 

            if ($request->getFreeShipping() === true || $request->getPackageQty() == $this->getFreeBoxes()) {
                $shippingPrice = '0.00';
            }

            $method->setPrice($rate->price);
            $method->setCost($rate->price);

            $result->append($method); 
        }

        return $result;
    }
    
    public function isActive()
    {
        $active = $this->getConfigData('active');

        return $active==1 || $active=='true';
    }
    
    public function getAllowedMethods()
    {
        return array('shiptimize'=>$this->getConfigData('name'));
    }
}
