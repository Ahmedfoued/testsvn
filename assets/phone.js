(()=>{function e(){jQuery(".Up2Pay-phone").each((function(e,i){let n,o=jQuery(this);n=window.intlTelInput(i,{utilsScript:pbx_fo.utilsUrl,initialCountry:jQuery("#billing_country").val(),showSelectedDialCode:!0,allowDropdown:!0});let u=function(){n.isValidNumber()?(console.log("is valid"),jQuery(".js-up2pay-valid").show(),jQuery(".js-up2pay-invalid").hide(),jQuery("#place_order").prop("disabled",!1)):(console.log("is invalid"),jQuery(".js-up2pay-valid").hide(),jQuery(".js-up2pay-invalid").show(),jQuery("#place_order").prop("disabled",!0)),jQuery(".Up2Pay-phone").val(o.val()),jQuery(".Up2Pay-countrycode").val(n.getSelectedCountryData().dialCode)};i.addEventListener("change",u),i.addEventListener("keyup",u),i.addEventListener("countrychange",(function(){u()})),n.promise.then((function(){u()}))}))}jQuery(document.body).on("updated_checkout",(function(){e()})),jQuery(document.body).on("update_checkout",(function(){e()})),jQuery(document).on("ready",(function(){jQuery("#billing_phone").val()?(jQuery(".iti").hide(),jQuery("#place_order").prop("disabled",!1),jQuery(".js-up2pay-invalid").hide()):jQuery(".iti").show()})),jQuery("#billing_phone").on("input",(function(){jQuery(this).val()?(jQuery(".iti").hide(),jQuery("#place_order").prop("disabled",!1),jQuery(".js-up2pay-invalid").hide()):jQuery(".iti").show()})),jQuery(document).on("change","input[name=payment_method]",(function(){console.log("test payment option"),console.log(jQuery(this).val().indexOf("etransactions_std_card")),-1===jQuery(this).val().indexOf("etransactions_std_card")?jQuery("#place_order").prop("disabled",!1):(jQuery("#billing_phone").val()&&(jQuery(".js-up2pay-invalid").hide(),jQuery("#place_order").prop("disabled",!1)),e())}))})();