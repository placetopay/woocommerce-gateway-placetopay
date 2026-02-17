<?php

namespace PlacetoPay\PaymentMethod;

use PlacetoPay\PaymentMethod\Constants\Environment;

abstract class CountryConfig
{
    public const CLIENT_ID = 'banchile-chile';
    public const CLIENT_URI = 'https://www.banchilepagos.cl';
    public const CLIENT = 'Banchile Pagos';
    public const IMAGE = 'https://placetopay-static-prod-bucket.s3.us-east-2.amazonaws.com/banchile/logos/Logotipo_superior.png';
    public const COUNTRY_CODE = 'CL';
    public const COUNTRY_NAME = 'Chile';

    public static function getEndpoints(): array
    {
        return [
            Environment::TEST => 'https://checkout.test.banchilepagos.cl',
            Environment::PROD => 'https://checkout.banchilepagos.cl',
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
            'fill_buyer_information' => [
                'title' => __('Predicting the buyer\'s information?', 'woocommerce-gateway-translations'),
                'type' => 'checkbox',
                'label' => sprintf(__('Enable to preload the buyer\'s information on the %s platform.',
                    'woocommerce-gateway-translations'), $gatewayMethod->getClient()),
                'default' => 'yes',
            ],
            'allow_to_pay_with_pending_orders' => [
                'title' => __('Allow to pay with pending orders', 'woocommerce-gateway-translations'),
                'type' => 'checkbox',
                'label' => __('If it is selected, it will allow the user to pay even if he has orders in pending status.',
                    'woocommerce-gateway-translations'),
                'default' => 'yes',
                'description' => __('If it is disabled, it displays a message when paying if the user has a pending order',
                    'woocommerce-gateway-translations'),
            ],
            'allow_partial_payments' => [
                'title' => __('Allow partial payments', 'woocommerce-gateway-translations'),
                'type' => 'checkbox',
                'label' => __('If it is selected, allows the user to pay their orders in partial payments.',
                    'woocommerce-gateway-translations'),
                'default' => 'yes',
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
                'description' => sprintf(__('It can be a URL, an image name (provide the image to the %s team as svg format for this to work) or a local path (save the image to the wp-content/uploads folder',
                    'woocommerce-gateway-translations'), $gatewayMethod->getClient()),
            ],
            'minimum_amount' => [
                'title' => __('Minimum Amount', 'woocommerce-gateway-translations'),
                'type' => 'text',
                'default' => '',
                'description' => __('Select a minimum amount per transaction', 'woocommerce-gateway-translations')
            ],
            'maximum_amount' => [
                'title' => __('Maximum Amount', 'woocommerce-gateway-translations'),
                'type' => 'text',
                'default' => '',
                'description' => __('Select a maximum amount per transaction', 'woocommerce-gateway-translations')
            ],
            'expiration_time_minutes' => [
                'title' => __('Expiration time session', 'woocommerce-gateway-translations'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 10,
                'options' => $gatewayMethod->getListOptionExpirationMinutes(),
                'description' => sprintf(__('Expiration of the session for payment in %s', 'woocommerce-gateway-translations'), $gatewayMethod->getClient()),
                'desc_tip' => true
            ],
            'taxes_others' => [
                'title' => __('Select taxes to include', 'woocommerce-gateway-translations'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'options' => $gatewayMethod->getListTaxes(),
                'description' => sprintf(__('Select the taxes that are included as VAT or other types of taxes for %s',
                    'woocommerce-gateway-translations'), $gatewayMethod->getClient()),
            ],
            'taxes_ico' => [
                'title' => __('Select ICO taxes to include', 'woocommerce-gateway-translations'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'options' => $gatewayMethod->getListTaxes(),
                'description' => sprintf(__('Select the taxes that are included as an ICO tax rate for %s',
                    'woocommerce-gateway-translations'), $gatewayMethod->getClient()),
            ],
            'taxes_ice' => [
                'title' => __('Select ICE taxes to include', 'woocommerce-gateway-translations'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'options' => $gatewayMethod->getListTaxes(),
                'description' => sprintf(__('Select the taxes that are included as an ICE tax rate for %s',
                    'woocommerce-gateway-translations'), $gatewayMethod->getClient()),
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
        }

        return $fields;
    }
}
