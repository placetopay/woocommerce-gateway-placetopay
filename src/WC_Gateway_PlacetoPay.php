<?php namespace PlacetoPay;


if( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


/**
 * @package \PlacetoPay;
 */
class WC_Gateway_PlacetoPay {

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
     * @var \WC_Gateway_PlacetoPay
     */
    private static $instance = null;


    /**
     * Constructor
     * @access private
     *
     * @param string $file    Filepath of main plugin file
     * @param string $version Plugin version
     */
    private function __construct( $version, $file ) {
        if( !$this->checkDependencies() )
            return null;

        add_filter( 'woocommerce_payment_gateways', [ $this, 'addPlacetoPayGatewayMethod' ]);
        add_filter( 'plugin_action_links_' . plugin_basename( $file ), [ $this, 'actionLinksPlacetopay' ]);

        $this->version = $version;
        // Paths
        $this->plugin_path = trailingslashit( plugin_dir_path( $file ) );
        $this->plugin_url = trailingslashit( plugin_dir_url( $file ) );

        $this->paymentMethod = new GatewayMethod();
    }


    /**
     * Method to implement a singleton pattern
     * @return \WC_Gateway_PlacetoPay
     */
    public static function getInstance( $version = null, $file = null ) {
        if( !self::$instance instanceof self )
            self::$instance = new self( $version, $file );

        return self::$instance;
    }


    /**
     * Verify if woocommerce plugin is installed
     * @return bool
     */
    public function checkDependencies() {
        if( !function_exists( 'WC' ) ) {
            add_action( 'admin_notices', function() {
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
    public function actionLinksPlacetopay( $links ) {
        $customLinks = [
            'settings' => sprintf(
                '<a href="%s">%s</a>',
                admin_url( 'admin.php?page=wc-settings&tab=checkout&section=placetopay' ),
                __( 'Settings', 'woocommerce-gateway-placetopay' )
            )
        ];

        return array_merge($links, $customLinks);
    }


    /**
     * Add the Gateway to WooCommerce, this method is a override and is called by woocommerce
     **/
    public function addPlacetoPayGatewayMethod( $methods ) {
        $methods[] = GatewayMethod::class;
        return $methods;
    }


    /**
     * Return the assets path
     *
     * @param  string $path Optional relative path to concatenate with assets path
     * @return string
     */
    public static function assets( $path = null ) {
        $assets_path = self::$instance->plugin_path . 'assets';

        if( $path === null )
            return $assets_path;

        return $assets_path . $path;
    }


    /**
     * Getter for version property
     * @return string
     */
    public static function version() {
        return self::$instance->version;
    }
}
