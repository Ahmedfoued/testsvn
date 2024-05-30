<?php

/**
 * E-Transactions - display class
 *
 * @class   WC_Etransactions_Display
 */
class WC_Etransactions_Display {

    protected $config;
    protected $defaultConfig;
    protected $etransactions;
    public $type;
    public $plugin_id;
    public $id;

    /**
     * The class constructor
     */
    public function __construct( $config, $defaultConfig, $etransactions, $plugin_id, $id ) {

        $this->config           = $config;
        $this->defaultConfig    = $defaultConfig;
        $this->etransactions    = $etransactions;
        $this->type             = $config->paymentType??'';
        $this->plugin_id        = $plugin_id;
        $this->id               = $id;
    }

    /**
     * Retrieve the fields for the global configuration
     *
     * @return array
     */
    public function getGlobalConfigurationFields() {

        if (!isset($this->config)) {
            $this->config = $this->defaultConfig;
        }
        $defaults   = $this->defaultConfig->getDefaults();
        $fields     = $this->config->getFields();

        $formFields = array();

        $formFields['enabled'] = array(
            'title'     => __('Enable/Disable', 'woocommerce'),
            'type'      => 'checkbox',
            'label'     => __('Enable E-Transactions Payment', WC_ETRANSACTIONS_PLUGIN),
            'default'   => 'yes'
        );

        if ( $this->type == 'threetime' ) {
            
            $formFields['active-notice'] = array(
                'title'     => __( 'The Payment in installments offered here is a form of payment by credit card for which the merchant bears the risk. Bank discounts are not guaranteed for the merchant.', WC_ETRANSACTIONS_PLUGIN ),
                'type'      => 'title',
                'default'   => null,
                'class'     => 'active-notice',
            );

        } elseif ( $this->type == 'threetime_sofinco' ) {

            $formFields['active-notice'] = array(
                'title'     => __( 'Payment in 3XCB CACF is an option in your contract. Please contact your customer advisor. Once the option is activated, you will be able to define the conditions of implementation: coverage of costs, minimum & maximum threshold when paying in 3xCB, bank discounts are guaranteed for the merchant.', WC_ETRANSACTIONS_PLUGIN ),
                'type'      => 'title',
                'default'   => null,
                'class'     => 'active-notice',
            );
        }

        $formFields['generic_method_settings'] = array(
            'title'     => __('Grouped payment configuration', WC_ETRANSACTIONS_PLUGIN),
            'type'      => 'title',
            'default'   => null,
        );

        if ($this->type != 'threetime' && $this->type != 'threetime_sofinco') {
            $formFields['display_generic_method'] = array(
                'title'     => __('Activate', WC_ETRANSACTIONS_PLUGIN),
                'type'      => 'checkbox',
                'label'     => __('Display one payment option for all means of payment available on payment page after redirection', WC_ETRANSACTIONS_PLUGIN),
                'default'   => $defaults['display_generic_method'],
            );
        }

        if ( $this->type == 'threetime_sofinco' ) {

            $formFields['fees_management'] = array(
                'title'         => __('Fees management', WC_ETRANSACTIONS_PLUGIN),
                'type'          => 'select',
                'options'       => array(
                    'SOF3X'     => __('With cost sharing with the customer', WC_ETRANSACTIONS_PLUGIN),
                    'SOF3XSF'   => __('No cost to the client', WC_ETRANSACTIONS_PLUGIN),
                ),
                'default'   => 'SOF3X',
            );

            $formFields['title_sof3x'] = array(
                'title'         => __('Title displayed on your payment page', WC_ETRANSACTIONS_PLUGIN),
                'type'          => 'text',
                'description'   => __('Title of generic payment option displayed on your page with means of payment choices', WC_ETRANSACTIONS_PLUGIN),
                'default'       => __($defaults['title_sof3x'], WC_ETRANSACTIONS_PLUGIN),
                'custom_attributes' => array(
                    'data-name' => 'title',
                    'data-key'  => 'SOF3X',
                )
            );

            $formFields['title_sof3xsf'] = array(
                'title'         => __('Title displayed on your payment page', WC_ETRANSACTIONS_PLUGIN),
                'type'          => 'text',
                'description'   => __('Title of generic payment option displayed on your page with means of payment choices', WC_ETRANSACTIONS_PLUGIN),
                'default'       => __($defaults['title_sof3xsf'], WC_ETRANSACTIONS_PLUGIN),
                'custom_attributes' => array(
                    'data-name' => 'title',
                    'data-key'  => 'SOF3XSF',
                )
            );

        } else {

            $formFields['title'] = array(
                'title'         => __('Title displayed on your payment page', WC_ETRANSACTIONS_PLUGIN),
                'type'          => 'text',
                'description'   => __('Title of generic payment option displayed on your page with means of payment choices', WC_ETRANSACTIONS_PLUGIN),
                'default'       => __($defaults['title'], WC_ETRANSACTIONS_PLUGIN),
            );

        }

        $allFiles = scandir(plugin_dir_path(__DIR__) . 'images/');
        $fileList = array();
        foreach ($allFiles as $id => $file) {
            if (in_array(explode(".", $file)[1], array('png','jpg','gif','svg'))) {
                $fileList[$file]=$file;
            }
        }

        $formFields['icon'] = array(
            'title'         => __('Logo displayed on your payment page', WC_ETRANSACTIONS_PLUGIN),
            'type'          => 'select',
            'description'   => __('Title of generic payment option displayed on your page with means of payment choices. Files are available on directory: ', WC_ETRANSACTIONS_PLUGIN) . apply_filters(WC_ETRANSACTIONS_PLUGIN, '' . plugin_dir_url(__DIR__) . 'images/'),
            'default'       => __($defaults['icon'], WC_ETRANSACTIONS_PLUGIN),
            'options'       => $fileList,
        );

        if ( $this->type != 'threetime_sofinco' ) {
            $formFields['description'] = array(
                'title'         => __('Description displayed on your payment page', WC_ETRANSACTIONS_PLUGIN),
                'type'          => 'textarea',
                'description'   => __('Description of generic payment option displayed on your page with means of payment choices.', WC_ETRANSACTIONS_PLUGIN),
                'default'       => __($defaults['description'], WC_ETRANSACTIONS_PLUGIN),
            );
        }

        $formFields['global_settings'] = array(
            'title'     => __('Cards default settings', WC_ETRANSACTIONS_PLUGIN),
            'type'      => 'title',
            'default'   => null,
        );

        if ($this->type == 'standard') {

            $formFields['delay'] = array(
                'title'     => __('Debit type', WC_ETRANSACTIONS_PLUGIN),
                'type'      => 'select',
                'options'   => array(
                    '0' => __('Immediate', WC_ETRANSACTIONS_PLUGIN),
                    WC_Etransactions_Config::ORDER_STATE_DELAY => __('On order event', WC_ETRANSACTIONS_PLUGIN),
                    '1' => __('1 day', WC_ETRANSACTIONS_PLUGIN),
                    '2' => __('2 days', WC_ETRANSACTIONS_PLUGIN),
                    '3' => __('3 days', WC_ETRANSACTIONS_PLUGIN),
                    '4' => __('4 days', WC_ETRANSACTIONS_PLUGIN),
                    '5' => __('5 days', WC_ETRANSACTIONS_PLUGIN),
                    '6' => __('6 days', WC_ETRANSACTIONS_PLUGIN),
                    '7' => __('7 days', WC_ETRANSACTIONS_PLUGIN),
                ),
                'default'   => $defaults['delay'],
            );

            $formFields['capture_order_status'] = array(
                'title'     => __('Order status that trigger capture', WC_ETRANSACTIONS_PLUGIN),
                'type'      => 'select',
                'options'   => wc_get_order_statuses(),
                'default'   => $defaults['capture_order_status'],
                'class'     => (!$this->config->isPremium() || $this->config->getDelay() != WC_Etransactions_Config::ORDER_STATE_DELAY ? 'hidden' : ''),
            );
        }

        if ($this->type != 'threetime' && $this->type != 'threetime_sofinco') {

            $formFields['payment_ux'] = array(
                'title'     => __('Display of payment method', WC_ETRANSACTIONS_PLUGIN),
                'type'      => 'select',
                'label'     => __('This setting does not apply on the generic method (redirect method is forced)', WC_ETRANSACTIONS_PLUGIN),
                'options'   => array(
                    'redirect'  => __('Redirect method (default)', WC_ETRANSACTIONS_PLUGIN),
                    'seamless'  => __('Seamless (iframe)', WC_ETRANSACTIONS_PLUGIN),
                ),
                'default'   => $defaults['payment_ux'],
            );

            $formFields['allow_one_click_payment'] = array(
                'title'     => __('1-click payment', WC_ETRANSACTIONS_PLUGIN),
                'type'      => 'checkbox',
                'label'     => __('Allow your customer to pay without entering his card number for every order (only for payment with CB, VISA and Mastercard)', WC_ETRANSACTIONS_PLUGIN),
                'default'   => $defaults['allow_one_click_payment'],
                'class'     => (!$this->config->isPremium() ? 'hidden' : ''),
            );
        }

        if ( $this->type == 'threetime_sofinco' ) {

            $formFields['min_amount'] = array(
                'title'         => __('Minimum amount to display payment option', WC_ETRANSACTIONS_PLUGIN),
                'type'          => 'number',
                'description'   => __('Enable this means of payment only for orders with amount equal or greater than the amount configured (let it empty for no condition)', WC_ETRANSACTIONS_PLUGIN),
            );

            $formFields['max_amount'] = array(
                'title'         => __('Maximum amount to display payment option', WC_ETRANSACTIONS_PLUGIN),
                'type'          => 'number',
                'description'   => __('Enable this means of payment only for orders with amount equal or lower than the amount configured (let it empty for no condition)', WC_ETRANSACTIONS_PLUGIN),
            );

        } else {

            $formFields['amount'] = array(
                'title'         => __('Minimal amount', WC_ETRANSACTIONS_PLUGIN),
                'type'          => 'number',
                'description'   => __('Enable this means of payment only for orders with amount equal or greater than the amount configured (let it empty for no condition)', WC_ETRANSACTIONS_PLUGIN),
                'default'       => $defaults['amount']
            );
        }
        
        $formFields['3ds_exemption_max_amount'] = array(
            'title'             => __('3DS exemption threshold', WC_ETRANSACTIONS_PLUGIN),
            'type'              => 'number',
            'description'       => __('Enable 3DS exemption means of payment only for orders with amount equal or smaller than the amount configured (let it empty for no condition)', WC_ETRANSACTIONS_PLUGIN),
            'default'           => $defaults['3ds_exemption_max_amount'],
            'custom_attributes' => array(
                'min'   => '0',
                'max'   => '30',
            ),
        );

        return $formFields;
    }

    /**
     * Retrieve the fields for the account configuration
     *
     * @return array
     */
    public function getAccountConfigurationFields() {

        if (!isset($this->config)) {
            $this->config = $this->defaultConfig;
        }
        $defaults   = $this->defaultConfig->getDefaults();
        $isTestMode = $this->defaultConfig->getCurrentEnvMode( $this->plugin_id, $this->id) === 'test';
        $formFields = array();

        if ($this->type != 'threetime' && $this->type != 'threetime_sofinco') {

            $formFields['subscription'] = array(
                'title'     => __('Up2pay e-Transactions offer subscribed', WC_ETRANSACTIONS_PLUGIN),
                'type'      => 'select',
                'default'   => $defaults['subscription'],
                'options'   => array(
                    '1' => __('e-Transactions Access', WC_ETRANSACTIONS_PLUGIN),
                    '2' => __('e-Transactions Premium', WC_ETRANSACTIONS_PLUGIN),
                ),
            );
        }

        $formFields['site'] = array(
            'title'             => __('Site number', WC_ETRANSACTIONS_PLUGIN),
            'type'              => 'text',
            'description'       => __('Site number provided by E-Transactions.', WC_ETRANSACTIONS_PLUGIN),
            'default'           => $defaults['site'],
            'custom_attributes' => array(
                'pattern' => '[0-9]{1,7}',
            ),
        );
        // if ($isTestMode) {
        //     $formFields['site']['custom_attributes']['readonly'] = 'readonly';
        // }

        $formFields['rank'] = array(
            'title'             => __('Rank number', WC_ETRANSACTIONS_PLUGIN),
            'type'              => 'text',
            'description'       => __('Rank number provided by E-Transactions (two last digits).', WC_ETRANSACTIONS_PLUGIN),
            'default'           => $defaults['rank'],
            'custom_attributes' => array(
                'pattern' => '[0-9]{1,3}'
            ),
        );
        // if ($isTestMode) {
        //     $formFields['rank']['custom_attributes']['readonly'] = 'readonly';
        // }

        $formFields['identifier'] = array(
            'title'             => __('Login', WC_ETRANSACTIONS_PLUGIN),
            'type'              => 'text',
            'description'       => __('Internal login provided by E-Transactions.', WC_ETRANSACTIONS_PLUGIN),
            'default'           => $defaults['identifier'],
            'custom_attributes' => array(
                'pattern' => '[0-9]+',
            ),
        );
        // if ($isTestMode) {
        //     $formFields['identifier']['custom_attributes']['readonly'] = 'readonly';
        // }

        $formFields['hmackey'] = array(
            'title'             => __('HMAC', WC_ETRANSACTIONS_PLUGIN),
            'type'              => 'text',
            'description'       => __('Secrete HMAC key to create using the E-Transactions interface.', WC_ETRANSACTIONS_PLUGIN),
            'default'           => $defaults['hmackey'],
            'custom_attributes' => array(
                'pattern' => '[0-9a-fA-F]{128}',
            ),
        );
        // if ($isTestMode) {
        //     $formFields['hmackey']['custom_attributes']['readonly'] = 'readonly';
        // }

        $formFields['technical'] = array(
            'title'     => __('Technical settings', WC_ETRANSACTIONS_PLUGIN),
            'type'      => 'title',
            'default'   => null,
        );

        $formFields['ips'] = array(
            'title'             => __('IPN IPs', WC_ETRANSACTIONS_PLUGIN),
            'type'              => 'text',
            'description'       => __('A coma separated list of E-Transactions IPN IPs.', WC_ETRANSACTIONS_PLUGIN),
            'default'           => $defaults['ips'],
            'custom_attributes' => array(
                'readonly' => 'readonly',
            ),
        );

        $formFields['debug'] = array(
            'title'     => __('Debug', WC_ETRANSACTIONS_PLUGIN),
            'type'      => 'checkbox',
            'label'     => __('Enable some debugging information', WC_ETRANSACTIONS_PLUGIN),
            'default'   => $defaults['debug'],
        );

        return $formFields;
    }

    /**
     * Retrieve the fields for the cards configuration
     *
     * @return array
     */
    public function getCardsConfigurationFields() {

        if (!isset($this->config)) {
            $this->config = $this->defaultConfig;
        }
        $defaults = $this->defaultConfig->getDefaults();

        $formFields = array();
        $formFields['title_cards_configuration'] = array(
            'title' => __('Means of payment configuration', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'title',
            'default' => null,
        );

        return $formFields;
    }

    /**
     * Output the payment form or iframe
     */
    public function outputPaymentForm($order, $url, $params) {

        $debugMode = $this->config->isDebug();

        if ($this->config->getPaymentUx($order) == WC_Etransactions_Config::PAYMENT_UX_REDIRECT) {

            ?>
                <form id="pbxep_form" method="post" action="<?php echo esc_url($url); ?>" enctype="application/x-www-form-urlencoded">
                    <?php if ($debugMode) : ?>
                        <p><?php echo __('This is a debug view. Click continue to be redirected to E-Transactions payment page.', WC_ETRANSACTIONS_PLUGIN); ?></p>
                    <?php else : ?>
                        <p><?php echo __('You will be redirected to the E-Transactions payment page. If not, please use the button bellow.', WC_ETRANSACTIONS_PLUGIN); ?></p>
                        <script type="text/javascript">
                            window.setTimeout(function () {
                                document.getElementById('pbxep_form').submit();
                            }, 1);
                        </script>
                    <?php endif; ?>
                    <center><button><?php echo __('Continue...', WC_ETRANSACTIONS_PLUGIN); ?></button></center>
                    <?php
                        $type = $debugMode ? 'text' : 'hidden';
                        foreach ($params as $name => $value) {
                            $name = esc_attr($name);
                            $value = esc_attr($value);
                            if ($debugMode) {
                                echo '<p><label for="' . $name . '">' . $name . '</label>';
                            }
                            echo '<input type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . $value . '" />';
                            if ($debugMode) {
                                echo '</p>';
                            }
                        } 
                    ?>
                </form>
            <?php

        } else {

            $this->load_custom_front_assets();

            ?>
                <input id="pbx-nonce" type="hidden" value="<?= wp_create_nonce($this->id . '-order-poll-' . $order->get_id()); ?>" />
                <input id="pbx-id-order" type="hidden" value="<?= (int)$order->get_id(); ?>" />
                <iframe
                    id="pbx-seamless-iframe"
                    src="<?php echo esc_url($url) . '?' . http_build_query($params); ?>"
                    scrolling="no"
                    frameborder="0">
                </iframe>
                <div class="pbx-btn-cancel">
                    <a class="pbx-btn-cancel__btn" href="<?= wc_get_checkout_url(); ?>"><?php _e('Back', WC_ETRANSACTIONS_PLUGIN); ?></a>
                </div>
                <script>
                    if (window.history && window.history.pushState) {
                        window.history.pushState('pbx-forward', null, '');
                        window.addEventListener('popstate', function() {
                            window.location = <?php echo json_encode($params['PBX_ANNULE']); ?>;
                        });
                    }
                </script>
            <?php

            if ($debugMode) {
                echo '<p>' . __('This is a debug view.', WC_ETRANSACTIONS_PLUGIN) . '</p>';
                echo '<form>';
                foreach ($params as $name => $value) {
                    $name = esc_attr($name);
                    $value = esc_attr($value);
                    echo '<p>';
                    echo '<label for="' . $name . '">' . $name . '</label>';
                    echo '<input type="text" id="' . $name . '" name="' . $name . '" value="' . $value . '" />';
                    echo '</p>';
                }
                echo '</form>';
            }
        }
    }

    public function load_phone_front_assets()
    {
        $frontAsset = include( WC_ETRANSACTIONS_PLUGIN_PATH . 'assets/build/phone.asset.php' );
        wp_enqueue_style( 'pbx_fo_intl_css', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/intlTelInput.min.css');
        wp_enqueue_script( 'pbx_fo_intl', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/intlTelInput.min.js');
        wp_enqueue_script( 'pbx_fo_phone', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/phone.js',$frontAsset['dependencies'], $frontAsset['version'], true );

        wp_localize_script( 'pbx_fo_phone', 'pbx_fo', array(
            'utilsUrl'       => WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/utils.js'),
        );

    }

    /**
     * Load the needed assets for seamless iframe integration
     *
     * @return void
     */
    public function load_custom_front_assets() {

        if (!is_order_received_page() && !is_account_page()) {
            return;
        }

        // Register JS & CSS files
        $frontAsset = include( WC_ETRANSACTIONS_PLUGIN_PATH . 'assets/build/front.asset.php' );
        wp_enqueue_style( 'pbx_fo', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/front.css', array(), $frontAsset['version'], 'all' );
        wp_enqueue_style( 'pbx_fo', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/intlTelInput.min.css');
        wp_enqueue_script( 'pbx_fo', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/front.js', $frontAsset['dependencies'], $frontAsset['version'], true );
        wp_enqueue_script( 'pbx_fo', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/intlTelInput.min.js');
        wp_enqueue_script( 'pbx_fo', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/utils.js');

        wp_localize_script( 'pbx_fo', 'pbx_fo', array(
            'homeUrl'       => home_url(),
            'orderPollUrl'  => home_url() . \WC_Ajax::get_endpoint($this->id . '_order_poll'),
        ));
    }

    /**
     * Load the needed assets for the plugin configuration
     *
     * @return void
     */
    public function load_custom_admin_assets( $hook_suffix ) {

        if ( $hook_suffix === 'woocommerce_page_wc-settings' ) {
            $adminAsset = include( WC_ETRANSACTIONS_PLUGIN_PATH . 'assets/build/admin-settings.asset.php' );
            wp_enqueue_style( 'admin-settings.css', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/admin-settings.css', array(), $adminAsset['version'], 'all' );
            wp_enqueue_script( 'admin-settings.js', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/admin-settings.js', $adminAsset['dependencies'], $adminAsset['version'], true );
        }

        if (!$this->defaultConfig->allowDisplay($this->id)) {
            return;
        }

        // Register JS & CSS files
        $adminAsset = include( WC_ETRANSACTIONS_PLUGIN_PATH . 'assets/build/admin.asset.php' );
        wp_enqueue_style( 'admin.css', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/admin.css', array(), $adminAsset['version'], 'all' );
        wp_enqueue_script( 'admin.js', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/build/admin.js', $adminAsset['dependencies'], $adminAsset['version'], true );
        wp_localize_script( 'admin.js', 'pbx_admin', array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'pbx_admin_nonce' ),
        ));
    }

    /**
     * Display payments methods
     */
    public function displayMethods() {

        ?>
            <div id="pbx-cards-container" class="row">
                <?php foreach ($this->config->getCards($this->defaultConfig->getCurrentConfigMode($this->plugin_id, $this->id), $this->id, false) as $card): ?>
                    <div class="pbx-card col-lg-3 col-md-4 col-sm-5">
                        <div class="card pbx-card-body" style="background-image: url('<?= plugins_url('cards/' . $card->type_card . '.svg', plugin_basename(dirname(__FILE__))) ?>')">
                            <div class="pbx-card-label">
                                <?= $card->label ?>
                            </div>
                            <div class="pbx-card-force-display-state">
                                <input id="card-<?= (int)$card->id_card ?>-force-display" name="<?= $this->plugin_id . $this->id . '_card-' . (int)$card->id_card ?>-force-display" type="checkbox" <?= !empty($card->force_display) ? 'checked' : '' ?> />
                                <label for="card-<?= (int)$card->id_card ?>-force-display"><?= __('Display on your payment page', WC_ETRANSACTIONS_PLUGIN) ?></label>
                            </div>
                            <div class="pbx-card-ux">
                                <label for="card-<?= (int)$card->id_card ?>-ux"><?= __('Display method', WC_ETRANSACTIONS_PLUGIN) ?></label>
                                <select class="select" id="card-<?= (int)$card->id_card ?>-ux" name="<?= $this->plugin_id . $this->id . '_card-' . (int)$card->id_card ?>-ux">
                                    <option
                                        value=""
                                        <?= (!empty($card->allow_iframe) && empty($card->user_xp) ? ' selected="selected"' : '') ?>
                                        <?= (empty($card->allow_iframe) ? ' disabled="disabled"' : '') ?>
                                    >
                                        <?= __('Same as global configuration', WC_ETRANSACTIONS_PLUGIN) ?>
                                    </option>
                                    <option
                                        value="<?= WC_Etransactions_Config::PAYMENT_UX_REDIRECT ?>"
                                        <?= (empty($card->allow_iframe) ? ' selected="selected"' : '') ?>
                                        <?= ($card->user_xp == WC_Etransactions_Config::PAYMENT_UX_REDIRECT ? ' selected="selected"' : '') ?>
                                    >
                                        <?= __('Redirect method', WC_ETRANSACTIONS_PLUGIN) ?>
                                    </option>
                                    <option
                                        value="<?= WC_Etransactions_Config::PAYMENT_UX_SEAMLESS ?>"
                                        <?= (empty($card->allow_iframe) ? ' disabled="disabled"' : '') ?>
                                        <?= (!empty($card->allow_iframe) && $card->user_xp == WC_Etransactions_Config::PAYMENT_UX_SEAMLESS ? ' selected="selected"' : '') ?>
                                    >
                                        <?= __('Seamless (iframe)', WC_ETRANSACTIONS_PLUGIN) ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php
    }

}