/** 
 * This script acts like an intermediary between our webpack module and mage. 
 */
require([
        'jquery',
        'leaflet',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',  
        'mage/url',
    ], function ($,
            L,
            quote,
            checkout,
            mageUrl){ 
        console.log(mageUrl.build('shiptimize/checkout/getShippingAddress')); 
        window.quote = quote; 

        console.log( quote );
        console.log( checkout );

        window.shiptimize_get_shipping_address = function(){ 

            var addr = quote.shippingAddress();
            
            if(addr == null){
                console.log("No shipping address was found, trying to obtain a billingAddress we can use");
                addr = quote.billingAddress();
            } 
            
            console.log('Shipping to: ',addr);

            window.shiptimize_address ={
                'Streetname1':  typeof(addr.street) != 'undefined' && typeof(addr.street[0]) != 'undefined' ? addr.street[0] :'',
                'Streetname2':  typeof(addr.street) != 'undefined' && typeof(addr.street[1]) != 'undefined' ? addr.street[1] :'',
                'HouseNumber':'',
                'NumberExtension': '',
                'PostalCode': addr.postcode,
                'City': addr.city,
                'Country': addr.countryId,
                "State":  typeof(addr.regionCode) != 'undefined'  ? addr.regionCode : ''
            };
 
            console.log("GET SHIPPING ADDRESS quoteaddr", addr, ' shiptimize_address ', shiptimize_address);
        }; 
});