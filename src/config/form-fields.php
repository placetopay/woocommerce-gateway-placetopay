<?php

if( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * @var \PlacetoPay\GatewayMethod $this
 */

/**
 * This file will be included into of GatewayMethod class
 * @package \PlacetoPay;
 */
return [
    'enabled' => [
        'title' 		=> __( 'Enable/Disable', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'checkbox',
        'label' 		=> __('Enable PlacetoPay payment method.', 'woocommerce-gateway-placetopay' ),
        'default' 		=> 'no',
        'description' 	=> __( 'Show in the Payment List as a payment option', 'woocommerce-gateway-placetopay' )
    ],
    'fill_buyer_information' => [
        'title' 		=> __( '¿Prediligenciar la información del comprador?', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'checkbox',
        'label' 		=> __('Habilitar para precargar la información del comprador en la plataforma PlacetoPay.', 'woocommerce-gateway-placetopay' ),
        'default' 		=> 'yes',
    ],
    'title' => [
        'title' 		=> __( 'Title:', 'woocommerce-gateway-placetopay' ),
        'type'			=> 'text',
        'default' 		=> __('PlacetoPay', 'woocommerce-gateway-placetopay' ),
        'description' 	=> __('This controls the title which the user sees during checkout.', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
    ],
    'description' => [
        'title' 		=> __('Description:', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'textarea',
        'default' 		=> __( 'Pay securely through PlacetoPay.','woocommerce-gateway-placetopay' ),
        'description' 	=> __('This controls the description which the user sees during checkout.', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
    ],
    'login' => [
        'title' 		=> __('Login', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'text',
        'description' 	=> __('Given to login by PlacetoPay', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
    ],
    'tran_key' => [
        'title' 		=> __('Transactional Key', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'password',
        'description' 	=>  __('Given to transactional key by PlacetoPay', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
    ],
    'enviroment_mode' => [
        'title' 		=> __( 'Enviroment mode', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'select',
        'default'       => 'dev',
        'options' 		=> $this->getEnvironments(),
        'description' 	=> __( 'Enable the enviroment PlacetoPay for testing or production transactions.<br />Note: <b>By default is "Development Test", if WP_DEBUG is actived</b>', 'woocommerce-gateway-placetopay' )
    ],
    'redirect_page_id' => [
        'title' 		=> __('Return Page', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'select',
        'options' 		=> $this->getPages(),
        'description' 	=> __('URL of success page', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
    ],
    'endpoint' => [
        'title' 		=> __( 'Notification url. EndPoint (WP >= 4.6)', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'text',
        'class'         => 'readonly',
        'description'   => __( 'Url of notification where PlacetoPay will send a notification of a transaction for Woocommerce.<br />If your Wordpress not support REST-API, please visit: https://wordpress.org/plugins/rest-api/', 'woocommerce-gateway-placetopay' )
    ],
    'merchant_phone' => [
        'title'         => __( 'Phone number', 'woocommerce-gateway-placetopay' ),
        'description'   => __( 'Provide the phone number used for the inquiries or support in your shop', 'woocommerce-gateway-placetopay' ),
        'type'          => 'text',
        'default'       => '',
        'desc_tip'      => true,
    ],
    'merchant_email' => [
        'title'         => __( 'Email', 'woocommerce-gateway-placetopay' ),
        'description'   => __( 'Provide contact email', 'woocommerce-gateway-placetopay' ),
        'type'          => 'text',
        'default'       => '',
        'desc_tip'      => true,
    ],
    'msg_approved' => [
        'title' 		=> __('Message for approved transaction', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'text',
        'default' 		=> __('PlacetoPay Payment Approved', 'woocommerce-gateway-placetopay' ),
        'description' 	=> __('Message for approved transaction', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
    ],
    'msg_pending' => [
        'title' 		=> __('Message for pending transaction', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'text',
        'default' 		=> __('Payment pending', 'woocommerce-gateway-placetopay' ),
        'description' 	=> __('Message for pending transaction', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
    ],
    'msg_cancel' => [
        'title' 		=> __('Message for cancel transaction', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'text',
        'default' 		=> __('Transaction Canceled.', 'woocommerce-gateway-placetopay' ),
        'description' 	=> __('Message for cancel transaction', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
    ],
    'msg_declined' => [
        'title' 		=> __('Message for rejected transaction', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'text',
        'default' 		=> __('Payment rejected via PlacetoPay.', 'woocommerce-gateway-placetopay' ),
        'description' 	=> __('Message for rejected transaction', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
    ]
];
