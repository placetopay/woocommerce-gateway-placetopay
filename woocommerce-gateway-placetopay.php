<?php
/**
 * Plugin Name: WooCommerce Placetopay Gateway
 * Plugin URI: https://docs-gateway.placetopay.com/docs/webcheckout-docs/9016e976d1ea0-plugins-y-componentes
 * Description: Adds Placetopay Payment Gateway to WooCommerce e-commerce plugin
 * Author: Placetopay
 * Author URI: https://www.evertecinc.com/pasarela-de-pagos-e-commerce/
 * Developer: PlacetoPay
 * Version: 3.1.0
 *
 * @package PlacetoPay/WC_Gateway_PlacetoPay
 *
 * @author Soporte <soporte@placetopay.com>
 * @copyright (c) 2013-2024 Evertec PlacetoPay S.A.S.
 * @version 3.1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if ( is_admin() ) {
    add_filter( 'all_plugins', 'dynamic_plugin_name' );
}

/**
 * @param array $plugins
 * @return array
 */
function dynamic_plugin_name( $plugins ) {
    $plugin_file = plugin_basename( __FILE__ );

    if ( isset( $plugins[ $plugin_file ] ) ) {
        $settings = get_option( 'woocommerce_placetopay_settings', false );

        $client = \PlacetoPay\PaymentMethod\CountryConfig::CLIENT;

        $plugins[ $plugin_file ]['Name'] = 'WooCommerce '. $client . ' Gateway';
        $plugins[ $plugin_file ]['Description'] = 'Adds ' . $client  . ' Payment Gateway to WooCommerce e-commerce plugin';
        $plugins[ $plugin_file ]['Author'] = $client;
    }

    return $plugins;
}

/**
 * @return \PlacetoPay\PaymentMethod\WC_Gateway_PlacetoPay
 */
function wc_gateway_placetopay()
{
    load_plugin_textdomain('woocommerce-gateway-placetopay', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    add_filter('woocommerce_locate_template', 'wooAddonPluginTemplate', 201, 3);

    /**
     * @param $template
     * @param $templateName
     * @param $templatePath
     * @return string
     */
    function wooAddonPluginTemplate($template, $templateName, $templatePath)
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
    return \PlacetoPay\PaymentMethod\WC_Gateway_PlacetoPay::getInstance(
        \PlacetoPay\PaymentMethod\GatewayMethod::VERSION,
        __FILE__
    );
}

add_action('plugins_loaded', 'wc_gateway_placetopay', 0);
