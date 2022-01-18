<?php
namespace Shiptimize\Shipping\Model;

use Shiptimize\Shipping\Model\Core\ShiptimizeOrder;

class ShiptimizeOrderMagento extends \Shiptimize\Shipping\Model\Core\ShiptimizeOrder
{
    private $magentoOrder;
    private $tableName;
 
    public function __construct(
        \Magento\Framework\App\ResourceConnection $dbResource,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Sales\Model\Convert\Order $convertOrder,
        \Magento\Sales\Model\Order\Shipment\TrackFactory $trackFactory,
        \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier
    )
    { 
        $this->dbResource = $dbResource;
        $this->orderRepository = $orderRepository;
        $this->connection = $this->dbResource->getConnection();

        $this->tableName = $this->dbResource->getTableName("shiptimize");
        $this->scopeConfig = $scopeConfig;
        $this->messageManager = $messageManager;
        $this->convertOrder = $convertOrder;
        $this->trackFactory = $trackFactory;
        $this->shipmentNotifier = $shipmentNotifier;  

        $this->is_dev = !isset($_SERVER['HTTP_HOST']) ||stripos($_SERVER['HTTP_HOST'], '.local') !== false ? 1 : 0;
    }

    /**
     * Add a message to the existing list of messages
     * If you want to append a date make sure to run the getFormatedMessage before
     *
     * @param string message
     */
    public function addMessage($message)
    {
        $meta = $this->getOrderMeta();

        if (!$meta) {
            $sql = sprintf(
                'insert into %s (shiptimize_order_id) VALUES(%d)',
                $this->tableName,
                $this->ShopItemId
            );
            $this->executeSQL($sql);
            $meta= ['shiptimize_message'  => ''];
        }
 
        $previous_message = is_array($meta) ? $meta['shiptimize_message'] : $meta->shiptimize_message;

        $sql = sprintf(
            "update %s set shiptimize_message=\"%s\" where shiptimize_order_id=%d",
            $this->tableName,
            $previous_message.$message,
            $this->ShopItemId
        );

        return $this->executeSQL($sql);
    }


    /**
     * insert order meta , don't forget to escape the strings
     *
     * @param {type} $order_id - the type is defined by the platform, usually int but it can be a string
     * @param int $status
     * @param int $carrier_id
     * @param int $pickup_id
     * @param string $pickup_label
     * @param string $pickup_extended
     * @param string $tracking_id
     * @param string $message
     */
    public function addOrderMeta($order_id, $status, $carrier_id, $pickup_id, $pickup_label, $pickup_extended, $tracking_id = '', $message = '')
    {
        $sql = sprintf(
            "insert into `%s` 
           (shiptimize_order_id,`shiptimize_status`,shiptimize_carrier_id,shiptimize_pickup_id,shiptimize_pickup_label,shiptimize_pickup_extended,shiptimize_tracking_id,shiptimize_message) VALUES(\"%s\",%d,%d,\"%s\",\"%s\",\"%s\",\"%s\",\"%s\") ",
            $order_id,
            $status,
            $carrier_id,
            $pickup_id,
            $pickup_label,
            $pickup_extended,
            $tracking_id,
            $message
        );

        return $this->executeSQL($sql);
    }


    /**
     * @param mixed $errors - array(Id, Tekst)
     */
    public function appendErrors($errors)
    {
        $messages = '';

        foreach ($errors as $error) {
            if ($error->Id == ShiptimizeOrder::$ERROR_ORDER_EXISTS) {
                $this->setStatus(ShiptimizeOrder::$STATUS_EXPORTED_SUCCESSFULLY);
                $this->addMessage($this->getFormatedMessage("Order Exists"));
            } else {
                $messages.= $this->getFormatedMessage($error->Tekst);
            }
        }

        $this->addMessage($messages);
    }

    /** 
     * Extract the shipment properties from magento to send to the api 
     */
    public function bootstrap($mage_id)
    {
        if (!$mage_id) {
            error_log( "Invalid EMPTY order id on ShiptimizeOrderMagento::bootstrap");
            return;
        }

        $this->ShopItemId = $mage_id;
        $this->magentoOrder = $this->orderRepository->get($this->ShopItemId);
        $this->ClientReferenceCode = $this->magentoOrder->getIncrementId();  // the name that shows up on the list, is not the same as the numerical Id

        $this->extractAddress();
        $this->extractCarrier();
        $this->extractItems();
    }
 
    public function extractAddress()
    {
        $shipping = $this->magentoOrder->getShippingAddress();
        
        if (!$shipping) {
            $shipping = $this->magentoOrder->getBillingAddress();
        }

        // In mage street is an array of arbitrary length set in the config
        $street = $shipping->getStreet();
        $houseNumberExtensionField = $this->scopeConfig->getValue('shipping/shiptimizeshipping/housenumberextension', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if ($houseNumberExtensionField && isset($street[$houseNumberExtensionField]) && $street[$houseNumberExtensionField] > 0) {
            $this->NumberExtension = $street[$houseNumberExtensionField];
        }
 
        $this->CompanyName = $shipping->getCompany();
        $this->Name = $shipping->getFirstname() . ' ' . $shipping->getMiddlename() . ' ' . $shipping->getLastname();
        $this->Streetname1 = $street[0];

        /** TODO: From the configs get if street 2 is the house number **/
        $this->Streetname2 = isset($street[1]) ? $street[1] : '';
        $this->PostalCode = $shipping->getPostcode();
        $this->City = $shipping->getCity();
        $this->State = $shipping->getRegionId();
        $this->Country = $shipping->getCountryId();

        $this->Phone = $shipping->getTelephone();
        $this->Email = $shipping->getEmail();

    }

    /**
     *
     */
    public function extractCarrier()
    {
        $shippingMethod = $this->magentoOrder->getShippingMethod();
        $parts = explode('_',$shippingMethod); 
        
        $meta = $this->getOrderMeta();
 
        if ($meta['shiptimize_pickup_id']) {
            $this->PointId = $meta['shiptimize_pickup_id'];
            $this->ExtendedInfo = $meta['shiptimize_pickup_extended'];
        }

        if (stripos($shippingMethod, 'ShiptimizeTableRates') !== false) {
            /** (number_(not_a_number)(number) **/ 
            if (preg_match("/ShiptimizeTableRates_([\d]+)_([^0-9]*)([\d]+)/",$shippingMethod,$carrierResults)) {
                $this->Transporter = $carrierResults[1]; 
                $this->extractCarrierFromRateId($carrierResults[3]);
            } 
            else {
                $this->addMessage($this->getFormatedMessage("This rule does not match a carrier we know about, ignoring carrier"));
            }
        }
        else if (stripos($shippingMethod, 'shiptimize') !== false) {
            $shiptimizeCarriers = json_decode( $this->scopeConfig->getValue('shipping/shiptimizeshipping/carriers')); 

            foreach ($shiptimizeCarriers as $carrier)
            {
                if (!isset($carrier->ClassName)) {
                    error_log(" invalid carrier in shiptimize carriers ".json_encode($carrier));
                } else if( $carrier->ClassName == $parts[0]) {
                    $this->Transporter = $carrier->Id;
                }
            }
        }

        $this->ShippingMethodId = $parts[0];

      //echo $shippingMethod; die(var_export($this->getApiProps()));
    }

    /**
     * @return  the id of the late night delivery option for the given carrierId
     */
    public function getAvondLeveringId($carrierId)
    {
        switch ($carrierId) {
            case '20':
                return 65;
            case '25':
                return 42;
            
            default: 
                $this->messageManager->addError("No late night delivery option for carrierId $carrierId "); 
                return '';
        }
    }

    /**
     * Evaluates the choosen rule and determines if we need to add options to the current shipment
     */
    public function extractCarrierFromRateId($rateId)
    {
        $tableName = $this->dbResource->getTableName('shiptimize_customtablerates');
        $rates = $this->sqlSelect(sprintf("select * from %s where id=%d", $tableName, intval($rateId)));
        
        if (count($rates)) {
            $options = explode(',',trim(strtolower($rates[0]['carrier_options'])));
            $carriers = json_decode(
                $this->scopeConfig->getValue(
                    'shipping/shiptimizeshipping/carriers',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                )
            );
            $carrierId = $rates[0]['carrier_id'];
            $this->OptionList = array(); 
            foreach ($options as $option) { 
                    switch ($option) {
                        case 'avondlevering':
                            $this->OptionList = array(
                                (object)array(
                                    'Id' => $this->getAvondLeveringId($carrierId),
                                    'OptionFields' => array(
                                        array('Id' => 1)
                                    )
                                )
                            );
                            break;
                        case 'servicepoint':
                            break; //not relevant header(string)re set by the api if pointId is set
                        default:
                            $foundoption = 0; 
                            //is it a service level ? 
                            foreach ($carriers as $carrier) {
                                if($carrier->Id == $carrierId) { 
                                    $optionsbody = '';
                                    if (isset($carrier->OptionList)) {
                                        foreach ($carrier->OptionList as $carrieroption) {
                                             
                                            if (isset($carrieroption->OptionValues)) {
                                                foreach ( $carrieroption->OptionValues as $optionValue) {
                                                    if($option == strtolower($optionValue->Name)) {
                                                        $foundoption = 1;              
                                                        
                                                        array_push($this->OptionList, (object)array(
                                                            'Id' => $carrieroption->Id, 
                                                            'Value' => $optionValue->Id
                                                        ));  

                                                        $this->addMessage($this->getFormatedMessage("Added option $optionValue->Name"));   
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            if (!$foundoption) {  
                                $this->addMessage($this->getFormatedMessage("unknown option $option ignoring "));
                            }
                        break;
                    } 
            }    
        }
        else {
            $this->addMessage( $this->getFormatedMessage("The rate id ". $rateId . " no longuer exists, did you upload new rules? ignoring options ") );
        } 
    }

    public function getWeightInGrams($productWeight, $systemUnit )
    {
        switch ($systemUnit) {
            case 'kgs':
                return  intval($productWeight * 1000);
            case 'lbs':
                return intval($productWeight * 453.592);
            default:
                $this->messageManager->addError("Unknown weight unit $systemUnit, tell support about this");
                return 0;
        }
    }

    public function getShippingMethodTitle()
    {
        if ($this->Transporter > 0) { 
            $shiptimizeCarriers = json_decode( $this->scopeConfig->getValue('shipping/shiptimizeshipping/carriers')); 

            foreach ($shiptimizeCarriers as $carrier)
            {
                if( $carrier->Id == $this->Transporter) {
                    return $carrier->Name; 
                }
            }
        } 

        return $this->magentoOrder->getShippingDescription(); 
    }

    /**
     * The list of items contains the variation and the parent product, ignore the parent 
     */
    public function extractItems()
    {
        $items = $this->magentoOrder->getAllItems();
        $this->ShipmentItems = array();
        $systemUnit = $this->scopeConfig->getValue('general/locale/weight_unit');
        $this->Weight = 0;
        $this->Value = 0;
        $this->Description = '';

        foreach ($items as $item) {
            $data = $item->getData(); 
            $qty =  $data['qty_ordered'];
            $weight = $this->getWeightInGrams($data['weight'],$systemUnit);
            $value =  number_format($qty * $data['price_incl_tax'], 2, '.', '');

            if ($value > 0) {
                array_push(
                    $this->ShipmentItems,
                        array(
                            'Count' => $qty,
                            'Id' => $data['item_id'],
                            'Name' => $this->escapeTextData($data['name']),
                            'Type' => 4, // 1 - Gift, 2 - Documents, 3 - Sample , 4 - Other
                            'Value' => $value,
                            'Weight' => intval($weight * $qty)
                        )
                );

                $this->Weight += $weight;
                $this->Value += $value;
                $this->Description .= $qty . ' - ' . $this->escapeTextData($data['name']) . '; ';
            }
        }

        //die(json_encode($this->getApiProps()));
    }

    public function executeSQL($sql)
    {
        if ($this->is_dev) {
            error_log($sql);
        }
        $this->connection->query($sql);
    }

    /**
     * If there is not meta for this order create it
     *
     * @param int $order_id
     */
    public function grantOrderMetaExists()
    {
        $meta = self::getOrderMeta($this->ShopItemId);

        if (! $meta) {
            $sql = sprintf(" insert into `%s` (`shiptimize_order_id`) VALUES( %d ) ", $this->tableName, $this->ShopItemId);
            $this->executeSQL($sql);
        }
    }

    public function getCarrierId()
    {
        return $this->Transporter;
    }

    /**
     * Retrieve shiptimize metadata for the order with id
     * The type of the id will vary with the platform
     * usually int or string
     *
     */
    public function getOrderMeta()
    {
        $results = $this->sqlSelect(sprintf(" select * from `%s` where shiptimize_order_id=%d", $this->tableName, $this->ShopItemId));
        return !empty($results) ? $results[0] : null;
    } 

    /**
     * Set the message for this order
     *
     * @param string message
     */
    public function setMessage($message)
    {
        $this->executeSQL(sprintf(" update `%s` set shiptimize_message=\"%s\"  where shiptimize_order_id=%d", $this->tableName, $message, $this->ShopItemId));
    }     

    /**
     * Set the exported Status
     *
     * @param string message
     */
    public function setStatus($status)
    {
        $this->executeSQL(sprintf(" update `%s` set shiptimize_status=\"%s\"  where shiptimize_order_id=%d", $this->tableName, $status, $this->ShopItemId));
    } 

    /**
     * @param number $status - this is mapped in tables shared between the plugin and the api - check the plugin docs
     * we should append a message to the order saying a status was pushed from the api
     */
    public function setStatusFromTheApi($status)
    {
        $mageStatus = null; 

        switch ($status) {
            case 1:
            case 2:
            case 4:
            case 5:
                /* 1: 'Cancelled',
                2 => 'Closed',
                4 => 'Fraud',
                5 => 'On Hold',
                6 => 'Payment Review',
                7 => 'Paypal Canceled Reversal',
                8 => 'Paypal Reversed',
                9 => 'Pending',
                10 => 'Pending Payment',
                11 => 'Pending Paypal',*/
                return;
            
            case 3:
                //'Complete',
                $mageStatus = \Magento\Sales\Model\Order::STATE_COMPLETE;
                break;
            case 12: 
                //processing 
                $mageStatus = \Magento\Sales\Model\Order::STATE_PROCESSING;
                break;

            default:
                return;
        }

        if ($this->is_dev) {
            error_log("Set  STATUS  ". $mageStatus);
        }
        $this->magentoOrder->setState($mageStatus);
        $this->magentoOrder->save();
    }

    /**
     * @param string tracking_id
     * we should append a message saying the tracking id was pushed from the api
     * this should also be appended to the order details so the client can check it
     */
    public function setTrackingId($tracking_id, $carrier_name)
    {
        $shipments = $this->magentoOrder->getShipmentsCollection()->getItems(); 
        
        $trackingdata = array(
                'carrier_code' => 'custom',
                'title'  =>  $carrier_name ? $carrier_name : $this->getShippingMethodTitle(),
                'number' => $tracking_id
        );

        if (empty($shipments)) { 
            $this->shipOrder($trackingdata); 
        }
        else { 
            $shipment = ''; 

            # yes, magento could pull off the amazing thing 
            # of returning a non empty array with undefined items in it   
            # reproduce by manually deleting the tracking info in the order details 
            # doing it the safe way
            foreach($shipments as $s) { 
                if( get_class($s) == 'Magento\Sales\Model\Order\Shipment') 
                {
                    $shipment = $s; 
                } 
            }
            
            # Could we get a valid shipment? 
            if ($shipment) { 
                $orderShipment = $this->convertOrder->toShipment($this->magentoOrder);
                $track = $this->trackFactory->create()->addData($trackingdata);
                $shipment->addTrack($track)->save();

                $this->shipmentNotifier->notify($orderShipment);
            }
            else {
                error_log("Could not find a valid shipment in the list of shipments returned by magento, adding a new one "); 
                $this->shipOrder($trackingdata); 
            }
        }

        $this->addMessage("<br/>TrackingId received from the api: $tracking_id ");
    }

    /**  
     * Create a shipment for this order
     * Return the created shipment
     */ 
    private function shipOrder($trackingdata)
    {
        if(! $this->magentoOrder->canShip() ){
            error_log( "Can't create shipment for this order "); 
            $this->addMessage("Can't add trackingId received from the api: $tracking_id ");
            return;
        }

        $orderShipment = $this->convertOrder->toShipment($this->magentoOrder);

        foreach ($this->magentoOrder->getAllItems() AS $orderItem) {

             // Check virtual item and item Quantity
            if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }

            $qty = $orderItem->getQtyToShip();
            $shipmentItem = $this->convertOrder->itemToShipmentItem($orderItem)->setQty($qty);
            $orderShipment->addItem($shipmentItem);
        }

        $orderShipment->register();
        //$orderShipment->getOrder()->setIsInProcess(true);
        try {

            // Save created Order Shipment
            $orderShipment->save();
            $orderShipment->getOrder()->save();
            $orderShipment->save();


            $track = $this->trackFactory->create()->addData($trackingdata);

            $orderShipment->addTrack($track)->save();

            // Send Shipment Email
            $this->shipmentNotifier->notify($orderShipment);

        } catch (\Exception $e) {
            $this->addMessage('<br/>' . $e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
            __($e->getMessage())
            );
        }

        return $orderShipment;
    }

    public function sqlSelect($sql)
    {
        if ($this->is_dev) {
            error_log($sql);
        }
        return $this->connection->fetchAll($sql);
    }
    
    /**
     * update order meta , don't forget to escape the strings
     *
     * @param int $status
     * @param int $carrier_id
     * @param int $pickup_id
     * @param string $pickup_label
     * @param string $pickup_extended
     * @param string $tracking_id
     * @param string $message
     */
    public function updateOrderMeta( $status, $carrier_id, $pickup_id, $pickup_label, $pickup_extended, $tracking_id = '', $message = '')
    {
        $sql = sprintf(
            "update %s
            set shiptimize_status =  %d, 
            shiptimize_carrier_id=%d,
            shiptimize_pickup_id =\"%s\",
            shiptimize_pickup_label = \"%s\",
            shiptimize_pickup_extended = \"%s\",
            shiptimize_tracking_id=\"%s\",
            shiptimize_message=\"%s\"
            where shiptimize_order_id=\"%s\"",
            $this->tableName,
            $status,
            $carrier_id,
            $pickup_id,
            $pickup_label,
            $pickup_extended,
            $tracking_id,
            $message,
            $this->ShopItemId
        );

        return $this->executeSQL($sql);
    }
}
