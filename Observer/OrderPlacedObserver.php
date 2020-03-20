<?php
namespace Shiptimize\Shipping\Observer;

class OrderPlacedObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function __construct(
        \Magento\Checkout\Model\Session $_checkoutSession,
        \Shiptimize\Shipping\Model\ShiptimizeOrderMagento $shiptimize_order,
        \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ){
        $this->_checkoutSession = $_checkoutSession;
        $this->shiptimize_order = $shiptimize_order;
        $this->scopeConfig  = $scopeConfig;
        $this->shiptimize = $shiptimize;
    }
 
    /**
     * This method runs everytime an order is saved
     * The first time the order is already declared but has not been commited 
     * If there is a pickup point save it to the order table,
     * clear our checkout info
     *
     * if autoexport is enabled and the status matches send this order to shiptimize 
     * 
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        
        $order = $observer->getEvent()->getOrder();
  
        $orderIncrementId =  $order->getIncrementId();
        $orderId = $order->getId();

        if( !$orderId ){
            error_log("Invalid empty orderid in OrderPlacedObserver");
            return;
        } 

        error_log("Order_placed $orderId ");
        
        $this->shiptimize_order->setShopItemId($orderId); 
        $this->shiptimize_order->grantOrderMetaExists(); 
        
        if($this->_checkoutSession->getShiptimizePickupId() ){
            try{
                $this->shiptimize_order->updateOrderMeta(0, '', $this->_checkoutSession->getShiptimizePickupId(), $this->_checkoutSession->getShiptimizePickupLabel(), $this->_checkoutSession->getShiptimizePickupExtendedInfo());   
            } catch(Exception $e){
                error_log( "Exception updating order meta ". $e->getMessage() ); 
            }
        }

        $autoexport = $this->scopeConfig->getValue('shipping/shiptimizeshipping/autoexport');
        $shiptimize_allowed_statuses = explode(',', $this->scopeConfig->getValue('shipping/shiptimizeshipping/exportpreset'))       
        ;

        error_log(" Auto Export $autoexport  Order: $orderId  OrderStatus: ".  $order->getStatus());
        if ($autoexport && in_array( $order->getStatus(), $shiptimize_allowed_statuses) && $order->getStatus() != 'pending') {
            try{
                error_log("Exporting order "); 
                $this->shiptimize->exportOrders(array($orderId));    
            }catch(Exception $e){
                error_log( "Error trying to export order ". $e->getMessage()); 
            } 
        }


        error_log("Session, Pickupid: ".$this->_checkoutSession->getShiptimizePickupId(). ' label: '. $this->_checkoutSession->getShiptimizePickupLabel() . ' Extended info: ' . $this->_checkoutSession->getShiptimizePickupExtendedInfo()  );


        $this->_checkoutSession->setShiptimizePickupId("");
        $this->_checkoutSession->setShiptimizePickupLabel("");
        $this->_checkoutSession->setShiptimizePickupExtendedInfo("");
    }
}
