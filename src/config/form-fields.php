<?php

if( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * This file will be incluided into of GatewayMethod class
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
    // 'icon_checkout' => [
    //     'title' 		=> __( 'Logo en el checkout:', 'woocommerce-gateway-placetopay' ),
    //     'type'			=> 'text',
    //     'default'		=> $this->icon_default,
    //     'description' 	=> __('URL de la Imagen para mostrar en el carrro de compra.', 'woocommerce-gateway-placetopay' ),
    //     'desc_tip' 		=> true
    // ],
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
    'testmode' => [
        'title' 		=> __('Test mode', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'checkbox',
        'label' 		=> __('Enable PlacetoPay TEST Transactions.', 'woocommerce-gateway-placetopay' ),
        'default' 		=> 'no',
        'description' 	=> __('Tick to run TEST Transaction on the PlacetoPay platform', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
    ],
    'redirect_page_id' => [
        'title' 		=> __('Return Page', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'select',
        'options' 		=> $this->getPages(__('Select Page', 'woocommerce-gateway-placetopay' )),
        'description' 	=> __('URL of success page', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
    ],
    'endpoint' => [
        'title' 		=> __('Page End Point (Woo > 2.1)', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'text',
        'default' 		=> '',
        'description' 	=> __('Return Page End Point.', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
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
        'title' 		=> __('Message for declined transaction', 'woocommerce-gateway-placetopay' ),
        'type' 			=> 'text',
        'default' 		=> __('Payment rejected via PlacetoPay.', 'woocommerce-gateway-placetopay' ),
        'description' 	=> __('Message for declined transaction ', 'woocommerce-gateway-placetopay' ),
        'desc_tip' 		=> true
    ]
];
