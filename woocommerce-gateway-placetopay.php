<?php
/**
 * Plugin Name: WooCommerce CLIENTNAME Gateway
 * Plugin URI: CLIENTURI
 * Description: Adds CLIENTNAME Payment Gateway to WooCommerce e-commerce plugin
 *
 * Author: CLIENTNAME
 * Author URI: https://www.evertecinc.com/pasarela-de-pagos-e-commerce/
 * Developer: CLIENTNAME
 * Version: PLUGINVERSION
 *
 * @package \CLIENTNAMESPACE\PaymentMethod\WC_Gateway_CLIENTCLASSNAME
 *
 * @author Soporte <soporte@placetopay.com>
 * @copyright (c) 2013-2026 Evertec PlacetoPay S.A.S.
 * @version PLUGINVERSION
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if ( is_admin() ) {
    add_filter( 'all_plugins', 'dynamic_plugin_name_CLIENTID' );
}

/**
 * @param array $plugins
 * @return array
 */
function dynamic_plugin_name_CLIENTID( $plugins ) {
    $plugin_file = plugin_basename( __FILE__ );

    if ( isset( $plugins[ $plugin_file ] ) ) {
        $client = \CLIENTNAMESPACE\PaymentMethod\CountryConfig::CLIENT;

        $plugins[ $plugin_file ]['Name'] = 'WooCommerce '. $client . ' Gateway';
        $plugins[ $plugin_file ]['Description'] = 'Adds ' . $client  . ' Payment Gateway to WooCommerce e-commerce plugin';
        $plugins[ $plugin_file ]['Author'] = $client;
    }

    return $plugins;
}

/**
 * IMPORTANTE: WordPress 6.7+ requiere que se cargue en 'init' o después
 */
function load_CLIENTID_textdomain() {
    load_plugin_textdomain('woocommerce-gateway-translations', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

add_action('init', 'load_CLIENTID_textdomain', 1);

/**
 * @return \CLIENTNAMESPACE\PaymentMethod\WC_Gateway_CLIENTCLASSNAME
 */
function wc_gateway_CLIENTID()
{
    add_filter('woocommerce_locate_template', 'wooAddonPluginTemplate_CLIENTID', 201, 3);

    /**
     * @param $template
     * @param $templateName
     * @param $templatePath
     * @return string
     */
    function wooAddonPluginTemplate_CLIENTID($template, $templateName, $templatePath)
    {
        global $woocommerce;

        $_template = $template;

        if (!$templatePath) {
            $templatePath = $woocommerce->template_url;
        }

        $pluginPath = untrailingslashit(plugin_dir_path(__FILE__)) . '/woocommerce/';

        $template = locate_template([
            $templatePath . $templateName,
            $templateName
        ]);

        if (!$template && file_exists($pluginPath . $templateName)) {
            $template = $pluginPath . $templateName;
        }

        if (!$template) {
            $template = $_template;
        }

        return $template;
    }

    require_once(__DIR__ . '/src/helpers.php');
    require_once(__DIR__ . '/vendor/autoload.php');

    return \CLIENTNAMESPACE\PaymentMethod\WC_Gateway_CLIENTCLASSNAME::getInstance(
        \CLIENTNAMESPACE\PaymentMethod\GatewayMethodCLIENTCLASSNAME::VERSION,
        __FILE__
    );
}

add_action('plugins_loaded', 'wc_gateway_CLIENTID', 0);
