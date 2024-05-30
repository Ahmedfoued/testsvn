<?php

/**
 * E-Transactions - Main class.
 *
 * @class   WC_Etransactions
 */
class WC_Etransactions {

    private $_helper;
    private $_config;
    private $_currencyDecimals = array(
        '008' => 2,
        '012' => 2,
        '032' => 2,
        '036' => 2,
        '044' => 2,
        '048' => 3,
        '050' => 2,
        '051' => 2,
        '052' => 2,
        '060' => 2,
        '064' => 2,
        '068' => 2,
        '072' => 2,
        '084' => 2,
        '090' => 2,
        '096' => 2,
        '104' => 2,
        '108' => 0,
        '116' => 2,
        '124' => 2,
        '132' => 2,
        '136' => 2,
        '144' => 2,
        '152' => 0,
        '156' => 2,
        '170' => 2,
        '174' => 0,
        '188' => 2,
        '191' => 2,
        '192' => 2,
        '203' => 2,
        '208' => 2,
        '214' => 2,
        '222' => 2,
        '230' => 2,
        '232' => 2,
        '238' => 2,
        '242' => 2,
        '262' => 0,
        '270' => 2,
        '292' => 2,
        '320' => 2,
        '324' => 0,
        '328' => 2,
        '332' => 2,
        '340' => 2,
        '344' => 2,
        '348' => 2,
        '352' => 0,
        '356' => 2,
        '360' => 2,
        '364' => 2,
        '368' => 3,
        '376' => 2,
        '388' => 2,
        '392' => 0,
        '398' => 2,
        '400' => 3,
        '404' => 2,
        '408' => 2,
        '410' => 0,
        '414' => 3,
        '417' => 2,
        '418' => 2,
        '422' => 2,
        '426' => 2,
        '428' => 2,
        '430' => 2,
        '434' => 3,
        '440' => 2,
        '446' => 2,
        '454' => 2,
        '458' => 2,
        '462' => 2,
        '478' => 2,
        '480' => 2,
        '484' => 2,
        '496' => 2,
        '498' => 2,
        '504' => 2,
        '504' => 2,
        '512' => 3,
        '516' => 2,
        '524' => 2,
        '532' => 2,
        '532' => 2,
        '533' => 2,
        '548' => 0,
        '554' => 2,
        '558' => 2,
        '566' => 2,
        '578' => 2,
        '586' => 2,
        '590' => 2,
        '598' => 2,
        '600' => 0,
        '604' => 2,
        '608' => 2,
        '634' => 2,
        '643' => 2,
        '646' => 0,
        '654' => 2,
        '678' => 2,
        '682' => 2,
        '690' => 2,
        '694' => 2,
        '702' => 2,
        '704' => 0,
        '706' => 2,
        '710' => 2,
        '728' => 2,
        '748' => 2,
        '752' => 2,
        '756' => 2,
        '760' => 2,
        '764' => 2,
        '776' => 2,
        '780' => 2,
        '784' => 2,
        '788' => 3,
        '800' => 2,
        '807' => 2,
        '818' => 2,
        '826' => 2,
        '834' => 2,
        '840' => 2,
        '858' => 2,
        '860' => 2,
        '882' => 2,
        '886' => 2,
        '901' => 2,
        '931' => 2,
        '932' => 2,
        '934' => 2,
        '936' => 2,
        '937' => 2,
        '938' => 2,
        '940' => 0,
        '941' => 2,
        '943' => 2,
        '944' => 2,
        '946' => 2,
        '947' => 2,
        '948' => 2,
        '949' => 2,
        '950' => 0,
        '951' => 2,
        '952' => 0,
        '953' => 0,
        '967' => 2,
        '968' => 2,
        '969' => 2,
        '970' => 2,
        '971' => 2,
        '972' => 2,
        '973' => 2,
        '974' => 0,
        '975' => 2,
        '976' => 2,
        '977' => 2,
        '978' => 2,
        '979' => 2,
        '980' => 2,
        '981' => 2,
        '984' => 2,
        '985' => 2,
        '986' => 2,
        '990' => 0,
        '997' => 2,
        '998' => 2,
    );
    private $_errorCode = array(
        '00000' => 'Successful operation',
        '00001' => 'Payment system not available',
        '00003' => 'Paybor error',
        '00004' => 'Card number or invalid cryptogram',
        '00006' => 'Access denied or invalid identification',
        '00008' => 'Invalid validity date',
        '00009' => 'Subscription creation failed',
        '00010' => 'Unknown currency',
        '00011' => 'Invalid amount',
        '00015' => 'Payment already done',
        '00016' => 'Existing subscriber',
        '00021' => 'Unauthorized card',
        '00029' => 'Invalid card',
        '00030' => 'Timeout',
        '00033' => 'Unauthorized IP country',
        '00040' => 'No 3D Secure',
    );

    /**
     * The class constructor
     */
    public function __construct(WC_Etransactions_Config $config) {

        $this->_config = $config;
        $this->_helper = new WC_Etransactions_Helper();
    }

    /**
     * Retrieve the language value for PBX_LANG parameter
     *
     * @return string
     */
    protected function getPbxLang() {

        // Choose correct language
        $lang = get_locale();
        if (!empty($lang)) {
            $lang = preg_replace('#_.*$#', '', $lang);
        }
        $languages = $this->_helper->getLanguages();
        if (!array_key_exists($lang, $languages)) {
            $lang = 'default';
        }

        return $languages[$lang];
    }

    /**
     * @params WC_Order $order Order
     * @params string $type Type of payment (standard or threetime)
     * @params array $additionalParams Additional parameters
     */
    public function buildSystemParams(WC_Order $order, $type, array $additionalParams = array(), $mode = '') {
        global $wpdb;

        // Parameters
        $values = array();
        $fields = $this->_config->getFields();

        // Retrieve the current card that was forced on the order (if any)
        $card = $this->_config->getOrderCard($order);
        // Retrieve the tokenized card (if any)
        $tokenizedCard = $this->_config->getTokenizedCard($order);

        // Merchant information
        $values['PBX_SITE'] = $this->_config->getSite();
        $values['PBX_RANG'] = $this->_config->getRank();
        $values['PBX_IDENTIFIANT'] = $this->_config->getIdentifier();
        $values['PBX_VERSION'] = WC_ETRANSACTIONS_PLUGIN . "-" . WC_ETRANSACTIONS_VERSION . "_WP" . get_bloginfo('version') . "_WC" . WC()->version;

        // Order information
        $values['PBX_PORTEUR'] = $this->_helper->getBillingEmail($order);
        $values['PBX_DEVISE'] = $this->_helper->getCurrency();

        // Add payment try count
        $paymentTryCount = (int)get_post_meta($order->get_id(), 'payment_try_count', true);
        if (empty($paymentTryCount)) {
            $paymentTryCount = 1;
        } else {
            $paymentTryCount++;
        }
        update_post_meta($order->get_id(), 'payment_try_count', $paymentTryCount);

        $pbx_cmd = 'woo_'.$order->get_id().'_'.$this->_helper->getBillingName($order).'_'.date('mdhi');
        $pbx_cmd = preg_replace('/[^A-Za-z0-9\-_]/', ' ', $pbx_cmd);
        $pbx_cmd = preg_replace('/\s+/', ' ', $pbx_cmd);
        $pbx_cmd = substr($pbx_cmd, 0, 250);

        $values['PBX_CMD'] = $pbx_cmd;

        // Amount
        $orderAmount = floatval($order->get_total());
        $amountScale = $this->_currencyDecimals[$values['PBX_DEVISE']];
        $amountScale = pow(10, $amountScale);
        switch ($type) {
            case 'standard':
                $delay = $this->_config->getDelay();

                // Debit on specific order status, force authorization only
                if ($this->_config->isPremium()
                && $delay === WC_Etransactions_Config::ORDER_STATE_DELAY) {
                    // Author only
                    $values['PBX_AUTOSEULE'] = 'O';
                }

                // Classic delay
                if ($delay != WC_Etransactions_Config::ORDER_STATE_DELAY) {
                    // The current card is not able to handle PBX_DIFF parameter
                    if (!empty($card->id_card) && empty($card->debit_differe)) {
                        // Reset the delay
                        $delay = 0;
                    }
                    // Delay must be between 0 & 7
                    $delay = max(0, min($delay, 7));
                    if ($delay > 0) {
                        $values['PBX_DIFF'] = sprintf('%02d', $delay);
                    }
                }

                $values['PBX_TOTAL'] = sprintf('%03d', round($orderAmount * $amountScale));
            break;
            case 'threetime':
                // Compute each payment amount
                $step = round($orderAmount * $amountScale / 3);
                $firstStep = ($orderAmount * $amountScale) - 2 * $step;
                $values['PBX_TOTAL'] = sprintf('%03d', $firstStep);
                $values['PBX_2MONT1'] = sprintf('%03d', $step);
                $values['PBX_2MONT2'] = sprintf('%03d', $step);

                // Payment dates
                $now = new DateTime();
                if ( $mode === 'test' ) {
                    $test_pbx_days = $order->get_meta('_test_pbx_days');
                    $test_pbx_days = $test_pbx_days ? $test_pbx_days : '1';
                    $now->modify($test_pbx_days . ' day');
                    $values['PBX_DATE1'] = $now->format('d/m/Y');
                    $now->modify($test_pbx_days . ' day');
                    $values['PBX_DATE2'] = $now->format('d/m/Y');
                } else {
                    $now->modify('1 month');
                    $values['PBX_DATE1'] = $now->format('d/m/Y');
                    $now->modify('1 month');
                    $values['PBX_DATE2'] = $now->format('d/m/Y');
                }

                // Force validity date of card
                $values['PBX_DATEVALMAX'] = $now->format('ym');
            break;
            case 'threetime_sofinco':

                $values['PBX_SOUHAITAUTHENT']   = '01';
                $values['PBX_TOTAL']            = sprintf('%03d', round($orderAmount * $amountScale));
                $values['PBX_TYPECARTE']        = $fields['fees_management'] ?? '';
                $values['PBX_TYPEPAIEMENT']     = 'LIMONETIK';
                $values['PBX_CUSTOMER']         = '<?xml version="1.0" encoding="utf-8"?><Customer><Id>01</Id></Customer>';
                
            break;
            default:
                $message  = __('Unexpected type %s', WC_ETRANSACTIONS_PLUGIN);
                $message = sprintf($message, $type);
                throw new Exception($message);
            break;
        }

        // E-Transactions => Magento
        $values['PBX_RETOUR'] = 'M:M;R:R;T:T;A:A;B:B;C:C;D:D;E:E;F:F;G:G;I:I;J:J;N:N;O:O;P:P;Q:Q;S:S;W:W;Y:Y;v:v;K:K';
        $values['PBX_RUF1'] = 'POST';

        // Allow tokenization ?
        $allowTokenization = (bool)get_post_meta($order->get_id(), $order->get_payment_method() . '_allow_tokenization', true);
        if (empty($tokenizedCard) && $this->_config->allowOneClickPayment($card) && $allowTokenization) {
            $values['PBX_REFABONNE'] = wp_hash($order->get_id() . '-' . $order->get_customer_id());
            $values['PBX_RETOUR'] = 'U:U;' . $values['PBX_RETOUR'];
        }

        // Add tokenized card information
        if (!empty($tokenizedCard)) {
            $cardToken = explode('|', $tokenizedCard->get_token());
            $values['PBX_REFABONNE'] = $cardToken[0];
            $values['PBX_TOKEN'] = $cardToken[1];
            $values['PBX_DATEVAL'] = sprintf('%02d', $tokenizedCard->get_expiry_month()) . sprintf('%02d', substr($tokenizedCard->get_expiry_year(), 2, 2));
        }

        // 3DSv2 parameters
        $values['PBX_SHOPPINGCART'] = $this->_helper->getXmlShoppingCartInformation($order);
        $values['PBX_BILLING'] = $this->_helper->getXmlBillingInformation($order);
        // Choose correct language
        $values['PBX_LANGUE'] = $this->getPbxLang();
        // Prevent PBX_SOURCE to be sent when card type is LIMONETIK
        if (empty($card->type_payment) || $card->type_payment != 'LIMONETIK') {
            $values['PBX_SOURCE'] = 'RWD';
        }

        if ($this->_config->getPaymentUx($order) == WC_Etransactions_Config::PAYMENT_UX_SEAMLESS) {
            $values['PBX_THEME_CSS'] = 'frame-puma.css';
        }

        // Misc.
        $values['PBX_TIME'] = date('c');
        $values['PBX_HASH'] = strtoupper($this->_config->getHmacAlgo());

        // Specific parameter to set a specific payment method
        if (!empty($card->id_card)) {
            $values['PBX_TYPEPAIEMENT'] = $card->type_payment;
            $values['PBX_TYPECARTE'] = $card->type_card;
        }

        // Check for 3DS exemption
        if ($this->_config->orderNeeds3dsExemption($order)) {
            $values['PBX_SOUHAITAUTHENT'] = '02';
        }

        // Adding additionnal informations
        $values = array_merge($values, $additionalParams);

        // Sort parameters for simpler debug
        ksort($values);

        // Sign values
        $values['PBX_HMAC'] = $this->signValues($values);

        return $values;
    }

    /**
     * Build parameters in order to create a token for a card
     *
     * @param object $card
     * @param array $additionalParams
     * @return array
     */
    public function buildTokenizationSystemParams($card = null, array $additionalParams = array()) {
        global $wpdb;

        // Parameters
        $values = array();

        // Merchant information
        $values['PBX_SITE'] = $this->_config->getSite();
        $values['PBX_RANG'] = $this->_config->getRank();
        $values['PBX_IDENTIFIANT'] = $this->_config->getIdentifier();
        $values['PBX_VERSION'] = WC_ETRANSACTIONS_PLUGIN . "-" . WC_ETRANSACTIONS_VERSION . "_WP" . get_bloginfo('version') . "_WC" . WC()->version;

        // "Order" information
        $apmId = uniqid();
        $values['PBX_PORTEUR'] = $this->_helper->getBillingEmail(WC()->customer);
        $values['PBX_REFABONNE'] = wp_hash($apmId . '-' . get_current_user_id());
        $values['PBX_DEVISE'] = $this->_helper->getCurrency();
        $values['PBX_CMD'] = 'APM-' . get_current_user_id() . '-' . $apmId;

        // Amount
        $orderAmount = floatval(1.0);
        $amountScale = pow(10, $this->_currencyDecimals[$values['PBX_DEVISE']]);
        // Author only
        $values['PBX_AUTOSEULE'] = 'O';
        $values['PBX_TOTAL'] = sprintf('%03d', round($orderAmount * $amountScale));
        $values['PBX_RETOUR'] = 'U:U;M:M;R:R;T:T;A:A;B:B;C:C;D:D;E:E;F:F;G:G;I:I;J:J;N:N;O:O;P:P;Q:Q;S:S;W:W;Y:Y;v:v;K:K';
        $values['PBX_RUF1'] = 'POST';

        // 3DSv2 parameters
        $values['PBX_SHOPPINGCART'] = $this->_helper->getXmlShoppingCartInformation();
        $values['PBX_BILLING'] = $this->_helper->getXmlBillingInformation(WC()->customer);

        // Choose correct language
        $values['PBX_LANGUE'] = $this->getPbxLang();
        // Prevent PBX_SOURCE to be sent when card type is LIMONETIK
        if (empty($card->type_payment) || $card->type_payment != 'LIMONETIK') {
            $values['PBX_SOURCE'] = 'RWD';
        }

        // Misc.
        $values['PBX_TIME'] = date('c');
        $values['PBX_HASH'] = strtoupper($this->_config->getHmacAlgo());

        // Specific parameter to set a specific payment method
        if (!empty($card->id_card)) {
            $values['PBX_TYPEPAIEMENT'] = $card->type_payment;
            $values['PBX_TYPECARTE'] = $card->type_card;
        }

        // Adding additionnal informations
        $values = array_merge($values, $additionalParams);

        // Sort parameters for simpler debug
        ksort($values);

        // Sign values
        $values['PBX_HMAC'] = $this->signValues($values);

        return $values;
    }

    /**
     * Get the order payments data
     */
    public function getOrderPayments($orderId, $type) {
        global $wpdb;

        $sql = 'select * from '.$wpdb->prefix.'wc_etransactions_payment where order_id = %d and type = %s';
        $sql = $wpdb->prepare($sql, $orderId, $type);
        return $wpdb->get_row($sql);
    }

    /**
     * Retrieve payment data for a specific order ID & transaction ID
     *
     * @param int $orderId
     * @param int $transactionId
     * @return ?array
     */
    public function getOrderPaymentDataByTransactionId($orderId, $transactionId) {
        global $wpdb;

        $sql = 'select * from '.$wpdb->prefix.'wc_etransactions_payment where order_id = %d';
        $sql = $wpdb->prepare($sql, $orderId);

        foreach ($wpdb->get_results($sql) as $order) {
            if (empty($order) || empty($order->data)) {
                continue;
            }
            $data = unserialize($order->data);
            if (empty($data) || empty($data['transaction'])) {
                continue;
            }

            if ($data['transaction'] == $transactionId) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Check if the is an existing transaction for a specific order
     *
     * @param int $orderId
     * @param string $paymentType
     * @return boolean
     */
    public function hasOrderPayment($orderId, $paymentType = null) {
        global $wpdb;

        $sql = 'select COUNT(*) from '.$wpdb->prefix.'wc_etransactions_payment where order_id = %d';
        if (!empty($paymentType)) {
            $sql .= ' AND `type` = %s';
            $sql = $wpdb->prepare($sql, $orderId, $paymentType);
        } else {
            $sql = $wpdb->prepare($sql, $orderId);
        }

        return ((int)$wpdb->get_var($sql) > 0);
    }

    /**
     * Get params
     */
    public function getParams() {

        // Retrieves data
        $data = file_get_contents('php://input');
        if (empty($data)) {
            $data = $_SERVER['QUERY_STRING'];
        }
        if (empty($data)) {
            $message = 'An unexpected error in E-Transactions call has occured: no parameters.';
            throw new Exception(__($message, WC_ETRANSACTIONS_PLUGIN));
        }

        // Extract signature
        $matches = array();
        if (!preg_match('#^(.*)&K=(.*)$#', $data, $matches)) {
            $message = 'An unexpected error in E-Transactions call has occured: missing signature.';
            throw new Exception(__($message, WC_ETRANSACTIONS_PLUGIN));
        }

        // Check signature
        $signature = base64_decode(urldecode($matches[2]));
        $pubkey = file_get_contents(dirname(__FILE__).'/pubkey.pem');
        $res = (boolean) openssl_verify($matches[1], $signature, $pubkey);

        // Try by removing extra HTTP parameters into the URL
        if (!$res) {
            $httpParameters = $_GET;
            if (isset($httpParameters['wc-api'])) {
                unset($httpParameters['wc-api']);
            }
            if (isset($httpParameters['status'])) {
                unset($httpParameters['status']);
            }
            // Rebuild the query string using the 3986 RFC
            $data = http_build_query($httpParameters, '?', '&', PHP_QUERY_RFC3986);

            // Extract signature
            $matches = array();
            if (!preg_match('#^(.*)&K=(.*)$#', $data, $matches)) {
                $message = 'An unexpected error in E-Transactions call has occured: missing signature.';
                throw new Exception(__($message, WC_ETRANSACTIONS_PLUGIN));
            }

            // Check signature
            $signature = base64_decode(urldecode($matches[2]));
            $pubkey = file_get_contents(dirname(__FILE__).'/pubkey.pem');
            $res = (bool) openssl_verify($matches[1], $signature, $pubkey);

            if (preg_match('#^s=i&(.*)&K=(.*)$#', $data, $matches)) {
                $signature = base64_decode(urldecode($matches[2]));
                $res = (boolean) openssl_verify($matches[1], $signature, $pubkey);
            }
    
            // IPN LIMONETIK case, we have to remove some args
            if (!$res) {
                // Remove any extra parameter that is not useful (prevent wrong signature too)
                $queryArgs = array();
                parse_str($data, $queryArgs);
                foreach (array_diff(array_keys($queryArgs), $this->_helper->getParametersKeys()) as $queryKey) {
                    unset($queryArgs[$queryKey]);
                }
                // Rebuild the data query string
                $data = http_build_query($queryArgs, '?', '&', PHP_QUERY_RFC3986);
                preg_match('#^(.*)&K=(.*)$#', $data, $matches);
    
                // Check signature
                $signature = base64_decode(urldecode($matches[2]));
                $pubkey = file_get_contents(dirname(__FILE__).'/pubkey.pem');
                $res = (boolean) openssl_verify($matches[1], $signature, $pubkey);
            }
    
            if (!$res) {
                $message = 'An unexpected error in E-Transactions call has occured: invalid signature.';
                throw new Exception(__($message, WC_ETRANSACTIONS_PLUGIN));
            }
        }

        $rawParams = array();
        parse_str($data, $rawParams);

        // Decrypt params
        $params = $this->_helper->convertParams($rawParams);
        if (empty($params)) {
            $message = 'An unexpected error in E-Transactions call has occured: no parameters.';
            throw new Exception(__($message, WC_ETRANSACTIONS_PLUGIN));
        }

        return $params;
    }

    /**
     * Get system URL
     */
    public function getSystemUrl(WC_Order $order = null) {

        $urls = $this->_config->getSystemUrls($order);
        if (empty($urls)) {
            $message = 'Missing URL for E-Transactions system in configuration';
            throw new Exception(__($message, WC_ETRANSACTIONS_PLUGIN));
        }

        // look for valid peer
        foreach ($urls as $url) {
            $testUrl = preg_replace('#^([a-zA-Z0-9]+://[^/]+)(/.*)?$#', '\1/load.html', $url);

            $connectParams = array(
                'timeout' => 5,
                'redirection' => 0,
                'user-agent' => 'Woocommerce E-Transactions module',
                'httpversion' => '2',
            );
            try {
                $response = wp_remote_get($testUrl, $connectParams);
                if (is_array($response) && ($response['response']['code'] == 200)) {
                    if (preg_match('#<div id="server_status" style="text-align:center;">OK</div>#', $response['body']) == 1) {
                        return $url;
                    }
                }
            } catch (Exception $e) {

            }
        }

        // Here, there's a problem
        throw new Exception(__('E-Transactions not available. Please try again later.', WC_ETRANSACTIONS_PLUGIN));
    }

    /**
     * Sign values
     */
    public function signValues(array $values) {

        // Serialize values
        $params = array();
        foreach ($values as $name => $value) {
            $params[] = $name.'='.$value;
        }
        $query = implode('&', $params);

        // Prepare key
        $key = pack('H*', $this->_config->getHmacKey());

        // Sign values
        $sign = hash_hmac($this->_config->getHmacAlgo(), $query, $key);
        if ($sign === false) {
            $errorMsg = 'Unable to create hmac signature. Maybe a wrong configuration.';
            throw new Exception(__($errorMsg, WC_ETRANSACTIONS_PLUGIN));
        }

        return strtoupper($sign);
    }

    /**
     * Get error message from error code
     */
    public function toErrorMessage($code) {

        if (isset($this->_errorCode[$code])) {
            return $this->_errorCode[$code];
        }

        return 'Unknown error '.$code;
    }

    /**
     * Load order from the $token
     * @param string $token Token (@see tokenizeOrder)
     * @return Mage_Sales_Model_Order
     */
    public function untokenizeOrder($token) {

        $token = str_replace('woo_', '', $token);

        $parts = explode('_', $token, 4);
        if (count($parts) < 2) {
            $message = 'Invalid decrypted token "%s"';
            throw new Exception(sprintf(__($message, WC_ETRANSACTIONS_PLUGIN), $token));
        }

        // Retrieves order
        $order = wc_get_order((int)$parts[0]);
        if (empty($order)) {
            $message = 'Not existing order id from decrypted token "%s"';
            throw new Exception(sprintf(__($message, WC_ETRANSACTIONS_PLUGIN), $token));
        }

        $name = $this->_helper->getBillingName($order);
        $nameVerify = $parts[1].'_'.$parts[2];
        if (($name != utf8_decode($nameVerify)) && ($name != $nameVerify)) {
            $message = 'Consistency error on descrypted token "%s"';
            throw new Exception(sprintf(__($message, WC_ETRANSACTIONS_PLUGIN), $token));
        }

        return $order;
    }

    /**
     * Retrieve the customer ID from the transaction reference
     * Use for the "Add payment method" action (APM)
     *
     * @param string $reference
     * @return int the customer ID
     */
    public function untokenizeCustomerId($reference) {

        $parts = explode('-', $reference);
        if (count($parts) < 3) {
            throw new Exception(sprintf(__('Invalid decrypted reference "%s"', WC_ETRANSACTIONS_PLUGIN), $reference));
        }

        return (int)$parts[1];
    }

    /**
     * Retrieve the APM unique ID from the transaction reference
     * Use for the "Add payment method" action (APM)
     *
     * @param string $reference
     * @return int the APM ID
     */
    public function untokenizeApmId($reference) {
        
        $parts = explode('-', $reference);
        if (count($parts) < 3) {
            throw new Exception(sprintf(__('Invalid decrypted reference "%s"', WC_ETRANSACTIONS_PLUGIN), $reference));
        }

        return (int)$parts[2];
    }
}
