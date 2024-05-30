<?php

/**
 * E-Transactions - Main class.
 *
 * @class   WC_Etransactions_Helper
 */
class WC_Etransactions_Helper {

    private $_resultMapping = array(
        'M' => 'amount',
        'R' => 'reference',
        'T' => 'call',
        'A' => 'authorization',
        'B' => 'subscription',
        'C' => 'cardType',
        'D' => 'validity',
        'E' => 'error',
        'F' => '3ds',
        'G' => '3dsWarranty',
        'H' => 'imprint',
        'I' => 'ip',
        'J' => 'lastNumbers',
        'K' => 'sign',
        'N' => 'firstNumbers',
        'O' => '3dsInlistment',
        'o' => 'celetemType',
        'P' => 'paymentType',
        'Q' => 'time',
        'S' => 'transaction',
        'U' => 'subscriptionData',
        'W' => 'date',
        'Y' => 'country',
        'Z' => 'paymentIndex',
        'v' => '3dsVersion',
    );

    /**
     * Convert parameters to the correct format
     */
    public function convertParams(array $params) {

        $result = array();

        foreach ($this->_resultMapping as $param => $key) {
            if (isset($params[$param])) {
                $result[$key] = utf8_encode($params[$param]);
            }
        }

        return $result;
    }

    /**
     * Retrieve keys used for the mapping
     *
     * @return array
     */
    public function getParametersKeys() {
        return array_keys($this->_resultMapping);
    }

    /**
     * Get billing email
     */
    public function getBillingEmail($object) {

        if (!is_a($object, 'WC_Order') && !is_a($object, 'WC_Customer')) {
            throw new Exception('Invalid object on getBillingEmail');
        }

        return $object->get_billing_email();
    }

    /**
     * Get billing name
     */
    public function getBillingName($object) {

        if (!is_a($object, 'WC_Order') && !is_a($object, 'WC_Customer')) {
            throw new Exception('Invalid object on getBillingName');
        }

        $firstName = remove_accents($object->get_billing_first_name());
        $lastName = remove_accents($object->get_billing_last_name());
        $firstName = str_replace(' - ', '-', $firstName);
        $lastName = str_replace(' - ', '-', $lastName);
        $firstName = trim(preg_replace($this->getRegexBillingName(), '', $firstName));
        $lastName = trim(preg_replace($this->getRegexBillingName(), '', $lastName));

        return $firstName.'_'.$lastName;

    }

    public function getRegexBillingName()
    {
        return '/[^A-Za-z0-9+_]/';
    }

    /**
     * Format a value to respect specific rules
     *
     * @param string $value
     * @param string $type
     * @param int $maxLength
     * @return string
     */
    public function formatTextValue($value, $type, $maxLength = null) {
        /*
        AN : Alphanumerical without special characters
        ANP : Alphanumerical with spaces and special characters
        ANS : Alphanumerical with special characters
        N : Numerical only
        A : Alphabetic only
        */

        switch ($type) {
            default:
            case 'AN':
                $value = remove_accents($value);
                break;
            case 'ANP':
                $value = remove_accents($value);
                $value = preg_replace('/[^-. a-zA-Z0-9]/', '', $value);
                break;
            case 'ANS':
                $value = remove_accents($value);
                $value = preg_replace('/[^a-zA-Z0-9\\s]/', '', $value);
                $value = trim(preg_replace('/\\\\s+/', ' ', $value));
                break;
            case 'N':
                $value = preg_replace('/[^0-9.]/', '', $value);
                break;
            case 'A':
                $value = remove_accents($value);
                $value = preg_replace('/[^A-Za-z]/', '', $value);
                break;
        }
        // Remove carriage return characters
        $value = trim(preg_replace("/\r|\n/", '', $value));

        // Cut the string when needed
        if (!empty($maxLength) && is_numeric($maxLength) && $maxLength > 0) {
            if (function_exists('mb_strlen')) {
                if (mb_strlen($value) > $maxLength) {
                    $value = mb_substr($value, 0, $maxLength);
                }
            } elseif (strlen($value) > $maxLength) {
                $value = substr($value, 0, $maxLength);
            }
        }

        return trim($value);
    }

    /**
     * Import XML content as string and use DOMDocument / SimpleXML to validate, if available
     *
     * @param string $xml
     * @return string
     */
    public function exportToXml($xml) {

        if (class_exists('DOMDocument')) {
            $doc = new DOMDocument();
            $doc->loadXML($xml);
            $xml = $doc->saveXML();
        } elseif (function_exists('simplexml_load_string')) {
            $xml = simplexml_load_string($xml)->asXml();
        }

        $xml = trim(preg_replace('/(\s*)(' . preg_quote('<?xml version="1.0" encoding="utf-8"?>') . ')(\s*)/', '$2', $xml));
        $xml = trim(preg_replace("/\r|\n/", '', $xml));

        return $xml;
    }

    /**
     * Generate XML value for PBX_BILLING parameter
     *
     * @param WC_Order|WC_Customer $object
     * @return string
     */
    public function getXmlBillingInformation($object) {
        
        if (!is_a($object, 'WC_Order') && !is_a($object, 'WC_Customer')) {
            throw new Exception('Invalid object on getXmlBillingInformation');
        }

        $firstName = $this->formatTextValue($object->get_billing_first_name(), 'ANS', 22);
        $lastName = $this->formatTextValue($object->get_billing_last_name(), 'ANS', 22);
        $addressLine1 = $this->formatTextValue($object->get_billing_address_1(), 'ANS', 50);
        $addressLine2 = $this->formatTextValue($object->get_billing_address_2(), 'ANS', 50);
        $zipCode = $this->formatTextValue($object->get_billing_postcode(), 'ANS', 10);
        $city = $this->formatTextValue($object->get_billing_city(), 'ANS', 50);
        $countryCode = (int)WC_Etransactions_Iso3166_Country::getNumericCode($object->get_billing_country());
        $countryCodeFormat = '%03d';
        if (empty($countryCode)) {
            // Send empty string to CountryCode instead of 000
            $countryCodeFormat = '%s';
            $countryCode = '';
        }

        $dataPhone = $this->get_phone_number_args($object);
        $mobilePhone = $dataPhone['mobile_phone'];
        $countryCodeMobilePhone = $dataPhone['code_phone'];

        if(empty($mobilePhone)){
            $mobilePhone = $this->formatTextValue($object->get_meta('_up2payphone'), 'N');
            $countryCodeMobilePhone = '+'.$this->formatTextValue($object->get_meta('_up2paycountrycode'),'N');
        }

        $xml = sprintf(
            '<?xml version="1.0" encoding="utf-8"?><Billing><Address><FirstName>%s</FirstName><LastName>%s</LastName><Address1>%s</Address1><Address2>%s</Address2><ZipCode>%s</ZipCode><City>%s</City><CountryCode>' . $countryCodeFormat . '</CountryCode><CountryCodeMobilePhone>%s</CountryCodeMobilePhone><MobilePhone>%s</MobilePhone></Address></Billing>',
            $firstName,
            $lastName,
            $addressLine1,
            $addressLine2,
            $zipCode,
            $city,
            $countryCode,
            $countryCodeMobilePhone,
            $mobilePhone

        );

        return $this->exportToXml($xml);
    }

    /**
     * Get phone number args for paypal request.
     *
     * @param  WC_Order|WC_Customer $object
     * @return array
     */
    public function get_phone_number_args( $object ) {
        $phone_number = wc_sanitize_phone_number( $object->get_billing_phone() );
        $calling_code = WC()->countries->get_country_calling_code( $object->get_billing_country() );
        $calling_code = is_array( $calling_code ) ? $calling_code[0] : $calling_code;

        if ( $calling_code ) {
            $phone_number = str_replace( $calling_code, '', $object->get_billing_phone());
        }
        $phone_args = array(
            'code_phone' => $calling_code,
            'mobile_phone' => $phone_number,
        );

        return $phone_args;
    }

    /**
     * Generate XML value for PBX_SHOPPINGCART parameter
     *
     * @param WC_Order $order
     * @return string
     */
    public function getXmlShoppingCartInformation(WC_Order $order = null) {
        
        $totalQuantity = 0;
        if (!empty($order)) {
            foreach ($order->get_items() as $item) {
                $totalQuantity += (int)$item->get_quantity();
            }
        } else {
            $totalQuantity = 1;
        }
        // totalQuantity must be less or equal than 99
        // totalQuantity must be greater or equal than 1
        $totalQuantity = max(1, min($totalQuantity, 99));

        return sprintf('<?xml version="1.0" encoding="utf-8"?><shoppingcart><total><totalQuantity>%d</totalQuantity></total></shoppingcart>', $totalQuantity);
    }

    /**
     * Get the IP address of the client
     */
    public function getClientIp() {

        $ipaddress = '';
        if ($_SERVER['HTTP_CLIENT_IP']) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ($_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ($_SERVER['HTTP_X_FORWARDED']) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif ($_SERVER['HTTP_FORWARDED_FOR']) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif ($_SERVER['HTTP_FORWARDED']) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif ($_SERVER['REMOTE_ADDR']) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }

    /**
     * Get the current currency iso code
     */
    public function getCurrency() {
        return WC_Etransactions_Iso4217_Currency::getIsoCode(get_woocommerce_currency());
    }

    /**
     * Get the correct language code
     */
    public function getLanguages() {

        return array(
            'fr' => 'FRA',
            'es' => 'ESP',
            'it' => 'ITA',
            'de' => 'DEU',
            'nl' => 'NLD',
            'sv' => 'SWE',
            'pt' => 'PRT',
            'default' => 'GBR',
        );
    }

    /**
     * Check if the current device is a mobile device
     */
    public function isMobile() {

        // From http://detectmobilebrowsers.com/, regexp of 09/09/2013
        global $_SERVER;
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $userAgent)) {
            return true;
        }
        if (preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($userAgent, 0, 4))) {
            return true;
        }
        return false;
    }

}