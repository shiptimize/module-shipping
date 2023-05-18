/** 
 * This script acts like an intermediary between our webpack module and mage. 
 */
require([
        'jquery',
        'leaflet',
        'Magento_Checkout/js/model/quote',
        'Shiptimize_Shipping/js/view/shipping-mixin',
        'uiRegistry',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/checkout-data-resolver'
    ], function ($,
            L,
            quote, 
            shipping,
            registry,
            checkoutData,
            checkoutDataResolver
        ){  

        window.quote = quote; 
        /** 
         * Not all magento configs will write the address the same way. 
         * Client side plugins will dump it to storage
         */ 
        window.shiptimize_get_shipping_address_from_checkout = function(){
            var shippingAddressData = checkoutData.getShippingAddressFromData();
             registry.async('checkoutProvider')(function (checkoutProvider) {
                checkoutProvider.set(
                    'shippingAddress',
                    $.extend({}, checkoutProvider.get('shippingAddress'), shippingAddressData)
                );
            });

            // this call will trigger a new call to the server to refresh the shipping methods
            //checkoutDataResolver.resolveShippingAddress(); 
            return shippingAddressData; 
        };

        window.shiptimize_get_shipping_address = function() { 
            var addr = shiptimize_get_shipping_address_from_checkout(); 
            
            if (!addr || !addr.city) {
                addr = quote.shippingAddress();
            }

            if (addr == null) {
                console.log("No shipping address was found, trying to obtain a billingAddress we can use");
                addr = quote.billingAddress();
            } 

            if (addr == null) {
                console.log("It was not possible to obtain an address");
                window.shiptimize_address = {
                    'Streetname1': '',
                    'Streetname2': '',
                    'HouseNumber':'',
                    'NumberExtension': '',
                    'PostalCode':'',
                    'City': '',
                    'Country': '',
                    'State': ''
                };
                return;
            }
            
            console.log('Shipping to: ', addr);

            window.shiptimize_address = {
                'Streetname1':  typeof(addr.street) != 'undefined' && typeof(addr.street[0]) != 'undefined' ? addr.street[0] :'',
                'Streetname2':  typeof(addr.street) != 'undefined' && typeof(addr.street[1]) != 'undefined' ? addr.street[1] :'',
                'HouseNumber':'',
                'NumberExtension': '',
                'PostalCode': addr.postcode,
                'City': addr.city,
                'Country': typeof(addr.countryId) != 'undefined' ? addr.countryId : (typeof(addr.country_id) != 'undefined' ? addr.country_id : '') ,
                "State":  typeof(addr.regionCode) != 'undefined' ? addr.regionCode : ''
            };
        }; 
});