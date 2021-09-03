<?php
namespace Shiptimize\Shipping\Block\Frontend\Order;

class TrackingEmail extends \Magento\Framework\View\Element\Template
{

	protected $shiptimizeOrder = null;
	protected $shiptimize = null; 
	protected $shipmentRepository = null; 

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
        \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize,
        \Shiptimize\Shipping\Model\ShiptimizeOrderMagento $shiptimizeOrder,
        \Magento\Sales\Model\Order\ShipmentRepository $shipmentRepository,
        array $data =[])
    {
    	$this->shiptimizeOrder = $shiptimizeOrder; 
    	$this->shiptimize = $shiptimize;  
        $this->shipmentRepository = $shipmentRepository;

        parent::__construct($context, $data);
    }  

    /** 
     * @return a line to be appended at the email in the language of the user 
     */ 
    public function getPickupLine() 
    { 
        $postbody = $this->getRequest()->getContent(); 
        $matches = array();   
        $shipment_id = $this->getRequest()->getParam('shipment_id');  

        error_log("\nGetPickupLine postbody $postbody");

        # Sending email from api update 
        if(preg_match("/ShopItemId\"\:\"([\d]+)/", $postbody, $matches)){  
            if($this->shiptimize->is_dev){
                 error_log("\norderid [$matches[1]]");
            }  
            $this->shiptimizeOrder->bootstrap($matches[1]);  
        }
        elseif($shipment_id) { 
            if($this->shiptimize->is_dev){
                 error_log("\nGetPickupLine shipmentid [$shipment_id]");
            }  

            $shipment = $this->shipmentRepository->get($shipment_id);    
            $this->shiptimizeOrder->bootstrap($shipment->getOrder()->getId()); 
        }
        else {
            error_log("\nShiptimize\Shipping\Block\Frontend\Order\TrackingEmail could not get an order id to get pickup point info"); 
            return; 
        }
        
        
        $meta = $this->shiptimizeOrder->getOrderMeta(); 
    	$pickupLine =  $meta != null && isset($meta['shiptimize_pickup_label']) ? "<b>" . $this->shiptimize->__("Selected Pickup") . ":</b> " .  $meta['shiptimize_pickup_label'] : '';

        if($this->shiptimize->is_dev)
        { 
            error_log("\npickupline $pickupLine \n."); 
        }
        
        return $pickupLine; 
    } 
}