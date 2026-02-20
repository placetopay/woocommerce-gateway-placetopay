<?php

namespace PlacetoPay\PaymentMethod;

use PlacetoPay\PaymentMethod\Constants\Environment;

abstract class CountryConfig
{
    public const CLIENT_ID = 'getnet-chile';
    public const CLIENT_URI = 'https://www.getnet.cl';
    public const CLIENT = 'Getnet';
    public const IMAGE = 'https://banco.santander.cl/uploads/000/029/870/0620f532-9fc9-4248-b99e-78bae9f13e1d/original/Logo_WebCheckout_Getnet.svg';
    public const COUNTRY_CODE = 'CL';
    public const COUNTRY_NAME = 'Chile';

    public static function getEndpoints(): array
    {
        return [
            Environment::TEST => 'https://checkout.test.getnet.cl',
            Environment::UAT => 'https://checkout.uat.getnet.cl',
            Environment::PROD => 'https://checkout.getnet.cl',
        ];
    }

    public static function getConfiguration(GatewayMethod $gatewayMethod): array
    {
        return [
            'allow_to_pay_with_pending_orders' => true,
            'allow_partial_payments' => false,
            'fill_buyer_information' => true,
            'minimum_amount' => '',
            'maximum_amount' => '',
            'payment_button_image' => 'https://banco.santander.cl/uploads/000/029/870/0620f532-9fc9-4248-b99e-78bae9f13e1d/original/Logo_WebCheckout_Getnet.svg',
            'expiration_time_minutes' => (defined('WP_DEBUG') && WP_DEBUG && $gatewayMethod->get_option('expiration_time_minutes'))
                ? (int) $gatewayMethod->get_option('expiration_time_minutes')
                : 10,
            'taxes' => [
                'taxes_others' => '',
                'taxes_ico' => '',
                'taxes_ice' => '',
            ],
        ];
    }

    public static function getFields(GatewayMethod $gatewayMethod): array
    {
        $fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce-gateway-translations'),
                'type' => 'checkbox',
                'label' => sprintf(__('Enable %s payment method.', 'woocommerce-gateway-translations'), $gatewayMethod->getClient()),
                'default' => 'no',
                'description' => sprintf(__('Show %s in the Payment List as a payment option', 'woocommerce-gateway-translations'), $gatewayMethod->getClient())
            ],
            'skip_result' => [
                'title' => __('Skip result?', 'woocommerce-gateway-translations'),
                'type' => 'checkbox',
                'label' => sprintf(__('Allow to skip the %s result screen.', 'woocommerce-gateway-translations'), $gatewayMethod->getClient()),
                'default' => 'no',
            ],
            'use_lightbox' => [
                'title' => __('Redirection using Lightbox', 'woocommerce-gateway-translations'),
                'type' => 'checkbox',
                'label' => __('Enable Lightbox Redirection', 'woocommerce-gateway-translations'),
                'description' => __('It should only be used for payment methods without redirection', 'woocommerce-gateway-translations'),
                'default' => 'no',
            ],
            'login' => [
                'title' => __('Login site', 'woocommerce-gateway-translations'),
                'type' => 'text',
                'description' => sprintf(__('Given to login by %s', 'woocommerce-gateway-translations'), $gatewayMethod->getClient()),
                'desc_tip' => true
            ],
            'tran_key' => [
                'title' => __('Transactional Key', 'woocommerce-gateway-translations'),
                'type' => 'password',
                'description' => sprintf(__('Given to transactional key by %s', 'woocommerce-gateway-translations'), $gatewayMethod->getClient()),
                'desc_tip' => true
            ],
            'enviroment_mode' => [
                'title' => __('Mode', 'woocommerce-gateway-translations'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'dev',
                'options' => $gatewayMethod->getEnvironments(),
                'description' => sprintf(__('Enable the environment %s for testing or production transactions.<br />Note: <b>By default is "Development Test", if WP_DEBUG is activated</b>',
                    'woocommerce-gateway-translations'), $gatewayMethod->getClient())
            ],
            'redirect_page_id' => [
                'title' => __('Return Page', 'woocommerce-gateway-translations'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'options' => $gatewayMethod->getPages(),
                'description' => __('URL of success page', 'woocommerce-gateway-translations'),
                'desc_tip' => true
            ],
            'payment_button_image' => [
                'title' => __('Payment button image', 'woocommerce-gateway-translations'),
                'type' => 'text',
                'custom_attributes' => [
                    'readonly' => 'readonly'
                ],
                'description' => sprintf(__('It can be a URL, an image name (provide the image to the %s team as svg format for this to work) or a local path (save the image to the wp-content/uploads folder',
                    'woocommerce-gateway-translations'), $gatewayMethod->getClient()),
                'default' => 'https://banco.santander.cl/uploads/000/029/870/0620f532-9fc9-4248-b99e-78bae9f13e1d/original/Logo_WebCheckout_Getnet.svg',
            ],
        ];

        if (WP_DEBUG) {
            $fields['endpoint'] = [
                'title' => __('Notification url. EndPoint (WP >= 4.6)', 'woocommerce-gateway-translations'),
                'type' => 'text',
                'custom_attributes' => [
                    'readonly' => 'readonly'
                ],
                'description' => sprintf(__('Url of notification where %s will send a notification of a transaction for Woocommerce.<br />If your Wordpress not support REST-API, please visit: https://wordpress.org/plugins/rest-api/',
                    'woocommerce-gateway-translations'), $gatewayMethod->getClient())
            ];

            $fields['schedule_task_path'] = [
                'title' => __('Scheduler task path', 'woocommerce-gateway-translations'),
                'type' => 'text',
                'custom_attributes' => [
                    'readonly' => 'readonly'
                ],
                'default' => $gatewayMethod->getScheduleTaskPath(),
                'description' => __('Set this task to validate payments with pending status in your site.', 'woocommerce-gateway-translations')
            ];

            $fields['custom_connection_url'] = [
                'title' => __('Custom connection URL', 'woocommerce-gateway-translations'),
                'type' => 'text',
                'description' => __('By example: "https://gateway.com/redirection". This value only is required when you select custom environment', 'woocommerce-gateway-translations'),
            ];

            $fields['expiration_time_minutes'] = [
                'title' => __('Expiration time session', 'woocommerce-gateway-translations'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 10,
                'options' => $gatewayMethod->getListOptionExpirationMinutes(),
                'description' => sprintf(__('Expiration of the session for payment in %s', 'woocommerce-gateway-translations'), $gatewayMethod->getClient()),
                'desc_tip' => true
            ];
        }

        return $fields;
    }
}
