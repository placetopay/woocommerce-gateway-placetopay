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
        // add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'processResponse' ]);
        add_action( 'woocommerce_api_' . $this->getClassName( true ), [ $this, 'checkResponse' ]);
        add_action( 'placetopay_init', [ $this, 'successfulRequest' ]);

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
        $productinfo = "Order $order_id";

        $redirectUrl = ( $this->redirect_page_id == "" || $this->redirect_page_id == 0 )
            ? get_site_url() . "/"
            : get_permalink( $this->redirect_page_id );

        //For wooCoomerce 2.0
        $redirectUrl = add_query_arg( 'wc-api', $this->getClassName(), $redirectUrl );
        $redirectUrl = add_query_arg( 'order_id', $order_id, $redirectUrl );
        $redirectUrl = add_query_arg( '', $this->endpoint, $redirectUrl );

        $req = [
            'expiration'=> date( 'c', strtotime( '+2 days' ) ),
            'returnUrl' => $redirectUrl . '?key=' . $ref,
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

        $this->logger( 'process_redirect - order #' . $order_id );

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
     * @param array $posted    Response datas in array format
     * @return void
     */
    public function successfulRequest( $req ) {
        global $woocommerce;

        $this->logger( json_encode( $req ), 'json' );
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

        dd( $req );

        if( !empty( $req[ 'transactionState' ] ) && !empty( $req[ 'referenceCode' ] ) ) {
            $this->returnProcess( $req );
        }

        if( !empty( $req[ 'state_pol' ] ) && !empty( $req[ 'reference_sale' ] ) ) {
            $this->confirmationProcess( $req );
        }

        $redirectUrl = $woocommerce->cart->get_checkout_url();

        //For wooCoomerce 2.0
        $redirectUrl = add_query_arg([
            'msg'   => urlencode( __( 'There was an error on the request. please contact the website administrator.', 'placetopay' ) ),
            'type'  => $this->msg[ 'class' ]
        ], $redirectUrl );

        wp_redirect( $redirectUrl );
        exit;
    }


    /**
     * Process page of response
     *
     * @param  array $posted
     * @return void
     */
    public function returnProcess( $posted ) {
        global $woocommerce;

        $order = $this->getOrder( $posted );

        $codes = [
            '4'     => 'APPROVED',
            '6'     => 'DECLINED',
            '104'   => 'ERROR',
            '5'     => 'EXPIRED',
            '7'     => 'PENDING'
        ];

        $state = $posted[ 'transactionState' ];

        // We are here so lets check status and do actions
        switch( $codes[$state] ) {
            case 'APPROVED' :
            case 'PENDING' :

                // Check order not already completed
                if ( $order->status == 'completed' ) {
                     if ( 'yes' == $this->debug )
                        $this->logger( __('Aborting, Order #' . $order->id . ' is already complete.', 'woocommerce-gateway-placetopay' ) );
                     exit;
                }

                // Validate Amount
                if ( $order->get_total() != $posted['TX_VALUE'] ) {
                    $order->update_status( 'on-hold', sprintf( __( 'Validation error: PlacetoPay amounts do not match (gross %s).', 'woocommerce-gateway-placetopay' ), $posted['TX_VALUE'] ) );

                    $this->msg['message'] = sprintf( __( 'Validation error: PlacetoPay amounts do not match (gross %s).', 'woocommerce-gateway-placetopay' ), $posted['TX_VALUE'] );
                    $this->msg['class'] = 'woocommerce-error';

                }

                // Validate Merchand id
                if ( strcasecmp( trim( $posted['merchantId'] ), trim( $this->merchant_id ) ) != 0 ) {
                    $order->update_status( 'on-hold', sprintf( __( 'Validation error: Payment in PlacetoPay comes from another id (%s).', 'woocommerce-gateway-placetopay' ), $posted['merchantId'] ) );
                    $this->msg['message'] = sprintf( __( 'Validation error: Payment in PlacetoPay comes from another id (%s).', 'woocommerce-gateway-placetopay' ), $posted['merchantId'] );
                    $this->msg['class'] = 'woocommerce-error';

                }

                 // Payment Details
                if ( ! empty( $posted['buyerEmail'] ) )
                    update_post_meta( $order->id, __('Payer PlacetoPay email', 'woocommerce-gateway-placetopay' ), $posted['buyerEmail'] );
                if ( ! empty( $posted['transactionId'] ) )
                    update_post_meta( $order->id, __('Transaction ID', 'woocommerce-gateway-placetopay' ), $posted['transactionId'] );
                if ( ! empty( $posted['trazabilityCode'] ) )
                    update_post_meta( $order->id, __('Trasability Code', 'woocommerce-gateway-placetopay' ), $posted['trazabilityCode'] );
                /*if ( ! empty( $posted['last_name'] ) )
                    update_post_meta( $order->id, 'Payer last name', $posted['last_name'] );*/
                if ( ! empty( $posted['lapPaymentMethodType'] ) )
                    update_post_meta( $order->id, __('Payment type', 'woocommerce-gateway-placetopay' ), $posted['lapPaymentMethodType'] );

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

        $redirectUrl = (
            $this->redirect_page_id == 'default'
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
     * @param  array $posted
     * @return void
     */
    public function confirmationProcess( $posted ) {
        global $woocommerce;
        $order = $this->getOrder( $posted );

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
        $state = $posted[ 'state_pol' ];
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
                if ( $order->get_total() != $posted['value'] ) {
                    $order->update_status( 'on-hold', sprintf( __( 'Validation error: PlacetoPay amounts do not match (gross %s).', 'woocommerce-gateway-placetopay' ), $posted['value'] ) );

                    $this->msg['message'] = sprintf( __( 'Validation error: PlacetoPay amounts do not match (gross %s).', 'woocommerce-gateway-placetopay' ), $posted['value'] );
                    $this->msg['class'] = 'woocommerce-error';
                }

                // Validate Merchand id
                if ( strcasecmp( trim( $posted['merchant_id'] ), trim( $this->merchant_id ) ) != 0 ) {

                    $order->update_status( 'on-hold', sprintf( __( 'Validation error: Payment in PlacetoPay comes from another id (%s).', 'woocommerce-gateway-placetopay' ), $posted['merchant_id'] ) );
                    $this->msg['message'] = sprintf( __( 'Validation error: Payment in PlacetoPay comes from another id (%s).', 'woocommerce-gateway-placetopay' ), $posted['merchant_id'] );
                    $this->msg['class'] = 'woocommerce-error';
                }

                 // Payment details
                if ( ! empty( $posted['email_buyer'] ) )
                    update_post_meta( $order->id, __('PlacetoPay Client email', 'woocommerce-gateway-placetopay' ), $posted['email_buyer'] );
                if ( ! empty( $posted['transaction_id'] ) )
                    update_post_meta( $order->id, __('Transaction ID', 'woocommerce-gateway-placetopay' ), $posted['transaction_id'] );
                if ( ! empty( $posted['reference_pol'] ) )
                    update_post_meta( $order->id, __('Trasability Code', 'woocommerce-gateway-placetopay' ), $posted['reference_pol'] );
                if ( ! empty( $posted['sign'] ) )
                    update_post_meta( $order->id, __('Tash Code', 'woocommerce-gateway-placetopay' ), $posted['sign'] );
                if ( ! empty( $posted['ip'] ) )
                    update_post_meta( $order->id, __('Transaction IP', 'woocommerce-gateway-placetopay' ), $posted['ip'] );

                update_post_meta( $order->id, __('Extra Data', 'woocommerce-gateway-placetopay' ), 'response_code_pol: '.$posted['response_code_pol'].' - '.'state_pol: '.$posted['state_pol'].' - '.'payment_method: '.$posted['payment_method'].' - '.'transaction_date: '.$posted['transaction_date'].' - '.'currency: '.$posted['currency'] );


                if ( ! empty( $posted['payment_method_type'] ) )
                    update_post_meta( $order->id, __('Payment type', 'woocommerce-gateway-placetopay' ), $posted['payment_method_type'] );

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
     *  Get order information
     *
     * @param mixed $posted
     * @return void
     */
    public function getOrder( $posted ) {
        $orderId = (int) $posted[ 'order_id' ];
        $referenceCode = ( $posted[ 'referenceCode' ] ) ? $posted[ 'referenceCode' ] : $posted[ 'reference_sale' ];
        $orderKey = explode( '-', $referenceCode );
        $orderKey = $orderKey[ 0 ] ? $orderKey[ 0 ] : $orderKey;

        $order = new WC_Order( $orderId );

        if ( ! isset( $order->id ) ) {
            $orderId 	= woocommerce_get_order_id_by_order_key( $orderKey );
            $order 		= new WC_Order( $orderId );
        }

        // Validate key
        if ( $order->order_key !== $orderKey ) {
            $this->logger( __( 'Error: Order Key does not match invoice.', 'woocommerce-gateway-placetopay' ) );
            exit;
        }

        return $order;
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
     * Check if it has transactions with status pending and generate a message warning
     * @return void
     */
    public function checkout_message() {
        // obtiene el usuario actual
        $userId = get_current_user_id();

        if( $userId ) {
            // obtiene los últimos pedidos del cliente para revisar si tiene uno pendiente
            $customer_orders = get_posts( apply_filters( 'woocommerce_my_account_my_orders_query', [
                'numberposts'       => 5,
                'meta_key'          => '_customer_user',
                'meta_value'        => get_current_user_id(),
                'post_type'         => 'shop_order',
                'post_status'       => 'publish',
                'shop_order_status' => 'on-hold'
            ] ) );

            // si obtuvo datos
            if ( $customer_orders ) {
                foreach ($customer_orders as $order_id) {
                    $order = new WC_Order();
                    $order->populate($order_id);
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
