<?php
namespace Shiptimize\Shipping\Model;

class ShiptimizeCarrierManager
{

    private $existingCarriers;

    private $messageManager;
    private $moduleReader;

    private $cacheTypeList;

    /** 
     * @param \Magento\Framework\Module\Dir\Reader $moduleReader,
     * @param \Magento\Framework\Message\ManagerInterface $messageManager,
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     */
    public function __construct(
        \Magento\Framework\Module\Dir\Reader $moduleReader,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Shipping\Model\Config $shippingConfig,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->moduleReader = $moduleReader;
        $this->messageManager = $messageManager;
        $this->shippingConfig = $shippingConfig;
        $this->scopeConfig = $scopeConfig; 
        $this->cacheTypeList = $cacheTypeList;
    }


    /**
     * Create the necessary fields to display a carrier node
     * We receive the node to append to because there is no good way to add a node with children to another node
     */
    public function createCarrierDescriptorNode($carrier, $node, $name = '')
    {
        if (!$name) {
            $name =  'Shiptimize '.$carrier->Name;
        }

        $node->addAttribute("translate", "label");
        $node->addAttribute("id", $this->getClassNameForCarrier($carrier));

        $node->addAttribute("showInDefault", "1");
        $node->addAttribute("showInWebsite", "1");
        $node->addAttribute("showInStore", "1");
        

        $node->addChild("label", $name);
 
        $this->createCarrierFieldNode($node, [
            'node_name'=>"active",
            'label'=>"Enabled",
            'frontend_type'=>"select",
            'show'=>[1,1,1],
            'sort_order'=> 1,
            'source_model'=> "Magento\Config\Model\Config\Source\Yesno"
        ]);

        $this->createCarrierFieldNode($node, [
            'node_name'=>"title",
            'label'=>"Title",
            'frontend_type'=>"text",
            'show'=>[1, 1, 1],
            'sort_order'=> 2
        ]);

        $this->createCarrierFieldNode($node, [
            'node_name'=> "price",
            'label'=> "Default Price",
            'frontend_type'=> "text",
            'show'=>[  1, 1, 1 ],
            'sort_order' =>  3
        ]);


        $this->createCarrierFieldNode($node, [
            'node_name'=> "type",
            'label'=> "Type",
            'frontend_type'=> "select",
            'show'=>[  1, 1, 1 ],
            'sort_order' =>  3,
            'source_model' => 'Magento\OfflineShipping\Model\Config\Source\Flatrate'
        ]);
         
        $this->createCarrierFieldNode($node, [
        'node_name'=> "handling_type",
        'label'=>"Calculate Handling Fee",
        'frontend_type' =>  "select",
        'show'=>[  1, 1, 1],
        'sort_order'  =>  6,
        'source_model'=>"Magento\Shipping\Model\Source\HandlingType"
        ]);
        
        $this->createCarrierFieldNode($node, [
            'node_name' => "handling_fee",
            'label'=> "Handling Fee",
            'frontend_type'=> "text",
            'show'  => [   1, 1, 1],
            'sort_order' => 7,
            'validate' => 'validate-number validate-zero-or-greater'
        ]);

        $this->createCarrierFieldNode($node, [
            'node_name' => "sallowspecific",
             'label'=>"Ship to Applicable Countries",
             'frontend_type'=> "select",
             'show' => [  1, 1, 0],
             'sort_order'=> 8,
             'source_model'=> 'Magento\Shipping\Model\Config\Source\Allspecificcountries',
             'frontend_class'=>'shipping-applicable-country'
        ]);

        $this->createCarrierFieldNode($node, [
            'node_name'=>"specificcountry",
            'label'=> "Ship to specific Countries",
            'frontend_type'=> "multiselect",
            'show' => [  1, 1, 0],
            'sort_order'=> 9,
            'source_model'=> 'Magento\Directory\Model\Config\Source\Country'
            ]);
    }

    /**
     * Declare the editable properties of the table rates 
     * @param xmlnode system > section id="carriers" 
     */ 
    private function tableratesSystemxml($carriersNode){
        $node = $carriersNode->addChild('group');  

        $node->addAttribute("translate", "label");
        $node->addAttribute("id", 'ShiptimizeTableRates');
        $node->addAttribute("sortOrder","10");
        $node->addAttribute("showInDefault", "1");
        $node->addAttribute("showInWebsite", "0");
        $node->addAttribute("showInStore", "0");
        

        $node->addChild("label", 'Shiptimize Table Rates');
 
        $this->createCarrierFieldNode($node, [
            'node_name'=>"active",
            'label'=>"Enabled",
            'frontend_type'=>"select",
            'show'=>[1,1,1],
            'sort_order'=> 1,
            'source_model'=> "Magento\Config\Model\Config\Source\Yesno"
        ]);

        $this->createCarrierFieldNode($node, [
            'node_name'=>"title",
            'label'=>"Title",
            'frontend_type'=>"text",
            'show'=>[1, 1, 1],
            'sort_order'=> 2
        ]);

        $this->createCarrierFieldNode($node, [
            'node_name'=>"export",
            'label'=> "Export",
            'frontend_model'=> "\Shiptimize\Shipping\Block\Adminhtml\Form\Field\Export",
            'show' => [  1, 1, 0],
            'sort_order'=> 9
        ]);

        $this->createCarrierFieldNode($node, [
            'node_name'=>"import",
            'label'=> "Import",
            'frontend_type'=> "Shiptimize\Shipping\Block\Adminhtml\Form\Field\Import",
            'backend_model' => '\Shiptimize\Shipping\Model\Config\Backend\CustomTableRates',
            'show' => [  1, 1, 0],
            'sort_order'=> 9
        ]);
    
    }
    
    private function createCarrierClasses()
    {
        $carrierModelFolder = $this->moduleFolder.'/Model/Carrier/';

        foreach ($this->existingCarriers as $carrier) {
            $className = $this->getClassNameForCarrier($carrier);
            $fileName = $carrierModelFolder.$className.'.php';
            $carrier->ClassName = $className;
            
            if (!file_exists($fileName)) {
                $this->createClass($carrier, $fileName);
            }
        }
    }

    /**
     * Creates an xml node with the carrier info provided
     *
     * @param $carrier - a carrier as received from the api
     * @param $carrier_node - the node where to add this carrier
     * @param $idx - the instance number so we can distinguish between multiple instances of the same carrier
     */
    public function createCarrierNode($carrier, $carrier_node, $idx = '')
    {
        $className = $this->getClassNameForCarrier($carrier, $idx);

        $carrier_node->addChild('active', 0);
        $carrier_node->addChild("model", "Shiptimize\Shipping\Model\Carrier\\".$className);
        $carrier_node->addChild("Name", $carrier->Name);
        $carrier_node->addChild("Id", $carrier->Id);
        $carrier_node->addChild("HasPickup", $carrier->HasPickup ? "1" : "0");
 
        return $carrier_node;
    }

/**
     * Creates an xml node for the table rates
     *
     * @param $parent_node - the carriers node to which we will append the table rates
     */
    public function createCarrierNodeTableRates($parent_node)
    {
        $className ='ShiptimizeTableRates';
        $carrier_node  = $parent_node->addChild($className, '');

        $carrier_node->addChild('active', 0);
        $carrier_node->addChild("model", "Shiptimize\Shipping\Model\Carrier\\".$className);
        $carrier_node->addChild("Name", "Shiptimize Table Rates");
        $carrier_node->addChild("Id", "");
        $carrier_node->addChild("HasPickup", "");
 
        return $carrier_node;
    }

    /**
     * If the file does not exist create it
     *
     * @param Carrier $carrier - a carrier object received from the api
     * @param String $fileName - the name of the file where to save this class
     * @param int idx - an integer to distinguish between carrier instances
     */
    public function createClass($carrier, $fileName, $idx = '')
    {
        $className = $this->getClassNameForCarrier($carrier, $idx);
        $classContent = "<?php 
        namespace Shiptimize\Shipping\Model\Carrier; 

class  {$className} extends \Shiptimize\Shipping\Model\Carrier\ShiptimizeShipping   
{
    protected \$_code='$className';
}";

        $file = fopen($fileName, 'w');
        fwrite($file, $classContent);
        fclose($file);
    }

    /**
     * @param node $parent_node
     * @param array $options
     */
    public function createCarrierFieldNode($parent_node, $options)
    {
        $node = $parent_node->addChild('field');

        $node->addAttribute("translate", "label");
        $node->addAttribute('id', $options['node_name']);
        $node->addAttribute("sortOrder", $options['sort_order']);
        $node->addAttribute("showInDefault", $options['show'][0]);
        $node->addAttribute("showInWebsite", $options['show'][1]);
        $node->addAttribute("showInStore", $options['show'][2]);

        $node->addChild("label", $options['label']);

        if (isset($options['frontend_type'])) {
            $node->addAttribute("type", $options['frontend_type']);
        }

        if (isset($options['source_model'])) {
            $node->addChild("source_model", $options['source_model']);
        }

        if (isset($options['frontend_class'])) {
            $node->addChild("frontend_class", $options['frontend_class']);
        }

        if (isset($options['validate'])) {
            $node->addChild("validate", $options['validate']);
        }

        if (isset($options['backend_model'])) {
            $node->addChild("backend_model", $options['backend_model']);
        }

        if (isset($options['frontend_model'])) {
            $node->addChild("frontend_model", $options['frontend_model']);
        }
    }


    /**
     * Remember: Underscores mean folders in Zend class names.. we can't use them
     * @param object $carrier - the carrier object as returned by the api
     * @param $idx - the carrier instance name so we can distinguish between multiple instances of the same carrier
     * @return a class name for this carrier
     */
    private function getClassNameForCarrier($carrier, $idx = '')
    {
        if (!isset($carrier->Name)) {
            $this->messageManager->addError("Invalid carrier ". var_export($carrier, true));
        }

        $clean_name = preg_replace('/[^a-zA-Z0-9]+/', '', $carrier->Name);
        return 'Shiptimize'.strtolower($clean_name).$idx;
    }

    /**
     * @return a list of class names
     * We do this to avoid writting a specific class for each carrier.
     * We need this list to determine the files we care about in CountryRates uploadAndImport
     */
    public function getClassNames()
    {
        $classes = [];

        if (!isset($this->systemFileName)) {
            $this->loadConfig();
        }

        $this->loadCarriers();

        foreach ($this->existingCarriers as $carrier) {
            array_push($classes, $this->getClassNameForCarrier($carrier));
        }

        return $classes;
    }

    public function getShippingMethods()
    {
        $magecarriers = $this->shippingConfig->getAllCarriers();
        $shippingmethods = array(); 
        foreach ($magecarriers as $carrierCode => $carrierModel) {
            $carrierTitle = $this->scopeConfig->getValue(
                'carriers/' . $carrierCode . '/title',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
            
            if ($carrierTitle) {
                array_push($shippingmethods, array('id' => $carrierCode, 'title' => $carrierTitle));
            }
        }
        return $shippingmethods;
    }

    /**
     * @return true if this carrier already exists in mage
     */
    private function isMagentoCarrier($carrier)
    {
        foreach ($this->existingCarriers as $mageCarrier) {
            if (isset($mageCarrier->Id) && $carrier->Id == $mageCarrier->Id) {
                return true;
            }
        }

        return false;
    }


    /*
     * Load the existing carriers into this object, models are declared in the config.xml file
     * default > carriers > carrier_class_handle
     */
    private function loadCarriers()
    {
        $xmlCarriers = $this->xml_config->xpath("//default//carriers");
 
        $carriers = [];
        foreach ($xmlCarriers[0]->children() as $carrier) {
            $curr_carrier = [];
            foreach ($carrier->children() as $key => $value) {
                $curr_carrier[$key] = $value;
            }
            array_push($carriers, (object)$curr_carrier);
        }

        $this->existingCarriers = $carriers;
        return $carriers;
    }


    private function loadConfig()
    {

        $this->moduleFolder =  $this->moduleReader->getModuleDir(
            '',
            'Shiptimize_Shipping'
        );

        $this->systemFileName = $this->moduleFolder.'/etc/adminhtml/system.xml';
        $this->configFileName = $this->moduleFolder.'/etc/config.xml';
        clearstatcache();

        if (file_exists($this->configFileName)) {
            $this->xml_config = simplexml_load_file($this->configFileName);
        } else {
            $this->messageManager->addError($this->configFileName. " Not found");
            return false;
        }

        if (file_exists($this->systemFileName)) {
            $this->xml_system = simplexml_load_file($this->systemFileName);
        } else {
            $this->messageManager->addError($this->systemFileName . " Not Found");
            return false;
        }


        return true;
    }

    /**
     * @param array $carriers - the carriers as received from the api
     */
    public function syncCarriers($carriers, $labels)
    {
        if (!$this->loadConfig()) {
            return false;
        }

        $this->existingCarriers = $this->loadCarriers();
        $sync_carriers = [];

        foreach ($carriers as $carrier) {
            if(!isset($carrier->Name)){
                error_log("Invalid Carrier ".json_encode($carrier));
                continue;
            }

            if (!$this->isMagentoCarrier($carrier)) {
                $this->messageManager->addWarning($labels['carrier_new'].' '.$carrier->Name);
            } else {
                $this->messageManager->addWarning($labels['carrier_exists'].' '.$carrier->Name);
            }
             
            array_push($sync_carriers, $carrier);
        }

        $this->existingCarriers = $sync_carriers;
        
        $this->updateConfigXml();
        $this->updateSystemXml();
        $this->createCarrierClasses();

        $this->flushConfigCache();
        return $sync_carriers;
    }

    /**
     * Rewrite the carriers node in config.xml
     * Remove the old carriers node
     * Add Cached carriers to the carrier node
     * ReWrite the config file
     */
    private function updateConfigXml()
    {
        $xmlCarriers = $this->xml_config->xpath("//default//carriers");
        $dom_ref  = dom_import_simplexml($xmlCarriers[0]);
        $dom_ref->parentNode->removeChild($dom_ref);

        $xmlDefault = $this->xml_config->xpath("//default");
        $carriersNode =  $xmlDefault[0]->addChild('carriers');
 
 //     Append the table rates 
        $this->createCarrierNodeTableRates($carriersNode); 

        foreach ($this->existingCarriers as $carrier) {
            $nodeName = $this->getClassNameForCarrier($carrier);
            $node = $carriersNode->addChild($nodeName, '');
            $curr = $this->createCarrierNode($carrier, $node);
        }

        if (!$this->xml_config->asXML($this->configFileName)) {
            $this->messageManager->addWarning("Cannot write  Shiptimize/Shipping/etc/config.xml please check the file permissions");
        }
    }



    /**
     * Remove the existing carriers node
     * Write a new carriers node with the carriers in cache
     */
    private function updateSystemXml()
    {
        $xmlCarriers = $this->xml_system->xpath("//config//system//section[@id=\"carriers\"]//group");
        
        //remove existing nodes
        foreach ($xmlCarriers as $group) {
            unset($group[0]);
        }

        $carrierNodes = $this->xml_system->xpath("//config//system//section[@id=\"carriers\"]");
        $carriersNode = $carrierNodes[0];
 
        $this->tableratesSystemxml($carriersNode);

        //Add all other carriers in this account
        foreach ($this->existingCarriers as $carrier) {
            $node = $carriersNode->addChild('group');
            $curr = $this->createCarrierDescriptorNode($carrier, $node);
        }
 
        if (!$this->xml_system->asXML($this->systemFileName)) {
            $this->messageManager->addWarning("Cannot write  Shiptimize/Shipping/etc/system.xml please check the file permissions");
        }
    }

    /**
     * Flush cache after we modify the config xml files
     */
    public function flushConfigCache()
    {
        $this->cacheTypeList->cleanType('config');
    }
}
