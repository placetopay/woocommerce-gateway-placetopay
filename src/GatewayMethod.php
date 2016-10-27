<?php namespace PlaceToPay;

use Dnetix\Redirection\PlacetoPay;

/**
 *
 */
class GatewayMethod extends \WC_Payment_Gateway {

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


    // login 6dd490faf9cb87a9862245da41170ff2
    // tranKey: 024h1IlD


    /**
     * Constructor
     *
     * @param string $file    Filepath of main plugin file
     * @param string $version Plugin version
     */
    function __construct( $version, $file ) {
        if( !$this->checkDependencies() )
        return null;

        $this->version = $version;
        // Paths
        $this->plugin_path = trailingslashit( plugin_dir_path( $file ) );
        $this->plugin_url = trailingslashit( plugin_dir_url( $file ) );
        // Configuration
        $this->init();
        $this->configPaymentMethod();
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
                            [WooCommerce Gateway PlaceToPay] plugin requires WooCommerce to run
                        </strong>
                    </p>
                </div>';
            });

            return false;
        }

        return true;
    }


    public function init() {
        add_filter( 'woocommerce_payment_gateways', [ $this, 'addPlaceToPayGateway' ]);
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ]);
    }


    /**
    * Instanciates the PlacetoPay object providing the login and tranKey, also the url that will be
    * used for the service
    * @return PlacetoPay
    */
    protected function placetopay() {
        return new PlacetoPay([
            'login' => getenv('P2P_LOGIN'),
            'tranKey' => getenv('P2P_TRANKEY'),
            'url' => getenv('P2P_URL'),
        ]);
    }


    /**
     * Set the configuration for parent class \WC_Payment_Gateway
     * @return void
     */
    public function configPaymentMethod() {
        $this->id                   = 'placetopay';
        $this->method_title         = __( 'PlaceToPay', 'woocommerce-gateway-placetopay' );
        $this->method_description   = __( "Sells online safely and agile", 'woocommerce-gateway-placetopay' );
        $this->icon_default         = $this->assets( '/img/placetopay-logo.png' );
        $this->has_fields           = false;

        $this->initFormFields();
        $this->init_settings();
    }


    /**
     * Settings Options
     *
     * @return void
     */
    public function initFormFields() {
        $this->form_fields = array(
            'enabled' => [
                'title' 		=> __( 'Enable/Disable', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'checkbox',
                'label' 		=> __('Enable PlaceToPay payment method.', 'woocommerce-gateway-placetopay' ),
                'default' 		=> 'no',
                'description' 	=> __( 'Show in the Payment List as a payment option', 'woocommerce-gateway-placetopay' )
            ],
            'icon_checkout' => [
                'title' 		=> __('Logo en el checkout:', 'woocommerce-gateway-placetopay' ),
                'type'			=> 'text',
                'default'		=> $this->icon_default,
                'description' 	=> __('URL de la Imagen para mostrar en el carrro de compra.', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ],
            'title' => [
                'title' 		=> __('Title:', 'woocommerce-gateway-placetopay' ),
                'type'			=> 'text',
                'default' 		=> __('PlaceToPay', 'woocommerce-gateway-placetopay' ),
                'description' 	=> __('This controls the title which the user sees during checkout.', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ],
            'description' => array(
                'title' 		=> __('Description:', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'textarea',
                'default' 		=> __( 'Pay securely through PlaceToPay.','woocommerce-gateway-placetopay' ),
                'description' 	=> __('This controls the description which the user sees during checkout.', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'merchant_id' => array(
                'title' 		=> __('Merchant ID', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'text',
                'description' 	=> __('Given to Merchant by PlaceToPay', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'account_id' => array(
                'title' 		=> __('Account ID', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'text',
                'description' 	=> __('Some Countrys (Brasil, Mexico) require this ID, Gived to you by PlaceToPay on regitration.', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'apikey' => array(
                'title' 		=> __('ApiKey', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'text',
                'description' 	=>  __('Given to Merchant by PlaceToPay', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'testmode' => array(
                'title' 		=> __('Test mode', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'checkbox',
                'label' 		=> __('Enable PlaceToPay TEST Transactions.', 'woocommerce-gateway-placetopay' ),
                'default' 		=> 'no',
                'description' 	=> __('Tick to run TEST Transaction on the PlaceToPay platform', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'taxes' => array(
                'title' 		=> __('Tax Rate - Read', 'woocommerce-gateway-placetopay' ).' <a target="_blank" href="http://docs.placetopay.com/manual-integracion-web-checkout/informacion-adicional/tablas-de-variables-complementarias/">PlaceToPay Documentacion</a>',
                'type' 			=> 'text',
                'default' 		=> '0',
                'description' 	=> __('Tax rates for Transactions (IVA).', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'tax_return_base' => array(
                'title' 		=> __('Tax Return Base', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'text',
                //'options' 		=> array('0' => 'None', '2' => '2% Credit Cards Payments Return (Colombia)'),
                'default' 		=> '0',
                'description' 	=> __('Tax base to calculate IVA ', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'placetopay_language' => array(
                'title' 		=> __('Gateway Language', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'select',
                'options' 		=> array('ES' => 'ES', 'EN' => 'EN', 'PT' => 'PT'),
                'description' 	=> __('PlaceToPay Gateway Language ', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'form_method' => array(
                'title' 		=> __('Form Method', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'select',
                'default' 		=> 'POST',
                'options' 		=> array('POST' => 'POST', 'GET' => 'GET'),
                'description' 	=> __('Checkout form submition method ', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'redirect_page_id' => array(
                'title' 		=> __('Return Page', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'select',
                'options' 		=> [], //$this->get_pages(__('Select Page', 'woocommerce-gateway-placetopay' )),
                'description' 	=> __('URL of success page', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'endpoint' => array(
                'title' 		=> __('Page End Point (Woo > 2.1)', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'text',
                'default' 		=> '',
                'description' 	=> __('Return Page End Point.', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'msg_approved' => array(
                'title' 		=> __('Message for approved transaction', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'text',
                'default' 		=> __('PlaceToPay Payment Approved', 'woocommerce-gateway-placetopay' ),
                'description' 	=> __('Message for approved transaction', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'msg_pending' => array(
                'title' 		=> __('Message for pending transaction', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'text',
                'default' 		=> __('Payment pending', 'woocommerce-gateway-placetopay' ),
                'description' 	=> __('Message for pending transaction', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'msg_cancel' => array(
                'title' 		=> __('Message for cancel transaction', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'text',
                'default' 		=> __('Transaction Canceled.', 'woocommerce-gateway-placetopay' ),
                'description' 	=> __('Message for cancel transaction', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
            'msg_declined' => array(
                'title' 		=> __('Message for declined transaction', 'woocommerce-gateway-placetopay' ),
                'type' 			=> 'text',
                'default' 		=> __('Payment rejected via PlaceToPay.', 'woocommerce-gateway-placetopay' ),
                'description' 	=> __('Message for declined transaction ', 'woocommerce-gateway-placetopay' ),
                'desc_tip' 		=> true
            ),
        );
    }


    public function process_payment( $order_id ) {
        global $woocommerce;
        $order = new \WC_Order( $order_id );

        // Mark as on-hold (we're awaiting the cheque)
        $order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));

        // Reduce stock levels
        $order->reduce_order_stock();

        // Remove cart
        $woocommerce->cart->empty_cart();

        // Return thankyou redirect
        return [
            'result' => 'success',
            'redirect' => $this->get_return_url( $order )
        ];
    }


    /**
     * Check if Gateway can be display
     *
     * @return bool
     */
    public function is_available() {
        global $woocommerce;

        if( $this->enabled == "yes" ) {

            if( !$this->is_valid_currency() )
                return false;

            if( $woocommerce->version < '1.5.8' )
                return false;

            if( $this->testmode != 'yes' && ( !$this->merchant_id || !$this->account_id || !$this->apikey ) )
                return false;

            return true;
        }

        return false;
    }


    /**
     * Add the Gateway to WooCommerce
     **/
    public function addPlaceToPayGateway( $methods ) {
        $methods[] = self::class;
        return $methods;
    }


    /**
     * Return the assets path
     * @param  string $path Optional relative path to concatenate with assets path
     * @return string
     */
    public function assets( $path = null ) {
        $assets_path = $this->plugin_path . 'assets';

        if( $path === null )
            return $assets_path;

        return $assets_path . $path;
    }
}
