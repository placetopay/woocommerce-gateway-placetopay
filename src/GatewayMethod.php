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
        $this->icon_default         = WC_Gateway_PlacetoPay::assets( '/img/placetopay-logo.png' );
        $this->has_fields           = false;

        // Init settings
        $this->initFormFields();

        $this->testmode         = $this->get_option( 'testmode' );
        $this->title            = $this->get_option( 'title' );
        $this->description 		= $this->get_option( 'description' );
        $this->login 		    = $this->get_option( 'login' );
        $this->tran_key         = $this->get_option( 'tran_key' );
        $this->redirect_page_id = $this->get_option( 'redirect_page_id' );
        $this->endpoint 		= $this->get_option( 'endpoint' );
        $this->form_method 		= $this->get_option( 'form_method' );
        $this->currency 		= get_woocommerce_currency();
        $this->currency         = Currency::isValidCurrency( $this->currency ) ? $this->currency : Currency::CUR_COP;
        $this->debug            = 'no';

        if( $this->testmode == "yes" )
            $this->debug = "yes";

        if( $this->debug == 'yes' )
            $this->log = ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ? new \WC_Logger(): $woocommerce->logger() );
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

        if( $this->enabled === 'yes' )
            add_action( 'woocommerce_before_checkout_form', [ $this, 'checkoutMessage' ], 5 );

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
        $redirectUrl = add_query_arg( '', $this->endpoint, $redirectUrl );

        $req = [
            'expiration'=> date( 'c', strtotime( '+2 days' ) ),
            'returnUrl' => $redirectUrl . 'key=' . $ref,
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

        // {
        //   "status": {
        //     "status": "APPROVED",
        //     "message": "",
        //     "reason": "",
        //     "date": "2016-09-15T13:49:01-05:00"
        //   },
        //   "requestId": 58,
        //   "reference": “ORDER-1000”,
        //   "signature": “feb3e7cc76939c346f9640573a208662f30704ab”
        // }

        // Callback when the service is sending the datas to the notificationUrl
        if( !empty( $req[ 'signature' ] ) && !empty( $req[ 'requestId' ] ) ) {
            $transactionInfo = $this->placetopay->query( $req[ 'requestId' ] );
            $this->confirmationProcess( $req, $transactionInfo );

        } else {
            // When the user is returned to the page specificated by redirectUrl
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
     * @param  array $req
     * @return void
     */
    public function returnProcess( $req, $transactionInfo ) {
        global $woocommerce;

        $order = $this->getOrder( $req );
        $statusEnt = $transactionInfo->status();

        $state = $req[ 'transactionState' ];

        dd( $statusEnt );

        // We are here so lets check status and do actions
        switch( $statusEnt->status() ) {
            case $statusEnt::ST_APROVED :
            case $statusEnt::ST_PENDING :

                // Check order not already completed
                if( $order->status == 'completed' ) {
                    $this->logger( __( 'Aborting, Order #' . $order->id . ' is already complete.', 'woocommerce-gateway-placetopay' ) );
                    exit;
                }

                dd( $order->get_total(), $transactionInfo );

                // Validate Amount
                if( $order->get_total() != $req['TX_VALUE'] ) {
                    $order->update_status( 'on-hold', sprintf( __( 'Validation error: PlacetoPay amounts do not match (gross %s).', 'woocommerce-gateway-placetopay' ), $req['TX_VALUE'] ) );

                    $this->msg[ 'message' ] = sprintf( __( 'Validation error: PlacetoPay amounts do not match (gross %s).', 'woocommerce-gateway-placetopay' ), $req['TX_VALUE'] );
                    $this->msg[ 'class' ] = 'woocommerce-error';
                }

                // Validate Merchand id
                if ( strcasecmp( trim( $req['merchantId'] ), trim( $this->merchant_id ) ) != 0 ) {
                    $order->update_status( 'on-hold', sprintf( __( 'Validation error: Payment in PlacetoPay comes from another id (%s).', 'woocommerce-gateway-placetopay' ), $req['merchantId'] ) );
                    $this->msg['message'] = sprintf( __( 'Validation error: Payment in PlacetoPay comes from another id (%s).', 'woocommerce-gateway-placetopay' ), $req['merchantId'] );
                    $this->msg['class'] = 'woocommerce-error';

                }

                 // Payment Details
                if ( ! empty( $req['buyerEmail'] ) )
                    update_post_meta( $order->id, __('Payer PlacetoPay email', 'woocommerce-gateway-placetopay' ), $req['buyerEmail'] );
                if ( ! empty( $req['transactionId'] ) )
                    update_post_meta( $order->id, __('Transaction ID', 'woocommerce-gateway-placetopay' ), $req['transactionId'] );
                if ( ! empty( $req['trazabilityCode'] ) )
                    update_post_meta( $order->id, __('Trasability Code', 'woocommerce-gateway-placetopay' ), $req['trazabilityCode'] );
                /*if ( ! empty( $req['last_name'] ) )
                    update_post_meta( $order->id, 'Payer last name', $req['last_name'] );*/
                if ( ! empty( $req['lapPaymentMethodType'] ) )
                    update_post_meta( $order->id, __('Payment type', 'woocommerce-gateway-placetopay' ), $req['lapPaymentMethodType'] );

                if ( $codes[$state] == 'APPROVED' ) {
                    $order->add_order_note( __( 'PlacetoPay payment approved', 'woocommerce-gateway-placetopay' ) );
                    $this->msg['message'] = $this->msg_approved;
                    $this->msg['class'] = 'woocommerce-message';
                    $order->payment_complete();
                } else {
                    $order->update_status( 'on-hold', sprintf( __( 'Payment pending: %s', 'woocommerce-gateway-placetopay' ), $codes[$state] ) );
                    $this->msg['message'] = $this->msg_pending;
                    $this->msg['class'] = 'woocommerce-info';
                }
            break;

            case 'DECLINED':
            case 'EXPIRED':
            case 'ERROR':
                // Order failed
                $order->update_status( 'failed', sprintf( __( 'Payment rejected via PlacetoPay. Error type: %s', 'woocommerce-gateway-placetopay' ), ( $codes[$state] ) ) );
                    $this->msg['message'] = $this->msg_declined ;
                    $this->msg['class'] = 'woocommerce-error';
            break;

            default:
                $order->update_status( 'failed', sprintf( __( 'Payment rejected via PlacetoPay.', 'woocommerce-gateway-placetopay' ), ( $codes[$state] ) ) );
                    $this->msg['message'] = $this->msg_cancel ;
                    $this->msg['class'] = 'woocommerce-error';
            break;
        }

        $redirectUrl = ( $this->redirect_page_id == 'default'
            || $this->redirect_page_id == ""
            || $this->redirect_page_id == 0
        )
            ? $order->get_checkout_order_received_url()
            : get_permalink( $this->redirect_page_id );

        //For wooCoomerce 2.0
        $redirectUrl = add_query_arg([
            'msg'   => urlencode( $this->msg[ 'message' ] ),
            'type'  => $this->msg[ 'class' ]
        ], $redirectUrl );

        wp_redirect( $redirectUrl );
        exit;
    }


    /**
     * Process page of confirmation
     *
     * @param  array $req
     * @return void
     */
    public function confirmationProcess( $req ) {
        global $woocommerce;
        $order = $this->getOrder( $req );

        $codes=array(
            '1'     => 'CAPTURING_DATA',
            '2'     => 'NEW',
            '101'   => 'FX_CONVERTED',
            '102'   => 'VERIFIED',
            '103'   => 'SUBMITTED',
            '4'     => 'APPROVED',
            '6'     => 'DECLINED',
            '104'   => 'ERROR',
            '7'     => 'PENDING',
            '5'     => 'EXPIRED'
        );

        $this->logger( 'Found order #' . $order->id );
        $state = $req[ 'state_pol' ];
        $this->logger( 'Payment status: ' . $codes[ $state ] );

        // We are here so lets check status and do actions
        switch ( $codes[$state] ) {
            case 'APPROVED' :
            case 'PENDING' :

                // Check order not already completed
                if ( $order->status == 'completed' ) {
                     if ( 'yes' == $this->debug )
                        $this->logger( __('Aborting, Order #' . $order->id . ' is already complete.', 'woocommerce-gateway-placetopay' ) );
                     exit;
                }

                // Validate Amount
                if ( $order->get_total() != $req['value'] ) {
                    $order->update_status( 'on-hold', sprintf( __( 'Validation error: PlacetoPay amounts do not match (gross %s).', 'woocommerce-gateway-placetopay' ), $req['value'] ) );

                    $this->msg['message'] = sprintf( __( 'Validation error: PlacetoPay amounts do not match (gross %s).', 'woocommerce-gateway-placetopay' ), $req['value'] );
                    $this->msg['class'] = 'woocommerce-error';
                }

                // Validate Merchand id
                if ( strcasecmp( trim( $req['merchant_id'] ), trim( $this->merchant_id ) ) != 0 ) {

                    $order->update_status( 'on-hold', sprintf( __( 'Validation error: Payment in PlacetoPay comes from another id (%s).', 'woocommerce-gateway-placetopay' ), $req['merchant_id'] ) );
                    $this->msg['message'] = sprintf( __( 'Validation error: Payment in PlacetoPay comes from another id (%s).', 'woocommerce-gateway-placetopay' ), $req['merchant_id'] );
                    $this->msg['class'] = 'woocommerce-error';
                }

                 // Payment details
                if ( ! empty( $req['email_buyer'] ) )
                    update_post_meta( $order->id, __('PlacetoPay Client email', 'woocommerce-gateway-placetopay' ), $req['email_buyer'] );
                if ( ! empty( $req['transaction_id'] ) )
                    update_post_meta( $order->id, __('Transaction ID', 'woocommerce-gateway-placetopay' ), $req['transaction_id'] );
                if ( ! empty( $req['reference_pol'] ) )
                    update_post_meta( $order->id, __('Trasability Code', 'woocommerce-gateway-placetopay' ), $req['reference_pol'] );
                if ( ! empty( $req['sign'] ) )
                    update_post_meta( $order->id, __('Tash Code', 'woocommerce-gateway-placetopay' ), $req['sign'] );
                if ( ! empty( $req['ip'] ) )
                    update_post_meta( $order->id, __('Transaction IP', 'woocommerce-gateway-placetopay' ), $req['ip'] );

                update_post_meta( $order->id, __('Extra Data', 'woocommerce-gateway-placetopay' ), 'response_code_pol: '.$req['response_code_pol'].' - '.'state_pol: '.$req['state_pol'].' - '.'payment_method: '.$req['payment_method'].' - '.'transaction_date: '.$req['transaction_date'].' - '.'currency: '.$req['currency'] );


                if ( ! empty( $req['payment_method_type'] ) )
                    update_post_meta( $order->id, __('Payment type', 'woocommerce-gateway-placetopay' ), $req['payment_method_type'] );

                if ( $codes[$state] == 'APPROVED' ) {
                    $order->add_order_note( __( 'PlacetoPay payment approved', 'woocommerce-gateway-placetopay' ) );
                    $this->msg['message'] =  $this->msg_approved;
                    $this->msg['class'] = 'woocommerce-message';
                    $order->payment_complete();

                    $this->logger( __('Payment complete.', 'woocommerce-gateway-placetopay' ) );

                } else {
                    $order->update_status( 'on-hold', sprintf( __( 'Payment pending: %s', 'woocommerce-gateway-placetopay' ), $codes[$state] ) );
                    $this->msg['message'] = $this->msg_pending;
                    $this->msg['class'] = 'woocommerce-info';
                }
            break;

            case 'DECLINED' :
            case 'EXPIRED' :
            case 'ERROR' :
            case 'ABANDONED_TRANSACTION':
                // Order failed
                $order->update_status( 'failed', sprintf( __( 'Payment rejected via PlacetoPay. Error type: %s', 'woocommerce-gateway-placetopay' ), ( $codes[$state] ) ) );
                    $this->msg['message'] = $this->msg_declined ;
                    $this->msg['class'] = 'woocommerce-error';
            break;

            default :
                $order->update_status( 'failed', sprintf( __( 'Payment rejected via PlacetoPay.', 'woocommerce-gateway-placetopay' ), ( $codes[$state] ) ) );
                    $this->msg['message'] = $this->msg_cancel ;
                    $this->msg['class'] = 'woocommerce-error';
            break;
        }
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

            if( $custmerOrders ) {
                foreach ($customerOrders as $orderId) {
                    $order = new WC_Order();
                    $order->populate($orderId);
                    if (($order->status == 'pending') || ($order->status == 'on-hold')) {
                        $authcode = get_post_meta($order->id, '_p2p_authorization', true);
                        $message = sprintf(
                            __( 'The order # %s is awaiting confirmation from your bank, Please wait a few minutes to check and see if your payment has been approved. For more information please contact our call center %s or via email %s and ask for the status of the transaction %s.', 'woocommerce-gateway-placetopay'),
                            ( string ) $order->id,
                            $this->merchantPhone,
                            $this->merchantEmail,
                            ( ( $authcode == '' ) ? '': sprintf( __( 'with Authorization/tracking %s', 'woocommerce-gateway-placetopay' ), $authcode ) ) );

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

        $pageList = [
            'default' => __( 'Default Page', 'woocommerce-gateway-placetopay' )
        ];

        if( $title )
            $pageList[] = $title;

        foreach( $pages as $page ) {
            $prefix = '';

            // show indented child pages
            if( $indent ) {
                $hasParent = $page->post_parent;

                while( $hasParent ) {
                    $prefix .=  ' - ';
                    $nextPage = get_page( $hasParent );
                    $hasParent = $nextPage->post_parent;
                }
            }

            // add to page list array array
            $pageList[ $page->ID ] = $prefix . $page->post_title;
        }

        return $pageList;
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
            'url'       => 'http://redirection.dnetix.co/',
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
