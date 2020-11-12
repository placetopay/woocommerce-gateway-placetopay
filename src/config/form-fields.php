<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * @var \PlacetoPay\PaymentMethod\GatewayMethod $this
 */

/**
 * This file will be included into of GatewayMethod class
 * @package \PlacetoPay;
 */
return [
    'enabled' => [
        'title' => __('Enable/Disable', 'woocommerce-gateway-placetopay'),
        'type' => 'checkbox',
        'label' => __('Enable Placetopay payment method.', 'woocommerce-gateway-placetopay'),
        'default' => 'no',
        'description' => __('Show in the Payment List as a payment option', 'woocommerce-gateway-placetopay')
    ],
    'fill_buyer_information' => [
        'title' => __('Predicting the buyer\'s information?', 'woocommerce-gateway-placetopay'),
        'type' => 'checkbox',
        'label' => __('Enable to preload the buyer\'s information on the Placetopay platform.',
            'woocommerce-gateway-placetopay'),
        'default' => 'yes',
    ],
    'allow_to_pay_with_pending_orders' => [
        'title' => __('Allow to pay with pending orders', 'woocommerce-gateway-placetopay'),
        'type' => 'checkbox',
        'label' => __('If it is selected, it will allow the user to pay even if he has orders in pending status.',
            'woocommerce-gateway-placetopay'),
        'default' => 'yes',
        'description' => __('If it is disabled, it displays a message when paying if the user has a pending order',
            'woocommerce-gateway-placetopay'),
    ],
    'allow_partial_payments' => [
        'title' => __('Allow partial payments', 'woocommerce-gateway-placetopay'),
        'type' => 'checkbox',
        'label' => __('If it is selected, allows the user to pay their orders in partial payments.',
            'woocommerce-gateway-placetopay'),
        'default' => 'yes',
    ],
    'skip_result' => [
        'title' => __('Skip result?', 'woocommerce-gateway-placetopay'),
        'type' => 'checkbox',
        'label' => __('Allow to skip the placetopay result screen.', 'woocommerce-gateway-placetopay'),
        'default' => 'no',
    ],
    'title' => [
        'title' => __('Title:', 'woocommerce-gateway-placetopay'),
        'type' => 'text',
        'default' => __('Placetopay', 'woocommerce-gateway-placetopay'),
        'description' => __('This controls the title which the user sees during checkout.',
            'woocommerce-gateway-placetopay'),
        'desc_tip' => true
    ],
    'description' => [
        'title' => __('Description:', 'woocommerce-gateway-placetopay'),
        'type' => 'textarea',
        'default' => __('Pay securely through Placetopay.', 'woocommerce-gateway-placetopay'),
        'description' => __('This controls the description which the user sees during checkout.',
            'woocommerce-gateway-placetopay'),
        'desc_tip' => true
    ],
    'login' => [
        'title' => __('Login', 'woocommerce-gateway-placetopay'),
        'type' => 'text',
        'description' => __('Given to login by Placetopay', 'woocommerce-gateway-placetopay'),
        'desc_tip' => true
    ],
    'tran_key' => [
        'title' => __('Transactional Key', 'woocommerce-gateway-placetopay'),
        'type' => 'password',
        'description' => __('Given to transactional key by Placetopay', 'woocommerce-gateway-placetopay'),
        'desc_tip' => true
    ],
    'country' => [
        'title' => __('Country', 'woocommerce-gateway-placetopay'),
        'type' => 'select',
        'class' => 'wc-enhanced-select',
        'default' => get_option('woocommerce_default_country'),
        'options' => $this->getCountryList(),
    ],
    'enviroment_mode' => [
        'title' => __('Mode', 'woocommerce-gateway-placetopay'),
        'type' => 'select',
        'class' => 'wc-enhanced-select',
        'default' => 'dev',
        'options' => $this->getEnvironments(),
        'description' => __('Enable the environment Placetopay for testing or production transactions.<br />Note: <b>By default is "Development Test", if WP_DEBUG is activated</b>',
            'woocommerce-gateway-placetopay')
    ],
    'redirect_page_id' => [
        'title' => __('Return Page', 'woocommerce-gateway-placetopay'),
        'type' => 'select',
        'class' => 'wc-enhanced-select',
        'options' => $this->getPages(),
        'description' => __('URL of success page', 'woocommerce-gateway-placetopay'),
        'desc_tip' => true
    ],
    'endpoint' => [
        'title' => __('Notification url. EndPoint (WP >= 4.6)', 'woocommerce-gateway-placetopay'),
        'type' => 'text',
        'class' => 'readonly',
        'description' => __('Url of notification where Placetopay will send a notification of a transaction for Woocommerce.<br />If your Wordpress not support REST-API, please visit: https://wordpress.org/plugins/rest-api/',
            'woocommerce-gateway-placetopay')
    ],
    'minimum_amount' => [
        'title' => __('Minimum Amount', 'woocommerce-gateway-placetopay'),
        'type' => 'text',
        'default' => '',
        'description' => __('Select a minimum amount per transaction', 'woocommerce-gateway-placetopay')
    ],
    'maximum_amount' => [
        'title' => __('Maximum Amount', 'woocommerce-gateway-placetopay'),
        'type' => 'text',
        'default' => '',
        'description' => __('Select a maximum amount per transaction', 'woocommerce-gateway-placetopay')
    ],
    'expiration_time_minutes' => [
        'title' => __('Expiration time session', 'woocommerce-gateway-placetopay'),
        'type' => 'select',
        'class' => 'wc-enhanced-select',
        'default' => 2880,
        'options' => $this->getListOptionExpirationMinutes(),
        'description' => __('Expiration of the session for payment in Placetopay', 'woocommerce-gateway-placetopay'),
        'desc_tip' => true
    ],
    'taxes_others' => [
        'title' => __('Select taxes to include', 'woocommerce-gateway-placetopay'),
        'type' => 'multiselect',
        'class' => 'wc-enhanced-select',
        'options' => $this->getListTaxes(),
        'description' => __('Select the taxes that are included as VAT or other types of taxes for Placetopay',
            'woocommerce-gateway-placetopay'),
    ],
    'taxes_ico' => [
        'title' => __('Select ICO taxes to include', 'woocommerce-gateway-placetopay'),
        'type' => 'multiselect',
        'class' => 'wc-enhanced-select',
        'options' => $this->getListTaxes(),
        'description' => __('Select the taxes that are included as an ICO tax rate for Placetopay',
            'woocommerce-gateway-placetopay'),
    ],
    'taxes_ice' => [
        'title' => __('Select ICE taxes to include', 'woocommerce-gateway-placetopay'),
        'type' => 'multiselect',
        'class' => 'wc-enhanced-select',
        'options' => $this->getListTaxes(),
        'description' => __('Select the taxes that are included as an ICE tax rate for Placetopay',
            'woocommerce-gateway-placetopay'),
    ],
    'merchant_phone' => [
        'title' => __('Phone number', 'woocommerce-gateway-placetopay'),
        'description' => __('Provide the phone number used for the inquiries or support in your shop',
            'woocommerce-gateway-placetopay'),
        'type' => 'text',
        'default' => '',
        'desc_tip' => true,
    ],
    'merchant_email' => [
        'title' => __('Email', 'woocommerce-gateway-placetopay'),
        'description' => __('Provide contact email', 'woocommerce-gateway-placetopay'),
        'type' => 'text',
        'default' => '',
        'desc_tip' => true,
    ],
    'msg_approved' => [
        'title' => __('Message for approved transaction', 'woocommerce-gateway-placetopay'),
        'type' => 'text',
        'default' => __('Placetopay Payment Approved', 'woocommerce-gateway-placetopay'),
        'description' => __('Message for approved transaction', 'woocommerce-gateway-placetopay'),
        'desc_tip' => true
    ],
    'msg_pending' => [
        'title' => __('Message for pending transaction', 'woocommerce-gateway-placetopay'),
        'type' => 'text',
        'default' => __('Payment pending', 'woocommerce-gateway-placetopay'),
        'description' => __('Message for pending transaction', 'woocommerce-gateway-placetopay'),
        'desc_tip' => true
    ],
    'msg_cancel' => [
        'title' => __('Message for cancel transaction', 'woocommerce-gateway-placetopay'),
        'type' => 'text',
        'default' => __('Transaction Canceled.', 'woocommerce-gateway-placetopay'),
        'description' => __('Message for cancel transaction', 'woocommerce-gateway-placetopay'),
        'desc_tip' => true
    ],
    'msg_declined' => [
        'title' => __('Message for rejected transaction', 'woocommerce-gateway-placetopay'),
        'type' => 'text',
        'default' => __('Payment rejected via Placetopay.', 'woocommerce-gateway-placetopay'),
        'description' => __('Message for rejected transaction', 'woocommerce-gateway-placetopay'),
        'desc_tip' => true
    ]
];
