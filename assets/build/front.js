(()=>{"use strict";var t={n:o=>{var n=o&&o.__esModule?()=>o.default:()=>o;return t.d(n,{a:n}),n},d:(o,n)=>{for(var e in n)t.o(n,e)&&!t.o(o,e)&&Object.defineProperty(o,e,{enumerable:!0,get:n[e]})},o:(t,o)=>Object.prototype.hasOwnProperty.call(t,o)};const o=window.jQuery;var n=t.n(o),e=null;function r(t){clearInterval(l),clearInterval(e);let o=document.getElementById("pbx-seamless-iframe");o.style.display="none",window.location="string"==typeof t?t:o.contentWindow.location.toString()}function i(){n()(".Up2Pay-phone").each((function(t,o){let e;n()(this),e=window.intlTelInput(o,{utilsScript:pbx_fo.utilsUrl,initialCountry:n()("#billing_country").val(),showSelectedDialCode:!0,allowDropdown:!0,customPlaceholder:"function"==typeof fctCustomPlaceholder?function(t,o){return fctCustomPlaceholder(t,o)}:""})}))}var l=setInterval((function(){let t=document.getElementById("pbx-seamless-iframe");try{if(t.contentWindow.location.toString().startsWith(pbx_fo.homeUrl))return void r(t.contentWindow.location.toString())}catch(t){}}),100);document.addEventListener("DOMContentLoaded",(function(){n()(document).on("pbx-order-poll",(function(){n().ajax({type:"POST",url:pbx_fo.orderPollUrl+"&nonce="+n()("#pbx-nonce").val(),data:{order_id:n()("#pbx-id-order").val()},dataType:"json",success:function(t){"string"==typeof t.data.redirect_url&&r(t.data.redirect_url)}})})),n()("iframe#pbx-seamless-iframe").on("load",(function(){try{if(this.contentWindow.location.toString().startsWith(pbx_fo.homeUrl))return void r(this.contentWindow.location.toString())}catch(t){}null===e&&(e=setInterval((function(){n()(document).trigger("pbx-order-poll")}),3e3))})),i()})),n()(document.body).on("updated_checkout",(function(){i()})),n()(document.body).on("update_checkout",(function(){i()}))})();