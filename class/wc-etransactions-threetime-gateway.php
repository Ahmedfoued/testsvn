<?php

/**
 * E-Transactions 3 times - Payment Gateway class.
 *
 * Extended by individual payment gateways to handle payments.
 *
 * @class   WC_E3_Gw
 * @extends WC_Etransactions_Abstract_Gateway
 */
class WC_E3_Gw extends WC_Etransactions_Abstract_Gateway {

    protected $defaultTitle;
    protected $defaultDesc;
    protected $type         = 'threetime';

    const REGEXP_DATE       = '/^([/d]{2})([/d]{2})([/d]{4})$/';
    const GROUP_DATE        = '$1/$2/$3';
    const FORMAT_PRICE_DATE = '%s (%s)';

    /**
     * The class constructor
     */
    public function __construct() {

        // Some properties
        $this->id                   = 'etransactions_3x';
        $this->method_title         = $this->title  = $this->defaultTitle = __('Up2pay e-Transactions Crédit Agricole 3 times', WC_ETRANSACTIONS_PLUGIN);
        $this->defaultDesc          = __('Choose your mean of payment directly on secured payment page of Credit Agricole', WC_ETRANSACTIONS_PLUGIN);
        $this->method_description   = __('Secured 3 times payment by Up2pay e-Transactions Crédit Agricole', WC_ETRANSACTIONS_PLUGIN);
        $this->has_fields           = false;
        $this->icon                 = plugin_dir_url(__DIR__) . 'cards/' . '3x.svg';

        parent::__construct();

        // Change title & description depending on the context
        if ( !is_admin() && $this->defaultConfig->getCurrentEnvMode( $this->plugin_id, $this->id ) == 'test' ) {
            /* Comment input test pbx_day (use field just to debug)*/
            //$this->description = apply_filters('description', '<input type="text" placeholder="1" name="test_pbx_days"><br /><strong>' . __('Test mode enabled - No debit will be made', WC_ETRANSACTIONS_PLUGIN) . '</strong><br /><br />' . $this->_config->getDescription());
            $this->description = apply_filters('description', '<strong>' . __('Test mode enabled - No debit will be made', WC_ETRANSACTIONS_PLUGIN) . '</strong><br /><br />' . $this->_config->getDescription());
        }
    }

    /**
     * Show row detail
     */
    private function _showDetailRow($label, $value) {

        return '<strong>'.$label.'</strong> '.$value;
    }

    /**
     * Check If The Gateway Is Available For Use
     *
     * @access public
     * @return bool
     */
    public function is_available() {

        if (!parent::is_available()) {
            return false;
        }

        $minimal = $this->_config->getAmount();
        if (empty($minimal)) {
            return true;
        }

        $total = WC()->cart->total;
        $minimal = floatval($minimal);

        return $total >= $minimal;
    }

    /**
     * Show payment details
     */
    public function showDetails($order) {

        $orderId = $order->get_id();
        $payment = $this->_etransactions->getOrderPayments($orderId, 'first_payment');

        if (empty($payment)) {
            return;
        }

        $data = unserialize($payment->data);
        $payment = $this->_etransactions->getOrderPayments($orderId, 'second_payment');
        if (!empty($payment)) {
            $second = unserialize($payment->data);
        }
        $payment = $this->_etransactions->getOrderPayments($orderId, 'third_payment');
        if (!empty($payment)) {
            $third = unserialize($payment->data);
        }

        $rows   = array();
        $rows[] = $this->_showDetailRow(__('Reference:', WC_ETRANSACTIONS_PLUGIN), $data['reference']);

        if (isset($data['ip'])) {
            $rows[] = $this->_showDetailRow(__('Country of IP:', WC_ETRANSACTIONS_PLUGIN), $data['ip']);
        }

        $rows[] = $this->_showDetailRow(__('Processing date:', WC_ETRANSACTIONS_PLUGIN), preg_replace(self::REGEXP_DATE, self::GROUP_DATE, $data['date'])." - ".$data['time']);

        if (isset($data['cardType'])) {
            $originalCardType = $cardType = strtoupper($data['cardType']);
            if (in_array($cardType, array('VISA', 'MASTERCARD', 'EUROCARD_MASTERCARD', 'CB'))) {
                $cardType = 'CB';
            }
            $rows[] = $this->_showDetailRow(__('Card type:', WC_ETRANSACTIONS_PLUGIN), '<img title="'. $originalCardType .'" alt="'. $originalCardType .'" src="' . apply_filters(WC_ETRANSACTIONS_PLUGIN, plugin_dir_url(__DIR__) . 'cards/') . $cardType . '.svg" onerror="this.onerror = null; this.src=\'' . apply_filters(WC_ETRANSACTIONS_PLUGIN, plugin_dir_url(__DIR__) . 'cards/') . $cardType . '.png\'" />');
        }

        if (isset($data['firstNumbers']) && isset($data['lastNumbers'])) {
            $rows[] = $this->_showDetailRow(__('Card numbers:', WC_ETRANSACTIONS_PLUGIN), $data['firstNumbers'].'...'.$data['lastNumbers']);
        }

        if (isset($data['validity'])) {
            $rows[] = $this->_showDetailRow(__('Validity date:', WC_ETRANSACTIONS_PLUGIN), preg_replace('/^([0-9]{2})([0-9]{2})$/', '$2/$1', $data['validity']));
        }

        // 3DS Version
        if (!empty($data['3ds']) && $data['3ds'] == 'Y') {
            $cc_3dsVersion = '1.0.0';
            if (!empty($data['3dsVersion'])) {
                $cc_3dsVersion = str_replace('3DSv', '', trim($data['3dsVersion']));
            }
            $rows[] = $this->_showDetailRow(__('3DS version:', WC_ETRANSACTIONS_PLUGIN), $cc_3dsVersion);
        }

        $date   = preg_replace(self::REGEXP_DATE, self::GROUP_DATE, $data['date']);
        $value  = sprintf(self::FORMAT_PRICE_DATE, wc_price($data['amount'] / 100.0, array('currency' => $order->get_currency())), $date);
        $rows[] = $this->_showDetailRow(__('First debit:', WC_ETRANSACTIONS_PLUGIN), $value);

        if (isset($second)) {
            $date = preg_replace(self::REGEXP_DATE, self::GROUP_DATE, $second['date']);
            $value = sprintf(self::FORMAT_PRICE_DATE, wc_price($second['amount'] / 100.0, array('currency' => $order->get_currency())), $date);
        } else {
            $value = __('Not achieved', WC_ETRANSACTIONS_PLUGIN);
        }

        $rows[] = $this->_showDetailRow(__('Second debit:', WC_ETRANSACTIONS_PLUGIN), $value);

        if (isset($third)) {
            $date = preg_replace(self::REGEXP_DATE, self::GROUP_DATE, $third['date']);
            $value = sprintf(self::FORMAT_PRICE_DATE, wc_price($third['amount'] / 100.0, array('currency' => $order->get_currency())), $date);
        } else {
            $value = __('Not achieved', WC_ETRANSACTIONS_PLUGIN);
        }
        $rows[] = $this->_showDetailRow(__('Third debit:', WC_ETRANSACTIONS_PLUGIN), $value);

        $rows[] = $this->_showDetailRow(__('Transaction:', WC_ETRANSACTIONS_PLUGIN), $data['transaction']);
        $rows[] = $this->_showDetailRow(__('Call:', WC_ETRANSACTIONS_PLUGIN), $data['call']);
        $rows[] = $this->_showDetailRow(__('Authorization:', WC_ETRANSACTIONS_PLUGIN), $data['authorization']);

        echo '<h4>'.__('Payment information', WC_ETRANSACTIONS_PLUGIN).'</h4>';
        echo '<p>'.implode('<br/>', $rows).'</p>';
    }
}
