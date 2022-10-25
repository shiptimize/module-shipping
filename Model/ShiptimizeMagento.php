<?php
namespace Shiptimize\Shipping\Model;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL ^ E_STRICT);
  
use Shiptimize\Shipping\Model\Core\ShiptimizeV3;
use Shiptimize\Shipping\Model\Core\ShiptimizeApiV3;
use Shiptimize\Shipping\Model\Core\ShiptimizeOrder;
use Shiptimize\Shipping\Model\ShiptimizeConstants;

class ShiptimizeMagento extends ShiptimizeV3
{

    /**
     * @var String BRAND
     */
    public static $BRAND = 'Shiptimize';
    
    /**
     * @var bool $debug
     * //production
     */
    private static $debug = false;
  
    /**
     * @var Shiptimize\Shipping\Model\ShiptimizeCarrierManager $carrierManager
     */
    private $carrierManager;

    /**
     * @var Magento\Framework\App\ProductMetadataInterface $productMeta
     */
    private $productMeta;

    /**
     *  @var \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @ScopeConfigInterface $scopeConfig
     */
    private $scopeConfig;
    private $configWriter;


    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Shiptimize\Shipping\Model\ShiptimizeCarrierManager $carrierManager,
        \Magento\Framework\App\ProductMetadataInterface $productMeta,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Shiptimize\Shipping\Model\ShiptimizeOrderMagentoFactory $orderFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory,
        \Magento\Framework\Locale\Resolver $locale,
        \Magento\Framework\App\Filesystem\DirectoryList $directory_list,
        \Magento\Framework\Module\ResourceInterface $db_resource, // get the module version 
        \Magento\Backend\Helper\Data $backendHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;

        $this->messageManager = $messageManager;
        $this->carrierManager = $carrierManager;
        $this->productMeta = $productMeta;
        $this->storeManager = $storeManager;

        $this->orderFactory = $orderFactory;
        $this->collectionFactory = $collectionFactory;

        $this->locale = $locale; 

        $this->db_resource = $db_resource;
        $this->backendHelper = $backendHelper; 
        $this->is_dev = file_exists($directory_list->getRoot().'/isdevmachine') ? 1 : 0; 

        if ($this->is_dev) {
            self::$debug = true; 
        }
    }

    /**
     * Handles an update from the api.
     * Receives a JSON object {"TrackingId":, "OrderId", "Hash", "Status"}
     * Should validate the hash before processing any updates
     * @return object result
     */
    public function apiUpdate()
    {
        $content = file_get_contents("php://input");
        $this->log(" API_UPDATE " . var_export($content, true),  true);

        if (!trim($content)) {
            return ['Error' => "No Content"];
        }

        $data = json_decode($content);
        $url = $this->getCallbackURL();
        $api = $this->getApi();

        if (!isset($data->Status)) {
            return ['Error' => 'Invalid data sent  '.json_encode($data)];
        }

        if (!$api->validateUpdateRequest($data->Status, $data->TrackingId, $url, $data->Hash)) {
            $this->log("API_UPDATE INVALID SIGNATURE IGNORING ");
            return ['Error' => "Invalid Signature"];
        }

        if(isset($data->Action) && $data->Action == 'getshippingmethods') {
          return $this->carrierManager->getshippingmethods(); 
        }
         
       if (isset($data->ShopItemId)) {
         $order =  $this->orderFactory->create();
         $shipmentid = 0; 
         $ordernr = $data->ShopItemId; 

         if(stripos($data->ShopItemId, "--") !== false) {
            $parts = explode('--', $data->ShopItemId);
            $shipmentid = $parts[1];
            $ordernr = $parts[0];
            $this->shiptimize->log("Found Shipment id in order reference " . implode(' ', $parts));
         }

         $order->bootstrap($ordernr,$shipmentid);

          if ($data->Status) {
              $order->setStatusFromTheApi($data->Status);
          }

          if ($data->TrackingId) {
              $order->setTrackingId($data->TrackingId, isset($data->CarrierName) ? $data->CarrierName : '');
          }
        
          return (object)["Tekst" => "Success"];
        }
    }

    /**
     * Executes the sql received by param. Each platform will have a different way of accessing the database
     *
     * @param string sql
     *
     * @return bool - if the query succeded
     */
    protected function executeSQL($sql)
    {
        if ($this->is_dev) {
            error_log($sql);
        }
        $this->connection->query($sql);
    }

    /** 
     * Push label monitor scripts into the ui 
     * @param string the callbackurl returned by the API to monitor the label status  
     **/
    protected function push_label_monitor_scrits($callbackurl, $labelorders) {

        // warnings load before page load 
        $this->messageManager->addWarning('<div id="shiptimize_label_status">' . $this->__("requestinglabel") . ' ' . implode(',', $labelorders) . '</div>'
            . "<script> 
            var labelmonitorurl = '" .  $this->backendHelper->getUrl("shiptimize/shipping/labelmonitorstatus", array()) . "';

            var shiptimize_label_request = '" . $this->__("requestinglabel") . "';
            var shiptimize_label_click = '" . $this->__("labelclick") . "';
            var shiptimize_label_label = '" . $this->__("label") . "'; 

            console.log('labelmonitor ' , labelmonitorurl);

            function shiptimizeMonitorLabelStatus() {
                if(typeof(shiptimize) == 'undefined') {
                    setTimeout(shiptimizeMonitorLabelStatus, 500);
                    return;  
                }
                shiptimize.openLoader(shiptimize_label_request);
                shiptimize.monitorLabelStatus('" . $callbackurl ."'); 
            }

            shiptimizeMonitorLabelStatus();
           </script>");
    }

    /** 
     * Create labels for the given order ids 
     * @param array orderids - an array of order ids 
     **/
    public function printLabel($order_ids) {

        if (empty($order_ids)) {
            error_log("\nno order id was provided, cannot print label");
            $this->messageManager->addWarning("No order id was provided cannot print label");
        }

        if (!is_array($order_ids[0]))
        {
            self::log("\n\n=== Requesting label for ". implode(',', $order_ids));    
        }
        else {
             self::log("\n\n=== Requesting label for ". implode(',', $order_ids[0]));    
        }

        $summary = $this->exportOrders($order_ids, 0,0,true);
        $labelorders = array(); 
        $errors = array(); 

        if(isset($summary->orderresponse)) {      
          if (isset($summary->message)) {
            array_push($errors, $summary->message); 
          }

          // Print Labels for exported orders without errors 
          foreach ($summary->orderresponse as $order) {
            if(isset($order->ErrorList)) { 
              foreach($order->ErrorList as $error) {
                if ($error->Id > 0 && ($error->Id != 200) && ($error->Id != 297)) {
                  array_push($errors, 'Error: ' . $error->Tekst);
                  self::log("Not printing $order->ShopItemId because $error->Tekst ");
                }
                else { // Error we want to ignore 
                  self::log("Ignoring error $error->Id for $order->ShopItemId $error->Tekst ");
                  array_push($labelorders, $order->ClientReferenceCode);               
                }
              }
            }
            else { // Print the label 
              self::log("Printing label for $order->ShopItemId");
              array_push($labelorders, $order->ClientReferenceCode); 
            }
          } 
        }  
        else {
          self::log("No orders in export summary"); 
        }
        
        self::log("Label orders  " . json_encode($labelorders));
        self::log("\nErrors " . json_encode($errors));

        if (!empty($labelorders)) { 
          $labelresponse = self::getApi()->postLabelsStep1($labelorders); 
          $labelresponse->ErrorList = $errors; 

          self::log( "Labelresponse " . json_encode($labelresponse) ); 

          if (isset($labelresponse->response->Error) && $labelresponse->response->Error->Id > 0 ) {
            $this->messageManager->addWarning( $labelresponse->response->Error->Info);
          }

          if (isset($labelresponse->response->CallbackURL)) {
            $this->push_label_monitor_scrits($labelresponse->response->CallbackURL, $labelorders); 
          }

          return $labelresponse;
        }
        else { 
          array_push($errors, 'no labels to print');
        }

        $this->messageManager->addWarning(implode('<br/>', $errors)); 
    }

    /**
     * If we get an auth error we try to get a new token, once
     * @param $order_ids - An array of order ids to export, can also be an array of arrays of (orderid,shipmentid)
     * @param $try - int the iteration of export
     */
    public function exportOrders($order_ids, $try = 0,$addWarning=0,$printLabel = false)
    {
        $summary = (object)[
          'n_success' => 0,
          'n_errors' => 0,
          'nOrders' => 0,
          'nInvalid' => 0,
          'orderresponse' => array()
        ];

        if (empty($order_ids)) {
            $this->messageManager->addWarning(self::getExportSummary($summary));
            return $summary;
        }

        self::log("exportOrders " . var_export($order_ids, true));

        $nInvalid = 0;
        $shiptimize_orders = array();
        $shiptimize_patch_orders = array(); 
     

        foreach ($order_ids as $order_id) {
            $order = $this->orderFactory->create();
            $orderid = $order_id; 
            $shipmentid = 0; 

            if (is_array($order_id)) {
                $orderid = $order_id['orderid']; 
                $shipmentid = $order_id['shipmentid'];
            } 
            else if(stripos($orderid, '--') !== false) {
                $parts = explode('--', $orderid);
                $orderid = $parts[0]; 
                $shipmentid = $parts[1]; 
            }

            $order->bootstrap($orderid, $shipmentid);
            $ordermeta = $order->getOrderMeta(); 

            if ( isset($ordermeta['shiptimize_status']) && $ordermeta['shiptimize_status'] == ShiptimizeOrder::$STATUS_EXPORTED_SUCCESSFULLY ) {
                array_push( $shiptimize_patch_orders, $order->getApiProps() );
            }
            else if ($order->isValid()) {
                array_push($shiptimize_orders, $order->getApiProps());
            } else {
                ++$nInvalid;
                $order->setStatus(\Shiptimize\Shipping\Model\Core\ShiptimizeOrder::$STATUS_EXPORT_ERRORS);
                $order->setMessage($order->getErrorMessages());
            }
        } 

        // POST new orders 
        if (count($shiptimize_orders)) { 
            $response = $this->getApi()->postShipments($shiptimize_orders);
            
            if ($response->httpCode == 401 && $try < 1) {
                $this->refreshToken();
                return $this->exportOrders($order_ids, 1);
            }

            if ($response->httpCode != 200) {
              $this->messageManager->addError("The API returned an error $response->httpCode no orders where exported ");
            }

            $summary = $this->shipmentsResponse($response,$printLabel);

            if (isset($response->response->AppLink)) {
                $summary->login_url =$response->response->AppLink;
            }
        }

        // PATCH Existing orders 
        if (count($shiptimize_patch_orders)) { 
            $response = $this->getApi()->patchShipments($shiptimize_patch_orders);

            if ($response->httpCode == 401 && $try < 1) {
                $this->refreshToken();
                return $this->exportOrders($order_ids, 1);
            }
            else if ($response->httpCode != 200) {
              $this->messageManager->addError("The API returned an error $response->httpCode no orders where exported ");
            }
            else {
                $patchsummary = $this->shipmentsResponse($response,$printLabel);
                $summary->n_success += $patchsummary->n_success; 
                $summary->n_errors += $patchsummary->n_errors; 

                foreach($patchsummary->orderresponse as $order) {
                  array_push($summary->orderresponse, $order);  
                }
            }

            if (isset($response->response->AppLink)) {
                $summary->login_url =$response->response->AppLink;
            }
        }

    
        $summary->nOrders = count($order_ids);
        $summary->nInvalid = $nInvalid;

        if($addWarning){
            $this->messageManager->addWarning($this->getExportSummary($summary));    
        }
        
        return $summary;
    }

    /**
     * Exports all orders with the status defined in config
     */
    public function exportAll()
    {
        $shiptimize_allowed_statuses = explode(',', $this->scopeConfig->getValue('shipping/shiptimizeshipping/exportpreset'));

        $orderscollection = $this->collectionFactory->create();
        $orderTable = $orderscollection->getTable('sales_order');
        $shiptimizeTable = $orderscollection->getTable('shiptimize');
       
        $orderscollection->getSelect()->joinLeft(
            ['shiptimize' => $shiptimizeTable],
            'main_table.entity_id = shiptimize.shiptimize_order_id',
            ['shiptimize_order_id','shiptimize_pickup_label','shiptimize_pickup_id','shiptimize_message','shiptimize_status']
        );

//      joinTable($table,  $cond = null, $fields = null)
        $orderscollection->addFieldToFilter('shiptimize_status', [
            ['neq' => \Shiptimize\Shipping\Model\Core\ShiptimizeOrder::$STATUS_EXPORTED_SUCCESSFULLY],
            ['null' => true]
        ]);

        $orders = $orderscollection->addFieldToFilter('main_table.status', ['in' => $shiptimize_allowed_statuses]);
        $order_ids = [];

        foreach ($orderscollection as $o) {
            $data = $o->getData();
            array_push($order_ids, $data['entity_id']);
        }

        return self::exportOrders($order_ids,0,1);
    }

    /**
     * Execute an sql select
     *
     * @param string $sql
     *
     * @return the results
     */
    public function sqlSelect($sql)
    {
        if ($this->is_dev) {
            error_log($sql);
        }
        return $this->connection->fetchAll($sql);
    }
 
    /**
     * get an api instance
     * @return ShiptimizeApi - an instance of the selected api version
     */
    public function getApi()
    {
     
        if ($this->api == null) {
            $publickey = $this->scopeConfig->getValue('shipping/shiptimizeshipping/publickey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $privatekey = $this->scopeConfig->getValue('shipping/shiptimizeshipping/privatekey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $token =  $this->scopeConfig->getValue('shipping/shiptimizeshipping/token', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $tokenexpires = $this->scopeConfig->getValue('shipping/shiptimizeshipping/token_expires', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            
            if ($this->is_dev) { 
                error_log("Shiptimize Magento is dev " . $this->is_dev);
            }

            // PHP 8 will fail on null here 
            if(!$publickey || !$privatekey){
                $publickey = ''; 
                $privatekey =''; 
                $token = ''; 
                $tokenexpires = '';
            }

            $this->api = ShiptimizeApiV3::instance(trim($publickey), trim($privatekey), ShiptimizeConstants::$SHIPTIMIZE_MAGENTO, trim($token), $tokenexpires, $this->is_dev);  
        }

        return  $this->api;
    }

    /**
     * Explicitly refresh the carriers from the api
     */
    protected function refreshCarriers()
    {
        $api = $this->getApi();

        $carriers = $api->getCarriers();
        if (!empty($carriers)) {
            $carriers = $this->carrierManager->syncCarriers($carriers, [
              'carrier_exists' => $this->__("Carrier Exists"),
              'carrier_new' => $this->__("Added Carrier"),
            ]);

            $this->configWriter->save('shipping/shiptimizeshipping/carriers', json_encode($carriers));
        } else {
            $this->messageManager->addError("Cannot fetch carriers");
        }
    }

    /**
     * When users update carrier settings the token is invalidated
     * Therefore everytime we get a new valid token we should also refresh the carriers
     * @return token object
     */
    public function refreshToken()
    {
        $api = $this->getApi();
        
        if (!$api) {
            error_log("Could not get an API instance, probably no keys are configured yet");
            return;
        }

        $platform_version = $this->productMeta->getVersion();
        
        $this->saveToken((object)[
            'Key' => '',
            'Expire' => ''
        ]);

        $callbackurl =  $this->scopeConfig->getValue('shipping/shiptimizeshipping/apiurl', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if(!$callbackurl){
            $callbackurl = $this->getCallbackURL();  
        } 
        
        $token = $api->getToken($callbackurl, $platform_version, $this->db_resource->getDbVersion('Shiptimize_Shipping'));

        if (isset($token->Key)) {
            $this->saveToken($token);
            $this->refreshCarriers();
        } else {
            $this->messageManager->addError($this->__('Invalid Credentials'));
        }
    }

    /**
     *
     */
    public function getCallbackURL()
    {
        $callbackurl =  $this->scopeConfig->getValue('shipping/shiptimizeshipping/apiurl', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($callbackurl) {
            return $callbackurl;
        }
        
        $platform_version = $this->productMeta->getVersion();
        $matches = array(); 
        preg_match("/([\d]{1}\.[\d]{1})/", $platform_version, $matches);
        $controller = !empty($matches) && $matches[1] == '2.2' ? '22' : ''; 

       return $this->storeManager->getStore()->getBaseUrl() . 'shiptimize/api/update'.$controller;  
    }
    
    /**
     * We will use this to generate the notice on the bulk action,
     * Since we are forced to redirect
     *
     * @param object $summary
     *
     * @return a string containing the notifications html
     */
    public function getExportSummary($summary)
    {

        $html= '<div class="notice notice-info"><p>';
        $html.= sprintf(
            $this->__('Sent %d orders. <br/>Exported: %d <br/> With Errors: %d'),
            isset($summary->nOrders) ? $summary->nOrders : 0,
            isset($summary->n_success) ? $summary->n_success : 0,
            $summary->nInvalid + $summary->n_errors
        );
    
        $html.='</p>';

        if (isset($summary->login_url)) {
            $html.='<p> 
                <strong>'. $this->__('Click') .' <a href="' . $summary->login_url . '" target="_blank">Shiptimize</a> ' .$this->__('if not opened') . '.</strong></p>';

            $html.='<script>window.open("'.$summary->login_url.'", "_blank");</script>';
        }

        $html.= '</div>';

        return $html;
    }

    /** 
     * Make sure we use the same callback url regardless of selected store 
     */ 
    protected function saveToken($token)
    {
        $callbackurl =  $this->scopeConfig->getValue('shipping/shiptimizeshipping/apiurl', \Magento\Store\Model\ScopeInterface::SCOPE_STORE); 
        
        if(!$callbackurl){
            $this->configWriter->save('shipping/shiptimizeshipping/apiurl', $this->getCallbackURL());
        }

        $this->configWriter->save('shipping/shiptimizeshipping/token', $token->Key);
        $this->configWriter->save('shipping/shiptimizeshipping/token_expires', $token->Expire);
    }

    /**
     * Process the server response and append the appropriate status and messages to each shipment
     */
    public function shipmentsResponse($response, $printLabel = false)
    {
        $summary = (object) [
              "n_success" => 0,
              "n_errors" => 0,
              "orderresponse" => array(),
              "errors" => array()
        ];

        if ($response->httpCode != 200) {
            return $summary; 
        } 


        if (isset($response->response->Shipment)) {
            foreach ($response->response->Shipment as $shipment) {
                $orderid = $shipment->ShopItemId; 
                $shipmentid = 0; 
                if(stripos($shipment->ClientReferenceCode, '--') !== false) {
                    $parts = explode("--", $shipment->ClientReferenceCode);
                    self::log("ClientReferenceCode $shipment->ClientReferenceCode " . var_export($shipment, true)  . ' parts ' . var_export($parts,true));
                    $orderid = $shipment->ShopItemId; 
                    $shipmentid = $parts[1]; 
                }

                array_push($summary->orderresponse, $shipment); 
                $order = $this->orderFactory->create();
                $order->bootstrap($orderid, $shipmentid);

                $actualerror = 0; 
                $hasErrors = isset($shipment->ErrorList);
                $order->grantOrderMetaExists();
            
                $orderids = array(
                    array(
                        'shipmentid' => $shipmentid,
                        'orderid' => $orderid
                    )
                ); 

                if ( $hasErrors ) {
                    $order->appendErrors($shipment->ErrorList);  
                    $actualerror = 1;   
                    foreach ($shipment->ErrorList as $error) {
                        $ordermeta = $order->getOrderMeta();
                        if($error->Id == 200 && (!$ordermeta || $ordermeta['shiptimize_status'] != ShiptimizeOrder::$LABEL_STATUS_PRINTED)) {
                            self::log("Order reference $shipment->ClientReferenceCode  was exported but status is not correctly set");
                            $order->setStatus(ShiptimizeOrder::$STATUS_EXPORTED_SUCCESSFULLY);  
                            
                            $this->exportOrders($orderids,1);    

                            $actualerror = 0;
                        }

                        if($error->Id == 298) { // Shipment was deleted in the app and contains incorrect export status in the shop system
                            self::log("Order $shipment->ClientReferenceCode was deleted in app export again orderid $orderid , shipmentid $shipmentid");
                            $order->setStatus(ShiptimizeOrder::$STATUS_NOT_EXPORTED); 
                            $this->exportOrders($orderids,1); 

                            if ($printLabel) { 
                                $labelsummary = $this->printLabel(array($shipment->ShopItemId)); 

                                if(isset($labelsummary->errors)) { 
                                    foreach ($labelsummary->errors as $err) {
                                        array_push( $summary->errors, $err );  
                                    }   
                                }
                                else {
                                    error_log("No error in label summary " . var_export($labelsummary, true));
                                }
                            }            

                            $actualerror = empty($labelsummary->errors) ? 0 : 1;
                        } 
                        else if($error->Id == 297 || $error->Id == 200) { // Shipment already pre-alerted 
                            self::log("Considering error $error->Id as warning. $error->Tekst");
                            $actualerror  = 0; 
                        }
                        else {
                            self::log("Appending Error $error->Id $error->Tekst");
                            array_push ( $summary->errors, "$id - " . $error->Tekst );   
                        } 
                    }
                }

                // some stuff the api considers an error we consider a warning 
                if ($actualerror) {
                    ++$summary->n_errors;  
                }
                else {
                    ++$summary->n_success; 
                }

                $order->setStatus($actualerror ?  \Shiptimize\Shipping\Model\Core\ShiptimizeOrder::$STATUS_EXPORT_ERRORS : \Shiptimize\Shipping\Model\Core\ShiptimizeOrder::$STATUS_EXPORTED_SUCCESSFULLY);

                $warnings = '';

                if (isset($shipment->WarningList)) {
                    foreach ($shipment->WarningList as $warning) {
                        $warnings .= $warning->Tekst . '<br/>';
                    }
                }

    //          Check if Carrier does not match , carrier exists , ID is not 0 and ID != Transporter
                if (isset($shipment->CarrierSelect) && $shipment->CarrierSelect->Id && $shipment->CarrierSelect->Id != $order->getCarrierId()) {
                    $order->addMessage($order->getFormatedMessage($this->__("Diferent carrier selected by the api")));
                }

                $meta = (object)$order->getOrderMeta();
                $order->addMessage(
                    $order->getFormatedMessage(
                        $warnings . ' ' . $this->getStatusLabel(
                            $meta->shiptimize_status
                        )
                    )
                );
            }
        } else if (isset($response->response->ErrorList)) {
            foreach ($response->response->ErrorList as $error) {
                $this->messageManager->addWarning(json_encode($error));
            }
        } else {
            $this->messageManager->addError(json_encode($response->response));
        }

        return $summary;
    } 
    /**
     * return a label for the given status
     * @param int $status
     *
     * @return a string representation of the status
     */
    public function getStatusLabel($status)
    {

        switch ($status) {
            case ShiptimizeOrder::$STATUS_NOT_EXPORTED:
                return $this->__('Not Exported');

            case ShiptimizeOrder::$STATUS_EXPORTED_SUCCESSFULLY:
                return $this->__('Exported');

            case ShiptimizeOrder::$STATUS_EXPORT_ERRORS:
                return $this->__('Error on Export');
           
            default:
                return $this->__("Unknown status of id"). ' ' . $status;
        }
    }

    /**
     * @param mixed $address
     * @param int $shipping_method_id
     * @return String json string with the locations
     */
    public function getPickupLocations($address, $shipping_method_id, $it = 0)
    {
        $api = $this->getApi();
        $response =  json_encode($api->getPickupLocations($address, $shipping_method_id));
        $results = json_decode($response);

        if ($it < 1 && isset($results->Error) && $results->Error->Id == 401) {
            $this->refreshToken();
            return $this->getPickupLocations($address, $shipping_method_id, 1);
        }

        return $response;
    }

    /**
     * Return an iso2 string with the lang
     */
    public function getLang()
    {
        $locale = $this->locale->getLocale();

        if ($this->is_dev) {
            error_log("LANG: $locale");
        }
        
        return substr($locale, 0, 2);
    }

    /**
     * Append a message to the log file, used in dev only
     *
     * @param string msg
     * @param bool $force - if true print the message regardless of debug mode
     */
    public static function log($msg, $force = false)
    {
        if (!$force && !self::$debug) {
            return;
        }
 
        error_log($msg);
    }
}
