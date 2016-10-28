<?php namespace PlacetoPay;


if( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


/**
 * @package \PlacetoPay;
 */
class WoocommerceGatewayPlacetoPay {

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
     * @var \WoocommerceGatewayPlacetoPay
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
        $this->version = $version;
        // Paths
        $this->plugin_path = trailingslashit( plugin_dir_path( $file ) );
        $this->plugin_url = trailingslashit( plugin_dir_url( $file ) );

        $this->paymentMethod = new GatewayMethod();
    }


    /**
     * Method to implement a singleton pattern
     * @return \WoocommerceGatewayPlacetoPay
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
}
