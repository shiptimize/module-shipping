<?php 
namespace Shiptimize\Shipping\Model;

use \Magento\Framework\Component\ComponentRegistrar;

class TableRatesModel
{
    /**
     * @var \Magento\Framework\Message\ManagerInterface  $messageManager
     */
    private $messageManager;

    /**
     * @var \Magento\Framework\App\ResourceConnection $dbresource
     */
    private $dbResource;

    /**
     * @var string tableName
     */
    private $tableName;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $dbResource,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Component\ComponentRegistrar $componentRegistrar
    ){
        $this->messageManager = $messageManager;
        $this->scopeConfig = $scopeConfig;
        $this->componentRegistrar = $componentRegistrar;

        $this->dbResource = $dbResource;
        $this->connection = $this->dbResource->getConnection();
        $this->tableName = $this->dbResource->getTableName("shiptimize_customtablerates");
    }

    public function clearRates()
    {
        if (!$this->tableName) {
            error_log("Cannot clear rates, tableName not defined($this->tableName)");
            return;
        }

        $this->connection->query(sprintf("delete from %s", $this->tableName));
    }

    /**
     * Validate all rates
     * if ALL are valid
     * sort and insert
     *
     * @param array of object rates
     */
    public function addRates($rates)
    {
        $hasErrors = false;

        foreach ($rates as $rate) {
            $rate_errors = $this->getErrors($rate);

            if (!empty($rate_errors)) {
                $hasErrors = true;
                $msg = '';
                foreach ($rate_errors as $error) {
                    $msg .= '<p>'.$error.'</p>';
                }

                $this->messageManager->addError($rate->dest_country_id.' '. $rate->dest_region_id .' '.$rate->dest_zip.' '. $msg);
                error_log( "Errors in rate ".json_encode($rate). ' '. json_encode($rate_errors));        
            }
        }


        $carrierJson =  $this->scopeConfig->getValue('shipping/shiptimizeshipping/carriers');
        $carriers = json_decode($carrierJson);

        if (!$hasErrors) {
//          Reset existing rates             
            $this->clearRates(); 

            foreach ($rates as $rate) {
                $rate->has_pickup = 0;

                if (stripos($rate->carrier_options, "ServicePoint") !== false) {
                    foreach ($carriers as $carrier) {
                        if (($carrier->Id == $rate->carrier_id || $rate->carrier_id == $carrier->Name) && $carrier->HasPickup) {
                            $rate->has_pickup = 1;
                            $rate->carrier_id = $carrier->Id; //Always save the numeric id
                        }
                    }
                }

                //try to find a carrier that matches
                if ($rate->carrier_id && !is_numeric( $rate->carrier_id )) {
                    foreach($carriers as $carrier){
                        if( $rate->carrier_id == $carrier->Name ){ 
                            $rate->carrier_id = $carrier->Id; //Always save the numeric id
                        }
                    }
                }

                $sql_insert = sprintf(
                    "insert into `%s`
                    (`dest_country_id`,
                    `dest_region_id`,
                    `dest_zip`,
                    `min_price`,
                    `min_weight`,
                    `min_items`,
                    `carrier_id`,
                    `carrier_options`,
                    `price`,
                    `display_name`,
                    `has_pickup`)
                    VALUES
                    ('%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    %s)",
                    $this->tableName,
                    $rate->dest_country_id,
                    $rate->dest_region_id,
                    $rate->dest_zip,
                    $rate->min_price,
                    $rate->min_weight,
                    $rate->min_items,
                    $rate->carrier_id,
                    $rate->carrier_options,
                    $rate->price,
                    $rate->display_name,
                    $rate->has_pickup
                );

                $this->connection->query($sql_insert);
                $this->messageManager->addWarning($sql_insert);
            }
        }  
    }

    /**
     * Remove currency simbols and make sure decimals are ,
     */
    public function getNumericValue ($str)
    {
        $str = str_replace(',', '.', $str);
        $str = str_replace(array('€','$'),'',$str); 
        return trim($str);
    }

    /**
     * Check if a rate is valid
     */
    public function getErrors($rate)
    {
        $errors = array();

        // to a human nothing is the same as 0. 
        if (!$rate->price) {
            $rate->price = "0";
        }

        if ($rate->dest_country_id == -1) {
            array_push($errors, 'The country is not valid');
        }

        if (!is_numeric($rate->min_price)) {
            array_push($errors, "Min Price is not a number ". $rate->min_price);
        }

        if (!is_numeric($rate->min_weight)) {
            array_push($errors, "Min Weight is not a number ". $rate->min_weight);
        }

        if (!is_numeric($rate->min_items)) {
            array_push($errors, "Min Items is not a number ". $rate->min_items);
        }

        if (!is_numeric($rate->price)) {
            array_push($errors, "Price is not a number ". $rate->price);
        }

        return $errors;
    }

    /**
     * Write the rates into a file
     * @return @string ratesfile full path 
     */
    public function exportRates()
    {
        $ratesfile = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Shiptimize_Shipping').'/shiptimizetablerates.csv';
        error_log("will write rates to $ratesfile "); 

        $sql = sprintf("select * from %s ", $this->tableName);
        $rates = $this->connection->fetchAll($sql);

        $columnNames = array(
            "Country",
            "Region",
            "PostalCode",
            "Min Price",
            "Min Weight",
            "Min Items",
            "Carrier",
            "Options",
            "Cost",
            "Display Name");

        $content = "\"" . join("\",\"",$columnNames) . "\"\n";


        foreach ($rates as $rate ) {
            $content .= "\"".join('","',array(
                $rate['dest_country_id'],
                $rate['dest_region_id'],
                $rate['dest_zip'],
                $rate['min_price'],
                $rate['min_weight'],
                $rate['min_items'],
                $rate['carrier_id'],
                $rate['carrier_options'],
                $rate['price'],
                trim($rate['display_name'])
            ))."\"";

            $content.="\n";
        }

        $f = fopen( $ratesfile, 'w' );
        fwrite( $f, $content );
        fclose( $f );

        return $ratesfile;
    }

    /**
     *
     * Select all the rates that match the region params
     * Sort them by ($weight , $orderprice, $nitems)
     * Then filter the available rates
     * 1. determine the weight category
     * 2. determine the price category
     * 3. determine the items category
     *
     * @param string iso2 country code
     * @param string region
     * @param string zipcode
     * @param decimal price - order total
     * @param int nitems - the number of items in this order
     *
     */
    public function getRates($country, $region, $zipcode,  $weight , $orderPrice, $nitems)
    {
        $matching_rates = array();

        if ($region == '-') {
            $region = '';
        }

        $rates = $this->getRatesForRegion($country, $region, $zipcode);

        uasort($rates, function($a,$b) {
            if($a->min_weight != $b->min_weight){
                return $a->min_weight - $b->min_weight;
            }

            if($a->min_price != $b->min_price ){
                return $a->min_price - $b->min_price;
            }

            return $a->min_items - $b->min_items;
        }); 

        //error_log("location $country, $region, $zipcode; weight: $weight;  orderPrice: $orderPrice");

        $weight_category = $this->getCategory($rates, 'min_weight', floatval($weight), floatval($weight),floatval($orderPrice), floatval($nitems) ); 
        $price_category = $this->getCategory($rates,'min_price', floatval($orderPrice), floatval($weight),floatval($orderPrice), floatval($nitems) ); 
        $items_category = $this->getCategory($rates, 'min_items', intval($nitems), floatval($weight),floatval($orderPrice), floatval($nitems) );

        //error_log("Categories: weight $weight_category , price_category: $price_category, Items: $items_category ");  

        foreach ($rates as $rate) {
           
            $matches_weight = floatval($weight_category) == floatval($rate->min_weight);
            $matches_price = floatval($price_category) == floatval($rate->min_price);
            $matches_items = floatval($items_category) == floatval($rate->min_items);

            if ($matches_weight && $matches_price && $matches_items) {
                error_log( "Match $weight_category >= $rate->min_weight  ; $price_category >= $rate->min_price ; $items_category >= $rate->min_items" );
                array_push($matching_rates, $rate);
            }
        }
 
        return $matching_rates;
    }

    /**
     * This function assumes that the rates are sorted
     * Return the last category that matches
     * Make sure the rule can be applied to the shipment  - all other attributes shoud be valid too
     * We pay the price of comparing the category twice to make this function generic, the alternative would be to have a similar function
     for each category
     */ 
    public function getCategory ($rates, $name, $value, $weight, $orderPrice, $nitems) {
        $categoryValue = 0;

        foreach ($rates as $rate) {
            if($value >= $rate->{$name} && ($weight >= $rate->min_weight && $orderPrice >= $rate->min_price && $nitems >= $rate->min_items) ){
                $categoryValue = $rate->{$name};
            }
        }

        return $categoryValue;
    }

    /**
     * Wildcard * matches all
     */
    private function getRatesForRegion($country, $region, $zipcode) {
        $rates = $this->connection->fetchAll(sprintf("select * from %s", $this->tableName));
        
        $matching_rates = array();

        foreach($rates as $rate){
            $matches_country = ($rate['dest_country_id'] == '*' || $rate['dest_country_id'] == $country);
            $matches_region = ($rate['dest_region_id'] == '*' || $rate['dest_region_id'] == $region );
            $matches_zipcode = ($rate['dest_zip'] == '*' || $rate['dest_zip'] == $zipcode );

             if ($matches_country && $matches_region && $matches_zipcode) {
                //error_log("MatchingZone:".$rate['dest_country_id']. ' Region:'. $rate['dest_region_id'] . ' zip: '.$rate['dest_zip']);
                array_push($matching_rates , (object)$rate);
            }
        }

        return $matching_rates;
    }
}