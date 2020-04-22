define([], function () {
  'use strict';

  return function (Shipping) {

    return Shipping.extend({
      validateShippingInformation: function () {

        console.log('validateShippingInformation', shiptimize_label_select_pickup);
        const id = shiptimize.platform.getSelectedShipppingMethod();
        const isTableRatesWithPickup = id.indexOf("ShiptimizeTableRates") >= 0 && id.indexOf("pickup") >= 0;
        const hasPickupPoint = shiptimize.platform.pickupPoint && shiptimize.platform.pickupPoint.PointId > 0;

        if (isTableRatesWithPickup && !hasPickupPoint) {
          this.errorValidationMessage(shiptimize_label_select_pickup);
          return false;
        }

        return this._super();
      }
    });
  }
});
