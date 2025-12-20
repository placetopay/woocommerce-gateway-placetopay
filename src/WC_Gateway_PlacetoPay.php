<?php

namespace PlacetoPay\PaymentMethod;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Gateway_PlacetoPay
{

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version;

    /**
     * Absolute plugin path.
     *
     * @var string
     */
    public $plugin_path;

    /**
     * Absolute plugin URL.
     *
     * @var string
     */
    public $plugin_url;

    /**
     * Unique instance of self
     * @var WC_Gateway_PlacetoPay
     */
    private static $instance = null;


    /**
     * Constructor
     *
     * @access private
     * @param string $file Filepath of main plugin file
     * @param string $version
     */
    private function __construct($version, $file)
    {
        if (!$this->checkDependencies()) {
            return null;
        }

        $this->migrateSettings();

        add_filter('woocommerce_payment_gateways', [$this, 'addPlacetoPayGatewayMethod']);
        add_filter('plugin_action_links_' . plugin_basename($file), [$this, 'actionLinksPlacetopay']);

        $client_id = CountryConfig::CLIENT_ID;
        $gateway_class_name = 'GatewayMethod' . ucfirst($client_id);
        $gateway_blocks_class_name = 'GatewayMethodBlocks' . ucfirst($client_id);
        $gateway_full_class = __NAMESPACE__ . '\\' . $gateway_class_name;
        $gateway_blocks_full_class = __NAMESPACE__ . '\\' . $gateway_blocks_class_name;

        $gateway_instance = new $gateway_full_class();
        
        add_action('woocommerce_before_checkout_form', [$gateway_instance, 'checkoutMessage']);
        add_action('woocommerce_before_account_orders', [$gateway_instance, 'checkoutMessage']);
        add_action('woocommerce_checkout_process', [$gateway_instance, 'checkoutFieldProcess']);

        $notification_hook = $gateway_full_class::NOTIFICATION_RETURN_PAGE;
        add_action($notification_hook, [$this, 'notificationReturnPage']);

        if ($gateway_full_class::validateVersionSupportBlocks()) {
            add_action('plugins_loaded', [$this, 'blocks_woocommerce_my_gateway'], 0);
            add_action('woocommerce_blocks_loaded', [$this, 'blocks_register_gateway_method_adapter']);
            add_action('before_woocommerce_init', [$this, 'blocks_declare_cart_checkout_blocks_compatibility']);
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function( PaymentMethodRegistry $payment_method_registry ) use ($gateway_blocks_full_class, $client_id) {
                    $instance = new $gateway_blocks_full_class();
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf('[Gateway Blocks] Registrando método de pago en registry para %s: %s', $client_id, $gateway_blocks_full_class));
                    }
                    
                    $payment_method_registry->register( $instance );
                });
        }

        add_action('rest_api_init', function () use ($gateway_full_class) {
            $self = new $gateway_full_class();
            $self->logger('register rest route', 'rest_api_init');

            register_rest_route($self::PAYMENT_ENDPOINT_NAMESPACE, $self::PAYMENT_ENDPOINT_CALLBACK, [
                'methods' => 'POST',
                'callback' => [$self, 'endpointPlacetoPay'],
                'permission_callback' => function() {
                    return true;
                }
            ]);
        }, 1);

        $this->version = $version;
        $this->plugin_path = trailingslashit(plugin_dir_path($file));
        $this->plugin_url = trailingslashit(plugin_dir_url($file));
    }

    /**
     * Method to implement a singleton pattern
     *
     * @param null $version
     * @param null $file
     * @return WC_Gateway_PlacetoPay
     */
    public static function getInstance($version = null, $file = null)
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self($version, $file);
        }

        return self::$instance;
    }

    /**
     * Verify if woocommerce plugin is installed
     * @return bool
     */
    public function checkDependencies()
    {
        if (!function_exists('WC')) {
            add_action('admin_notices', function () {
                echo '<div class="error fade">
                    <p>
                        <strong>
                            [WooCommerce Gateway Placetopay] plugin requires WooCommerce to run
                        </strong>
                    </p>
                </div>';
            });

            return false;
        }

        return true;
    }

    /**
     * Migrate settings from old format to new client-specific format
     * @return void
     */
    private function migrateSettings()
    {
        if (class_exists('PlacetoPay\PaymentMethod\DataMigration')) {
            $client_id = DataMigration::getCurrentClientId();
            $client_name = DataMigration::getCurrentClientName();
            
            if ($client_id && $client_name) {
                DataMigration::migrateIfNeeded($client_id, $client_name);
            }
        }
    }

    /**
     * Add the links to show aside of the plugin
     * @param  array $links
     * @return array
     */
    public function actionLinksPlacetopay($links)
    {
        $client_id = CountryConfig::CLIENT_ID;
        $customLinks = [
            'settings' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $client_id),
                __('Settings', 'woocommerce-gateway-translations')
            )
        ];

        return array_merge($links, $customLinks);
    }

    /**
     * Add the Gateway to WooCommerce, this method is a override and is called by woocommerce
     **/
    public function addPlacetoPayGatewayMethod($methods)
    {
        $client_id = CountryConfig::CLIENT_ID;
        $gateway_class_name = 'GatewayMethod' . ucfirst($client_id);
        $gateway_full_class = __NAMESPACE__ . '\\' . $gateway_class_name;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Gateway] Registrando método de pago: %s (ID: %s)', $gateway_full_class, $client_id));
        }
        
        $methods[] = $gateway_full_class;
        return $methods;
    }

    /**
     * Return the assets path
     *
     * @param null $path Optional relative path to concatenate with assets path
     * @param string $type
     * @return string
     */
    public static function assets($path = null, $type = 'path')
    {
        $assets = (
            $type === 'path'
                ? self::$instance->plugin_path
                : (self::getInstanceName())
            ) . 'assets';

        if ($path === null) {
            return $assets;
        }

        return $assets . $path;
    }

    /**
     * @return string
     */
    private static function getInstanceName()
    {
        return self::$instance
            ? self::$instance->plugin_url
            : '';
    }

    /**
     * Getter for version property
     * @return string
     */
    public static function version()
    {
        return self::$instance->version;
    }

    public function notificationReturnPage()
    {
        $client_id = CountryConfig::CLIENT_ID;
        if (isset($_REQUEST['order_key'])
            && isset($_REQUEST['payment_method'])
            && $_REQUEST['payment_method'] === $client_id
        ) {
            $orderId = wc_get_order_id_by_order_key($_REQUEST['order_key']);
            $order = new \WC_Order($orderId);

            wc_get_template('checkout/thankyou.php', array('order' => $order, 'name'));
        }
    }

    public function blocks_woocommerce_my_gateway(): void
    {
        if (!class_exists('WC_Payment_Gateway')){
            return;
        }
        $client_class_name = 'GatewayMethod' . ucfirst(\PlacetoPay\PaymentMethod\CountryConfig::CLIENT_ID);
        include(plugin_dir_path(__FILE__) . $client_class_name . '.php');
    }

    public function blocks_declare_cart_checkout_blocks_compatibility(): void
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            $client_id = CountryConfig::CLIENT_ID;
            $plugin_file = plugin_dir_path(__FILE__) . 'woocommerce-gateway-' . $client_id . '.php';
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                $plugin_file,
            );
        }
    }

    public function blocks_register_gateway_method_adapter(): void
    {
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        $client_id = CountryConfig::CLIENT_ID;
        $gateway_blocks_class_name = 'GatewayMethodBlocks' . ucfirst($client_id);
        require_once plugin_dir_path(__FILE__) . $gateway_blocks_class_name . '.php';
    }
}
