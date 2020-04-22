<?php
namespace Shiptimize\Shipping\Model\Core;

/** Because php5.6 does not like static abstract methods **/
error_reporting(E_ALL & ~E_STRICT);

/**
 * Shiptimize
 * Logic should be handled here
 * !!!!! THIS CLASS IS NO LONGUER COMPATIBLE WITH THE MAIN CORE
 * !!!!! MAGENTO USES A STATUS COLUMN AND POORLY HANDLES JOINS
 * !!!!! SO WE ARE FORCED TO BRAND OUR COLUMN NAME
 * !!!!  Also Mage2 does not have a way to get a table prefix independently
 * !!!!  So all our generic methods had to be rewriten
 * @author Shiptimize
 * @copyright Shiptimize
 * @license
 * @package Shiptimize.core
 * @since   1.0.0
 */
 
/**
 * Main shiptimize class
 * @class Shipitmize
 */
abstract class ShiptimizeV3
{

  /**
   * abstract Shiptimize version
   *
   * @var string
   */
    protected static $shiptimize_version = '1.0.0';

    /**
     * The datamodel version
     * @var string
     */
    protected static $database_version = "1.0";

    /**
     * The single instance
     *
     * @var Shiptimize
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * The api instance
     *
     * @var ShiptimizeApi
     */
    protected $api = null;

    /**
     * The db prefix for the  platform
     * can either be set while creating the instance of in your child class
     * @var string $db_prefix
     */
    protected $db_prefix = '';

    /**
     * @var String $lang
     */
    protected $lang = null;

    /**
     * @var array
     */
    protected $langs = [];

    public function __construct()
    {
    }

    /**
     * Executes the sql received by param. Each platform will have a different way of accessing the database
     *
     * @param string sql
     *
     * @return bool - if the query succeded
     */
    abstract protected function executeSQL($sql);


    /**
     * Execute an sql select
     *
     * @param string $sql
     *
     * @return the results
     */
    abstract protected function sqlSelect($sql);
 

    /**
     * Handles an update from the api.
     * Receives a JSON object {"TrackingId":, "OrderId", "Hash", "Status"}
     * Should validate the hash before processing any updates
     */
    abstract public function apiUpdate();

    /**
     * get an api instance
     * @return ShiptimizeApi - an instance of the selected api version
     */
    abstract protected function getApi();

    /**
     * When users update carrier settings the token is invalidated
     * Therefore everytime we get a new valid token we should also refresh the carriers
     */
    abstract protected function refreshToken();

    /**
     * Explicitly refresh the carriers from the api
     */
    abstract protected function refreshCarriers();

    /**
     * @param mixed $address
     * @param int $shipping_method_id
     */
    public function getPickupLocations($address, $shipping_method_id)
    {
    }
  
    /**
     * Get the string from the correct file
     * @param String $lang
     * @param String $string
     */
    public function __($string)
    { 
        if (!$this->lang) {
            $this->lang  = $this->getLang();
        }

        if (!isset($this->langs[$this->lang]) && file_exists(__DIR__.'/lang/'.$this->lang.'.json')) {
            try {
                $contents  = file_get_contents(__DIR__.'/lang/'.$this->lang.'.json');
                $this->langs[$this->lang] = json_decode($contents);
            } catch(Exception $e) {
                error_log("ERROR!!  Invalid json in ".__DIR__.'/lang/'.$this->lang.'.json');
            }

            error_log('LANG VALUES: '.     var_export($this->langs[$this->lang], true) . ' ' .  json_last_error());
        }

        return isset($this->langs[$this->lang]->$string) ? $this->langs[$this->lang]->$string  : $string;
    }

    /**
     * Return an iso2 string with the lang
     */
    abstract public function getLang();
}
