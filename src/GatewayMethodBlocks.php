<?php

namespace PlacetoPay\PaymentMethod;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

require_once plugin_dir_path(__FILE__) . 'GatewayMethod.php';

class GatewayMethodBlocks extends AbstractPaymentMethodType
{
    public function initialize()
    {
        $this->gateway = new GatewayMethod();
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description
         ];
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'my_custom_gateway-blocks-integration',
            plugin_dir_url(__FILE__) . '../block/checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );

        wp_localize_script(
            'my_custom_gateway-blocks-integration',
            'myCustomGatewayData',
            [
            'title' => $this->gateway->method_title,
            'description' => $this->gateway->description,
            'image' => $this->gateway->icon,
            ]
        );

        if( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations('my_custom_gateway-blocks-integration');
        }
        return ['my_custom_gateway-blocks-integration'];
    }
}
