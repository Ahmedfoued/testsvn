import jQuery from 'jquery';

(function ($) {

    $( function(){

        const gatewayIds = {
            'etransactions_3x'          : ['etransactions_3x_sofinco'],
            'etransactions_3x_sofinco'  : ['etransactions_3x']
        };

        const gatewayIdsToArray = Object.keys(gatewayIds);

        gatewayIdsToArray.forEach( id => {
            
            $(`[data-gateway_id="${id}"] a.wc-payment-gateway-method-toggle-enabled`).on('click', e => {

                const enabled = $(e.currentTarget)
                            .find('span.woocommerce-input-toggle')
                            .hasClass('woocommerce-input-toggle--disabled');

                if ( enabled ) {

                    gatewayIds[id].forEach( idToDisable => {

                        $(`[data-gateway_id="${idToDisable}"] a.wc-payment-gateway-method-toggle-enabled`)
                            .find('span.woocommerce-input-toggle')
                            .addClass('woocommerce-input-toggle--disabled');
                    });
                }

            });
        });

    });

})(jQuery);
