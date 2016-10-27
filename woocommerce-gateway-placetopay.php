<?php
/**
 * @package PlaceToPay/GatewayMethod
 * @version 0.0.1
 */

/*
Plugin Name: WooCommerce PlaceToPay Gateway
Plugin URI: después :P
Description: Plugin for integrate PlaceToPay gateway with your shop woocommerce
Version: 0.0.1
Author: PlaceToPay
Author URI: https://www.placetopay.com/
Developer: Cristian Salazar
*/

if( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


/**
 * Return instance of \PlaceToPay\GatewayMethod
 *
 * @return \PlaceToPay\GatewayMethod
 */
function wc_gateway_placetopay() {
    static $plugin;

    if( !isset( $plugin ) ) {
        require_once( __DIR__ . '/vendor/autoload.php' );

        $plugin = new \PlaceToPay\GatewayMethod( '0.0.1', __FILE__ );
    }

    return $plugin;
}

add_action( 'plugins_loaded', 'wc_gateway_placetopay', 0 );
