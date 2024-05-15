<?php
/**
 * Plugin Name: WooCommerce Placetopay Gateway
 * Plugin URI: https://docs-gateway.placetopay.com/docs/webcheckout-docs/9016e976d1ea0-plugins-y-componentes
 * Description: Adds Placetopay Payment Gateway to Woocommerce e-commerce plugin
 * Author: Placetopay
 * Author URI: https://www.evertecinc.com/pasarela-de-pagos-e-commerce/
 * Developer: PlacetoPay
 * Version: 2.24.2
 *
 * @package PlacetoPay/WC_Gateway_PlacetoPay
 *
 * @author Soporte <soporte@placetopay.com>
 * @copyright (c) 2013-2024 Evertec PlacetoPay S.A.S.
 * @version 2.24.2
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

    require_once(__DIR__ . '/src/helpers.php');
    require_once(__DIR__ . '/vendor/autoload.php');
    return \PlacetoPay\PaymentMethod\WC_Gateway_PlacetoPay::getInstance(
        \PlacetoPay\PaymentMethod\GatewayMethod::VERSION,
        __FILE__
    );
}

add_action('plugins_loaded', 'wc_gateway_placetopay', 0);
