<?php

/**
 * E-Transactions - callbacks class
 *
 * @class   WC_Etransactions_Callbacks
 */
class WC_Etransactions_Callbacks {

    protected $config;
    protected $defaultConfig;
    protected $etransactions;
    public $plugin_id;
    public $type;
    public $id;

    /**
     * The class constructor
     */
    public function __construct( $config, $defaultConfig, $etransactions, $plugin_id, $id ) {

        $this->config           = $config;
        $this->defaultConfig    = $defaultConfig;
        $this->etransactions    = $etransactions;
        $this->plugin_id        = $plugin_id;
        $this->type             = $config->paymentType;
        $this->id               = $id;
    }

    /**
     * Handle the return from the payment gateway
     */
    public function api_call() {

        if (!isset($_GET['status'])) {
            header('Status: 404 Not found', true, 404);
            die();
        }

        switch ($_GET['status']) {
            case 'cancel':
                return $this->on_payment_canceled();
            break;
            case 'failed':
                return $this->on_payment_failed();
            break;
            case 'ipn':
                return $this->on_ipn();
            break;
            case 'success':
                return $this->on_payment_succeed();
            break;
            case 'success-tokenization':
                return $this->onTokenizationSucceed();
            break;
            case 'ipn-tokenization':
                return $this->onTokenizationIpn();
            break;
            case 'cancel-tokenization':
                return wp_redirect(wc_get_endpoint_url('add-payment-method', '', wc_get_page_permalink('myaccount')));
            break;
            case 'failed-tokenization':
                return $this->onTokenizationFailed();
            break;
            default:
                header('Status: 404 Not found', true, 404);
                die();
            break;
        }
    }

    /**
     * On payment cancellation, redirect the customer to the checkout page
     */
    public function on_payment_canceled() {

        $order = null;
        try {
            $params = $this->etransactions->getParams();

            if ($params !== false) {
                $order = $this->etransactions->untokenizeOrder($params['reference']);
                $message = __('Payment canceled', WC_ETRANSACTIONS_PLUGIN);
                $this->addCartErrorMessage($message);

                wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::on_payment_canceled params: %s", json_encode($params) ), $this->config->isDebug() );

            }
        } catch (Exception $e) {
            wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::on_payment_canceled: %s", $e ), $this->config->isDebug() );
        }

        $this->redirectToCheckout($order);
    }

    /**
     * On payment fialure, redirect the customer to the checkout page
     */
    public function on_payment_failed() {

        $order = null;
        try {
            $params = $this->etransactions->getParams();

            if ($params !== false) {
                $order = $this->etransactions->untokenizeOrder($params['reference']);
                $message = __('Customer is back from E-Transactions payment page.', WC_ETRANSACTIONS_PLUGIN);
                $message .= ' ' . __('Payment refused by E-Transactions', WC_ETRANSACTIONS_PLUGIN);
                $this->addCartErrorMessage($message);

                wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::on_payment_failed params: %s", json_encode($params) ), $this->config->isDebug() );

            }
        } catch (Exception $e) {
            wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::on_payment_failed: %s", $e ), $this->config->isDebug() );
        }

        $this->redirectToCheckout($order);
    }

    /**
     * Save card token information after payment
     */
    public function on_ipn() {

        try {
            $params = $this->etransactions->getParams();

            if ($params === false) {
                return;
            }

            $order = $this->etransactions->untokenizeOrder($params['reference']);

            // Check required parameters
            $this->checkRequiredParameters($order, $params);

            // Payment success
            $this->addPaymentInfosAndChangeOrderStatus($order, $params, 'ipn');

            // Save card token information
            $this->saveCardTokenAfterPayment($order, $params);

            wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::on_ipn params: %s", json_encode($params) ), $this->config->isDebug() );
            
        } catch (Exception $e) {
            wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::on_ipn: %s", $e ), $this->config->isDebug() );
            throw $e;
        }
    }

    /**
     * On payment success, redirect the customer to the checkout page
     */
    public function on_payment_succeed() {

        $order = null;
        try {
            $params = $this->etransactions->getParams();
            if ($params === false) {
                return;
            }

            // Retrieve order
            $order = $this->etransactions->untokenizeOrder($params['reference']);

            // Check required parameters
            $this->checkRequiredParameters($order, $params);

            $message = __('Customer is back from E-Transactions payment page.', WC_ETRANSACTIONS_PLUGIN);
            $this->addOrderNote($order, $message);
            WC()->cart->empty_cart();

            // Payment success
            $this->addPaymentInfosAndChangeOrderStatus($order, $params, 'customer');

            // Save card token information
            $this->saveCardTokenAfterPayment($order, $params);

            wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::on_payment_succeed params: %s", json_encode($params) ), $this->config->isDebug() );

            wp_redirect($order->get_checkout_order_received_url());
            die();
        } catch (Exception $e) {
            wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::on_payment_succeed: %s", $e ), $this->config->isDebug() );
        }

        $this->redirectToCheckout($order);
    }

    /**
     * Retrieve parameters & customer id, backup the tokenized card if not already exists
     * Redirect the customer to the payments methods list
     *
     * @return void
     */
    public function onTokenizationSucceed() {

        try {
            $params = $this->etransactions->getParams();
            $customerId = $this->etransactions->untokenizeCustomerId($params['reference']);

            $this->saveTokenToDatabase($params, $customerId);

            wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::onTokenizationSucceed: %s", json_encode($params) ), $this->config->isDebug() );

        } catch (Exception $e) {
            wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::onTokenizationSucceed: %s", $e ), $this->config->isDebug() );
            wc_add_notice($e->getMessage(), 'error');
            wp_redirect(wc_get_endpoint_url('payment-methods', '', wc_get_page_permalink('myaccount')));
        }

        wc_add_notice(__('Your card has been added as a new payment method.', WC_ETRANSACTIONS_PLUGIN));
        wp_redirect(wc_get_endpoint_url('payment-methods', '', wc_get_page_permalink('myaccount')));
    }

    /**
     * Retrieve parameters & customer id, backup the tokenized card (IPN case)
     *
     * @return void
     */
    public function onTokenizationIpn() {

        try {
            $params = $this->etransactions->getParams();
            $customerId = $this->etransactions->untokenizeCustomerId($params['reference']);

            if ($params['error'] != '00000') {
                // Payment refused
                $error = $this->etransactions->toErrorMessage($params['error']);
                wc_etransactions_add_log( sprintf( "Payment was refused by E-Transactions (%s).", $error ), $this->config->isDebug() );
                return;
            }

            $this->saveTokenToDatabase($params, $customerId);
            wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::onTokenizationIpn params: %s", json_encode($params) ), $this->config->isDebug() );

        } catch (Exception $e) {
            wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::onTokenizationIpn: %s", $e ), $this->config->isDebug() );
        }
    }

    /**
     * Redirect the customer to the "Add payment method" page in case of failure
     *
     * @return void
     */
    public function onTokenizationFailed() {

        try {
            $params = $this->etransactions->getParams();
            $message = __('Payment was refused by E-Transactions (%s).', WC_ETRANSACTIONS_PLUGIN);
            $error = $this->etransactions->toErrorMessage($params['error']);
            wc_add_notice(sprintf($message, $error), 'error');
            
            wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::onTokenizationFailed params %s", json_encode($params) ), $this->config->isDebug() );

        } catch (Exception $e) {
            wc_etransactions_add_log( sprintf( "WC_Etransactions_Callbacks::onTokenizationFailed: %s", $e ), $this->config->isDebug() );
            wc_add_notice( $e->getMessage(), 'error' );
        }

        wp_redirect(wc_get_endpoint_url('add-payment-method', '', wc_get_page_permalink('myaccount')));
    }

    /**
     * Redirect to checkout page
     */
    public function redirectToCheckout($order) {

        if ($order !== null) {
            // Try to pay again, redirect to checkout page
            wp_redirect($order->get_checkout_payment_url());
        } else {
            // Unable to retrieve the order, redirect to shopping cart
            wp_redirect(WC()->cart->get_cart_url());
        }
        die();
    }

    /**
     * Check required parameters on IPN / Customer back on shop
     *
     * @param WC_Order $order
     * @param array $params
     * @return void
     */
    public function checkRequiredParameters(WC_Order $order, $params) {

        $requiredParams = array('amount', 'transaction', 'error', 'reference', 'sign', 'date', 'time');
        foreach ($requiredParams as $requiredParam) {
            if (!isset($params[$requiredParam])) {
                $message = sprintf(__('Missing %s parameter in E-Transactions call', WC_ETRANSACTIONS_PLUGIN), $requiredParam);
                $this->addOrderNote($order, $message);
                throw new Exception($message);
            }
        }
    }

    /**
     * Save payment infos, add note on order and change its status
     *
     * @param WC_Order $order
     * @param array $params
     * @param string $context (ipn or customer)
     * @return void
     */
    public function addPaymentInfosAndChangeOrderStatus(WC_Order $order, $params, $context) {
        global $wpdb;

        // Check if the order has already been captured
        // Manage specific LIMONETIK case
        if ($this->type == 'standard' && $this->etransactions->hasOrderPayment($order->get_id()) && $params['paymentType'] != 'LIMONETIK') {
            return;
        }

        if ($params['error'] != '00000') {
            // Payment refused
            $message = __('Payment was refused by E-Transactions (%s).', WC_ETRANSACTIONS_PLUGIN);
            $error = $this->etransactions->toErrorMessage($params['error']);
            $message = sprintf($message, $error);
            $this->addOrderNote($order, $message);
            return;
        }

        // Payment accepted / author OK
        switch ($this->type) {
            case 'standard':
                switch ($params['cardType']) {
                    case 'CVCONNECT':
                        $paymentType = 'first_payment';
                        if ($context == 'customer') {
                            $paymentType = 'capture';
                        }
                        if ($this->etransactions->hasOrderPayment($order->get_id(), $paymentType)) {
                            break;
                        }
                        $this->addOrderNote($order, __('Payment was authorized and captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                        $this->addOrderPayment($order, $paymentType, $params);
                        $order->payment_complete($params['transaction']);
                    break;
                    case 'LIMOCB':
                        if ($this->etransactions->hasOrderPayment($order->get_id(), 'second_payment')) {
                            break;
                        }

                        $this->addOrderNote($order, __('Second payment was captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                        $this->addOrderPayment($order, 'second_payment', $params);
                        $order->payment_complete($params['transaction']);
                    break;
                    default:
                        if ($this->config->getDelay() == WC_Etransactions_Config::ORDER_STATE_DELAY) {
                            $this->addOrderPayment($order, 'authorization', $params);
                            $this->addOrderNote($order, __('Payment was authorized by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                            $order->update_status('on-hold');
                        } else {
                            $this->addOrderPayment($order, 'capture', $params);
                            $this->addOrderNote($order, __('Payment was authorized and captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                            $order->payment_complete($params['transaction']);
                        }
                    break;
                }
            break;
            case 'threetime':
                // Prevent duplicate transactions (IPN vs customer)
                if ($this->etransactions->getOrderPaymentDataByTransactionId($order->get_id(), $params['transaction']) !== null) {
                    return;
                }

                $sql = 'select distinct type from ' . $wpdb->prefix . 'wc_etransactions_payment where order_id = ' . $order->get_id();
                $done = $wpdb->get_col($sql);
                if (!in_array('first_payment', $done)) {
                    $this->addOrderNote($order, __('Payment was authorized and captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                    $order->payment_complete($params['transaction']);
                    $this->addOrderPayment($order, 'first_payment', $params);
                } elseif (!in_array('second_payment', $done)) {
                    $this->addOrderNote($order, __('Second payment was captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                    $this->addOrderPayment($order, 'second_payment', $params);
                } elseif (!in_array('third_payment', $done)) {
                    $this->addOrderNote($order, __('Third payment was captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                    $this->addOrderPayment($order, 'third_payment', $params);
                } else {
                    $message = __('Invalid three-time payment status', WC_ETRANSACTIONS_PLUGIN);
                    $this->addOrderNote($order, $message);
                    throw new Exception($message);
                }
            break;
            default:
                $message = __('Unexpected type %s', WC_ETRANSACTIONS_PLUGIN);
                $message = sprintf($message, $this->type);
                $this->addOrderNote($order, $message);
                throw new Exception($message);
            break;
        }
    }

    /**
     * After the payment, if subscriptionData is available,
     * create the token if not already exists
     *
     * @param WC_Order $order
     * @param array $params
     * @return int the token id
     */
    public function saveCardTokenAfterPayment(WC_Order $order, $params) {

        // Check for Premium subscription & subscriptionData information
        if (!$this->config->isPremium() || empty($params['subscriptionData'])) {
            return;
        }

        // Allow tokenization ?
        $allowTokenization = (bool)get_post_meta($order->get_id(), $order->get_payment_method() . '_allow_tokenization', true);
        if (!$allowTokenization) {
            return;
        }

        return $this->saveTokenToDatabase($params, $order->get_customer_id(), $order);
    }

    /**
     * Save the token to the database if not already exists
     *
     * @param array $params
     * @param int $customerId
     * @param WC_Order $order
     * @return bool
     */
    public function saveTokenToDatabase($params, $customerId, $order = null) {

        // Retrieve original order
        if (empty($order)) {
            // APM case
            if (preg_match('/APM-.*/', $params['reference'])) {
                $referenceId = $this->etransactions->untokenizeApmId($params['reference']);
            } else {
                $order = $this->etransactions->untokenizeOrder($params['reference']);
                $referenceId = $order->get_id();
            }
        } else {
            $referenceId = $order->get_id();
        }

        // Retrieve token information & card expiry date from subscriptionData
        $subscriptionData = explode('  ', $params['subscriptionData']);
        // Build token using order id too, so we can duplicate the cards
        // Token content : PBX_REFABONNE|PBX_TOKEN
        $token = wp_hash($referenceId . '-' . $customerId) . '|' . trim($subscriptionData[0]);

        $expiryDate = trim($subscriptionData[1]);
        $expiryYear = '20' . substr($expiryDate, 0, 2);
        $expiryMonth = substr($expiryDate, 2, 2);

        // If tokenization is available, create the tokenized card
        // First, check if the token already exists on our side
        $exitingTokens = WC_Payment_Tokens::get_tokens(array(
            'user_id' => $customerId,
            'gateway_id' => $this->id,
        ));

        // Check if the token already exists
        $tokenAlreadyExists = false;
        foreach ($exitingTokens as $existingToken) {

            $token_data = $existingToken->get_data();
            if ($existingToken->get_token() == $token && $token_data['expiry_month'] == $expiryMonth && $token_data['expiry_year'] == $expiryYear ) {
                $tokenAlreadyExists = true;
                break;
            }
        }

        // The token already exists
        if ($tokenAlreadyExists) {
            return;
        }

        // Create the payment token
        $paymentToken = new WC_Payment_Token_CC();
        $paymentToken->set_token($token);
        $paymentToken->set_gateway_id($this->id);
        $paymentToken->set_card_type($params['cardType']);
        $paymentToken->set_last4($params['lastNumbers']);
        $paymentToken->set_expiry_month($expiryMonth);
        $paymentToken->set_expiry_year($expiryYear);
        $paymentToken->set_user_id($customerId);

        return $paymentToken->save();
    }

    /**
     * If the "Save the card" option is checked
     * Add the info to a meta key is saved as <payment_method>_allow_tokenization
     *
     * @param int $orderId
     * @return void
     */
    public function saveAllowTokenInformation($orderId, $original_id, $card_id) {

        // Retrieve card
        $card = $this->config->getCurrentCard( $original_id, $this->id, $card_id );

        if (!$this->config->allowOneClickPayment($card) || empty($_POST['payment_method'])) {
            return;
        }

        // Retrieve "save the card" checkbox value
        $allowTokenization = !empty($_POST['wc-' . $this->id . '-new-payment-method']);
        // Payment identifier
        $paymentMethod = !empty($original_id) ? $original_id : $this->id;

        // Add or reset the specific meta for the card id
        update_post_meta($orderId, $paymentMethod . '_allow_tokenization', $allowTokenization);
    }

    /**
     * Save the specific card/token id to use while creating the order
     * The meta key is saved as <payment_method>_card_id / <payment_method>_token_id
     *
     * @param int $orderId
     * @return void
     */
    public function savePaymentMethodCardOrTokenToForce($orderId, $tokenized_card_id, $original_id, $card_id) {

        if (empty($_POST['payment_method'])) {
            return;
        }

        // Payment identifier
        $paymentMethod = !empty($original_id) ? $original_id : $this->id;

        $order = wc_get_order($orderId);
        // Reset payment method to the original one
        $order->set_payment_method($paymentMethod);
        $order->save();

        // Reset any previous values
        update_post_meta($orderId, $paymentMethod . '_card_id', null);
        update_post_meta($orderId, $paymentMethod . '_token_id', null);

        // Retrieve card id
        if (!empty($card_id)) {
            $card = $this->config->getCard($paymentMethod, $card_id);
            if (!empty($card->id_card)) {
                // Add or reset the specific meta for the card id
                update_post_meta($orderId, $paymentMethod . '_card_id', $card->id_card);
            }
        }

        // Retrieve tokenized card id
        if (!empty($tokenized_card_id)) {
            $token = WC_Payment_Tokens::get($tokenized_card_id);
            if ($token !== null && $token->get_user_id() == get_current_user_id()) {
                // Add or reset the specific meta for the token card id
                update_post_meta($orderId, $paymentMethod . '_token_id', $tokenized_card_id);
            }
        }
    }

    /**
     * Allow retrieving of order status & URL to follow depending on the order state
     *
     * @return json
     */
    public function ajax_poll_order() {

        // Check submitted parameters & nonce
        if (empty($_POST['order_id'])) {
            wp_send_json_error();
        }
        $orderId = (int)$_POST['order_id'];
        check_ajax_referer($this->id . '-order-poll-' . $orderId, 'nonce');

        // Retrieve the order and check the payment method
        $order      = wc_get_order($orderId);
        $orderData  = $order->get_data();
        if (empty($orderData['payment_method']) || $orderData['payment_method'] != $this->id) {
            wp_send_json_error();
        }

        $redirectUrl    = null;
        $paymentExists  = (bool)$this->etransactions->hasOrderPayment($orderId);
        if (in_array($orderData['status'], array('failed', 'cancelled'))) {
            // Try to pay again
            $redirectUrl = $order->get_checkout_payment_url();
        } elseif ($paymentExists) {
            // Success page
            $redirectUrl = $order->get_checkout_order_received_url();
        }

        wp_send_json_success([
            'payment_exists'    => $paymentExists,
            'order_status'      => $orderData['status'],
            'redirect_url'      => $redirectUrl,
        ]);
    }

    /**
     * Hide 3x payment gateway, it will not be used for tokenization process
     * on add payment method page
     *
     * @param array $params
     * @return array
     */
    public function hide_payment_gateway($params) {

        if (is_add_payment_method_page() && isset($params['etransactions_3x'])) {
            unset($params['etransactions_3x']);
        }

        return $params;
    }

    /**
     * Add a message to the cart
     */
    public function addCartErrorMessage($message) {
        wc_add_notice($message, 'error');
    }

    /**
     * Add a note to the order
     */
    public function addOrderNote(WC_Order $order, $message) {
        $order->add_order_note($message);
    }

    /**
     * Add the payment gateway to Order
     */
    public function addOrderPayment(WC_Order $order, $type, array $data) {
        global $wpdb;

        $wpdb->insert($wpdb->prefix.'wc_etransactions_payment', array(
            'order_id' => $order->get_id(),
            'type' => $type,
            'data' => serialize($data),
        ));
    }

    /**
     * Get all log files
     */
    function getLogFiles() {

        $upload_dir = wp_upload_dir();
        $log_dir    = $upload_dir['basedir'] . '/wc-logs/';
        $files      = @scandir( $log_dir );
        $result     = array();

        if ( !empty( $files ) ) {

            rsort( $files );

            foreach ( $files as $file ) {

                if ( strstr( $file, 'wc-etransactions' ) ) {
                    array_push( $result, $file );
                }
            }
        }
        
        return $result;
    }

}