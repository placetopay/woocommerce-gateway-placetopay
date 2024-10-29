<?php

namespace PlacetoPay\PaymentMethod\Countries;

use PlacetoPay\PaymentMethod\Constants\Client;
use PlacetoPay\PaymentMethod\Constants\Environment;
use PlacetoPay\PaymentMethod\GatewayMethod;

abstract class CountryConfig implements CountryConfigInterface
{
    public static function resolve(string $countryCode): bool
    {
        return true;
    }

    public static function getEndpoints(string $client): array
    {
        return [
            Environment::DEV => 'https://checkout-co.placetopay.dev',
            Environment::TEST => 'https://checkout-test.placetopay.com',
            Environment::PROD => 'https://checkout.placetopay.com',
        ];
    }

    public static function getConfiguration(GatewayMethod $gatewayMethod): array
    {
        return [
            'allow_to_pay_with_pending_orders' => $gatewayMethod->get_option('allow_to_pay_with_pending_orders') === "yes",
            'allow_partial_payments' => $gatewayMethod->get_option('allow_partial_payments') === "yes",
            'fill_buyer_information' => $gatewayMethod->get_option('fill_buyer_information') === "yes",
            'minimum_amount' => $gatewayMethod->get_option('minimum_amount'),
            'maximum_amount' => $gatewayMethod->get_option('maximum_amount'),
            'expiration_time_minutes' => $gatewayMethod->get_option('expiration_time_minutes'),
            'payment_button_image' => $gatewayMethod->get_option('payment_button_image') ?? $gatewayMethod->getImageUrl(),
            'taxes' => [
                'taxes_others' => $gatewayMethod->get_option('taxes_others', []),
                'taxes_ico' => $gatewayMethod->get_option('taxes_ico', []),
                'taxes_ice' => $gatewayMethod->get_option('taxes_ice', []),
            ],
        ];
    }

    public static function getClients(): array
    {
        return [
            unmaskString(Client::PTP) => __(unmaskString(Client::PTP), 'woocommerce-gateway-placetopay'),
        ];
    }

    public static function getFields(GatewayMethod $gatewayMethod): array
    {
        $fields = [
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
        ];

        if (WP_DEBUG) {
            $fields['endpoint'] = [
                'title' => __('Notification url. EndPoint (WP >= 4.6)', 'woocommerce-gateway-placetopay'),
                'type' => 'text',
                'custom_attributes' => [
                    'readonly' => 'readonly'
                ],
                'description' => sprintf(__('Url of notification where %s will send a notification of a transaction for Woocommerce.<br />If your Wordpress not support REST-API, please visit: https://wordpress.org/plugins/rest-api/',
                    'woocommerce-gateway-placetopay'), $gatewayMethod->getClient())
            ];

            $fields['schedule_task_path'] = [
                'title' => __('Scheduler task path', 'woocommerce-gateway-placetopay'),
                'type' => 'text',
                'custom_attributes' => [
                    'readonly' => 'readonly'
                ],
                'default' => $gatewayMethod->getScheduleTaskPath(),
                'description' => __('Set this task to validate payments with pending status in your site.', 'woocommerce-gateway-placetopay')
            ];
        }

        return $fields;
    }
}
