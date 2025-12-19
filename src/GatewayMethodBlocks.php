<?php

namespace PlacetoPay\PaymentMethod;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class GatewayMethodBlocks extends AbstractPaymentMethodType
{
    private $gateway;

    /**
     * WooCommerce Blocks usa este nombre para identificar métodos de pago únicos
     * 
     * @return string
     */
    public function get_name(): string
    {
        $client_id = CountryConfig::CLIENT_ID;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Gateway Blocks] get_name() llamado para %s, retornando: %s', $client_id, $client_id));
        }
        return $client_id;
    }

    public function initialize(): void
    {
        $client_id = CountryConfig::CLIENT_ID;
        $gateway_class_name = 'GatewayMethod' . ucfirst($client_id);
        $gateway_full_class = __NAMESPACE__ . '\\' . $gateway_class_name;
        $this->gateway = new $gateway_full_class();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Gateway Blocks] initialize() llamado para %s', $client_id));
        }
    }

    public function is_active(): bool
    {
        $is_available = $this->gateway->is_available();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $client_id = CountryConfig::CLIENT_ID;
            error_log(sprintf('[Gateway Blocks] is_active() para %s: %s (enabled: %s)', $client_id, $is_available ? 'true' : 'false', $this->gateway->enabled ?? 'N/A'));
        }
        
        return $is_available;
    }

    public function get_payment_method_data(): array
    {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description
         ];
    }

    public function get_payment_method_script_handles(): array
    {
        $client_id = CountryConfig::CLIENT_ID;
        $script_handle = 'gateway-blocks-integration-' . $client_id;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Gateway Blocks] get_payment_method_script_handles() llamado para %s', $client_id));
        }

        $js_file = '../block/checkout_' . $client_id . '.js';
        $js_url = plugin_dir_url(__FILE__) . $js_file;
        

        $script_version = '1.0.0-' . $client_id;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Gateway Blocks] Registrando script para %s: handle=%s, url=%s', $client_id, $script_handle, $js_url));
        }

        if (!wp_script_is($script_handle, 'registered')) {
            wp_register_script(
                $script_handle,
                $js_url,
                [
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
                ],
                $script_version,
                true
            );
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[Gateway Blocks] Script ya registrado para %s: %s', $client_id, $script_handle));
            }
        }


        $localize_var = 'gatewayData' . ucfirst($client_id);
        $localize_data = [
            'title' => $this->gateway->method_title,
            'description' => $this->gateway->description,
            'image' => $this->gateway->icon,
            'id' => $client_id,
        ];
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Gateway Blocks] Localizando datos para %s: %s = %s', $client_id, $localize_var, json_encode($localize_data)));
        }
        
        wp_localize_script(
            $script_handle,
            $localize_var,
            $localize_data
        );

        if( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations(
                $script_handle,
                'woocommerce-gateway-translations',
                plugin_dir_path(__FILE__) . '../languages'
            );
        }
        return [$script_handle];
    }
}
