<?php
namespace Shiptimize\Shipping\Model\Core;

use Shiptimize\Shipping\Model\ShiptimizeConstants;

/**
 * The V3 of the api
 */
class ShiptimizeApiV3
{
    /**
     * @var String servertimezone
     */
    protected static $_server_timezone = 'Europe/Amsterdam';
 
    /**
     * The single instance
     *
     * @var ShiptimizeApi
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * the public key
     * @var Sring $public_key
     */
    protected $public_key = null;

    /**
     * the private key
     * @var String $private_key
     */
    protected $private_key = null;

    /**
     * the token expires date
     * @var date - token expires
     */
    protected $token_expires = null;

    /**
     * the temporary token
     * @var String $token
     */
    protected $token = null;

    /**
     * The pakketmail identifier for this platform
     *
     * @var number $appclass
     */
    protected $app_id = null;

    /**
     * The local dev url
     */
    protected $api_url_dev = 'https://api.lan/v3';

    /**
     * @var String
     * @since 1.0.0
     */
    protected $version = '3.0.0'; 


    private function __construct($public_key, $private_key, $app_id, $token = '', $token_expires = '', $is_dev=false)
    {
        $this->private_key = $private_key;
        $this->public_key = $public_key;
        $this->token = $token;
        $this->token_expires = $token_expires;
        
        $this->app_id = $app_id;
        $this->is_dev = $is_dev;
    }

    /**
     * Singleton pattern ensures only one shiptimize instance
     * @since 3.0.0
     *
     * @param string $public_key
     * @param string $private_key
     * @param string $app_id
     *
     * @return ShiptimizeApi - instance.
     */
    public static function instance($public_key, $private_key, $app_id, $token = '', $token_expires = '', $is_dev = false)
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($public_key, $private_key, $app_id, $token, $token_expires, $is_dev);
        }
        
        return self::$_instance;
    }

    /**
     * Retrives a temporary token that replaces the public key in auth for any subsequent requests
     * Make sure we reset the token before requesting a new one, in case there is one
     *
     * @since 3.0.0
     *
     * @param String $shop_url - a callback url the api can use to say it has updates
     * @param String $plugin_version  - this plugin's version
     * @param String $platform_version - the version of the system
     *
     * @return mixed {String Key - the token, Date Expire - YYYY-mm-dd 00:00 UTC+1}
     */
    public function getToken($shop_url, $platform_version, $plugin_version)
    {
        $data = [
            'PluginVersion' => $plugin_version,
            'ShopUrl' =>  $shop_url,
            'PlatformVersion' => $platform_version
        ];
        
        $this->token = '';
        $this->token_expires ='';

        $serverResponse = $this->sendToApi('POST', '/keys', $data);
        
        if ($this->is_dev) {
            error_log(' Received Token ' . var_export($serverResponse, true));
        }

        if ($serverResponse->httpCode == 200) {
            $token = $serverResponse->response;
            $this->token = $token->Key;
            $this->token_expires = $token->Expire;

            return $serverResponse->response;
        } else {
            return $serverResponse;
        }
    }
 
    /**
     * Determine if the token we have is still valid
     * @since 3.0.0
     *
     * @return boolean - true if the token exists and has not expired
     */
    public function isTokenValid()
    {
        if (!$this->token || !$this->token_expires) {
            return false;
        }

        $expires = new \DateTime($this->token_expires.' 00:00:00 '.self::$_server_timezone);
        $now = new \DateTime();
 
        return $expires > $now;
    }

    /**
     * Generate a base64 hmac using sha256
     * @since 3.0.0
     *
     * Note that to guarante we obtain an equivalent output across all languages used in the shiptimize ecossystem we
     * should set raw_output to true when generating the hash
     * Remember that php adds padding to encoding while perl does not
     *
     * @param  String $data - the data to be sent
     * @return String - a hmac_sha256_base64 of the data hashed with the private key
     */
    private function getRequestSignature($data)
    {
        $hmac256 = hash_hmac('sha256', $data, $this->private_key, true);
        return base64_encode($hmac256);
    }

    /**
     * Get the carriers for this contract
     * @return mixed $carriers - an array of carriers or server response {Error:, response} if error
     */
    public function getCarriers()
    {
        if (!$this->token) {
            return null;
        }

        $serverResponse = $this->sendToApi('GET', '/carriers');

        if ('200' == $serverResponse->httpCode) {
            $carrier_list =   $serverResponse->response;
            if ($this->is_dev) {
                error_log(" getCarriers ".json_encode($carrier_list));
            }
            return !$carrier_list->Error->Id ? $carrier_list->Carrier : $carrier_list;
        }

        return $serverResponse->response;
    }

    /**
     * Return a human readable html string with the request summary
     * If we need to debug what is being sent to the server
     */
    private function printRequestData($method, $endpoint, $data, $headers)
    {
        $url = ($this->is_dev ? $this->api_url_dev :  ShiptimizeConstants::$API_URL) . $endpoint;
        $json_data = json_encode($data);
        $username = $this->isTokenValid() ? $this->token : $this->public_key;
        $password = $this->getRequestSignature($json_data);

        return " Url: $url
        Method:$method
        json_data: $json_data 
        Username: $username 
        private_key: {$this->private_key}
        token: {$this->token}
        token_expires:  {$this->token_expires}
        password: $password 
        Headers: ".json_encode($headers);
    }

    /**
     * If the carrier has been meanwhile disabled in the client settings the api will return a 403
     * If the StreetName2 is a number the api will return an error.
     *
     * @param mixed $address - an object in the format described in the documenation that describes this address
     * @param int carrier_id - the carrier id according to the API NOT the platform
     *
     */
    public function getPickupLocations($address, $carrier_id)
    {
        if (!$carrier_id) {
            return [
                'Error' => [
                    'Id' => 1111,
                    'Info' => "Invalid carrier of id $carrier_id"
                ]
            ];
        }
 
        if (is_numeric($address['Streetname2'])) {
            $address['HouseNumber'] = $address['Streetname2'];
            $address['Streetname2'] ='';
        }

        $data = [
                'Address' =>  $address,
            'CarrierId' => $carrier_id
        ];

        if ($this->is_dev) {
            error_log("get_pickup_locations Address ".var_export($address, true) .";  
            Carrier :".$carrier_id);
        }

        $curl =  $this->sendToApi('POST', '/pickuppoints', $data);
        return $curl->response;
    }

    /**
     * Send Shipments to the api
     *
     * @param mixed $shiptments - an array of shipments to send to the API
     * @param string $accept_lang - ex en_US is set messages will be localized, defaults to english
     *
     */
    public function postShipments($shipments, $accept_lang = '')
    {
        $headers = [
            'accept-language'=> $accept_lang ? $accept_lang : 'en_US',
        ];

        if ($this->is_dev) {
            error_log("postShipments ".json_encode($shipments));
        }

        $data = (object) [
            'Shipment' => $shipments
        ];
        return $this->sendToApi('POST', '/shipments', $data, $headers);
    }

    /**
     * Grant we are sending utf8 strings to the api
     * @param String $str - the string to encode
     * @return String an utf8 encoded string
     */
    public function getUtf8($str)
    {
        $enc =  mb_detect_encoding($str, "UTF-8,ASCII,JIS,ISO-8859-1,ISO-8859-2,ISO-8859-4,ISO-8859-8,EUC-JP,SJIS");

        if ($enc && $enc != 'UTF-8') {
            return iconv($enc, 'UTF-8', $str);
        } else {
            return $str;
        }
    }

    /**
     * Send the data to the api
     * HTTP 401 == ( bad hash, bad keys, bad token )
     *
     * @param String method - the http method to use for this request
     * @param String $endpoint - ex /keys
     * @param mixed $data - the data to be sent to the server
     * @param string $content_type
     * @param Array $headers - associative array ( header_name => value ) of headers to append
     * @param int $iteration - used in recursion
     * @return mixed - object {response, error}
     * @override
     */
    protected function sendToApi($method = 'GET', $endpoint = '/', $data = '', $headers = [])
    {
        $result = new \stdClass(); 
 

        // this can be called from crontab or other local scripts
        if (stripos($endpoint, "http") !== FALSE) { 
            $url = $endpoint;
        }
        else {
            $url = ($this->is_dev ? $this->api_url_dev :  ShiptimizeConstants::$API_URL ) . $endpoint;
        }

        if ($this->is_dev) {
            error_log('==== > ' . $method . ' ' . $url);
        }

        $json_data = $data ? $this->getUtf8(json_encode($data)) : '';

        $username = $this->isTokenValid() ? $this->token : $this->public_key;
        $password = $this->getRequestSignature($username.$json_data);

        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';
        $headers[] = 'X-APPID: '.$this->app_id;

        $ch = curl_init($url);

        $options = [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $username . ":" . $password,
            CURLOPT_RETURNTRANSFER => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        ];
        
        curl_setopt_array($ch, $options);


        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        }

        $response = curl_exec($ch);
        $result->response = json_decode($response);
        $result->error = curl_error($ch);
        $result->httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
 
        curl_close($ch);

        if ($this->is_dev || $result->httpCode != 200) {
            error_log("Request Info: " . $this->printRequestData($method, $endpoint, $data, $headers));
            error_log("Response " . json_encode($response));
        }

        return $result;
    }

    /**
     * Remove padding from the hashed string
     * @param string $encoded_string
     */
    public function removeBase64Padding($encoded_string)
    {
        $chars = str_split($encoded_string); 
        $len =  strlen($encoded_string); 
        for ($i =$len -1; $chars[$i] == '='; --$i) {
        }
        
        return substr($encoded_string, 0, $i+1);
    }

    /**
     * Validate a request to update
     *
     * @return true if the incoming request is correctly signed
     */
    public function validateUpdateRequest($status, $tracking_id, $url, $hash)
    {
        $string = "{$status},{$tracking_id},{$url}";
        $confirm_hash = $this->getRequestSignature($string);
        $confirm_hash_without_padding = $this->removeBase64Padding($confirm_hash);

        if($this->is_dev){
            error_log("\nHashSTR $string\nPrivatekey $this->private_key \nConfirm Hash $confirm_hash_without_padding against received hash  $hash \n.");
        }
        
        return $hash == $confirm_hash_without_padding;
    }


    /** 
     * Request the labels  
     * @param array $clientreferences -  an array with the clientreferences to include in the label pdf
     * @param labelstart - where to start 1 is top left 
     * @param labeltype - 0 is whatever is in the client settings 
     **/
    public function postLabelsStep1($clientreferences, $labelstart = 1, $labeltype = 0) {
        $data = array(
            'ClientReferenceCodeList' => $clientreferences,
            'LabelStart' => $labelstart, 
            'LabelType' => $labeltype
        ); 

        return $this->sendToApi('POST','/labels', $data); 
    }

    /** 
     *  Monitor the label request, is it finished? 
     */ 
    public function monitorLabelStatus($callbackurl) {
        return $this->sendToApi('GET', $callbackurl); 
    }

}
