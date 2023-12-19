<?php

namespace PlacetoPay\PaymentMethod\Countries;

use PlacetoPay\PaymentMethod\Constants\Client;
use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Environment;
use PlacetoPay\PaymentMethod\GatewayMethod;

abstract class ChileCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return Country::CL === $countryCode;
    }

    public static function getEndpoints(string $client): array
    {
        return array_merge(parent::getEndpoints($client), [
            Environment::PROD => unmaskString('uggcf://purpxbhg.trgarg.py'),
            Environment::TEST => unmaskString('uggcf://purpxbhg.grfg.trgarg.py'),
        ]);
    }

    public static function getClients(): array
    {
        return [
            unmaskString(Client::GNT) => __(unmaskString(Client::GNT), 'woocommerce-gateway-placetopay')
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
            'expiration_time_minutes' => 10,
            'taxes' => [
                'taxes_others' => '',
                'taxes_ico' => '',
                'taxes_ice' => '',
            ],
        ];
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
            'payment_button_image' => [
                'title' => __('Payment button image', 'woocommerce-gateway-placetopay'),
                'type' => 'text',
                'description' => sprintf(__('It can be a URL, an image name (provide the image to the %s team as svg format for this to work) or a local path (save the image to the wp-content/uploads folder',
                    'woocommerce-gateway-placetopay'), $gatewayMethod->getClient()),
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
        ];
    }
}
