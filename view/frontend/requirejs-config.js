var config = {
  "paths": {
    "leaflet": "Shiptimize_Shipping/js/leaflet"
  },
  "shim": {
    "leaflet": {
      "exports": "L"
    }
  },
  'config': {
    'mixins': {
      'Magento_Checkout/js/view/shipping': {
        'Shiptimize_Shipping/js/view/shipping-mixin': true
      }
    }
  }
};
