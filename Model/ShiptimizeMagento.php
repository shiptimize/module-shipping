<?php
namespace Shiptimize\Shipping\Model;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL ^ E_STRICT);
  
use Shiptimize\Shipping\Model\Core\ShiptimizeV3;
use Shiptimize\Shipping\Model\Core\ShiptimizeApiV3;
use Shiptimize\Shipping\Model\Core\ShiptimizeOrder;

class ShiptimizeMagento extends ShiptimizeV3
{
    /**
     * @var String version - the plugin version
     */
    public static $version = '3.0.4';

    /**
     * @var String THE app_key
     */
    public static $SHIPTIMIZE_MAGENTO = '69710227-6298-3C4C-8386-F6DB72B04BAD';

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
        \Magento\Framework\Locale\Resolver $locale
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
        $this->is_dev = !isset($_SERVER['HTTP_HOST']) ||stripos($_SERVER['HTTP_HOST'], '.local') !== false ? 1 : 0;
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
        $this->log(" API_UPDATE ".var_export($content, true));

        if (!trim($content)) {
            return ['Error' => "No Content"];
        }

        $data = json_decode($content);
        $url = $this->getCallbackURL();
        $api = $this->getApi();

        if(!isset($data->Status)){
            return ['Error' => 'Invalid data sent  '.json_encode($data)];
        }

        if (!$api->validateUpdateRequest($data->Status, $data->TrackingId, $url, $data->Hash)) {
            $this->log("API_UPDATE INVALID SIGNATURE IGNORING ");
            return ['Error' => "Invalid Signature"];
        }
         
       $order =  $this->orderFactory->create();
       $order->bootstrap($data->ShopItemId);

        if ($data->Status) {
            $order->setStatusFromTheApi($data->Status);
        }

        if ($data->TrackingId) {
            $order->setTrackingId($data->TrackingId);
        }
      
        return (object)["Tekst" => "Success"];
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
     * If we get an auth error we try to get a new token, once
     * @param $order_ids - An array of order ids to export
     * @param $try - int the iteration of export
     */
    public function exportOrders($order_ids, $try = 0,$addWarning=0)
    {
        $summary = (object)[
          'n_success' => 0,
          'n_errors' => 0,
          'nOrders' => 0,
          'nInvalid' => 0,
        ];

        if (empty($order_ids)) {
            $this->messageManager->addWarning(self::getExportSummary($summary));
            return $summary;
        }

        $nInvalid = 0;
        $shiptimize_orders = [];

        foreach ($order_ids as $order_id) {
            $order = $this->orderFactory->create();
            $order->bootstrap($order_id);

            if ($order->isValid()) {
                array_push($shiptimize_orders, $order->getApiProps());
            } else {
                ++$nInvalid;
                $order->setStatus(\Shiptimize\Shipping\Model\Core\ShiptimizeOrder::$STATUS_EXPORT_ERRORS);
                $order->setMessage($order->getErrorMessages());
            }
        }
 
        $response = $this->getApi()->postShipments($shiptimize_orders);
        if ($response->httpCode == 401 && $try < 1) {
            $this->refreshToken();
            return $this->exportOrders($order_ids, 1);
        }

        $summary = $this->shipmentsResponse($response);

        if (isset($response->response->AppLink)) {
            $summary->login_url =$response->response->AppLink;
        }

    
        $summary->nOrders = count($order_ids);
        $summary->nInvalid = $nInvalid;

        if($addWarning){
            $this->messageManager->addWarning($this->getExportSummary($summary));    
        }
        
        return $summary;
    }

    /**
     * exports all orders with the status defined in config
     *
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

            $this->api = ShiptimizeApiV3::instance(trim($publickey), trim($privatekey), self::$SHIPTIMIZE_MAGENTO, trim($token), $tokenexpires);
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
 
        $platform_version = $this->productMeta->getVersion();
        
        $this->saveToken((object)[
            'Key' => '',
            'Expire' => ''
        ]);

        $token = $api->getToken($this->getCallbackURL(), $platform_version, self::$version);

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
        return $this->storeManager->getStore()->getBaseUrl().'shiptimize/api/update';
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


    protected function saveToken($token)
    {
        $this->configWriter->save('shipping/shiptimizeshipping/token', $token->Key);
        $this->configWriter->save('shipping/shiptimizeshipping/token_expires', $token->Expire);
    }

    /**
     * Process the server response and append the appropriate status and messages to each shipment
     */
    public function shipmentsResponse($response)
    {
        if ($response->httpCode != 200) {
            return (object) [
              "n_success" => 0,
              "n_errors" => 0
            ];
        }
 
        $n_success = 0;
        $n_errors = 0;

        if (isset($response->response->Shipment)) {
            foreach ($response->response->Shipment as $shipment) {
                $id = $shipment->ShopItemId;
                $order = $this->orderFactory->create();
                $order->bootstrap($id);

                $hasErrors = isset($shipment->ErrorList);
                $order->grantOrderMetaExists();
            
                $order->setStatus($hasErrors ?  \Shiptimize\Shipping\Model\Core\ShiptimizeOrder::$STATUS_EXPORT_ERRORS : \Shiptimize\Shipping\Model\Core\ShiptimizeOrder::$STATUS_EXPORTED_SUCCESSFULLY);
            
                if ($hasErrors) {
                    $order->appendErrors($shipment->ErrorList);
                    ++$n_errors;
                } else {
                    ++$n_success;
                }

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

        return (object) [
          "n_success" => $n_success,
          "n_errors" => $n_errors
        ];
    } 
    /**
     * return a label for the given status
     *
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
