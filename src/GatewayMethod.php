<?php namespace PlacetoPay;


if( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

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
        $this->icon_default         = WoocommerceGatewayPlacetoPay::assets( '/img/placetopay-logo.png' );
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
    }


    /**
     * Configuraction initial
     *
     * @return void
     */
    public function init() {
        add_action( 'woocommerce_receipt_placetopay', [ $this, 'receiptPage' ]);
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
                'reference'     => $ref,
                'description'   => $productinfo,
                'amount'        => [
                    'currency' => $this->currency,
                    'total'    => floatval( $order->order_total )
                ],
            ],
        ];

        try {
            $res = $this->placetopay->request( $req );

            if( $res->isSuccessful() ) {
                // Redirect the client to the processUrl or display it on the JS extension
                return [
                    'result'    => 'success',
                    'redirect'  => $res->processUrl()
                ];

            }

            wc_add_notice( __( 'Payment error:', 'woothemes' ) . $response->status()->message(), 'error' );
            return;

        } catch( \Exception $ex ) {
            wc_add_notice( __( 'Payment error:', 'woothemes' ) . $ex->getMessage(), 'error' );
            return;
        }
    }


    /**
     * Check if the response server is correct
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
     * Process PlacetoPay response and update order information
     *
     * @param array $posted    Response datas in array format
     * @return void
     */
    public function successfulRequest( $posted ) {
        global $woocommerce;

        dd( $posted );

        if( !empty( $posted[ 'transactionState' ] ) && !empty( $posted[ 'referenceCode' ] ) ) {
            $this->returnProcess( $posted );
        }

        if( !empty( $posted[ 'state_pol' ] ) && !empty( $posted[ 'reference_sale' ] ) ) {
            $this->confirmationProcess( $posted );
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
                        $this->log->add( 'placetopay', __('Aborting, Order #' . $order->id . ' is already complete.', 'woocommerce-gateway-placetopay' ) );
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

        if ( 'yes' == $this->debug )
            $this->log->add( 'placetopay', 'Found order #' . $order->id );

        $state=$posted['state_pol'];

        if ( 'yes' == $this->debug )
            $this->log->add( 'placetopay', 'Payment status: ' . $codes[$state] );

        // We are here so lets check status and do actions
        switch ( $codes[$state] ) {
            case 'APPROVED' :
            case 'PENDING' :

                // Check order not already completed
                if ( $order->status == 'completed' ) {
                     if ( 'yes' == $this->debug )
                        $this->log->add( 'placetopay', __('Aborting, Order #' . $order->id . ' is already complete.', 'woocommerce-gateway-placetopay' ) );
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

                    if ( 'yes' == $this->debug ){ $this->log->add( 'placetopay', __('Payment complete.', 'woocommerce-gateway-placetopay' ));}

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
            if ( $this->debug == 'yes' )
                $this->log->add( 'placetopay', __( 'Error: Order Key does not match invoice.', 'woocommerce-gateway-placetopay' ) );
            exit;
        }

        return $order;
    }


    /**
     * Generate the PlacetoPay block modal for checkout
     *
     * @param mixed $order
     * @return string
     */
    public function receiptPage( $order ) {
        global $woocommerce;

        $code = 'jQuery("body").block({
            message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to PlacetoPay to make payment.', 'woocommerce-gateway-placetopay' ) ) . '",
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
        jQuery("#submit_placetopay_payment_form").click();';

        if( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=')) {
             wc_enqueue_js( $code );
        } else {
            $woocommerce->add_inline_js( $code );
        }
    }


    /**
     * Generate PlacetoPay POST arguments
     *
     * @param mixed $orderId
     * @return string
     */
    public function getArgs( $orderId ) {
        global $woocommerce;

        $order = new WC_Order( $orderId );
        $txnid = $order->order_key . '-' . time();

        $redirect_url = $this->redirect_page_id == "" || $this->redirect_page_id == 0
            ? get_site_url() . "/"
            : get_permalink( $this->redirect_page_id );

        //For wooCoomerce 2.0
        $redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
        $redirect_url = add_query_arg( 'order_id', $orderId, $redirect_url );
        $redirect_url = add_query_arg( '', $this->endpoint, $redirect_url );

        $productinfo = "Order $orderId";

        $str = "$this->apikey~$this->merchant_id~$txnid~$order->order_total~$this->currency";
        $hash = strtolower( md5( $str ) );

        $_args = [
            'login'             => $this->login,
            'tran_key'          => $this->tran_key,
            'signature'         => $hash,
            'referenceCode' 	=> $txnid,
            'amount' 			=> $order->order_total,
            'currency' 			=> $this->currency,
            'payerFullName'		=> $order->billing_first_name .' '.$order->billing_last_name,
            'buyerEmail' 		=> $order->billing_email,
            'telephone' 		=> $order->billing_phone,
            'billingAddress' 	=> $order->billing_address_1.' '.$order->billing_address_2,
            'shippingAddress' 	=> $order->billing_address_1.' '.$order->billing_address_2,
            'billingCity' 		=> $order->billing_city,
            'shippingCity' 		=> $order->billing_city,
            'billingCountry' 	=> $order->billing_country,
            'shippingCountry' 	=> $order->billing_country,
            'zipCode' 			=> $order->billing_postcode,
            'description'		=> $productinfo,
            'responseUrl' 		=> $redirect_url,
            'confirmationUrl'	=> $redirect_url
        ];

        if( $this->testmode == 'yes' ) {
            $_args[ 'ApiKey' ] = $this->testapikey;
            $_args[ 'test' ] = '1';

        } else {
            $_args[ 'ApiKey' ] = $this->apikey;
        }

        return $_args;
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
     * @return \PlacetoPay
     */
    private function initPlacetoPay() {
        $this->placetopay = new PlacetoPay([
            'login'     => $this->login,
            'tranKey'   => $this->tran_key,
            'url'       => 'http://redirection.dnetix.co/',
        ]);
    }


    private function getClassName( $lowercase = false ) {
        return str_replace( "\\", "_", $lowercase ? strtolower( get_class( $this ) ) : get_class( $this ) );
    }
}
