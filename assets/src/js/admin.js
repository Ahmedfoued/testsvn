import '../scss/admin.scss';

import jQuery from 'jquery';

jQuery(document).ready(function () {

    jQuery(document).on('click', '#pbx-tabs a', function (e) {
        jQuery('#pbx-plugin-configuration .tab-active').removeClass('tab-active');
        jQuery(jQuery(this).attr('href')).addClass('tab-active');
        jQuery('#pbx-tabs a.nav-tab-active').removeClass('nav-tab-active');
        jQuery(this).addClass('nav-tab-active');
        jQuery('#mainform').attr('action', jQuery(this).attr('href'));
        e.preventDefault();
    });

    // Auto-select nav tab
    if (typeof (window.location.hash) == 'string') {
        jQuery('.nav-tab[href="' + window.location.hash + '"]').trigger('click');
        jQuery('#mainform').attr('action', window.location.hash);
    }

    // Ask for confirmation
    jQuery(document).on('change', '#woocommerce_' + pbxGatewayId + '_environment', function () {
        if (confirm(pbxConfigModeMessage)) {
            jQuery('.woocommerce-save-button').trigger('click');
        }
    });

    // Ask for confirmation
    jQuery(document).on('change', '[name="typeOfConfiguration"]', function () {
        window.onbeforeunload = null;
        window.location = pbxUrl + '&config_mode=' + this.value;
    });

    jQuery(document).on('change', '#woocommerce_' + pbxGatewayId + '_delay', function () {
        if (jQuery(this).val() == pbxOrderStateDelay) {
            jQuery('#woocommerce_' + pbxGatewayId + '_capture_order_status').parents('tr').removeClass('hidden');
        } else {
            jQuery('#woocommerce_' + pbxGatewayId + '_capture_order_status').parents('tr').addClass('hidden');
        }
    });

    jQuery(document).on('change', '#woocommerce_' + pbxGatewayId + '_subscription', function () {
        let currentSubscription = jQuery(this).val();
        jQuery('#woocommerce_' + pbxGatewayId + '_delay option[value="' + pbxOrderStateDelay + '"]').prop('disabled', (currentSubscription != pbxPremiumSubscriptionId));
        jQuery(pbxPremiumSubscriptionFields).each(function (i, pbxPremiumField) {
            if (currentSubscription == pbxPremiumSubscriptionId) {
                jQuery('#woocommerce_' + pbxGatewayId + '_' + pbxPremiumField).parents('tr').removeClass('hidden');
            } else {
                jQuery('#woocommerce_' + pbxGatewayId + '_' + pbxPremiumField).parents('tr').addClass('hidden');
            }
        });
    });

    jQuery('#woocommerce_' + pbxGatewayId + '_delay option[value="' + pbxOrderStateDelay + '"]').prop('disabled', (pbxCurrentSubscription != pbxPremiumSubscriptionId));

    jQuery(pbxPremiumSubscriptionFields).each(function (i, pbxPremiumField) {
        if (jQuery('#woocommerce_' + pbxGatewayId + '_' + pbxPremiumField).hasClass('hidden')) {
            jQuery('#woocommerce_' + pbxGatewayId + '_' + pbxPremiumField).parents('tr').addClass('hidden');
            jQuery('#woocommerce_' + pbxGatewayId + '_' + pbxPremiumField).removeClass('hidden');
        }
    });

    jQuery('#woocommerce_' + pbxGatewayId + '_delay').trigger('change');

    // Log files download
    jQuery('#JS-WC-select-log-file').on('change', function (e) {

        let selectValue = e.currentTarget.value;

        if (selectValue) {
            let btn = document.querySelector('#JS-WC-button-dwn-log-file');
            if (btn) {
                btn.dataset.href = selectValue;
            }
        }
    });
    jQuery('#JS-WC-button-dwn-log-file').on('click', function (e) {
        e.preventDefault();

        let target      = e.currentTarget;
        let spinner     = target.querySelector('.spinner');
        let fileName    = target.dataset.href;

        spinner.classList.add('is-active');

        let formData    = new FormData();
        formData.append( 'action', 'wc_etransactions_get_log_file_content' );
        formData.append( 'nonce', pbx_admin.nonce );
        formData.append( 'name', fileName );

        let requestOptions = {
            method: 'POST',
            body: formData,
            redirect: 'follow'
        };

        fetch( pbx_admin.ajaxUrl, requestOptions )
        .then( response => response.json() )
        .then( result => {

            if ( result.success ) {

                let data = new Blob( [result.data] );
                let aElement = document.createElement('a');
                aElement.setAttribute('download', fileName);
                let href = URL.createObjectURL(data);
                aElement.href = href;
                aElement.setAttribute('target', '_blank');
                aElement.click();
                URL.revokeObjectURL(href);

            } else {

                console.log( result.data );
            }

            spinner.classList.remove('is-active');
        })
        .catch( error => console.log('error', error) );

    });

    /**
     * Show/Hide titles fields
     */
    jQuery('#woocommerce_etransactions_3x_sofinco_fees_management').on('change', function (e) {
        
        var selectVal = jQuery(this).val();

        jQuery( 'input[data-name="title"]' ).each( function (indexInArray, valueOfElement) { 
            
            var key = jQuery( valueOfElement ).attr('data-key');

            if ( selectVal == key ) {
                jQuery( valueOfElement ).parents('tr').removeClass('hidden');
            } else {
                jQuery( valueOfElement ).parents('tr').addClass('hidden');
            }
        });
    });

    /**
     * Show/Hide titles fields in first load
     */
    function toggleTitles() {

        var selectVal = jQuery('#woocommerce_etransactions_3x_sofinco_fees_management').val();

        jQuery( 'input[data-name="title"]' ).each( function (indexInArray, valueOfElement) { 
                
            var key = jQuery( valueOfElement ).attr('data-key');
    
            if ( selectVal == key ) {
                jQuery( valueOfElement ).parents('tr').removeClass('hidden');
            } else {
                jQuery( valueOfElement ).parents('tr').addClass('hidden');
            }
        });
    }
    toggleTitles();

});
