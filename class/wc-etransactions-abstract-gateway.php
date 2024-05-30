<?php

/**
 * E-Transactions - Payment Gateway class.
 *
 * Extended by individual payment gateways to handle payments.
 *
 * @class   WC_Etransactions_Abstract_Gateway
 * @extends WC_Payment_Gateway
 */
abstract class WC_Etransactions_Abstract_Gateway extends WC_Payment_Gateway {

    protected $_config;
    protected $_etransactions;
    protected $display;
    protected $callbacks;
    protected $defaultConfig;
    protected $encryption;
    protected $defaultTitle;
    protected $defaultDesc;
    protected $type;
    protected $commonDescription;
    protected $originalTitle;
    protected $original_id;
    protected $card_id;
    protected $tokenized_card_id;

    /**
     * @var WC_Etransactions_Abstract_Gateway
     */
    private static $pluginInstance = array();

    /**
     * Returns payment gateway instance
     *
     * @return WC_Etransactions_Abstract_Gateway
     */
    public static function getInstance($class) {

        if (empty(self::$pluginInstance[$class])) {
            self::$pluginInstance[$class] = new static();
        }

        return self::$pluginInstance[$class];
    }

    /**
     * The class constructor
     */
    public function __construct() {
        global $wp;

        // Load the settings
        $this->defaultConfig        = new WC_Etransactions_Config(array(), $this->defaultTitle, $this->defaultDesc, $this->type);
        $this->encryption           = new ET_ransactions_Encrypt();
        $this->init_settings();
        $this->_config              = new WC_Etransactions_Config($this->settings, $this->defaultTitle, $this->defaultDesc, $this->type);
        $this->_etransactions       = new WC_Etransactions($this->_config);
        $this->display              = new WC_Etransactions_Display($this->_config, $this->defaultConfig, $this->_etransactions, $this->plugin_id, $this->id );
        $this->callbacks            = new WC_Etransactions_Callbacks($this->_config, $this->defaultConfig, $this->_etransactions, $this->plugin_id, $this->id );

        $this->description          = apply_filters('description', $this->_config->getDescription());
        $this->commonDescription    = '';

        // Change title & description depending on the context
        if ( !is_admin() && $this->defaultConfig->getCurrentEnvMode( $this->plugin_id, $this->id ) == 'test' ) {

            $this->title                = apply_filters('title', $this->_config->getTitle() . ' (' . __('TEST MODE', WC_ETRANSACTIONS_PLUGIN) . ')');
            $this->description          = apply_filters('description', '<strong>' . __('Test mode enabled - No debit will be made', WC_ETRANSACTIONS_PLUGIN) . '</strong><br /><br />' . $this->_config->getDescription());
            $this->commonDescription    = apply_filters('description', '<strong>' . __('Test mode enabled - No debit will be made', WC_ETRANSACTIONS_PLUGIN) . '</strong><br /><br />');
        }

        $this->description = $this->addFieldVerificationPhone($this->description);

        // Handle specific payment gateway features for Premium subscription
        if ( $this->_config->isPremium() ) {
            $this->supports = array(
                'refunds',
                'tokenization',
                'add_payment_method',
            );
            // Set has fields to true, allow display of checkbox even if description is empty
            $this->has_fields = true;
        }

        // Prevent cart to be cleared when the customer is getting back after an order cancel
        $orderId    = isset($wp->query_vars) && is_array($wp->query_vars) && isset($wp->query_vars['order-received']) ? absint($wp->query_vars['order-received']) : 0;
        if ( !empty($orderId) && isset($_GET['key']) && !empty($_GET['key']) ) {

            // Retrieve order key and order object
            $orderKey   = wp_unslash($_GET['key']);
            $order      = wc_get_order($orderId);

            // Compare order id, hash and payment method
            if ($orderId === $order->get_id()
                && hash_equals($order->get_order_key(), $orderKey) && $order->needs_payment()
                && $order->get_payment_method() == $this->id
            ) {
                // Prevent wc_clear_cart_after_payment to run in this specific case
                remove_action('get_header', 'wc_clear_cart_after_payment');
                // WooCommerce 6.4.0
                remove_action('template_redirect', 'wc_clear_cart_after_payment', 20);
            }
        }
    }

    /**
     * Register some hooks
     *
     * @return void
     */
    public function initHooksAndFilters() {

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this->callbacks, 'api_call'));
        add_action('admin_notices', array($this, 'display_custom_admin_notices'));
        add_action('admin_enqueue_scripts', array($this->display, 'load_custom_admin_assets'));
        add_action( 'woocommerce_checkout_create_order', array($this, 'save_order_meta_data'), 10, 2 );

        // Call to detect change on order state (seamless transactions)
        add_action('wc_ajax_' . $this->id . '_order_poll', array($this->callbacks, 'ajax_poll_order'));

        if ($this->_config->isPremium()) {
            // Hide payment gateway in some specific cases
            add_filter('woocommerce_available_payment_gateways', array($this->callbacks, 'hide_payment_gateway'), 10);
            add_filter('woocommerce_before_account_payment_methods', array($this->display, 'load_custom_front_assets'));
            add_filter('woocommerce_before_account_payment_methods', array($this->display, 'load_custom_front_assets_redirection'));

            // Capture on a specific order state
            if ($this->_config->getDelay() == WC_Etransactions_Config::ORDER_STATE_DELAY) {
                $orderStatus = str_replace('wc-', '', $this->_config->getCaptureOrderStatus());
                add_action('woocommerce_order_status_' . $orderStatus, array($this, 'process_order_status_changed'));
            }

            // Cards managements
            add_filter('woocommerce_available_payment_gateways', array($this, 'display_tokens_as_payment_gateways'));
        }

        // Hide main payment method if enabled into plugin configuration
        add_filter('woocommerce_available_payment_gateways', array($this, 'hide_main_payment_gateway'), 99999);
        add_filter('woocommerce_review_order_before_payment', array($this->display,'load_phone_front_assets'));
        add_filter('wc_ajax_update_order_review', array($this->display,'load_phone_front_assets'));

        // Handle display of forced cards
        if (!empty($this->_config->getCards($this->defaultConfig->getCurrentEnvMode( $this->plugin_id, $this->id ), $this->id, true))) {
            add_filter('woocommerce_available_payment_gateways', array($this, 'display_custom_cards_as_payment_gateways'));
        }
    }

    /**
     * Save order meta data
     */
    public function save_order_meta_data( $order, $data ) {

        if ( $this->id === $data['payment_method'] && isset($_POST['test_pbx_days']) ) {
            $order->update_meta_data( '_test_pbx_days', sanitize_text_field( $_POST['test_pbx_days'] ) );
        }

        $order->update_meta_data ('_up2payphone', sanitize_text_field($_POST['Up2Pay_phone']));
        $order->update_meta_data ('_up2paycountrycode', sanitize_text_field($_POST['Up2Pay_countrycode']));
    }

    /**
     * Build the parameters and redirect the customer to the payment page
     * to proceed the "Add payment method" action
     *
     * @return void
     */
    public function add_payment_method() {

        if (empty($_POST['payment_method'])) {
            return;
        }

        // Payment identifier
        $paymentMethod = !empty($this->original_id) ? $this->original_id : $this->id;

        // Retrieve card id
        $card = null;
        if (!empty($this->card_id)) {
            $card = $this->_config->getCard($paymentMethod, $this->card_id);
        }

        $urls   = $this->_config->getReturnUrls($this, '-tokenization');
        $params = $this->_etransactions->buildTokenizationSystemParams($card, $urls);

        try {
            $url = $this->_etransactions->getSystemUrl();
        } catch (Exception $e) {
            $message = sprintf( "WC_Etransactions_Abstract_Gateway::add_payment_method: %s", $e );
            wc_etransactions_add_log( $message, $this->_config->isDebug() );
            wc_add_notice($e->getMessage(), 'error');
            return;
        }

        wp_redirect(esc_url($url) . '?' . http_build_query($params));
    }

    /**
     * Process the payment, redirecting user to E-Transactions.
     *
     * @param int $order_id The order ID
     * @return array TODO
     */
    public function process_payment($orderId) {

        $order = wc_get_order($orderId);

        $tokenized_card_id  = isset($this->tokenized_card_id) ? $this->tokenized_card_id : '';
        $original_id        = isset($this->original_id) ? $this->original_id : '';
        $card_id            = isset($this->card_id) ? $this->card_id : '';

        // Save the specific card/token id to use while creating the order
        $this->callbacks->savePaymentMethodCardOrTokenToForce($orderId, $tokenized_card_id, $original_id, $card_id);

        // Save the checkbox state for "Save payment method"
        $this->callbacks->saveAllowTokenInformation($orderId, $original_id, $card_id);

        $message = __('Customer is redirected to E-Transactions payment page', WC_ETRANSACTIONS_PLUGIN);
        $this->callbacks->addOrderNote($order, $message);

        return array(
            'result' => 'success',
            'redirect' => add_query_arg('order-pay', $order->get_id(), add_query_arg('key', $order->get_order_key(), $order->get_checkout_order_received_url())),
        );
    }

    /**
     * save_hmackey
     * Used to save the settings field of the custom type HSK
     * @param  array $field
     * @return void
     */
    public function process_admin_options() {

        // Handle encrypted fields
        foreach (array('hmackey') as $field) {
            $_POST[$this->plugin_id . $this->id . '_' . $field] = $this->encryption->encrypt($_POST[$this->plugin_id . $this->id . '_' . $field]);
        }

        // Handle environment config data separately
        if (isset($_POST[$this->plugin_id . $this->id . '_environment'])
        && in_array($_POST[$this->plugin_id . $this->id . '_environment'], array('TEST', 'PRODUCTION'))) {
            update_option($this->plugin_id . $this->id . '_env', $_POST[$this->plugin_id . $this->id . '_environment']);
            unset($_POST[$this->plugin_id . $this->id . '_environment']);
        }

        // Handle cards update
        if ($this->type != 'threetime' && $this->type != 'threetime_sofinco') {
            foreach ($this->_config->getCards($this->defaultConfig->getCurrentConfigMode($this->plugin_id, $this->id), $this->id, false) as $card) {
                if (!isset($_POST[$this->plugin_id . $this->id . '_card-' . (int)$card->id_card . '-ux'])) {
                    continue;
                }
                $cardUpdateData = array(
                    'user_xp' => !empty($_POST[$this->plugin_id . $this->id . '_card-' . (int)$card->id_card . '-ux']) ? $_POST[$this->plugin_id . $this->id . '_card-' . (int)$card->id_card . '-ux'] : null,
                    'force_display' => (int)(!empty($_POST[$this->plugin_id . $this->id . '_card-' . (int)$card->id_card . '-force-display']) && $_POST[$this->plugin_id . $this->id . '_card-' . (int)$card->id_card . '-force-display'] == 'on'),
                );
                $this->_config->updateCard($card, $cardUpdateData);
            }
        }

        parent::process_admin_options();
    }

    /**
     * Retrieve form fields for the gateway plugin
     *
     * @return array
     */
    public function get_form_fields() {

        $fields = parent::get_form_fields();

        if ( $this->display instanceof WC_Etransactions_Display ) {
            $fields += $this->display->getGlobalConfigurationFields();
            $fields += $this->display->getAccountConfigurationFields();
            $fields += $this->display->getCardsConfigurationFields();
        }

        return $fields;
    }

    /**
     * Display the save payment method checkbox
     *
     * @see WC_Payment_Gateway::payment_fields
     * @return void
     */
    public function payment_fields() {

        parent::payment_fields();

        // Do not display "save the card" card checkbox on add payment method page
        if (is_add_payment_method_page()) {
            return;
        }

        // Retrieve current card
        $original_id    = isset($this->original_id) ? $this->original_id : '';
        $card_id        = isset($this->card_id) ? $this->card_id : '';
        $card = $this->_config->getCurrentCard( $original_id, $this->id, $card_id );

        // Display checkbox if enabled into configuration
        if ($this->_config->allowOneClickPayment($card) && empty($this->tokenized_card_id)) {
            $this->save_payment_method_checkbox();
        }
    }

    /**
     * Display admin options
     */
    public function admin_options() {

        $log_files = $this->callbacks->getLogFiles();

        $this->settings['hmackey'] = $this->_config->getHmacKey();

        ?>
            <script>
                var pbxUrl = <?= json_encode(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id)) ?>;
                var pbxConfigModeMessage = <?= json_encode(__('Do you really want to change the current shop environment mode?', WC_ETRANSACTIONS_PLUGIN)) ?>;
                var pbxGatewayId = <?= json_encode($this->id) ?>;
                var pbxOrderStateDelay = <?= json_encode(WC_Etransactions_Config::ORDER_STATE_DELAY) ?>;
                var pbxCurrentSubscription = <?= json_encode($this->_config->getSubscription()) ?>;
                var pbxPremiumSubscriptionId = <?= json_encode(WC_Etransactions_Config::PREMIUM_SUBSCRIPTION) ?>;
                var pbxPremiumSubscriptionFields = <?= json_encode(array('capture_order_status','allow_one_click_payment',)) ?>;
            </script>

            <div id="pbx-plugin-configuration">
                <div class="pbx-flex-container">
                    <div class="pbx-plugin-info">
                        <div class="pbx-plugin-info__image"></div>
                        <div class="pbx-plugin-info__guide">
                            <div class="pbx-plugin-info__guide__left"><?= file_get_contents( WC_ETRANSACTIONS_PLUGIN_PATH . 'images/question-circle.svg' ); ?></div>
                            <div class="pbx-plugin-info__guide__right">
                                <p><b><?= __( 'Do you have a question?', WC_ETRANSACTIONS_PLUGIN ); ?></b></p>
                                <p class="pbx-plugin-info__guide__right__link"><?=__('Contact us using',WC_ETRANSACTIONS_PLUGIN); ?> <a href="mailto:support@e-transactions.fr"><?= __( 'this link', WC_ETRANSACTIONS_PLUGIN ); ?></a></p>
                                <?php
		                            $current_language       = substr( get_locale(), 0, 2 );
		                            $header_download_pdf    = file_exists( WC_ETRANSACTIONS_PLUGIN_PATH . 'pdf/readme_'.$current_language.'.pdf' ) ? WC_ETRANSACTIONS_PLUGIN_PATH . 'public/assets/pdf/readme_'.$current_language.'.pdf' : WC_ETRANSACTIONS_PLUGIN_PATH . 'public/assets/pdf/readme_en.pdf';
                                ?>
                                <a style="display: none;" class="pbx-plugin-info__guide__right__download" href="<?php echo $header_download_pdf; ?>" download><?php _e( 'Download User guide', WC_ETRANSACTIONS_PLUGIN ); ?></a>
                            </div>
                        </div>
                    </div>
                    <div id="pbx-current-mode-selector" class="pbx-current-mode-<?= $this->defaultConfig->getCurrentEnvMode( $this->plugin_id, $this->id ); ?>">
                        <table class="form-table">
                            <?= $this->generate_settings_html($this->defaultConfig->get_payment_mode_fields()); ?>
                        </table>
                        <?php if ( !empty( $log_files ) ): ?>
                            <table class="form-table">
                                <tr>
                                    <th><?php _e( 'Logs files', WC_ETRANSACTIONS_PLUGIN ); ?></th>
                                    <td>
                                        <select id="JS-WC-select-log-file">
                                            <?php foreach( $log_files as $log_file ): ?>
                                                <option value="<?php echo esc_attr( $log_file); ?>"><?php echo esc_html($log_file); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span>
                                            <a id="JS-WC-button-dwn-log-file" href="javascript:void(0)" data-href="<?php echo isset($log_files[0]) ? esc_attr( $log_files[0]) : ''; ?>">
                                                <?php _e( 'Download', WC_ETRANSACTIONS_PLUGIN ); ?>
                                                <span class="spinner" style="margin: 0;"></span>
                                            </a>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="clear"></div>

                <div class="pbx-current-config-mode pbx-current-config-mode-<?= $this->defaultConfig->getCurrentConfigMode($this->plugin_id, $this->id) ?>">
                    <span class="dashicons dashicons-<?= ($this->defaultConfig->getCurrentConfigMode($this->plugin_id, $this->id) == 'test' ? 'warning' : 'yes-alt') ?>"></span>
                    <?= sprintf(__('You are currently editing the <strong><u>%s</u></strong> configuration', WC_ETRANSACTIONS_PLUGIN), $this->defaultConfig->getCurrentConfigMode($this->plugin_id, $this->id)); ?>
                    <span class="dashicons dashicons-<?= ($this->defaultConfig->getCurrentConfigMode($this->plugin_id, $this->id) == 'test' ? 'warning' : 'yes-alt') ?>"></span>
                    <br /><br />
                    <a href="<?= admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id) ?>&config_mode=<?= ($this->defaultConfig->getCurrentConfigMode($this->plugin_id, $this->id) == 'production' ? 'test' : 'production') ?>">
                        <?= sprintf(__('=> Click here to switch to the <strong>%s</strong> configuration', WC_ETRANSACTIONS_PLUGIN), ($this->defaultConfig->getCurrentConfigMode($this->plugin_id, $this->id) == 'production' ? 'test' : 'production')); ?>
                    </a>
                </div>

                <h2 id="pbx-tabs" class="nav-tab-wrapper">
                    <a href="#pbx-pbx-account-configuration" class="nav-tab nav-tab-active">
                        <?= __('My account', WC_ETRANSACTIONS_PLUGIN); ?>
                    </a>
                    <a href="#pbx-global-configuration" class="nav-tab">
                        <?= __('Global configuration', WC_ETRANSACTIONS_PLUGIN); ?>
                    </a>
                    <?php if ($this->type != 'threetime' && $this->type != 'threetime_sofinco') : ?>
                        <a href="#pbx-cards-configuration" class="nav-tab">
                            <?= __('Means of payment configuration', WC_ETRANSACTIONS_PLUGIN); ?>
                        </a>
                    <?php endif; ?>
                </h2>
                <div id="pbx-pbx-account-configuration" class="tab-content tab-active">
                    <table class="form-table">
                        <?= $this->generate_settings_html($this->display->getAccountConfigurationFields()); ?>
                    </table>
                </div>
                <div id="pbx-global-configuration" class="tab-content">
                    <table class="form-table">
                        <?= $this->generate_settings_html($this->display->getGlobalConfigurationFields()); ?>
                    </table>
                </div>
                <?php if ($this->type != 'threetime') : ?>
                    <div id="pbx-cards-configuration" class="tab-content">
                        <table class="form-table">
                            <?php $this->generate_settings_html($this->display->getCardsConfigurationFields()); ?>
                        </table>
                        <?php $this->display->displayMethods(); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php
    }

    /**
     * Handle custom config key for test / production settings
     *
     * @return string
     */
    public function get_option_key() {

        // Inherit settings from the previous version
        if ($this->defaultConfig->getCurrentConfigMode($this->plugin_id, $this->id) != 'production') {
            return $this->plugin_id . $this->id . '_' .  $this->defaultConfig->getCurrentConfigMode($this->plugin_id, $this->id) . '_settings';
        }

        return parent::get_option_key();
    }

    /**
     * Init payment gateway settings + separately handle environment
     *
     * @return void
     */
    public function init_settings() {

        parent::init_settings();

        // Set default env if not exists (upgrade / new install cases for example)
        if (empty($this->settings['environment'])) {
            $defaults = $this->defaultConfig->getDefaults();
            $this->settings['environment'] = $defaults['environment'];
        }

        // Set custom setting for environment (global to any env)
        if (get_option($this->plugin_id . $this->id . '_env') === false && !empty($this->settings['environment'])) {
            update_option($this->plugin_id . $this->id . '_env', $this->settings['environment']);
            unset($this->settings['environment']);
            update_option($this->get_option_key(), $this->settings);
        }

        // Module upgrade case, copy same settings on test env
        if (get_option($this->plugin_id . $this->id . '_settings') !== false && get_option($this->plugin_id . $this->id . '_test_settings') === false) {
            // Apply the same configuration on test vs production
            $testConfiguration = get_option($this->plugin_id . $this->id . '_settings');
            $testConfiguration['environment'] = 'TEST';
            update_option($this->plugin_id . $this->id . '_test_settings', $testConfiguration);
        }

        // Define the current environment
        $this->settings['environment'] = get_option($this->plugin_id . $this->id . '_env');

        $this->_config = new WC_Etransactions_Config($this->settings, $this->defaultTitle, $this->defaultDesc, $this->type);
        $this->settings = $this->_config->getFields();
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
        $maximal = $this->_config->getMaxAmount();

        if (empty($minimal) && empty($maximal)) {
            return true;
        }

        // Retrieve total from cart, or order
        $total = null;
        if (is_checkout_pay_page() && get_query_var('order-pay')) {
            $order = wc_get_order((int)get_query_var('order-pay'));
            if (!empty($order)) {
                $total = $order->get_total();
            }
        } elseif (WC()->cart) {
            $total = WC()->cart->total;
        }

        if ($total === null) {
            // Unable to retrieve order/cart total
            return false;
        }

        if ( empty($maximal) ) {
            return $total >= $minimal;
        } else {
            return $total >= $minimal && $total <= $maximal;
        }

    }

    /**
     * Output for the order received page.
     */
    public function receipt_page($orderId) {

        $order = wc_get_order($orderId);
        $urls = $this->_config->getReturnUrls($this,'', $order);

        $mode = $this->_config->getCurrentConfigMode($this->plugin_id,$this->id);

        $params = $this->_etransactions->buildSystemParams($order, $this->type, $urls, $mode);
        wc_etransactions_add_log( sprintf( "WC_Etransactions_Abstract_Gateway::receipt_page params order(%s): %s", $orderId, json_encode($params) ), $this->_config->isDebug() );

        try {
            $url = $this->_etransactions->getSystemUrl($order);
        } catch (Exception $e) {
            wc_etransactions_add_log( sprintf( "WC_Etransactions_Abstract_Gateway::receipt_page: %s", $e ), $this->_config->isDebug() );
            echo "<p>" . $e->getMessage() . "</p>";
            echo "<form><center><button onClick='history.go(-1);return true;'>" . __('Back...', WC_ETRANSACTIONS_PLUGIN) . "</center></button></form>";
            exit;
        }

        // Output the payment form or iframe if seemsless is enabled
        $this->display->outputPaymentForm($order, $url, $params);
    }

    /**
     * Used to display some specific notices regarding the current gateway env
     *
     * @return void
     */
    public function display_custom_admin_notices() {

        static $displayed = false;

        // HMAC or WooCommerce alerts
        if (wooCommerceActiveETwp()) {
            $encryption = $this->encryption->decrypt($this->settings['hmackey']);
            if ($this->defaultConfig->allowDisplay($this->id) && !$encryption) {
                echo "<div class='notice notice-error is-dismissible'>
                <p><strong>/!\ Attention ! plugin " . $this->get_title() . " (" . $this->defaultConfig->getCurrentConfigMode($this->plugin_id,$this->id) . ") : </strong>" . __('HMAC key cannot be decrypted please re-enter or reinitialise it.', WC_ETRANSACTIONS_PLUGIN) . "</p>
                </div>";
            }
        } else {
            echo "<div class='notice notice-error is-dismissible'>
            <p><strong>/!\ Attention ! plugin E-Transactions : </strong>" . __('Woocommerce is not active !', WC_ETRANSACTIONS_PLUGIN) . "</p>
            </div>";
        }

        if (!$this->defaultConfig->allowDisplay($this->id) || $displayed) {
            return;
        }

        // Display alert banner if the extension is into TEST mode
        if (get_option($this->plugin_id . $this->id . '_env') == 'TEST') {
            $displayed = true;
            
            ?>
                <div id="pbx-alert-mode" class="pbx-alert-box notice notice-warning notice-alt">
                    <div class="dashicons dashicons-warning"></div>
                    <div class="pbx-alert-box-content">
                        <strong class="pbx-alert-title"><?= __('Test mode enabled', WC_ETRANSACTIONS_PLUGIN); ?></strong>
                        <?= __('No debit will be made', WC_ETRANSACTIONS_PLUGIN); ?>
                    </div>
                    <div class="dashicons dashicons-warning"></div>
                </div>
            <?php
        }
    }

    /**
     * Triggered when the order status is changed
     */
    public function process_order_status_changed($orderId) {

        $order      = wc_get_order($orderId);
        $orderData  = $order->get_data();

        if (empty($orderData['payment_method']) || $orderData['payment_method'] != $this->id) {
            return;
        }

        // Check if the order has already been captured
        $orderPayment = $this->_etransactions->getOrderPayments($orderId, 'capture');
        if (!empty($orderPayment->data)) {
            return;
        }

        // Retrieve the current authorization infos
        $orderPayment = $this->_etransactions->getOrderPayments($orderId, 'authorization');
        if (empty($orderPayment->data)) {
            return;
        }

        $orderPaymentData = unserialize($orderPayment->data);
        $httpClient = new WC_Etransactions_Curl_Helper($this->_config, $this->plugin_id, $this->id);

        $params = $httpClient->makeCapture($order, $orderPaymentData['transaction'], $orderPaymentData['call'], $orderPaymentData['amount'], $orderPaymentData['cardType']);
        if (isset($params['CODEREPONSE']) && $params['CODEREPONSE'] == '00000') {
            // Capture done
            wc_etransactions_add_log( sprintf( "Payment captured response order(%s): %s", $orderId, json_encode($params) ), $this->_config->isDebug() );
            $this->callbacks->addOrderNote($order, __('Payment was captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
            // Backup the capture operation timestamp
            $params['CAPTURE_DATE_ADD'] = time();
            $this->callbacks->addOrderPayment($order, 'capture', $params);
        } else {
            // Payment refused
            wc_etransactions_add_log( sprintf( "Payment refused response order(%s): %s", $orderId, json_encode($params) ), $this->_config->isDebug() );
            $message = __('Payment was refused by E-Transactions (%s).', WC_ETRANSACTIONS_PLUGIN);
            $error = $this->_etransactions->toErrorMessage($params['CODEREPONSE']);
            $message = sprintf($message, $error);
            $this->callbacks->addOrderNote($order, $message);
        }
    }

    /**
     * Fake payment gateways list and add the saved cards
     *
     * @param array $params
     * @return array
     */
    public function display_tokens_as_payment_gateways($params) {

        if (!isset($params[$this->id]) || !get_current_user_id() || is_add_payment_method_page()) {
            return $params;
        }

        // If tokenization is available, create the tokenized card
        // First, check if the token already exists on our side
        $exitingTokens = WC_Payment_Tokens::get_tokens(array(
            'user_id' => get_current_user_id(),
            'gateway_id' => $this->id,
        ));

        foreach ($exitingTokens as $idToken => $token) {
            // Clone the payment gateway, set a new id (temp), title & icon
            $paymentMethodKey                       = $this->id . '-token-' . $idToken;
            $newPaymentGateway                      = clone($params[$this->id]);
            $newPaymentGateway->id                  = $paymentMethodKey;
            $newPaymentGateway->tokenized_card_id   = $idToken;
            $newPaymentGateway->original_id         = $this->id;
            $newPaymentGateway->description         = $this->addFieldVerificationPhone($this->commonDescription);


            $token_data = $token->get_data();
            $cardTitle = sprintf(
                __('Pay with my stored card - **%02d - %02d/%02d', WC_ETRANSACTIONS_PLUGIN),
                $token_data['last4'] ?? '',
                $token_data['expiry_month'] ?? '',
                $token_data['expiry_year'] ?? ''
            );
            $newPaymentGateway->title = apply_filters('title', $cardTitle);
            if ($this->defaultConfig->getCurrentEnvMode( $this->plugin_id, $this->id ) == 'test') {
                $newPaymentGateway->title .= ' (' . __('TEST MODE', WC_ETRANSACTIONS_PLUGIN) . ')';
            }
            $newPaymentGateway->icon = apply_filters(WC_ETRANSACTIONS_PLUGIN, plugin_dir_url(__DIR__) . 'cards/') . apply_filters('icon', strtoupper($token_data['card_type']??'') . '.svg');

            $params = wc_etransactions_array_insert_at_position($params, array_search($this->id, array_keys($params)), array(
                $paymentMethodKey => $newPaymentGateway
            ));
        }

        return $params;
    }

    public function addFieldVerificationPhone($description)
    {
        $field = '<br><div class="Up2Pay-block"><input type="tel" class="Up2Pay-phone" name="Up2Pay_phone" value=""><img src="'.WC_ETRANSACTIONS_PLUGIN_URL.'assets/build/images/icon_valid.png" class="up2pay-valid js-up2pay-valid"/><br></b><span class="up2pay-invalid js-up2pay-invalid"
                    style="color:red">'.esc_html__('Please fill a valid number',WC_ETRANSACTIONS_PLUGIN).'</span><input type="hidden" class="Up2Pay-countrycode" name="Up2Pay_countrycode" value=""></div>';
        return $description.$field;

    }

    /**
     * Hide main payment method if enabled into plugin configuration
     *
     * @param array $params
     * @return array
     */
    public function hide_main_payment_gateway($params) {

        if (empty($this->original_id) && !$this->_config->allowDisplayGenericMethod()) {
            unset($params[$this->id]);
        }

        return $params;
    }

    /**
     * Fake payment gateways list and add the forced cards
     *
     * @param array $params
     * @return array
     */
    public function display_custom_cards_as_payment_gateways($params) {

        if (!isset($params[$this->id])) {
            return $params;
        }

        foreach ($this->_config->getCards($this->defaultConfig->getCurrentEnvMode( $this->plugin_id, $this->id ), $this->id, true) as $card) {
            $paymentMethodKey = $this->id . '_card_' . (int)$card->id_card;
            if (isset($params[$paymentMethodKey])) {
                continue;
            }

            // Clone the payment gateway, set a new id (temp), title & icon
            $newPaymentGateway              = clone($params[$this->id]);
            $newPaymentGateway->id          = $paymentMethodKey;
            $newPaymentGateway->original_id = $this->id;
            $newPaymentGateway->card_id     = $card->id_card;
            $newPaymentGateway->title       = apply_filters('title', $card->label);

            if ($this->defaultConfig->getCurrentEnvMode( $this->plugin_id, $this->id ) == 'test') {
                $newPaymentGateway->title .= ' (' . __('TEST MODE', WC_ETRANSACTIONS_PLUGIN) . ')';
            }
            $newPaymentGateway->description = $this->addFieldVerificationPhone($this->commonDescription);
            $newPaymentGateway->icon = apply_filters(WC_ETRANSACTIONS_PLUGIN, plugin_dir_url(__DIR__) . 'cards/') . apply_filters('icon', $card->type_card . '.svg');

            $params = wc_etransactions_array_insert_at_position($params, array_search($this->id, array_keys($params)), array(
                $paymentMethodKey => $newPaymentGateway
            ));
        }

        return $params;
    }

    /**
	 * Process the refund
	 */
	public function process_refund( $orderId, $amount = null, $reason = '' ) {

        $order = wc_get_order( $orderId );
		if ( !$order ) {
			return false;
		}

        $orderPayment = $this->_etransactions->getOrderPayments($orderId, 'capture');
        if (empty($orderPayment)) {
            return false;
        }

        $orderPaymentData = unserialize($orderPayment->data);
        $httpClient = new WC_Etransactions_Curl_Helper($this->_config, $this->plugin_id, $this->id);

        $amountInCents  = $amount * 100;
        $params = $httpClient->makeRefund($order, $orderPaymentData['transaction'], $orderPaymentData['call'], $amountInCents, $orderPaymentData['cardType']);

        if (isset($params['CODEREPONSE']) && $params['CODEREPONSE'] == '00000') {
            // Refund done
            wc_etransactions_add_log( sprintf( "Refund succed response order(%s): %s", $orderId, json_encode($params) ), $this->_config->isDebug() );
            $this->callbacks->addOrderNote($order, sprintf(__('Refund succed of (%s)', WC_ETRANSACTIONS_PLUGIN), wc_price($amount)));
        } else {
            // Refund refused
            wc_etransactions_add_log( sprintf( "Refund refused response order(%s): %s", $orderId, json_encode($params) ), $this->_config->isDebug() );
            $this->callbacks->addOrderNote($order, sprintf(__('Refund refused of (%s)', WC_ETRANSACTIONS_PLUGIN), wc_price($amount)));
            return false;
        }

		return true;
	}

}
