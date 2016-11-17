<?php namespace PlacetoPay;


if( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

use \Exception;
use \WC_Order;
use \WC_Payment_Gateway;
use Dnetix\Redirection\PlacetoPay;
use Dnetix\Redirection\Validators\Currency;

/**
 * @package \PlacetoPay
 */
class GatewayMethod extends WC_Payment_Gateway {

    /**
     * Constant key for the session requestId
     * @var string
     */
    const SESSION_REQ_ID = 'placetopay_request_id';

    /**
     * PlacetoPay uri endpoint namespace via wordpress for the notification of the service
     *
     * @var array
     */
    const PAYMENT_ENDPOINT_NAMESPACE = 'placetopay-payment/v2';

    /**
    * PlacetoPay uri endpoint namespace via wordpress for the notification of the service
    *
    * @var array
    */
    const PAYMENT_ENDPOINT_CALLBACK = '/callback/';

    /**
     * URI for production service
     * @var string
     */
    private $prodUri = 'https://secure.placetopay.com/redirection';

    /**
     * URI for testing enviroment
     * @var string
     */
    private $testUri = 'https://test.placetopay.com/redirection';

    /**
     * Instance of placetopay to manage the connection with the webservice
     * @var \Dnetix\Redirection\PlacetoPay
     */
    private $placetopay;

    /**
     * Transactional key for connection with web services placetopay
     * @var string
     */
    private $tran_key;


    /**
     * Constructor
     */
    function __construct() {
        $this->configPaymentMethod();
        $this->init();
        $this->initPlacetoPay();
    }


    /**
    * Set the configuration for parent class \WC_Payment_Gateway
    *
    * @return void
    */
    public function configPaymentMethod() {
        $this->id                   = 'placetopay';
        $this->method_title         = __( 'PlacetoPay', 'woocommerce-gateway-placetopay' );
        $this->method_description   = __( "Sells online safely and agile", 'woocommerce-gateway-placetopay' );
        $this->icon                 = WC_Gateway_PlacetoPay::assets( '/images/placetopay.png', 'url' );
        $this->has_fields           = false;

        // Init settings
        $this->initFormFields();
        $this->settings[ 'endpoint' ] = home_url( '/wp-json/' ) . self::getPaymentEndpoint();

        $this->endpoint 		= $this->settings[ 'endpoint' ];
        $this->testmode         = $this->get_option( 'testmode' );
        $this->title            = $this->get_option( 'title' );
        $this->description 		= $this->get_option( 'description' );
        $this->login 		    = $this->get_option( 'login' );
        $this->tran_key         = $this->get_option( 'tran_key' );
        $this->redirect_page_id = $this->get_option( 'redirect_page_id' );
        $this->form_method 		= $this->get_option( 'form_method' );

        $this->merchant_phone   = $this->get_option('merchant_phone');
        $this->merchant_email   = $this->get_option('merchant_email');
        $this->msg_approved		= $this->get_option( 'msg_approved' );
        $this->msg_pending		= $this->get_option( 'msg_pending' );
        $this->msg_declined		= $this->get_option( 'msg_declined' );
        $this->msg_cancel 		= $this->get_option( 'msg_cancel' );

        $this->currency 		= get_woocommerce_currency();
        $this->currency         = Currency::isValidCurrency( $this->currency ) ? $this->currency : Currency::CUR_COP;

        if( $this->testmode == "yes" ) {
            $this->debug = "yes";
            $this->uri_service = $this->testUri;
            $this->log = ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ? new \WC_Logger(): $woocommerce->logger() );

        } else {
            $this->debug = 'no';
            $this->uri_service = $this->prodUri;
        }
    }


    /**
     * Configuraction initial
     *
     * @return void
     */
    public function init() {
        add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'receiptPage' ]);
        add_action( 'woocommerce_api_' . $this->getClassName( true ), [ $this, 'checkResponse' ]);
        add_action( 'placetopay_init', [ $this, 'successfulRequest' ]);

        // Register endpoint for placetopay
        add_action( 'rest_api_init', function() {
            register_rest_route( self::PAYMENT_ENDPOINT_NAMESPACE, self::PAYMENT_ENDPOINT_CALLBACK, [
                'methods' => 'POST',
                'callback' => [ $this, 'endpointPlacetoPay' ]
            ]);
        } );

        if( $this->enabled === 'yes' ) {
            add_action( 'woocommerce_before_checkout_form', [ $this, 'checkoutMessage' ], 5 );
        }

        if( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ &$this, 'process_admin_options' ]);
            return;
        }

        add_action( 'woocommerce_update_options_payment_gateways', [ &$this, 'process_admin_options' ]);
    }


    /**
     * Settings Options
     *
     * @return void
     */
    public function initFormFields() {
        $this->form_fields = include( __DIR__ . '/config/form-fields.php' );
        $this->init_form_fields();
        $this->init_settings();
    }


    /**
     * Endpoint for the notification of PlacetoPay
     * @param  \WP_REST_Request $params  Params given by route endpoint
     * @return void
     */
    public function endpointPlacetoPay( \WP_REST_Request $req ) {
        $data = $req->get_params();

        if( !empty( $data[ 'signature' ] ) && !empty( $data[ 'requestId' ] ) ) {
            $notification = new \Dnetix\Redirection\Message\Notification( $data, $this->tran_key );

            if( !$notification->isValidNotification() ) {
                if( $this->testmode == "yes" )
                    return $notification->makeSignature();

                return;
            }

            $transactionInfo = $this->placetopay->query( $notification->requestId() );
            $this->returnProcess([ 'key' => $data[ 'reference' ] ], $transactionInfo, true );

            return [ 'success' => true ];
        }

        return null;
        die();
    }


    /**
     * Process the payment for a order
     *
     * @param  int $orderId
     * @return array
     */
    public function process_payment( $orderId ) {
        $order = new WC_Order( $orderId );

        $ref = $order->order_key . '-' . time();
        $productinfo = "Order $orderId";

        $redirectUrl = ( $this->redirect_page_id == "" || $this->redirect_page_id == 0 )
            ? get_site_url() . "/"
            : get_permalink( $this->redirect_page_id );

        //For wooCoomerce 2.0
        $redirectUrl = add_query_arg( 'wc-api', $this->getClassName(), $redirectUrl );
        $redirectUrl = add_query_arg( 'order_id', $orderId, $redirectUrl );
        // $redirectUrl = add_query_arg( '', $this->endpoint, $redirectUrl );

        $req = [
            'expiration'=> date( 'c', strtotime( '+2 days' ) ),
            'returnUrl' => $redirectUrl . '&key=' . $ref,
            'ipAddress' => ( new RemoteAddress() )->getIpAddress(),
            'userAgent' => $_SERVER[ 'HTTP_USER_AGENT' ],
            'buyer'     => [
                'name'      => $order->billing_first_name,
                'surname'   => $order->billing_last_name,
                'email'     => $order->billing_email,
                'company'   => $order->billing_company,
                'mobile'    => $order->billing_phone,
                'address'   => [
                    'street'    => $order->billing_address_1 . ' ' . $order->billing_address_2,
                    'city'      => $order->billing_city,
                    'state'     => $order->billing_state,
                    'country'   => $order->billing_country,
                    'postalCode'=> $order->postcode
                ]
            ],
            'payment' => [
                'reference'     => $order->order_key,
                'description'   => $productinfo,
                'amount'        => [
                    'currency' => $this->currency,
                    'total'    => floatval( $order->order_total )
                ]
            ]
        ];

        try {
            $res = $this->placetopay->request( $req );

            if( $res->isSuccessful() ) {
                // Store the requestId in the session
                WC()->session->set( self::SESSION_REQ_ID, $res->requestId() );

                // Redirect the client to the processUrl or display it on the JS extension
                $processUrl = urlencode( $res->processUrl() );

                return [
                    'result'    => 'success',
                    'redirect'  => add_query_arg( 'redirect-url', $processUrl, $order->get_checkout_payment_url( true ) )
                ];
            }

            $this->logger( __( 'Payment error:', 'woothemes' ) . $res->status()->message(), 'error' );
            wc_add_notice( __( 'Payment error:', 'woothemes' ) . $res->status()->message(), 'error' );

        } catch( Exception $ex ) {
            $this->logger( $ex->getMessage(), 'error' );
            wc_add_notice( __( 'Payment error', 'woothemes' ), 'error' );
        }
    }


    /**
     * After of process_payment, generate the PlacetoPay block modal with form datas to sending
     *
     * @param mixed $order
     * @return string
     */
    public function receiptPage( $orderId ) {
        global $woocommerce, $wpdb;

        $this->logger( 'order #' . $orderId, 'receiptPage' );

        try {
            $order = new WC_Order( $orderId );
            $requestId = WC()->session->get( self::SESSION_REQ_ID );
            $transactionInfo = $this->placetopay->query( $requestId );

            $authorizationCode = count( $transactionInfo->payment ) > 0
                ? array_map( function( $trans ) {
                    return $trans->authorization();
                }, $transactionInfo->payment )
                : [];

            // Payment Details
            if( count( $authorizationCode ) > 0 )
                update_post_meta( $orderId, '_p2p_authorization', implode( ",", $authorizationCode ) );


            // Add information to the order to notify that exit to PlacetoPay
            // and invalidates the shopping cart
            $order->update_status( 'on-hold', __( 'Redirecting to Place to Pay', 'woocommerce-gateway-placetopay' ) );

            // Add the order to the pending list
            $wpdb->insert( $wpdb->prefix . 'woocommerce_placetopay_pending', [
                'order_id'      => $order->id,
                'customer_id'   => $order->user_id,
                'timestamp'     => time(),
                'currency'      => $this->currency,
                'amount'        => $order->get_total()
            ]);

            $woocommerce->cart->empty_cart();

            $code = 'jQuery("body").block({
                message: "' . esc_js( __('We are now redirecting you to Place to Pay to make payment, if you are not redirected please press the bottom.', 'woocommerce-gateway-placetopay' ) ) . '",
                baseZ: 99999,
                overlayCSS: { background: "#fff", opacity: 0.6 },
                css: {
                    padding:        "20px",
                    zindex:         "9999999",
                    textAlign:      "center",
                    color:          "#555",
                    border:         "3px solid #aaa",
                    backgroundColor:"#fff",
                    cursor:         "wait",
                    lineHeight:		"24px",
                }
            });

            setTimeout( function() {
                window.location.href = "' . $_REQUEST[ 'redirect-url' ] .'";
            }, 1000 );
            ';

            if( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
                wc_enqueue_js( $code );
            } else {
                $woocommerce->add_inline_js( $code );
            }

        } catch( Exception $ex ) {
            $this->logger( $ex->getMessage(), 'error' );
        }
    }


    /**
     * Check if the response server is correct, callback
     * @return void
     */
    public function checkResponse() {
        @ob_clean();

        if( !empty( $_REQUEST ) ) {
            header( 'HTTP/1.1 200 OK' );
            do_action( "placetopay_init", $_REQUEST );

            return;
        }

        wp_die( __( "PlacetoPay Request Failure", 'woocommerce-gateway-placetopay' ) );
    }


    /**
     * After checkResponse, Process PlacetoPay response and update order information
     *
     * @param array $req    Response datas in array format
     * @return void
     */
    public function successfulRequest( $req ) {
        global $woocommerce;

        // When the user is returned to the page specificated by redirectUrl
        if( !empty( $req[ 'key' ] ) && !empty( $req[ 'wc-api' ] ) ) {
            $requestId = WC()->session->get( self::SESSION_REQ_ID );
            $transactionInfo = $this->placetopay->query( $requestId );

            $this->returnProcess( $req, $transactionInfo );
        }

        //For wooCoomerce 2.0
        $redirectUrl = add_query_arg([
            'msg'   => urlencode( __( 'There was an error on the request. please contact the website administrator.', 'placetopay' ) ),
            'type'  => $this->msg[ 'class' ]
        ], $woocommerce->cart->get_checkout_url() );

        wp_redirect( $redirectUrl );
        exit;
    }


    /**
     * Process page of response
     *
     * @param  array $req             Rename for $_REQUEST
     * @param  array $transactionInfo Information of transaction in placetopay
     * @param  boolean $isCallback    Define if is notification or return request
     *
     * @return void
     */
    public function returnProcess( $req, $transactionInfo, $isCallback = false ) {
        global $woocommerce;

        $order = $this->getOrder( $req );
        $statusEnt = $transactionInfo->status();
        $status = $statusEnt->status();

        // Register status PlacetoPay for the order
        update_post_meta( $order->id, '_p2p_status', $status );

        // We are here so lets check status and do actions
        switch( $status ) {
            case $statusEnt::ST_APPROVED :
            case $statusEnt::ST_PENDING :

                // Check order not already completed
                if( $order->status == 'completed' ) {
                    $this->logger( __( 'Aborting, Order #' . $order->id . ' is already complete.', 'woocommerce-gateway-placetopay' ) );

                    if( $isCallback )
                        return;

                    exit;
                }

                $totalAmount = $transactionInfo->request()->payment()->amount()->total();
                $payerEmail = $transactionInfo->request()->payer()->email();

                $paymentMethodName = count( $transactionInfo->payment ) > 0
                    ? array_map( function( $trans ) {
                        return $trans->paymentMethodName();
                    }, $transactionInfo->payment )
                    : [];

                $authorizationCode = count( $transactionInfo->payment ) > 0
                    ? array_map( function( $trans ) {
                        return $trans->authorization();
                    }, $transactionInfo->payment )
                    : [];


                // Validate Amount
                if( $order->get_total() != floatval( $totalAmount ) ) {
                    $msg = sprintf( __( 'Validation error: PlacetoPay amounts do not match (gross %s).', 'woocommerce-gateway-placetopay' ), $totalAmount );
                    $order->update_status( 'on-hold', $msg );

                    $this->msg[ 'message' ] = $msg;
                    $this->msg[ 'class' ] = 'woocommerce-error';
                }

                 // Payment Details
                if( count( $authorizationCode ) > 0 )
                    update_post_meta( $order->id, '_p2p_authorization', implode( ",", $authorizationCode ) );

                if( !empty( $payerEmail ) )
                    update_post_meta( $order->id, __( 'Payer PlacetoPay email', 'woocommerce-gateway-placetopay' ), $payerEmail );

                if( count( $paymentMethodName ) > 0 )
                    update_post_meta( $order->id, __( 'Payment type', 'woocommerce-gateway-placetopay' ), implode( ",", $paymentMethodName ) );

                if( $status == $statusEnt::ST_APPROVED ) {
                    $order->add_order_note( __( 'PlacetoPay payment approved', 'woocommerce-gateway-placetopay' ) );
                    $this->msg[ 'message' ] = $this->msg_approved;
                    $this->msg[ 'class' ] = 'woocommerce-message';

                    $order->payment_complete();
                    $this->logger( 'Order # ' . $order->id, 'Payment approved' );

                } else {
                    $order->update_status( 'on-hold', sprintf( __( 'Payment pending: %s', 'woocommerce-gateway-placetopay' ), $status ) );
                    $this->msg[ 'message' ] = $this->msg_pending;
                    $this->msg[ 'class' ] = 'woocommerce-info';
                }
            break;

            // Order failed
            case $statusEnt::ST_REJECTED :
            case $statusEnt::ST_ERROR :
                $order->update_status( 'failed', sprintf( __( 'Payment rejected via PlacetoPay. Error type: %s', 'woocommerce-gateway-placetopay' ), $status ) );
                $this->msg[ 'message' ] = $this->msg_declined;
                $this->msg[ 'class' ] = 'woocommerce-error';
            break;

            default:
                $order->update_status( 'failed', sprintf( __( 'Payment rejected via PlacetoPay.', 'woocommerce-gateway-placetopay' ), $status ) );
                $this->msg[ 'message' ] = $this->msg_cancel;
                $this->msg[ 'class' ] = 'woocommerce-error';
            break;
        }

        // Is notification request
        if( $isCallback )
            return;

        $redirectUrl = $this->getRedirectUrl( $order );
        //For wooCoomerce 2.0
        $redirectUrl = add_query_arg([
            'msg'   => urlencode( $this->msg[ 'message' ] ),
            'type'  => $this->msg[ 'class' ]
        ], $redirectUrl );

        wp_redirect( $redirectUrl );
        exit;
    }


    /**
     * Return redirect url
     * @return string
     */
    public function getRedirectUrl( $order ) {

        if( $this->redirect_page_id == 'default' || !$this->redirect_page_id ) {
            $this->logger( 'is default = ' . $this->redirect_page_id == 'default', 'Called by: getRedirectUrl' );
            $this->logger( 'is empty or zero = ' . !$this->redirect_page_id, 'Called by: getRedirectUrl' );
            $this->logger( 'redirect_page_id = ' . $this->redirect_page_id, 'Called by: getRedirectUrl' );
            return $order->get_checkout_order_received_url();
        }

        if( $this->redirect_page_id === 'my-orders' ) {
            return wc_get_account_endpoint_url( get_option( 'woocommerce_myaccount_orders_endpoint', 'orders' ) );
        }

        return get_permalink( $this->redirect_page_id );
    }


    /**
     *  Get order instance with a given order key
     *
     * @param mixed $req
     * @return \WC_Order
     */
    public function getOrder( $req ) {
        $orderId = isset( $req[ 'order_id' ] ) ? (int) $req[ 'order_id' ] : null;
        $key = isset( $req[ 'key' ] ) ? $req[ 'key' ] : '';
        $orderKey = explode( '-', $key );
        $orderKey = $orderKey[ 0 ] ? $orderKey[ 0 ] : $orderKey;

        $order = new WC_Order( $orderId );

        if( !isset( $order->id ) || $order->id === 0 ) {
            $orderId = woocommerce_get_order_id_by_order_key( $orderKey );
            $order = new WC_Order( $orderId );
        }

        // Validate key
        if ( $order->order_key !== $orderKey ) {
            $this->logger( __( 'Error: Order Key does not match invoice.', 'woocommerce-gateway-placetopay' ) );
            exit;
        }

        return $order;
    }


    /**
     * Check if it has transactions with status pending and generate a message warning
     * @return void
     */
    public function checkoutMessage() {
        $userId = get_current_user_id();

        if( $userId ) {
            // obtiene los últimos pedidos del cliente para revisar si tiene uno pendiente
            $customerOrders = get_posts( apply_filters( 'woocommerce_my_account_my_orders_query', [
                'numberposts'       => 5,
                'meta_key'          => '_customer_user',
                'meta_value'        => $userId,
                'post_type'         => 'shop_order',
                'post_status'       => 'publish',
                'shop_order_status' => 'on-hold'
            ] ) );

            if( $customerOrders ) {
                foreach( $customerOrders as $orderId ) {
                    $order = new WC_Order();
                    $order->populate( $orderId );

                    if( ( $order->status == 'pending' ) || ( $order->status == 'on-hold' ) ) {
                        $authcode = get_post_meta( $order->id, '_p2p_authorization', true );

                        $msg = 'En este momento su orden # %s presenta un proceso de pago cuya transacción se encuentra PENDIENTE de recibir confirmación por parte de su entidad financiera,
                        por favor espere unos minutos y vuelva a consultar más tarde para verificar si su pago fue confirmado de forma exitosa.
                        Si desea mayor información sobre el estado actual de su operación puede comunicarse a nuestras líneas de atención al cliente %s o enviar un correo electrónico a %s
                        y preguntar por el estado de la transacción: %s';

                        $message = sprintf(
                            __( $msg, 'woocommerce-gateway-placetopay' ),
                            ( string ) $order->id,
                            $this->merchant_phone,
                            $this->merchant_email,
                            ( $authcode == '' ? '': sprintf( __( 'con Authorization/CUS #%s', 'woocommerce-gateway-placetopay' ), $authcode ) )
                        );

                        echo "<table class='shop_table order_details'>
                            <tbody>
                                <tr>
                                    <th scope='row'>{$message}</th>
                                </tr>
                            </tbody>
                        </table>";

                        return;
                    }
                }
            }
        }
    }


    /**
     * Check if Gateway can be display
     *
     * @return bool
     */
    public function is_available() {
        global $woocommerce;

        if( $this->enabled == "yes" ) {
            if( !Currency::isValidCurrency( $this->currency ) )
                return false;

            if( $woocommerce->version < '1.5.8' )
                return false;

            if( $this->testmode != 'yes' && ( !$this->login || !$this->tran_key ) )
                return false;

            return true;
        }

        return false;
    }


    /**
     * Manage the log instance if the debug is actived
     * @return void
     */
    public function logger( $message, $type = null ) {
        if( $this->debug == 'yes' ) {
            $this->log->add( 'PlacetoPay', ( $type ? "($type): " : '' ) . $message );
        }
    }


    /**
     * Get pages for return page setting
     *
     * @param  boolean $title  Title of the page
     * @param  boolean $indent Identation to show in dropdown list
     * @return array
     */
    public function getPages( $title = false, $indent = true ) {
        $pages = get_pages( 'sort_column=menu_order' );
        $myAccountPageId = get_option( 'woocommerce_myaccount_page_id' );

        $pageList = [
            'default'   => __( 'Default Page', 'woocommerce-gateway-placetopay' ),
        ];

        if( $title )
            $pageList[] = $title;

        $pageList[ $myAccountPageId ] = 'My Account';
        $pageList[ 'my-orders' ] = ' -- ' . __( 'My Orders', 'woocommerce-gateway-placetopay' );

        foreach( $pages as $page ) {
            $prefix = '';

            // show indented child pages
            if( $indent ) {
                $hasParent = $page->post_parent;

                while( $hasParent ) {
                    $prefix .=  ' -- ';
                    $nextPage = get_page( $hasParent );
                    $hasParent = $nextPage->post_parent;
                }
            }

            // add to page list array array
            $pageList[ $page->ID ] = $prefix . $page->post_title;
        }

        return $pageList;
    }


    public static function getPaymentEndpoint() {
        return self::PAYMENT_ENDPOINT_NAMESPACE . self::PAYMENT_ENDPOINT_CALLBACK;
    }


    /**
     * Instantiates a PlacetoPay object providing the login and tranKey,
     * also the url that will be used for the service
     *
     * @return \PlacetoPay
     */
    private function initPlacetoPay() {
        $this->placetopay = new PlacetoPay([
            'login'     => $this->login,
            'tranKey'   => $this->tran_key,
            'url'       => $this->uri_service,
        ]);
    }


    /**
     * Get the class name with namespaces modificated
     *
     * @param  boolean $lowercase
     * @return string
     */
    private function getClassName( $lowercase = false ) {
        return str_replace( "\\", "_", $lowercase ? strtolower( get_class( $this ) ) : get_class( $this ) );
    }
}
