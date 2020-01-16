<?php
/**
 * Plugin Name: WooCommerce PlacetoPay Gateway
 * Plugin URI:  https://www.placetopay.com/component/placetopay-for-woocommerce/
 * Description: Adds Place to Pay Payment Gateway to Woocommerce e-commerce plugin
 * Author:      PlacetoPay
 * Author URI:  https://www.placetopay.com/
 * Developer:   PlacetoPay
 * Version:     2.16.0
 *
 *
 * @package PlacetoPay/WC_Gateway_PlacetoPay
 *
 * @author Soporte <soporte@placetopay.com>
 * @copyright (c) 2013-2017 EGM Ingenieria sin fronteras S.A.S.
 * @version 2.16.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Return instance of \PlacetoPay\WC_Gateway_PlacetoPay
 *
 * @return \PlacetoPay\PaymentMethod\WC_Gateway_PlacetoPay
 */
function wc_gateway_placetopay()
{
    // carga las traducciones de PlacetoPay
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

        // Look within passed path within the theme - this is priority
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

    require_once(__DIR__ . '/vendor/autoload.php');
    return \PlacetoPay\PaymentMethod\WC_Gateway_PlacetoPay::getInstance('2.16.0', __FILE__);
}

add_action('plugins_loaded', 'wc_gateway_placetopay', 0);
