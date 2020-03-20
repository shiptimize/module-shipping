<?php
namespace Shiptimize\Shipping\Model\Config\Backend;

use Magento\Framework\Model\AbstractModel;

/**
 * Allow the user to set one rate per country
 */
class CustomTableRates extends \Magento\Framework\App\Config\Value
{ 
    private $_iso2Countries = null;
    private $_iso3Countries = null;

    /**
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Shiptimize\Shipping\Model\TableRatesModel $tableRates,
        \Magento\Directory\Model\ResourceModel\Country\Collection $countryCollection,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\MediaStorage\Model\File\UploaderFactory $uploaderFactory,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->tableRates = $tableRates;
        $this->messageManager = $messageManager;
        $this->countryCollection = $countryCollection;
        $this->uploaderFactory = $uploaderFactory;
    }

 
    /**
     * Load directory countries
     *
     * @return Mage_Shipping_Model_Resource_Carrier_Tablerate
     */
    protected function _loadDirectoryCountries()
    {
        if (!is_null($this->_iso2Countries) && !is_null($this->_iso3Countries)) {
            return;
        }

        $this->_iso2Countries = array();
        $this->_iso3Countries = array();
 
        foreach ($this->countryCollection->getData() as $row) {
            $this->_iso2Countries[$row['iso2_code']] = $row['country_id'];
            $this->_iso3Countries[$row['iso3_code']] = $row['country_id'];
        }
    }

    private function _getCountryIdFromCode($country_code)
    {
        if (isset($this->_iso2Countries[$country_code])) {
            return $this->_iso2Countries[$country_code];
        }

        return isset($this->_iso3Countries[$country_code]) ? $this->_iso3Countries[$country_code] : -1;
    }

    /**
     * Check if a file was uploaded
     * Each rate is a line in the file.
     *
     */
    public function afterSave() 
    { 
//      Magento throws an exception if the file is not present, but we have NO WAY OF TESTING IF IT WAS UPLOADED 
//      WITHOUT ACCESSING $_FILES WHICH IS FORBIDEN BY GUIDELINES
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'ShiptimizeTableRates']);    
        }
        catch(\Exception $e){ 
            return parent::afterSave(); 
        }
        
        $csvFile = $uploader->validateFile();

        if (!$csvFile['name']) {
            return parent::afterSave();
        }

        $this->_loadDirectoryCountries();

        $rates = array();
        $fHandle = fopen($csvFile['tmp_name'],"r");
        $separator = '';
        $rowNumber = 0;

        while (($line = fgets($fHandle)) !==false) {
            if (!$separator) {
                $separator = stripos($line,';') !== false ? ';' : ','; //because MS office uses ; as a separator
            }

            if (!$line) {
                continue;
            }

            $line = str_replace("\"", "", $line);
            $csvLine =  explode($separator, $line);

            ++$rowNumber;

            if ($rowNumber > 1) {
                $rate = new \stdClass();
                $rate->dest_country_id = $this->_getCountryIdFromCode( trim(strtoupper($csvLine[0])));
                $rate->dest_region_id = $csvLine[1];
                $rate->dest_zip = $csvLine[2];
                $rate->min_price = $this->tableRates->getNumericValue($csvLine[3]);
                $rate->min_weight = $this->tableRates->getNumericValue($csvLine[4]);
                $rate->min_items = $this->tableRates->getNumericValue($csvLine[5]);
                $rate->carrier_id = trim($csvLine[6]);
                $rate->carrier_options = $csvLine[7];
                $rate->price = $this->tableRates->getNumericValue($csvLine[8]);

                if (isset($csvLine[9])) {
                    $rate->display_name = $csvLine[9];
                }
                
 
                if ($rate->dest_country_id == -1) {
                    $this->messageManager->addWarning(" invalid country code ".$csvLine[0] . " on line $rowNumber ");
                    continue;
                }

                array_push($rates, $rate);
            }
        }

        $this->tableRates->addRates($rates);
        return parent::afterSave();
    }
}
