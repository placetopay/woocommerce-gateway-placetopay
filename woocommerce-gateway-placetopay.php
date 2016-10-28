<?php
/**
 * @package PlacetoPay/WoocommerceGatewayPlacetoPay
 * @version 0.0.1
 */

/*
Plugin Name: WooCommerce PlacetoPay Gateway
Plugin URI: después :P
Description: Plugin for integrate PlacetoPay gateway with your shop woocommerce
Version: 0.0.1
Author: PlacetoPay
Author URI: https://www.placetopay.com/
Developer: Cristian Salazar
*/

if( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


/**
 * Return instance of \PlacetoPay\WoocommerceGatewayPlacetoPay
 *
 * @return \PlacetoPay\WoocommerceGatewayPlacetoPay
 */
function wc_gateway_placetopay() {
    require_once( __DIR__ . '/vendor/autoload.php' );
    return \PlacetoPay\WoocommerceGatewayPlacetoPay::getInstance( '0.0.1', __FILE__ );
}

add_action( 'plugins_loaded', 'wc_gateway_placetopay', 0 );
