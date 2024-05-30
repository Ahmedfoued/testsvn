<?php
/**
 * Plugin Name: Up2pay e-Transactions
 * Description: Up2pay e-Transactions gateway payment plugins for WooCommerce
 * Version: 2.0.4
 * Author: Up2pay e-Transactions
 * Author URI: https://www.ca-moncommerce.com/espace-client-mon-commerce/up2pay-e-transactions/
 * Text Domain: wc-etransactions
 *
 * @package WordPress
 * @since 0.9.0
 */

// Ensure not called directly
if ( !defined('ABSPATH') ) {
    exit;
}

/**
 * Check if the previous plugin exists
 */
$previousET = (in_array('woocommerce-etransactions/woocommerce-etransactions.php', apply_filters('active_plugins', get_option('active_plugins'))));
if (is_multisite()) {
    $previousET = (array_key_exists('woocommerce-etransactions/woocommerce-etransactions.php', apply_filters('active_plugins', get_site_option('active_sitewide_plugins'))));
}

if ( $previousET || defined('WC_ETRANSACTIONS_PLUGIN') ) {

    add_action('admin_notices', function(){
        echo '<div class="error"><p>' . __('Previous plugin already installed. deactivate the previous one first.', WC_ETRANSACTIONS_PLUGIN) . '</p></div>';
    });

    add_action('admin_init', function(){
        deactivate_plugins(plugin_basename(__FILE__));
    });
}

// Define constants
defined('WC_ETRANSACTIONS_PLUGIN')              || define('WC_ETRANSACTIONS_PLUGIN', 'wc-etransactions');
defined('WC_ETRANSACTIONS_VERSION')             || define('WC_ETRANSACTIONS_VERSION', '2.0.4');
defined('WC_ETRANSACTIONS_KEY_PATH')            || define('WC_ETRANSACTIONS_KEY_PATH', ABSPATH . '/kek.php');
defined('WC_ETRANSACTIONS_PLUGIN_URL')          || define('WC_ETRANSACTIONS_PLUGIN_URL', plugin_dir_url(__FILE__));
defined('WC_ETRANSACTIONS_PLUGIN_PATH')         || define('WC_ETRANSACTIONS_PLUGIN_PATH', plugin_dir_path( __FILE__ ));

/**
 * Ensure woocommerce is active
 */
function wooCommerceActiveETwp() {
    
    if ( !class_exists('WC_Payment_Gateway') ) {
        return false;
    }
    return true;
}

/**
 * Add a message to the log file
 */
function wc_etransactions_add_log( $message, $is_debug = false ) {

    if ( $is_debug ) {

        $logger = wc_get_logger();
        $logger->debug( $message, array('source' => WC_ETRANSACTIONS_PLUGIN) );
    }

}

/**
 * Insert a new array item at a specific position
 */
function wc_etransactions_array_insert_at_position(&$input, $pos, $item) {
    return array_merge(array_splice($input, 0, $pos), $item, $input);
}

/**
 * Plugin activation
 */
function wc_etransactions_installation() {
    global $wpdb;

    $installed_ver = get_option(WC_ETRANSACTIONS_PLUGIN . '_version');
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');

    if ($installed_ver != WC_ETRANSACTIONS_VERSION) {
        require_once(ABSPATH.'wp-admin/includes/upgrade.php');
        $sql = "CREATE TABLE `{$wpdb->prefix}wc_etransactions_payment` (
             id int not null auto_increment,
             order_id bigint not null,
             type enum('capture', 'authorization', 'first_payment', 'second_payment', 'third_payment') not null,
             data varchar(2048) not null,
             KEY order_id (order_id),
             PRIMARY KEY (id));";

        $sql .= "CREATE TABLE `{$wpdb->prefix}wc_etransactions_cards` (
            `id_card` int(2) not null auto_increment PRIMARY KEY,
            `payment_method` varchar(30) not null,
            `env` enum('test', 'production') not null,
            `user_xp` enum('redirect', 'seamless') null,
            `type_payment` varchar(12) not null,
            `type_card` varchar(30) not null,
            `label` varchar(30) not null,
            `position` tinyint(1) unsigned default '0' not null,
            `force_display` tinyint(1) unsigned default '0' null,
            `allow_iframe` tinyint(1) unsigned default '1' null,
            `debit_differe` tinyint(1) unsigned null,
            `3ds` tinyint(1) unsigned null,
            UNIQUE KEY `cards_unique` (`env`, `payment_method`, `type_payment`, `type_card`));";
        dbDelta($sql);
        wc_etransactions_sql_initialization();
        update_option(WC_ETRANSACTIONS_PLUGIN.'_version', WC_ETRANSACTIONS_VERSION);
    }
}

/**
 * Sql initialization
 */
function wc_etransactions_sql_initialization() {
    global $wpdb;

    require_once(dirname(__FILE__).'/trait/wc-etransactions-config-get-options.php');
    require_once(dirname(__FILE__).'/trait/wc-etransactions-config-cards.php');
    require_once(dirname(__FILE__).'/class/wc-etransactions-config.php');

    // Remove cards that aren't used anymore into default card list
    $existingCards = $wpdb->get_results("select distinct `type_payment`, `type_card` from `{$wpdb->prefix}wc_etransactions_cards`");
    foreach ($existingCards as $existingCard) {
        $cardExists = false;
        // Check if card already exists
        foreach (WC_Etransactions_Config::getDefaultCards() as $card) {
            if ($card['type_card'] != 'SODEXO' && $card['type_payment'] == $existingCard->type_payment
            && $card['type_card'] == $existingCard->type_card) {
                $cardExists = true;
                break;
            }
        }
        if (!$cardExists) {
            // The card is not managed anymore, delete it
            $wpdb->delete($wpdb->prefix . 'wc_etransactions_cards', array(
                'type_payment' => $existingCard->type_payment,
                'type_card' => $existingCard->type_card,
            ));
        }
    }

    // Create the cards
    foreach (array('test', 'production') as $env) {
        foreach (array('etransactions_std') as $paymentMethod) {
            foreach (WC_Etransactions_Config::getDefaultCards() as $card) {
                $card['env'] = $env;
                $card['payment_method'] = $paymentMethod;
                // Check if card already exists
                $sql = $wpdb->prepare("select `id_card` from `{$wpdb->prefix}wc_etransactions_cards`
                where `env` = %s
                and `payment_method` = %s
                and `type_payment` = %s
                and `type_card` = %s", $card['env'], $paymentMethod, $card['type_payment'], $card['type_card']);
                $idCard = $wpdb->get_col($sql);
                if (!empty($idCard)) {
                    continue;
                }
                // Create the card
                $wpdb->insert($wpdb->prefix . 'wc_etransactions_cards', $card);
            }
        }
    }
}

/**
 * Plugin Initialization
 */
function wc_etransactions_initialization() {

    if ( ! wooCommerceActiveETwp() ) {

        add_action('admin_notices', function(){
            echo '<div class="error"><p><strong>Up2pay e-Transactions:</strong> ' . __('WooCommerce must be activated.', WC_ETRANSACTIONS_PLUGIN) . '</p></div>';
        });
    
        add_action('admin_init', function(){
            deactivate_plugins(plugin_basename(__FILE__));
        });

        return;
    }

    if ( !class_exists('WC_Etransactions_Abstract_Gateway') ) {
        require_once(dirname(__FILE__).'/trait/wc-etransactions-config-get-options.php');
        require_once(dirname(__FILE__).'/trait/wc-etransactions-config-cards.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-config.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-display.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-callbacks.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-iso4217currency.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-iso3166-country.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-curl-helper.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-helper.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-abstract-gateway.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-standard-gateway.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-threetime-gateway.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-encrypt.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-hooks.php');
    }

    load_plugin_textdomain(WC_ETRANSACTIONS_PLUGIN, false, dirname(plugin_basename(__FILE__)).'/lang/');

    $crypto = new ET_ransactions_Encrypt();
    if (!file_exists(WC_ETRANSACTIONS_KEY_PATH)) {
        $crypto->generateKey();
    }

    if (get_site_option(WC_ETRANSACTIONS_PLUGIN.'_version') != WC_ETRANSACTIONS_VERSION) {
        wc_etransactions_installation();
    }

    wc_etransactions_register_hooks();
}

/**
 * Register actions & filters
 */
function wc_etransactions_register_hooks() {

    // Register actions & filters for each instance
    foreach ( wc_get_etransactions_classes() as $gatewayClass ) {
        $gatewayClass::getInstance($gatewayClass)->initHooksAndFilters();
    }

    new WC_Etransactions_Hooks();
}

/**
 * Get all ETransactions classes
 */
function wc_get_etransactions_classes() {

    $classes = array(
        'WC_EStd_Gw'
    );

    $env = get_option('woocommerce_etransactions_std_env', 'TEST');
    if ( $env === 'TEST' ) {
        $options = get_option('woocommerce_etransactions_std_test_settings', array());
    } else {
        $options = get_option('woocommerce_etransactions_std_settings', array());
    }

    $subscription = $options['subscription'] ?? '1';

    if ( $subscription === '2' ) {
        $classes = array_merge( $classes, array( 'WC_E3_Gw') );
    }

    return $classes;
}

register_activation_hook( __FILE__, 'wc_etransactions_installation' );
add_action( 'plugins_loaded', 'wc_etransactions_initialization' );