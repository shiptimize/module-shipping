<?php
namespace Shiptimize\Shipping\Model\Core;

/** Because php5.6 does not like static abstract methods **/
error_reporting(E_ALL & ~E_STRICT);

/**
 * An abstract class for the order, contains the basic structure
 * for a shiptimize order object and forces implementation of
 * platform dependent methods
 *
 * @package Shiptimize.core
 * @since 1.0.0
 *
 */
abstract class ShiptimizeOrder
{

  /**
   * @var int status not exported
   */
    public static $STATUS_NOT_EXPORTED = 1;

    /**
     * @var int status exported succesfully
     */
    public static $STATUS_EXPORTED_SUCCESSFULLY = 2;

    /**
     * @var int status test successfull
     */
    public static $STATUS_TEST_SUCCESSFUL = 4;

    /**
     * @var int status export error
     */
    public static $STATUS_EXPORT_ERRORS = 3;

    /**
     * @var int $ERROR_ORDER_EXISTS
     */
    public static $ERROR_ORDER_EXISTS = 200;

    /**
     *
     * @var string $ShopItemId - the order id  before filters are applied
     */
    protected $ShopItemId=null;

    /**
     *
     * @var string $CompanyName
     */
    protected $CompanyName = null;

    /**
     *
     * @var string $Name -  Name of the recipient
     */
    protected $Name = null;

    /**
     *
     * @var string $ClientReference - the order number after filters are applied
     */
    protected $ClientReferenceCode = null;

    /**
     *
     * @var string $Streetname - the first line of the shipping address
     */
    protected $Streetname1 = null;

    /**
     *
     * @var string $Streetname2 - the second line of the shippping ddress
     */
    protected $Streetname2 = null;

    /**
     *
     * @var string $HouseNumber - the house number - applicable in some addresses
     */
    protected $HouseNumber = null;

    /**
     *
     * @var string $NumberExtension - the number extension ex. App B,A, etc.
     */
    protected $NumberExtension = null;

    /**
     *
     * @var string Postalcode - the shipping address postal code
     */
    protected $PostalCode = null;

    /**
     *
     * @var string $City - the shipping address City
     */
    protected $City = null;

    /**
     * @var string $State - the State/Province/County when applicable
     */
    protected $State = null;

    /**
     *
     * @var string $Country - the shipping address country
     */
    protected $Country = null;

    /**
     *
     * @var string $Phone - the shipping phone
     */
    protected $Phone = null;

    /**
     * Transporter - the carrier id
     */
    protected $Transporter = 0;

    /**
     *
     * @var string $Email - the shipping email
     */
    protected $Email = null;

    /**
     * @var String $Neighborhood
     */
    protected $Neighborhood = null;

    /**
     *
     * @var string $Weight - the total weight of this order in grams
     */
    protected $Weight = null;

    /**
     *
     * @var number $Length -  length  of this order in cm
     */
    protected $Length = null;

    /**
     *
     * @var number $Height -   height of this order in cm
     */
    protected $Height = null;

    /**
     * @var number Width
     */
    protected $Width = null;

    /**
     *
     * @var int CustomsType - customs information: type of shipment
     */
    protected $CustomsType = null;

    /**
     *
     * @var string Description - a short description of the package content
     */
    protected $Description = null;

    /**
     *
     * @var string HSCode - the customs HS code
     */
    protected $HSCode = null;

    /**
     *
     * @var decimal Value - the total value of the shipped items
     */
    protected $Value = null;

    /**
     * @var string pointId  - should fit in a varchar(25)
     */
    protected $PointId = null;

    /** 
     * @var array optionList - a list of options to send with the shipment 
     */  
    protected $OptionList  = null;
    
    /**
     *
     * @var mixed array of ExtendedInfo { FieldName - the name, FieldId - the point id a string, Tekst - inserted by the client } - the id of the pickupPoint
     */
    protected $ExtendedInfo = [];

    /**
     * @param string ShippingMethodId - the shipping method id to be used in rules over the API
     */
    protected $ShippingMethodId = '';

    /**
     * @var string[] errors
     */
    protected $errors = [];

    /**
     * @var Number shiptimize_status
     */
    protected $shiptimize_status = 0;

    /**
     *
     * @var string message
     */
    protected $shiptimize_message = '';

    /**
     * @var array ShipmentItems - optional - a list of the items in the shipment
     */
    protected $ShipmentItems = [];

    /**
     * @var string BTW - VAT ID
     */
    protected $BTW = '';


    /**
     * A list of status ids and their localized values
     * TODO: pending API, the api will send localized messages
     */
    public static $status_text =  [
      1 => 'Not Exported',
      2 => 'Exported',
      3 => 'Exported Error',
      4 => 'Test Succesfull'
    ];

    /**
     *
     * @var string id - the system identifier for the order
     * @return ShiptimizeOrder
     */
    public function __construct($id)
    {
        $this->ShopItemId = $id;
        $this->bootstrap();
    }

    /** 
     * @param string $message
     */ 
    public abstract function addMessage($message);
 

    /**
     * Appends new line and current date to message
     */
    public static function getFormatedMessage($message)
    {
        return "<br/>".date("d/m").' - '.$message;
    }

    /**
     * @return string - a string containing all the error messages
     */
    public function getErrorMessages()
    {
        $errors = '';

        foreach ($this->errors as $error) {
            $errors .= $error;
        }

        return $errors;
    }

    /**
     * @return boolean true if this order is valid
     */
    public function isValid()
    {
        return $this->isNameValid() && $this->isAddressValid();
    }

    /**
     * @return boolean true if the name for this address is valid
     */
    public function isNameValid()
    {
        if (!($nameValid = ($this->Name))) {
            $this->errors[] = __('Name is required', 'shiptimize');
        }
        return $nameValid;
    }

    public function isWeightValid()
    {
        return $this->Weight && is_float($this->Weight);
    }

    /**
     * Checks if the address is correctly set for this order.
     * This is important because some plugins may change the order meta
     * and save crucial address parts in other fields
     *
     * @since 1.0.0
     * @return boolean - true If the address contains all required fields
     */
    public function isAddressValid()
    {
        //TODO: consider does it make sense to have a special validation by country? how does the app handle different country addresses ?
        $addressValid = trim($this->Streetname1) != '' && trim($this->PostalCode) != '' && trim($this->City) != '' && trim($this->Country) != '' ;

        if (!$addressValid) {
            $this->errors[] = 'Invalid Shipping Address';
        }

        return $addressValid;
    }

    /**
     * Sometimes systems are messy in how they assign data and this may trigger api errors
     */
    public function normalizeData()
    {
        if (is_numeric($this->Streetname2)) {
            $this->HouseNumber = $this->Streetname2;
            $this->Streetname2 = "";
        }

//      Sometimes people will input - To mean Idfk why are you asking me to input this?
        if ($this->Phone && strlen($this->Phone) < 3) {
            $this->addMessage($this->getFormatedMessage("Invalid Phone [$this->Phone] ignoring"));
            $this->Phone = '';
        }

        if ($this->State && strlen($this->State) < 2) {
            $this->addMessage($this->getFormatedMessage("Invalid State [$this->State] ignoring"));
            $this->State = '';
        }

        if ($this->CompanyName && strlen($this->CompanyName) < 3) {
            $this->addMessage($this->getFormatedMessage("Invalid CompanyName[$this->CompanyName] ignoring "));
            $this->CompanyName = '';
        }

        $this->Description = $this->escapeTextData($this->Description);
        if (strlen($this->Description) > 255) {
            $this->Description = substr($this->Description, 0, 255);

            //Make sure we are not sending a broken special char
            $descChars = str_split($this->Description); 
            for ($i = 254; $i > 251; --$i) {
                if ($descChars[$i] == '&') {
                    $this->Description = substr($this->Description, 0, $i);
                }
            }
        }

        if(strlen($this->Description) < 3) {
            $this->addMessage($this->getFormatedMessage("Invalid description $this->Description ignoring "));
            $this->Description = ''; 
        }
    }

    /**
     * Remove all non-latin one characters we can find since our app does not support it
     */
    public function escapeNonLatin1($str)
    {
        $normalize = array(
            'Ā'=>'A','Ă'=>'A','Ą'=>'A','Ḁ'=>'A', 'Ắ'=>'A',
            'Ḃ'=>'B','Ḅ' => 'B', 'Ḇ' => 'B',
            'Ć'=>'C','Ĉ'=>'C','Ċ'=>'C','Č'=>'C','Ḉ' => 'C',
            'Đ'=>'D','Ḋ' => 'D','Ḍ' => 'D','Ḏ' => 'D','Ḑ' => 'D','Ḓ' => 'D',
            'Ē'=>'E','Ĕ'=>'E','Ė'=>'E','Ę'=>'E','Ě'=>'E','Ḕ' => 'E','Ḗ' => 'E','Ḙ' => 'E','Ḛ' => 'E','Ḝ' => 'E','Ẽ‬'=>'E',
            'ā'=>'a','ă'=>'a','ą'=>'a','ḁ' => 'a', 
            'ḃ' => 'b','ḅ' => 'b','ḇ' => 'b',
            'ć'=>'c','ĉ'=>'c','ċ'=>'c','č'=>'c','ḉ' => 'c',
            'đ'=>'d','ḋ' => 'd','ḍ' => 'd','ḏ' => 'd','ḑ' => 'd','ḓ' => 'd',
            'ē'=>'e','ĕ'=>'e','ė'=>'e','ę'=>'e','ě'=>'e','ḕ'=>'e','ḗ' => 'e','ḙ' => 'e','ḛ' => 'e','ḝ' => 'e',
            'ñ'=>'n',
            'ņ'=>'n', 'ṅ' => 'n','ṇ' => 'n','ṉ'=> 'n','ṋ' => 'n',
            'Š'=>'S', 'š'=>'s', 'ś' => 's',
            'Ž'=>'Z', 'ž'=>'z',
            'ƒ'=>'f','ḟ' => 'f',
            'Ḟ' => 'F',
            'Ĝ'=>'G', 'ğ'=>'g', 'Ġ'=>'G', 'ġ'=>'g', 'Ģ'=>'G', 'ģ'=>'g','Ḡ' => 'G', 'ḡ' =>'g',
            'Ĥ'=>'H', 'ĥ'=>'h', 'Ħ'=>'H', 'ħ'=>'h','Ḣ' => 'H','ḣ' => 'h','Ḥ' => 'h','ḥ' => 'h','Ḧ' => 'H','ḧ' => 'h','Ḩ' => 'H','ḩ' => 'h','Ḫ' => 'H','ḫ' => 'h',
            'Ĩ'=>'I', 'ĩ'=>'i', 'Ī'=>'I', 'ī'=>'i', 'Ĭ'=>'I', 'ĭ'=>'i', 'Į'=>'I', 'į'=>'i', 'İ'=>'I', 'ı'=>'i','Ḭ' => 'I','ḭ' => 'i','Ḯ' => 'I','ḯ' => 'i',
            'Ĳ'=>'IJ', 'ĳ'=>'ij',
            'Ĵ'=>'j', 'ĵ'=>'j',
            'Ķ'=>'K', 'ķ'=>'k', 'ĸ'=>'k','Ḱ' => 'K','ḱ' => 'k','Ḳ' => 'K','ḳ' => 'k','Ḵ' => 'K','ḵ' => 'k',
            'Ĺ'=>'L', 'ĺ'=>'l', 'Ļ'=>'L', 'ļ'=>'l', 'Ľ'=>'L', 'ľ'=>'l', 'Ŀ'=>'L', 'ŀ'=>'l', 'Ł'=>'L', 'ł'=>'l','Ḷ' => 'L','ḷ' => 'l','Ḹ'=>'L','ḹ' => 'l','Ḻ' => 'L','ḻ' => 'l','Ḽ' => 'L','ḽ' => 'l',
            'Ḿ' => 'M','ḿ' => 'm','Ṁ' => 'M','ṁ' => 'm','Ṃ' => 'M','ṃ' => 'm',
            'Ń'=>'N', 'ń'=>'n', 'Ņ'=>'N', 'ņ'=>'n', 'Ň'=>'N', 'ň'=>'n', 'ŉ'=>'n', 'Ŋ'=>'N', 'ŋ'=>'n','Ṅ'=> 'N','Ṇ' => 'N','Ṉ' => 'N','Ṋ' => 'N',
            'Ō'=>'O', 'ō'=>'o', 'Ŏ'=>'O', 'ŏ'=>'o', 'Ő'=>'O', 'ő'=>'o', 'Œ'=>'OE', 'œ'=>'oe','Ṍ'=> 'O','ṍ'=>'o','Ṏ' => 'O','ṏ' => 'ṏ','Ṑ' => 'O','ṑ'=>'O','Ṓ' => 'O','ṓ' => 'o',
            'Ṕ' => 'P','ṕ' => 'p','Ṗ' => 'P','ṗ' => 'p',
            'Ŕ'=>'R', 'ŕ'=>'r', 'Ŗ'=>'R', 'ŗ'=>'r', 'Ř'=>'R', 'ř'=>'r','Ṙ' => 'R','ṙ' => 'r','Ṛ' => 'R','ṛ' => 'r','Ṝ' => 'R','ṝ' => 'r','Ṟ'=> 'R','ṟ' => 'r',
            'Ś'=>'S', 'ś'=>'s', 'Ŝ'=>'S', 'ŝ'=>'s', 'Ş'=>'S', 'ş'=>'s', 'Š'=>'S', 'š'=>'s','Ṡ' => 'S','ṡ'=>'s','Ṣ' => 'S',
'ṣ'=>'s', 'Ṥ' => 'S','ṥ'=>'s','Ṧ'=>'S','ṧ'=>'s','Ṩ' => 'S','ṩ' => 's',
            'Ţ'=>'T', 'ţ'=>'t', 'Ť'=>'T', 'ť'=>'t', 'Ŧ'=>'T', 'ŧ'=>'t','Ṫ' => 'T','ṫ'=>'t','Ṭ' => 'T','ṭ'=>'t','Ṯ'=>'T','ṯ' => 't','Ṱ'=>'T','ṱ' =>'t',
            'Ũ'=>'U', 'ũ'=>'u', 'Ū'=>'U', 'ū'=>'u', 'Ŭ'=>'U', 'ŭ'=>'u', 'Ů'=>'U', 'ů'=>'u', 'Ű'=>'U', 'ű'=>'u','Ṳ' => 'U','ṳ'=> 'u','Ṵ'=>'U','ṵ'=>'u','Ṷ' => 'U','ṷ' => 'u','Ṹ' => 'U','ṹ'=>'u','Ṻ' => 'U','ṻ' => 'u',
            'Ų'=>'U', 'ų'=>'u',
            'Ṽ' => 'v','ṽ'=>'v','Ṿ' => 'v','ṿ' => 'v',
            'Ŵ'=>'W', 'ŵ'=>'w',
            'Ŷ'=>'Y', 'ŷ'=>'y',
            'Ź'=>'Z', 'ź'=>'z', 'Ż'=>'Z', 'ż'=>'z', 'Ž'=>'Z', 'ž'=>'z', 'ſ'=>'f',
            '"' => "'",
            //control chars in windows1252 that perl Forks up
            '€' => 'E', 
            '‚'=>',',
            'ƒ'=>'f', 
            '„'=>',,', 
            '…' => '...', 
            '†'=>'t',
            '‡' => '+', 
            'ˆ' => '^', 
            '‰'=>'%', 
            '‹' => '(', 
            'Œ' => 'CE',
            '‘' => "'", 
            '’' => "'", 
            '“' => "\"", 
            '”'=>"\"", 
            '•'=> '.', 
            '–'=> '-',
            '—'=>'-', 
            '˜'=>'~', 
            '™'=>'TM', 
            '›'=>')', 
            'œ'=>'OE',
            'Ÿ'=>'Y'
        );
        return strtr($str, $normalize);
    }

    /**
     * Because you don't know what a nightmare char encodings are untill you
     * make software that is used accross borders
     * Unicode-proof htmlentities.
     * Returns 'normal' chars as chars and weirdos as numeric html entites.
     */
    public function escapeTextData($str)
    {
        
        //It's the wild wild web... and people import stuff from everywhere 
        //We've seen these things pop up... 
        $str = preg_replace("/\r|\n|\t|\'|\"/", " ",$str);

        // get rid of existing entities else double-escape
        $str = html_entity_decode(stripslashes($str),ENT_QUOTES,'UTF-8');
        $str = $this->escapeNonLatin1($str); 
        $ar = preg_split('/(?<!^)(?!$)/u', $str );  // return array of every multi-byte character
        $str2 = '';  
        foreach ($ar as $c){
            $o = ord($c); 
            $charInBytes = strlen($c); 

            # trash any remaining larger than  3 bytes or 1 byte and bellow 31 
            if ( $charInBytes < 3  &&  ($o > 31 || strlen($c) > 1) ) {
                $str2 .= $c; 
            }  
            else {
                error_log("invalid char o: $o - c:[$c]");
                $str2 .= ' '; 
            }               
        } 
        return trim($str2);
    }

    /**
     * TODO: remove id and add shoptitemid when moving to v3
     * @return mixed - associative array with the properties we will export to the api
     */
    public function getApiProps()
    {
        $this->normalizeData();

        $data = [
           'ShopItemId'  => $this->ShopItemId,
           'ClientReferenceCode' => ''.$this->ClientReferenceCode,
          
           "Address" => [
                "CompanyName" => $this->escapeTextData($this->CompanyName),
                'Name' => $this->escapeTextData($this->Name),
                'Streetname1' => $this->escapeTextData($this->Streetname1),
                'Streetname2' => $this->escapeTextData($this->Streetname2),
                'HouseNumber' => $this->HouseNumber,
                'NumberExtension' => $this->NumberExtension,
                'PostalCode' => $this->PostalCode,
                'City' =>  $this->escapeTextData($this->City),
                'State' => strlen($this->State) > 1 ? $this->escapeTextData($this->State) : '',
                'Country' => $this->Country,
                'Phone' => strlen($this->Phone) > 2 ? $this->Phone : '',
                'Email' => trim($this->Email),
                'BTW' => trim($this->BTW),
                'Neighborhood' => $this->escapeTextData(trim($this->Neighborhood))
           ],
           "Customs" => [
                'CustomsType' => $this->CustomsType,
                'Description' => trim($this->Description) ? $this->escapeTextData($this->Description) : 'could not find a description for this order, please input manually and send a printscreen of the original order to support',
                'HSCode' => $this->HSCode,
                'Type' => 4,
                'Value' => $this->Value ? number_format($this->Value, 2, '.', '') : '' , // API assumes a max of 2 decimal places
           ],

        ];

        if ($this->ShippingMethodId) {
            $data['ShippingMethodId'] = $this->ShippingMethodId;
        }

        if ($this->Transporter) {
            $data['Carrier'] = [
                "Id"=> $this->Transporter,
            ];
        }

        if ($this->Weight != '') {
            $data['Weight'] = $this->Weight; //in grams
        }

        if ($this->Length || $this->Width || $this->Height) {
            $data['Dimensions'] = [
                'Width' => $this->Width,
                'Length' => $this->Length,
                'Height' => $this->Height,
            ];
        }

        if ($this->PointId) {
            $data["PickupPoint"] = [
            "PointId" => $this->PointId,
            ];

            if ($this->ExtendedInfo) {
                $data["PickupPoint"]["ExtendedInfo"] = $this->ExtendedInfo;
            }
        }

        if ($this->OptionList) {
            $data['OptionList'] = $this->OptionList;
        }

        if (!empty($this->ShipmentItems)) {
            $data['ShipmentItems'] = $this->ShipmentItems;
        }

        return (object) $data;
    }

    public function setShopItemId($value)
    {
        $this->ShopItemId = $value;
    }
}
