<?php

/**
 * E-Transactions - hooks class.
 *
 * @class   WC_Etransactions_Hooks
 */
class WC_Etransactions_Hooks {

    /**
     * The class constructor.
     */
    public function __construct() {

        add_filter('woocommerce_payment_gateways', array($this, 'wc_etransactions_register') );
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'wc_etransactions_show_details') );
        add_action('wp_ajax_wc_etransactions_get_log_file_content', array($this, 'wc_etransactions_get_log_file_content') );
        add_action('admin_menu', array($this, 'add_settings_menu') );
        add_action('woocommerce_update_option', array($this, 'enable_one_3times_method') );

    }

    /**
     * Add ETransactions classes to WooCommerce payment gateways
     */
    public function wc_etransactions_register(array $methods) {

        return array_merge( $methods, wc_get_etransactions_classes() );
    }

    /**
     * Show ETransactions details on admin order page
     */
    public function wc_etransactions_show_details( WC_Order $order ) {

        $method = get_post_meta($order->get_id(), '_payment_method', true);

        switch ($method) {
            case 'etransactions_std':
                $method = new WC_EStd_Gw();
                $method->showDetails($order);
            break;
            case 'etransactions_3x':
                $method = new WC_E3_Gw();
                $method->showDetails($order);
            break;
        }
    }

    /**
     * Get log file content
     */
    public function wc_etransactions_get_log_file_content() {

        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if( !wp_verify_nonce( $nonce, 'pbx_admin_nonce' ) ) {
            wp_send_json_error();
        }

        $upload_dir = wp_upload_dir();
        $log_dir    = $upload_dir['basedir'] . '/wc-logs/';

        $file_name	= isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $file_path	= $log_dir . $file_name;
        $file_exist	= file_exists( $file_path );

        if ( $file_exist ) {

            $content = esc_html( file_get_contents( $file_path ) );
            wp_send_json_success( $content );

        } else {

            wp_send_json_error( __( 'File not exist!', WC_ETRANSACTIONS_PLUGIN ) );
        }
    }

    /**
     * Add settings menu
     */
    public function add_settings_menu() {

        add_menu_page(
			__("Credit Agricole Settings", WC_ETRANSACTIONS_PLUGIN),
			__("Credit Agricole", WC_ETRANSACTIONS_PLUGIN),
			'manage_options',
			'credit-agricole-settings',
			array( $this, 'render_settings_page' ),
			WC_ETRANSACTIONS_PLUGIN_URL . 'images/menu-logo.png',
			56
		);
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {

        ?>
            <script>window.location.href = '<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=etransactions_std'); ?>';</script>
        <?php
        exit;
    }

    /**
     * Enable one 3 times method each time
     */
    public function enable_one_3times_method( $option ) {

        $gateway_prefix         = 'etransactions_3x';
        $current_gateway_key    = $option['id'] ?? '';

        if ( strpos($current_gateway_key, $gateway_prefix ) === false ) return;

        $current_option_enabled         = false;
        $payment_gateways_to_disable    = array();
        $payment_gateways               = WC()->payment_gateways->payment_gateways();

        foreach ( $payment_gateways as $gateway ) {

            $gateway_key = $gateway->get_option_key();

            if ( $gateway_key === $current_gateway_key ) {

                $enabled = $gateway->get_option( 'enabled', 'no' );

                if ( $enabled === 'no' ) {
                    $current_option_enabled = true;
                }

            } else {

                if ( strpos($gateway_key, $gateway_prefix ) !== false ) {
                    $payment_gateways_to_disable[] = $gateway;
                }
            }
        }

        if ( $current_option_enabled && count($payment_gateways_to_disable) > 0 ) {
            foreach ( $payment_gateways_to_disable as $payment_gateway ) {
                $payment_gateway->update_option( 'enabled', 'no' );
            }
        }
    }

}