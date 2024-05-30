<?php

/**
 * E-Transactions - Configuration class.
 *
 * @class   WC_Etransactions_Config
 */
class WC_Etransactions_Config {

    use WC_Etransactions_Config_Get_Options;
    use WC_Etransactions_Config_Cards;

    public $_values;
    public $encryption;
    public $paymentType;
    private $_defaults = array(
        'icon'                      => 'logo.png',
        'amount'                    => '',
        'min_amount'                => '',
        'max_amount'                => '',
        'fees_management'           => '',
        'debug'                     => 'no',
        'enabled'                   => 'yes',
        'delay'                     => 0,
        'capture_order_status'      => 'wc-processing',
        'payment_ux'                => 'redirect',
        'allow_one_click_payment'   => 'no',
        'display_generic_method'    => 'no',
        'environment'               => 'TEST',
        'hmackey'                   => '4642EDBBDFF9790734E673A9974FC9DD4EF40AA2929925C40B3A95170FF5A578E7D2579D6074E28A78BD07D633C0E72A378AD83D4428B0F3741102B69AD1DBB0',
        'subscription'              => 1,
        'identifier'                => 3262411,
        'ips'                       => '194.2.122.190,195.25.67.22',
        'rank'                      => 95,
        'site'                      => 9999999,
        '3ds_exemption_max_amount'  => '',
        'title_sof3x'               => 'Pay in 3xCB with fees',
        'title_sof3xsf'             => 'Pay in 3CB without fees'
    );

    /**
     * Custom delay value to capture on a specific order status
     */
    const ORDER_STATE_DELAY = 9999;

    /**
     * Identifier for an Access subscription (default)
     */
    const ACCESS_SUBSCRIPTION = 1;

    /**
     * Identifier for a Premium subscription
     */
    const PREMIUM_SUBSCRIPTION = 2;

    /**
     * Identifier for default payment UX (redirect)
     */
    const PAYMENT_UX_REDIRECT = 'redirect';

    /**
     * Identifier for Seamless payment UX (iframe)
     */
    const PAYMENT_UX_SEAMLESS = 'seamless';

    /**
     * Class constructor
     */
    public function __construct( array $values, $defaultTitle, $defaultDesc, $paymentType ) {

        $this->_values                      = $values;
        $this->_defaults['title']           = $defaultTitle;
        $this->_defaults['description']     = $defaultDesc;
        $this->encryption                   = new ET_ransactions_Encrypt();
        $this->paymentType                  = $paymentType;

        if ( $paymentType === 'threetime' || $paymentType == 'threetime_sofinco' ) {
            $this->_defaults['subscription'] = '2';
        }
    }

    /**
     * Retrieve all settings by using defined or default value
     *
     * @return array
     */
    public function getFields() {

        $settings = array();

        foreach (array_keys($this->_defaults) as $configKey) {
            $settings[$configKey] = $this->_getOption($configKey);
        }

        return $settings;
    }

    /**
     * Retrieve the payment UX information from the global configuration or forced card
     *
     * @param WC_Order $order
     * @return string
     */
    public function getPaymentUx(WC_Order $order = null) {

        // Force redirect method for 3x payment method
        if ($this->isThreeTimePayment()) {
            return self::PAYMENT_UX_REDIRECT;
        }

        // Default behaviour for "add payment method" page
        if (is_add_payment_method_page()) {
            return $this->getDefaultOption('payment_ux');
        }

        if (empty($order)) {
            return $this->_getOption('payment_ux');
        }

        // If a specific card type is used, check the payment UX on the card
        $card = $this->getOrderCard($order);

        // Check if we have a tokenized card for this order
        if (empty($card)) {
            $tokenizedCard = $this->getTokenizedCard($order);
            if (!empty($tokenizedCard)) {
                // Look for an existing card using card_type
                $ccList = array(
                    'CB',
                    'VISA',
                    'E_CARD',
                    'EUROCARD_MASTERCARD',
                    'MASTERCARD',
                    'MAESTRO',
                );
                $cardType = strtoupper($tokenizedCard->get_card_type());
                if (in_array($cardType, $ccList)) {
                    $cardType = 'CB';
                }
                // Retrieve the card, if any
                $card = $this->getCardByType($tokenizedCard->get_gateway_id(), $cardType);
            }
        }

        if (empty($card)) {
            // Force redirect method for generic payment
            return self::PAYMENT_UX_REDIRECT;
        }

        if (!empty($card->user_xp)) {
            return $card->user_xp;
        }

        // The card itself does not allow iframe, force redirect in this case
        if (!empty($card->id_card) && empty($card->allow_iframe)) {
            return self::PAYMENT_UX_REDIRECT;
        }

        return $this->_getOption('payment_ux');
    }

    /**
     * Retrieve the "allow one-click" payments
     *
     * @param object|null $card
     * @return bool
     */
    public function allowOneClickPayment($card = null) {

        // Disable one click payment for 3x payment method
        if ($this->isThreeTimePayment()) {
            return false;
        }

        // Disable one-click payment for all cards that aren't managing tokenization
        // Disable for the generic method too
        if (empty($card) || !in_array($card->type_card, $this->getTokenizableCards())) {
            return false;
        }

        return $this->isPremium() && in_array($this->_getOption('allow_one_click_payment'), array('yes', '1'));
    }

    /**
     * Retrieve the system urls
     */
    public function getSystemUrls(WC_Order $order = null) {

        if ($this->isProduction()) {
            return $this->getSystemProductionUrls($order);
        }

        return $this->getSystemTestUrls($order);
    }

    /**
     * Retrieve the direct urls
     */
    public function getDirectUrls() {

        if ($this->isProduction()) {
            return $this->getDirectProductionUrls();
        }

        return $this->getDirectTestUrls();
    }


    /**
     * Reteieve the debug mode
     */
    public function isDebug() {

        return $this->_getOption('debug') === 'yes';
    }

    /**
     * Getter for display_generic_method option
     *
     * @return bool
     */
    public function allowDisplayGenericMethod() {

        // Force generic payment for 3x payment method
        if ($this->isThreeTimePayment()) {
            return true;
        }

        return $this->_getOption('display_generic_method') === 'yes';
    }

    /**
     * Retrieve the production mode
     */
    public function isProduction() {

        return $this->_getOption('environment') === 'PRODUCTION';
    }

    /**
     * Retrieve the subscription type
     */
    public function isPremium() {

        return ($this->getSubscription() == WC_Etransactions_Config::PREMIUM_SUBSCRIPTION);
    }

    /**
     * Check if the current config is related to threetime method
     *
     * @return boolean
     */
    protected function isThreeTimePayment() {
        
        return $this->paymentType == 'threetime' || $this->paymentType == 'threetime_sofinco';
    }

    /**
     * Retrieve current environment mode (production / test)
     *
     * @return string
     */
    public function getCurrentEnvMode( $plugin_id, $id ) {

        // Use current defined mode into the global configuration
        if (!empty(get_option($plugin_id . $id . '_env')) && in_array(get_option($plugin_id . $id . '_env'), array('TEST', 'PRODUCTION'))) {
            return strtolower(get_option($plugin_id . $id . '_env'));
        }

        // Use the default mode from WC_Etransactions_Config
        // $defaults = $this->defaultConfig->getDefaults();
        $defaults = $this->getDefaults();

        return strtolower($defaults['environment']);
    }

    /**
     * Retrieve current configuration mode (production / test)
     *
     * @return string
     */
    public function getCurrentConfigMode( $plugin_id, $id ) {

        // Check previous configuration mode before computing the option key (upgrade case)
        $settings = get_option($plugin_id . $id . '_settings');
        if (get_option($plugin_id . $id . '_env') === false && !empty($settings['environment'])) {
            update_option($plugin_id . $id . '_env', $settings['environment']);
            unset($settings['environment']);
            update_option($plugin_id . $id . '_settings', $settings);
        }

        // Use current defined mode into the URL (only if request is from admin)
        if (is_admin() && !empty($_GET['config_mode']) && in_array($_GET['config_mode'], array('test', 'production'))) {
            return $_GET['config_mode'];
        }

        // Use current defined mode into the global configuration
        if (!empty(get_option($plugin_id . $id . '_env')) && in_array(get_option($plugin_id . $id . '_env'), array('TEST', 'PRODUCTION'))) {
            return strtolower(get_option($plugin_id . $id . '_env'));
        }

        // Use the default mode from WC_Etransactions_Config
        $defaults = $this->getDefaults();

        return $defaults['environment'];
    }

    /**
     * Retrieve specific fields, dedicated to environment
     *
     * @return array
     */
    public function get_payment_mode_fields() {

        $defaults = $this->getDefaults();

        return array(
            'environment' => array(
                'title' => __('Current shop environment mode', WC_ETRANSACTIONS_PLUGIN),
                'type' => 'select',
                // 'description' => __('In test mode your payments will not be sent to the bank.', WC_ETRANSACTIONS_PLUGIN),
                'options' => array(
                    'PRODUCTION' => __('Production', WC_ETRANSACTIONS_PLUGIN),
                    'TEST' => __('Test (no debit)', WC_ETRANSACTIONS_PLUGIN),
                ),
                'default' => $defaults['environment'],
            ),
        );
    }

    /**
     * Check the current context so allow/disallow a specific display action
     *
     * @return bool
     */
    public function allowDisplay( $id ) {

        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        // Prevent display on others pages than setting, and if the current id isn't the one we are trying to configure
        if (
            !is_object($screen)
            || empty($screen->id)
            || $screen->id != 'woocommerce_page_wc-settings'
            || empty($_GET['section'])
            || $id != $_GET['section']
        ) {
            return false;
        }

        return true;
    }

     /**
     * Retrieve current card to be used
     *
     * @return object|null
     */
    public function getCurrentCard( $original_id, $id, $card_id ) {

        // Payment identifier
        $paymentMethod = !empty($original_id) ? $original_id : $id;

        // Retrieve card id
        $card = null;
        if (!empty($card_id)) {
            $card = $this->getCard($paymentMethod, $card_id);
        }

        return $card;
    }

    /**
     * Retrieve all return URL
     *
     * @param string $suffix
     * @param WC_Order $order
     * @return array
     */
    public function getReturnUrls($class, $suffix = '', $order = null) {

        $pbxAnnule = null;
        if (!empty($order)) {
            $pbxAnnule = $order->get_checkout_payment_url();
        }

        if (!is_multisite()) {
            return array(
                'PBX_ANNULE' => (!empty($pbxAnnule) ? $pbxAnnule : add_query_arg('status', 'cancel' . $suffix, add_query_arg('wc-api', get_class($class), get_permalink()))),
                'PBX_EFFECTUE' => add_query_arg('status', 'success' . $suffix, add_query_arg('wc-api', get_class($class), get_permalink())),
                'PBX_REFUSE' => add_query_arg('status', 'failed' . $suffix, add_query_arg('wc-api', get_class($class), get_permalink())),
                'PBX_REPONDRE_A' => add_query_arg('status', 'ipn' . $suffix, add_query_arg('wc-api', get_class($class), get_permalink())),
            );
        }

        return array(
            'PBX_ANNULE' => (!empty($pbxAnnule) ? $pbxAnnule : add_query_arg(array(
                'wc-api' => get_class($class),
                'status' => 'cancel' . $suffix,
            ), trailingslashit(site_url()))),
            'PBX_EFFECTUE' => add_query_arg(array(
                'wc-api' => get_class($class),
                'status' => 'success' . $suffix,
            ), trailingslashit(site_url())),
            'PBX_REFUSE' => add_query_arg(array(
                'wc-api' => get_class($class),
                'status' => 'failed' . $suffix,
            ), trailingslashit(site_url())),
            'PBX_REPONDRE_A' => add_query_arg(array(
                'wc-api' => get_class($class),
                'status' => 'ipn' . $suffix,
            ), trailingslashit(site_url())),
        );
    }

}
