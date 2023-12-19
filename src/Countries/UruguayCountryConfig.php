<?php

namespace PlacetoPay\PaymentMethod\Countries;

use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Environment;
use PlacetoPay\PaymentMethod\GatewayMethod;

abstract class UruguayCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return Country::UY === $countryCode;
    }

    public static function getEndpoints(string $client): array
    {
        return array_merge(parent::getEndpoints($client), [
            Environment::TEST => 'https://uy-uat-checkout.placetopay.com',
            Environment::PROD => 'https://checkout.placetopay.uy',
        ]);
    }

    public static function getFields(GatewayMethod $gatewayMethod): array
    {
        return [
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce-gateway-placetopay'),
                'type' => 'checkbox',
                'label' => sprintf(__('Enable %s payment method.', 'woocommerce-gateway-placetopay'), $gatewayMethod->getClient()),
                'default' => 'no',
                'description' => sprintf(__('Show %s in the Payment List as a payment option', 'woocommerce-gateway-placetopay'), $gatewayMethod->getClient())
            ],
            'fill_buyer_information' => [
                'title' => __('Predicting the buyer\'s information?', 'woocommerce-gateway-placetopay'),
                'type' => 'checkbox',
                'label' => sprintf(__('Enable to preload the buyer\'s information on the %s platform.',
                    'woocommerce-gateway-placetopay'), $gatewayMethod->getClient()),
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
                'label' => sprintf(__('Allow to skip the %s result screen.', 'woocommerce-gateway-placetopay'), $gatewayMethod->getClient()),
                'default' => 'no',
            ],
            'use_lightbox' => [
                'title' => __('Redirection using Lightbox', 'woocommerce-gateway-placetopay'),
                'type' => 'checkbox',
                'label' => __('Enable Lightbox Redirection', 'woocommerce-gateway-placetopay'),
                'description' => __('It should only be used for payment methods without redirection', 'woocommerce-gateway-placetopay'),
                'default' => 'no',
            ],
            'client' => [
                'title' => __('Client', 'woocommerce-gateway-placetopay'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'Getnet',
                'options' => $gatewayMethod->getClientList(),
                'description' => sprintf(__('I am integrated with %s', 'woocommerce-gateway-placetopay'), $gatewayMethod->getClient()),
            ],
            'login' => [
                'title' => __('Login site', 'woocommerce-gateway-placetopay'),
                'type' => 'text',
                'description' => sprintf(__('Given to login by %s', 'woocommerce-gateway-placetopay'), $gatewayMethod->getClient()),
                'desc_tip' => true
            ],
            'tran_key' => [
                'title' => __('Transactional Key', 'woocommerce-gateway-placetopay'),
                'type' => 'password',
                'description' => sprintf(__('Given to transactional key by %s', 'woocommerce-gateway-placetopay'), $gatewayMethod->getClient()),
                'desc_tip' => true
            ],
            'enviroment_mode' => [
                'title' => __('Mode', 'woocommerce-gateway-placetopay'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'dev',
                'options' => $gatewayMethod->getEnvironments(),
                'description' => sprintf(__('Enable the environment %s for testing or production transactions.<br />Note: <b>By default is "Development Test", if WP_DEBUG is activated</b>',
                    'woocommerce-gateway-placetopay'), $gatewayMethod->getClient())
            ],
            'custom_connection_url' => [
                'title' => __('Custom connection URL', 'woocommerce-gateway-placetopay'),
                'type' => 'text',
                'description' => __('By example: "https://gateway.com/redirection". This value only is required when you select custom environment', 'woocommerce-gateway-placetopay'),
            ],
            'redirect_page_id' => [
                'title' => __('Return Page', 'woocommerce-gateway-placetopay'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'options' => $gatewayMethod->getPages(),
                'description' => __('URL of success page', 'woocommerce-gateway-placetopay'),
                'desc_tip' => true
            ],
            'endpoint' => [
                'title' => __('Notification url. EndPoint (WP >= 4.6)', 'woocommerce-gateway-placetopay'),
                'type' => 'text',
                'class' => 'readonly',
                'description' => sprintf(__('Url of notification where %s will send a notification of a transaction for Woocommerce.<br />If your Wordpress not support REST-API, please visit: https://wordpress.org/plugins/rest-api/',
                    'woocommerce-gateway-placetopay'), $gatewayMethod->getClient())
            ],
            'schedule_task_path' => [
                'title' => __('Scheduler task path', 'woocommerce-gateway-placetopay'),
                'type' => 'text',
                'class' => 'readonly',
                'default' => $gatewayMethod->getScheduleTaskPath(),
                'description' => __('Set this task to validate payments with pending status in your site.', 'woocommerce-gateway-placetopay')
            ],
            'payment_button_image' => [
                'title' => __('Payment button image', 'woocommerce-gateway-placetopay'),
                'type' => 'text',
                'description' => sprintf(__('It can be a URL, an image name (provide the image to the %s team as svg format for this to work) or a local path (save the image to the wp-content/uploads folder',
                    'woocommerce-gateway-placetopay'), $gatewayMethod->getClient()),
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
                'options' => $gatewayMethod->getListOptionExpirationMinutes(),
                'description' => sprintf(__('Expiration of the session for payment in %s', 'woocommerce-gateway-placetopay'), $gatewayMethod->getClient()),
                'desc_tip' => true
            ],
            'taxes_others' => [
                'title' => __('Select taxes to include', 'woocommerce-gateway-placetopay'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'options' => $gatewayMethod->getListTaxes(),
                'description' => sprintf(__('Select the taxes that are included as VAT or other types of taxes for %s',
                    'woocommerce-gateway-placetopay'), $gatewayMethod->getClient()),
            ],
            'taxes_ico' => [
                'title' => __('Select ICO taxes to include', 'woocommerce-gateway-placetopay'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'options' => $gatewayMethod->getListTaxes(),
                'description' => sprintf(__('Select the taxes that are included as an ICO tax rate for %s',
                    'woocommerce-gateway-placetopay'), $gatewayMethod->getClient()),
            ],
            'taxes_ice' => [
                'title' => __('Select ICE taxes to include', 'woocommerce-gateway-placetopay'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'options' => $gatewayMethod->getListTaxes(),
                'description' => sprintf(__('Select the taxes that are included as an ICE tax rate for %s',
                    'woocommerce-gateway-placetopay'), $gatewayMethod->getClient()),
            ],
            'discount' => [
                'title' => __('Discount', 'woocommerce-gateway-placetopay'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'options' => $gatewayMethod->getDiscounts(),
            ],
            'invoice' => [
                'title' => __('Invoice', 'woocommerce-gateway-placetopay'),
                'type' => 'text',
            ],
        ];
    }
}
