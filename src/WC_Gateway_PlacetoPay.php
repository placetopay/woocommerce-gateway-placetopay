<?php

namespace PlacetoPay\PaymentMethod;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


/**
 * @package \PlacetoPay;
 */
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

        add_filter('woocommerce_payment_gateways', [$this, 'addPlacetoPayGatewayMethod']);
        add_filter('plugin_action_links_' . plugin_basename($file), [$this, 'actionLinksPlacetopay']);

        add_action('woocommerce_before_checkout_form', [new GatewayMethod(), 'checkoutMessage']);
        add_action('woocommerce_before_account_orders', [new GatewayMethod(), 'checkoutMessage']);
        add_action('woocommerce_checkout_process', [new GatewayMethod(), 'checkoutFieldProcess']);

        add_action(GatewayMethod::NOTIFICATION_RETURN_PAGE, [$this, 'notificationReturnPage']);

        // Register endpoint for placetopay
        add_action('rest_api_init', function () {
            $self = new GatewayMethod();
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
        // Paths
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
                            [WooCommerce Gateway PlacetoPay] plugin requires WooCommerce to run
                        </strong>
                    </p>
                </div>';
            });

            return false;
        }

        return true;
    }

    /**
     * Add the links to show aside of the plugin
     * @param  array $links
     * @return array
     */
    public function actionLinksPlacetopay($links)
    {
        $customLinks = [
            'settings' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=wc-settings&tab=checkout&section=placetopay'),
                __('Settings', 'woocommerce-gateway-placetopay')
            )
        ];

        return array_merge($links, $customLinks);
    }

    /**
     * Add the Gateway to WooCommerce, this method is a override and is called by woocommerce
     **/
    public function addPlacetoPayGatewayMethod($methods)
    {
        $methods[] = GatewayMethod::class;
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
        if (isset($_REQUEST['order_key'])
            && isset($_REQUEST['payment_method'])
            && $_REQUEST['payment_method'] === 'placetopay'
        ) {
            $orderId = wc_get_order_id_by_order_key($_REQUEST['order_key']);
            $order = new \WC_Order($orderId);

            wc_get_template('checkout/thankyou.php', array('order' => $order));
        }
    }
}
