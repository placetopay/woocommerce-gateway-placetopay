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
 * @param string $template
 * @param string $templateName
 * @param string $templatePath
 * @param string $defaultPath
 * @param array $args
 * @return string
 */
function wooAddonResolveTemplate_CLIENTID($template, $templateName, $templatePath, $defaultPath, $args = [])
{
    if (!function_exists('WC')) {
        return $template;
    }

    $pluginTemplate = untrailingslashit(plugin_dir_path(__FILE__)) . '/woocommerce/' . $templateName;

    if (!file_exists($pluginTemplate)) {
        return $template;
    }

    if (!$templatePath) {
        $templatePath = WC()->template_path();
    }

    if (!$defaultPath) {
        $defaultPath = WC()->plugin_path() . '/templates/';
    }

    if (locate_template([trailingslashit($templatePath) . $templateName, $templateName])) {
        return $template;
    }

    $defaultTemplate = trailingslashit($defaultPath) . $templateName;
    $order = wooAddonOrderInContext_CLIENTID($args);

    if ($order instanceof WC_Order) {
        if ($order->get_payment_method() === \CLIENTNAMESPACE\PaymentMethod\CountryConfig::CLIENT_ID) {
            return $pluginTemplate;
        }

        return $template === $pluginTemplate && file_exists($defaultTemplate)
            ? $defaultTemplate
            : $template;
    }

    return $template === $defaultTemplate ? $pluginTemplate : $template;
}

/**
 *
 * @param array $args
 * @return WC_Order|null
 */
function wooAddonOrderInContext_CLIENTID($args)
{
    if (isset($args['order']) && $args['order'] instanceof WC_Order) {
        return $args['order'];
    }

    $orderId = absint(get_query_var('order-received')) ?: absint(get_query_var('view-order'));

    if (!$orderId) {
        return null;
    }

    $order = wc_get_order($orderId);

    return $order instanceof WC_Order ? $order : null;
}

/**
 * @param string $template
 * @param string $templateName
 * @param string $templatePath
 * @param string $defaultPath
 * @return string
 */
function wooAddonPluginTemplate_CLIENTID($template, $templateName, $templatePath, $defaultPath = '')
{
    return wooAddonResolveTemplate_CLIENTID($template, $templateName, $templatePath, $defaultPath);
}

/**
 *
 * @param string $template
 * @param string $templateName
 * @param array $args
 * @param string $templatePath
 * @param string $defaultPath
 * @return string
 */
function wooAddonPluginTemplateArgs_CLIENTID($template, $templateName, $args, $templatePath, $defaultPath)
{
    return wooAddonResolveTemplate_CLIENTID(
        $template,
        $templateName,
        $templatePath,
        $defaultPath,
        is_array($args) ? $args : []
    );
}

/**
 * @return \CLIENTNAMESPACE\PaymentMethod\WC_Gateway_CLIENTCLASSNAME
 */
function wc_gateway_CLIENTID()
{
    add_filter('woocommerce_locate_template', 'wooAddonPluginTemplate_CLIENTID', 201, 4);
    add_filter('wc_get_template', 'wooAddonPluginTemplateArgs_CLIENTID', 201, 5);

    require_once(__DIR__ . '/src/helpers.php');
    require_once(__DIR__ . '/vendor/autoload.php');

    return \CLIENTNAMESPACE\PaymentMethod\WC_Gateway_CLIENTCLASSNAME::getInstance(
        \CLIENTNAMESPACE\PaymentMethod\GatewayMethodCLIENTCLASSNAME::VERSION,
        __FILE__
    );
}

add_action('plugins_loaded', 'wc_gateway_CLIENTID', 0);
