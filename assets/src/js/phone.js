jQuery(document.body).on('updated_checkout', function (){
    checkPhone();
});
jQuery(document.body).on('update_checkout', function (){
    checkPhone();
});

jQuery(document).on('ready',function () {
    if(jQuery("#billing_phone").val()){
        jQuery('#place_order').prop('disabled', false);
        jQuery('.Up2Pay-block').hide();
    }else {
        jQuery(".iti").show();
        jQuery('.Up2Pay-block').show();
        displayPhone();
    }
})
jQuery("#billing_phone").on('input',function (){
    if(jQuery(this).val()){
        jQuery('#place_order').prop('disabled', false);
        jQuery('.Up2Pay-block').hide();
    }else {
        jQuery(".iti").show();
        jQuery('.Up2Pay-block').show();
        displayPhone();
    }
})

jQuery(document).on('change', 'input[name=payment_method]', function () {
    if(jQuery(this).val().indexOf('etransactions_std_card') === -1){
        jQuery('#place_order').prop('disabled', false);
        console.log('not caps');
    } else {
        checkPhone();
    }
})

function displayPhone(){
    jQuery(".Up2Pay-phone").each(function (index, inputtel) {
        let tel;
        let elm = jQuery(this);
        tel = window.intlTelInput(inputtel, {
            utilsScript: pbx_fo.utilsUrl,
            initialCountry: jQuery('#billing_country').val(),
            showSelectedDialCode: true,
            allowDropdown: true,
        });
        let handleChangeTel = function () {
            if (tel.isValidNumber()) {
                console.log('is valid');
                jQuery('.js-up2pay-valid').show();
                jQuery('.js-up2pay-invalid').hide();
                jQuery('#place_order').prop('disabled', false);
            } else {
                console.log('is invalid');
                jQuery('.js-up2pay-valid').hide();
                jQuery('.js-up2pay-invalid').show();
                jQuery('#place_order').prop('disabled', true);
            }
            jQuery('.Up2Pay-phone').val(elm.val());
            jQuery('.Up2Pay-countrycode').val(tel.getSelectedCountryData().dialCode)
        };

        inputtel.addEventListener('change', handleChangeTel);
        inputtel.addEventListener('keyup', handleChangeTel);
        inputtel.addEventListener('countrychange', function () {
            handleChangeTel();
        });

        tel.promise.then(function () {
            handleChangeTel();
        });
    });

}

function checkPhone (){
    if(jQuery("#billing_phone").val()) {
        jQuery('#place_order').prop('disabled', false);
        jQuery('.Up2Pay-block').hide();
    } else {
        jQuery('.Up2Pay-block').show();
        displayPhone();

    }
}