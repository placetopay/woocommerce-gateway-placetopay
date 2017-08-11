<?php namespace PlacetoPay;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Dnetix\Redirection\Entities\Transaction;
use Dnetix\Redirection\Message\Notification;
use Dnetix\Redirection\Message\RedirectInformation;
use Dnetix\Redirection\PlacetoPay;
use Dnetix\Redirection\Validators\Currency;
use Dnetix\Redirection\Validators\PersonValidator;
use Exception;
use WC_Order;
use WC_Payment_Gateway;

/**
 * @package \PlacetoPay
 */
class GatewayMethod extends WC_Payment_Gateway
{

    /**
     * Constant key for the session requestId
     * @var string
     */
    const SESSION_REQ_ID = 'placetopay_request_id';

    const META_AUTHORIZATION_CUS = '_p2p_authorization';

    const META_REQUEST_ID = '_p2p_request_id';

    const META_STATUS = '_p2p_status';

    const META_STOCK_RESTORED = '_p2p_stock_restored:%s';

    /**
     * PlacetoPay uri endpoint namespace via wordpress for the notification of the service
     * @var array
     */
    const PAYMENT_ENDPOINT_NAMESPACE = 'placetopay-payment/v2';

    /**
     * PlacetoPay uri endpoint namespace via wordpress for the notification of the service
     * @var array
     */
    const PAYMENT_ENDPOINT_CALLBACK = '/callback/';

    /**
     * Name of action to draw view with order data after payment process
     */
    const NOTIFICATION_RETURN_PAGE = 'placetopay_notification_return_page';

    /**
     * URI for production service
     * @var string
     */
    private $prodUri = 'https://secure.placetopay.com/redirection';

    /**
     * URI for testing in production enviroment
     * @var string
     */
    private $testUri = 'https://test.placetopay.com/redirection';

    /**
     * URI for testing in development enviroment
     * @var string
     */
    private $testUriDev = 'https://dev.placetopay.com/redirection';

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
     * @var array
     */
    private $msg = [];

    /**
     * @var \WC_Logger
     */
    private $log;

    private $endpoint;
    private $currency;
    private $enviroment_mode;
    private $fill_buyer_information;
    private $login;
    private $redirect_page_id;
    private $form_method;
    private $testmode;
    private $merchant_phone;
    private $merchant_email;
    private $msg_approved;
    private $msg_pending;
    private $msg_declined;
    private $msg_cancel;
    private $debug;
    private $uri_service;


    /**
     * GatewayMethod constructor.
     */
    function __construct()
    {
        $this->configPaymentMethod();
        $this->init();
        $this->initPlacetoPay();
    }

    /**
     * Set the configuration for parent class \WC_Payment_Gateway
     * @return void
     */
    public function configPaymentMethod()
    {
        $this->id = 'placetopay';
        $this->method_title = __('PlacetoPay', 'woocommerce-gateway-placetopay');
        $this->method_description = __("Sells online safely and agile", 'woocommerce-gateway-placetopay');
        $this->icon = WC_Gateway_PlacetoPay::assets('/images/placetopay.png', 'url');
        $this->has_fields = false;

        // Init settings
        $this->initFormFields();
        $this->settings['endpoint'] = home_url('/wp-json/') . self::getPaymentEndpoint();

        $this->endpoint = $this->settings['endpoint'];
        $this->fill_buyer_information = $this->get_option('fill_buyer_information');
        $this->enviroment_mode = $this->get_option('enviroment_mode');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->login = $this->get_option('login');
        $this->tran_key = $this->get_option('tran_key');
        $this->redirect_page_id = $this->get_option('redirect_page_id');
        $this->form_method = $this->get_option('form_method');

        $this->merchant_phone = $this->get_option('merchant_phone');
        $this->merchant_email = $this->get_option('merchant_email');
        $this->msg_approved = $this->get_option('msg_approved');
        $this->msg_pending = $this->get_option('msg_pending');
        $this->msg_declined = $this->get_option('msg_declined');
        $this->msg_cancel = $this->get_option('msg_cancel');

        $this->currency = get_woocommerce_currency();
        $this->currency = Currency::isValidCurrency($this->currency) ? $this->currency : Currency::CUR_COP;

        $this->testmode = in_array($this->enviroment_mode, ["test", 'dev']) ? 'yes' : 'no';

        if ($this->testmode == "yes") {
            $this->debug = "yes";
            $this->log = $this->wooCommerceVersionCompare('2.1')
                ? new \WC_Logger()
                : WC()->logger();

            $this->uri_service = $this->enviroment_mode === 'dev'
                ? $this->testUriDev
                : $this->testUri;

        } else {
            if ($this->enviroment_mode === 'prod') {
                $this->debug = 'no';
                $this->uri_service = $this->prodUri;
            }
        }

        // By default always it will be enviroment of development testing
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->settings['enviroment_mode'] = 'dev';
            $this->uri_service = $this->testUriDev;
        }
    }


    /**
     * Configuration initial
     * @return void
     */
    public function init()
    {
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receiptPage']);
        add_action('woocommerce_api_' . $this->getClassName(true), [$this, 'checkResponse']);
        add_action('placetopay_init', [$this, 'successfulRequest']);

        if ($this->wooCommerceVersionCompare('2.0.0')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [&$this, 'process_admin_options']);
            return;
        }

        add_action('woocommerce_update_options_payment_gateways', [&$this, 'process_admin_options']);
    }

    /**
     * Settings Options
     * @return void
     */
    public function initFormFields()
    {
        $this->form_fields = include(__DIR__ . '/config/form-fields.php');
//        $this->init_form_fields();
        $this->init_settings();
    }


    /**
     * Endpoint for the notification of PlacetoPay
     *
     * @param \WP_REST_Request $req
     * @return mixed
     */
    public function endpointPlacetoPay(\WP_REST_Request $req)
    {
        $this->logger('starting request api', 'endpointPlacetoPay');

        $data = $req->get_params();

        if (!empty($data['signature']) && !empty($data['requestId'])) {
            $notification = new Notification($data, $this->tran_key);

            if (!$notification->isValidNotification()) {
                if ($this->testmode == "yes") {
                    return $notification->makeSignature();
                }

                return null;
            }

            $transactionInfo = $this->placetopay->query($notification->requestId());
            $fields = $transactionInfo->request()->fieldsToKeyValue();

            if (!isset($fields['orderKey'])) {
                $this->logger('Not orderKey in response for notificationUrl', 'endpointPlacetoPay');
                return null;
            }

            $this->returnProcess(['key' => $fields['orderKey']], $transactionInfo, true);
            $this->logger('Response successfully', 'endpointPlacetoPay');

            return ['success' => true];
        }

        return null;
    }


    /**
     * Process the payment for a order
     *
     * @param int $orderId
     * @return array|null
     */
    public function process_payment($orderId)
    {
        $requestId = get_post_meta($orderId, self::META_REQUEST_ID, true);
        $order = new WC_Order($orderId);

        $ref = $order->get_order_key() . '-' . time();
        $productInfo = "Order $orderId";
        $redirectUrl = $this->getRedirectUrl($order);

        //For wooCoomerce 2.0
        $redirectUrl = add_query_arg('wc-api', $this->getClassName(), $redirectUrl);
        $redirectUrl = add_query_arg('order_id', $orderId, $redirectUrl);

        if (!$this->validateFields($order)) {
            return null;
        }

        $req = [
            'expiration' => date('c', strtotime('+2 days')),
            'returnUrl' => $redirectUrl . '&key=' . $ref,
            'noBuyerFill' => !($this->fill_buyer_information === 'yes'),
            'ipAddress' => (new RemoteAddress())->getIpAddress(),
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'buyer' => [
                'name' => $order->get_billing_first_name(),
                'surname' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'company' => $order->get_billing_company(),
                'mobile' => $order->get_billing_phone(),
                'address' => [
                    'street' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'country' => $order->get_billing_country(),
                    'postalCode' => $order->get_billing_postcode()
                ]
            ],
            'payment' => [
                'reference' => self::getOrderNumber($order),
                'description' => $productInfo,
                'amount' => [
                    'currency' => $this->currency,
                    'total' => floatval($order->order_total)
                ]
            ],
            'fields' => [
                [
                    'keyword' => 'orderKey',
                    'value' => $order->get_order_key(),
                    'displayOn' => 'none',
                ],
            ],
        ];

        try {
            $res = $this->placetopay->request($req);

            if ($res->isSuccessful()) {
                // Store the requestId in the session
                WC()->session->set(self::SESSION_REQ_ID, $res->requestId());
                update_post_meta($order->get_id(), self::META_REQUEST_ID, $res->requestId());

                // Redirect the client to the processUrl or display it on the JS extension
                $processUrl = urlencode($res->processUrl());

                if (!$requestId || !$this->isPendingStatusOrder($order->get_id())) {
                    // Reduce stock levels tempory
                    wc_reduce_stock_levels($order->get_id());
                }

                // Remove cart
                WC()->cart->empty_cart();

                return [
                    'result' => 'success',
                    'redirect' => add_query_arg('redirect-url', $processUrl, $order->get_checkout_payment_url(true))
                ];
            }

            $this->logger(__('Payment error:', 'woothemes') . $res->status()->message(), 'error');
            wc_add_notice(__('Payment error:', 'woothemes') . $res->status()->message(), 'error');

        } catch (Exception $ex) {
            $this->logger($ex->getMessage(), 'error');
            wc_add_notice(__('Payment error:', 'woothemes'), 'error');
        }

        return null;
    }

    /**
     * After of process_payment, generate the PlacetoPay block modal with form datas to sending
     *
     * @param $orderId
     */
    public function receiptPage($orderId)
    {
        try {
            $requestId = WC()->session->get(self::SESSION_REQ_ID);
            $transactionInfo = $this->placetopay->query($requestId);

            $authorizationCode = count($transactionInfo->payment) > 0
                ? array_map(function (Transaction $trans) {
                    return $trans->authorization();
                }, $transactionInfo->payment)
                : [];

            // Payment Details
            if (count($authorizationCode) > 0) {
                $this->logger('Adding authorization code $auth = ' . $authorizationCode, 'receiptPage');
                update_post_meta($orderId, self::META_AUTHORIZATION_CUS, implode(",", $authorizationCode));
            }

            // Add information to the order to notify that exit to PlacetoPay
            // and invalidates the shopping cart
            $order = new WC_Order($orderId);
            $order->update_status('on-hold', __('Redirecting to PlacetoPay', 'woocommerce-gateway-placetopay'));

            $code = 'jQuery("body").block({
                message: "' . esc_js(__('We are now redirecting you to PlacetoPay to make payment, if you are not redirected please press the bottom.',
                    'woocommerce-gateway-placetopay')) . '",
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
                window.location.href = "' . $_REQUEST['redirect-url'] . '";
            }, 1000 );
            ';

            if ($this->wooCommerceVersionCompare('2.1')) {
                wc_enqueue_js($code);
            } else {
                WC()->add_inline_js($code);
            }

        } catch (Exception $ex) {
            $this->logger($ex->getMessage(), 'error');
        }
    }

    /**
     * Check if the response server is correct, callback
     * @return void
     */
    public function checkResponse()
    {
        @ob_clean();

        if (!empty($_REQUEST)) {
            header('HTTP/1.1 200 OK');
            do_action("placetopay_init", $_REQUEST);

            return;
        }

        wp_die(__("PlacetoPay Request Failure", 'woocommerce-gateway-placetopay'));
    }

    /**
     * After checkResponse, Process PlacetoPay response and update order information
     *
     * @param array $req Response datas in array format
     * @return void
     */
    public function successfulRequest($req)
    {
        // When the user is returned to the page specificated by redirectUrl
        if (!empty($req['key']) && !empty($req['wc-api'])) {
            $requestId = WC()->session->get(self::SESSION_REQ_ID);
            $transactionInfo = $this->placetopay->query($requestId);

            $this->returnProcess($req, $transactionInfo);
        }

        // For WooCoomerce 2.0
        $redirectUrl = add_query_arg([
            'msg' => urlencode(__('There was an error on the request. please contact the website administrator.',
                'placetopay')),
            'type' => $this->msg['class']
        ], wc_get_checkout_url());

        wp_redirect($redirectUrl);
        exit;
    }

    /**
     * Process page of response
     *
     * @param $request
     * @param RedirectInformation $transactionInfo Information of transaction in placetopay
     * @param bool $isCallback Define if is notification or return request
     */
    public function returnProcess($request, RedirectInformation $transactionInfo, $isCallback = false)
    {
        $order = $this->getOrder($request);
        $sessionStatusInstance = $transactionInfo->status();
        $status = $sessionStatusInstance->status();

        // Register status PlacetoPay for the order
        update_post_meta($order->get_id(), self::META_STATUS, $status);

        $this->logger([
            'Processing order #%s with status %s',
            $order->get_id(),
            $status
        ], __METHOD__);

        $authorizationCode = count($transactionInfo->payment) > 0
            ? array_map(function (Transaction $trans) {
                return $trans->authorization();
            }, $transactionInfo->payment)
            : [];

        // Payment Details
        if (count($authorizationCode) > 0) {
            update_post_meta($order->get_id(), self::META_AUTHORIZATION_CUS, implode(",", $authorizationCode));
        }

        $paymentFirstStatus = count($transactionInfo->payment()) > 0
            ? $transactionInfo->payment()[0]->status()
            : null;

        // Get order updated with metas refreshed
        $order = new WC_Order($order->get_id());

        // We are here so lets check status and do actions
        switch ($status) {
            case $sessionStatusInstance::ST_APPROVED :
            case $sessionStatusInstance::ST_PENDING :

                // Check order not already completed
                if ($order->get_status() == 'completed') {
                    $this->logger(__('Aborting, Order #' . $order->get_id() . ' is already complete.',
                        'woocommerce-gateway-placetopay'));

                    if ($isCallback) {
                        return;
                    }

                    exit;
                }

                $totalAmount = $transactionInfo->request()->payment()->amount()->total();

                $payerEmail = $transactionInfo->request()->payer()
                    ? $transactionInfo->request()->payer()->email()
                    : null;

                $paymentMethodName = count($transactionInfo->payment) > 0
                    ? array_map(function (Transaction $trans) {
                        return $trans->paymentMethodName();
                    }, $transactionInfo->payment)
                    : [];

                // Validate Amount
                if ($order->get_total() != floatval($totalAmount)) {
                    $msg = sprintf(__('Validation error: PlacetoPay amounts do not match (gross %s).',
                        'woocommerce-gateway-placetopay'), $totalAmount);
                    $order->update_status('on-hold', $msg);

                    $this->msg['message'] = $msg;
                    $this->msg['class'] = 'woocommerce-error';
                }

                if (!empty($payerEmail)) {
                    update_post_meta($order->get_id(), __('Payer PlacetoPay email', 'woocommerce-gateway-placetopay'),
                        $payerEmail);
                }

                if (count($paymentMethodName) > 0) {
                    update_post_meta($order->get_id(), __('Payment type', 'woocommerce-gateway-placetopay'),
                        implode(",", $paymentMethodName));
                }

                if ($status == $sessionStatusInstance::ST_APPROVED) {
                    $this->msg['message'] = $this->msg_approved;
                    $this->msg['class'] = 'woocommerce-message';

                    $order->add_order_note(__('PlacetoPay payment approved', 'woocommerce-gateway-placetopay'));
//                    $this->restoreOrderStock($order->get_id(), false);
                    $order->payment_complete();
                    $this->logger('Payment approved for order # ' . $order->get_id(), __METHOD__);

                } else {
                    $statusOrder = $paymentFirstStatus && $paymentFirstStatus->status() === $paymentFirstStatus::ST_PENDING
                        ? 'on-hold'
                        : 'pending';

                    $order->update_status($statusOrder,
                        sprintf(__('Payment pending: %s', 'woocommerce-gateway-placetopay'), $status));
                    $this->msg['message'] = $this->msg_pending;
                    $this->msg['class'] = 'woocommerce-info';
                }

                break;

            case $sessionStatusInstance::ST_REJECTED :
            case $sessionStatusInstance::ST_REFUNDED :

                if ($status === $sessionStatusInstance::ST_REJECTED) {
                    $order->update_status(
                        'failed',
                        sprintf(__('Payment rejected via PlacetoPay.', 'woocommerce-gateway-placetopay'), $status)
                    );

                    $this->msg['message'] = $this->msg_cancel;

                    if ($paymentFirstStatus) {
                        $this->logger($paymentFirstStatus->message(), $status);
                    }

                    $this->restoreOrderStock($order->get_id());

                } else {
                    $order->update_status(
                        'refunded',
                        sprintf(__('Payment rejected via PlacetoPay. Error type: %s.',
                            'woocommerce-gateway-placetopay'), $status)
                    );
                    $this->msg['message'] = $this->msg_declined;
                }

                $this->msg['class'] = 'woocommerce-error';

                break;

            case $sessionStatusInstance::ST_ERROR :
            case $sessionStatusInstance::ST_FAILED :
            default:
                $order->update_status('failed',
                    sprintf(__('Payment rejected via PlacetoPay.', 'woocommerce-gateway-placetopay'), $status));
                $this->msg['message'] = $this->msg_cancel;
                $this->msg['class'] = 'woocommerce-error';

                $this->restoreOrderStock($order->get_id());

                break;
        }

        // Is notification request
        if ($isCallback) {
            $this->logger('Returning method with status ' . $status, __METHOD__);
            return;
        }

        $redirectUrl = $this->getRedirectUrl($order);
        //For wooCoomerce 2.0
        $redirectUrl = add_query_arg([
//            'msg' => urlencode($this->msg['message']),
//            'type' => $this->msg['class'],
            'order_key' => $order->get_order_key(),
            'payment_method' => 'placetopay'
        ], $redirectUrl);

        wp_redirect($redirectUrl);
        exit;
    }

    /**
     *  Get order instance with a given order key
     *
     * @param mixed $request
     * @return \WC_Order
     */
    public function getOrder($request)
    {
        $orderId = isset($request['order_id']) ? (int)$request['order_id'] : null;
        $key = isset($request['key']) ? $request['key'] : '';
        $orderKey = explode('-', $key);
        $orderKey = $orderKey[0] ? $orderKey[0] : $orderKey;

        $order = new WC_Order($orderId);

        if (!$order->get_id() || $order->get_id() === 0) {
            $orderId = wc_get_order_id_by_order_key($orderKey);
            $order = new WC_Order($orderId);
        }

        // Validate key
        if (!!$key && $order->get_order_key() !== $orderKey) {
            $this->logger(__('Error: Order Key does not match invoice.', 'woocommerce-gateway-placetopay'), 'getOrder');
            exit;
        }

        return $order;
    }

    /**
     * Check if it has transactions with status pending and generate a message warning
     * @return void
     */
    public function checkoutMessage()
    {
        $userId = get_current_user_id();

        if ($userId) {
            // Getting the last client's order to view if he has one pending
            $customerOrders = wc_get_orders(apply_filters('woocommerce_my_account_my_orders_query', [
                'numberposts' => 5,
                'limit' => 5,
                'meta_key' => '_customer_user',
                'meta_value' => $userId,
                'customer' => $userId,
                'status' => [
                    'wc-pending',
                    'wc-on-hold',
                ],
                'shop_order_status' => 'on-hold'
            ]));

            if ($customerOrders) {
                foreach ($customerOrders as $_order) {
                    $order = new WC_Order();
                    $order->populate($_order);

                    if (!self::isPendingStatusOrder($order->get_id())) {
                        continue;
                    }

                    if ($order->get_status() == 'pending' || $order->get_status() == 'on-hold') {
                        $authCode = get_post_meta($order->get_id(), self::META_AUTHORIZATION_CUS, true);

                        $message = sprintf(
                            __("At this time your order #%s display a checkout transaction which is pending receipt of confirmation from your financial institution,
                            please wait a few minutes and check back later to see if your payment was successfully confirmed. For more information about the current
                            state of your operation you may contact our customer service line at %s or send your concerns to the email %s and ask for the status of the transaction: '%s'",
                                'woocommerce-gateway-placetopay'),
                            ( string )$order->get_id(),
                            $this->merchant_phone,
                            $this->merchant_email,
                            ($authCode == '' ? '' : sprintf(__('CUS/Authorization',
                                    'woocommerce-gateway-placetopay') . ' #%s', $authCode))
                        );

                        echo "<div class='shop_table order_details'>
                            <p scope='row'>{$message}</p>
                        </div>";

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
    public function is_available()
    {
        global $woocommerce;

        if ($this->enabled == "yes") {
            if (!Currency::isValidCurrency($this->currency)) {
                return false;
            }

            if ($woocommerce->version < '1.5.8') {
                return false;
            }

            if ($this->testmode != 'yes' && (!$this->login || !$this->tran_key)) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Manage the log instance if the debug is actived else nothing happen baby
     *
     * @param $message
     * @param null $type
     */
    public function logger($message, $type = null)
    {
        if ($this->debug != 'yes') {
            return;
        }

        if (is_array($message) && count($message) > 1) {
            $format = $message[0];
            array_shift($message);

            $message = vsprintf($format, $message);
        }

        $this->log->add(
            'PlacetoPay',
            ($type ? "($type): " : '') . $message
        );
    }

    /**
     * @param $orderId
     */
    public function restoreOrderStock($orderId, $logger = true)
    {
        $order = new WC_Order($orderId);

        if (!get_option('woocommerce_manage_stock') == 'yes' && !sizeof($order->get_items()) > 0) {
            return;
        }

        $requestId = get_post_meta($orderId, self::META_REQUEST_ID, true);

        if ($requestId) {
            $stockReduced = get_post_meta($orderId, self::META_STOCK_RESTORED . $requestId, true);

            if($stockReduced === 'yes') {
                return;
            }
        }

        /** @var \WC_Order_Item_Product $item */
        foreach ($order->get_items() as $item) {
            if ($item['product_id'] <= 0) {
                continue;
            }

            /** @var \WC_Product $product */
            $product = $item->get_product();

            if (!$product || !$product->exists() || !$product->managing_stock()) {
                continue;
            }

            $oldStock = $product->get_stock_quantity();
            $qty = apply_filters('woocommerce_order_item_quantity', $item['qty'], $this, $item);

            $newQuantity = wc_update_product_stock($product, $qty, 'increase');
            do_action('woocommerce_auto_stock_restored', $product, $item);

            if ($logger) {
                $order->add_order_note(sprintf(
                    __('Item #%s stock incremented from %s to %s.', 'woocommerce'),
                    $item['product_id'],
                    $oldStock,
                    $newQuantity
                ));
            }
        }

        update_post_meta($orderId, self::META_STOCK_RESTORED . $requestId, 'yes');
    }

    /**
     * Get pages for return page setting
     *
     * @param  boolean $title Title of the page
     * @return array
     */
    public function getPages($title = false)
    {
        $pageList = [
            'default' => __('Default Page', 'woocommerce-gateway-placetopay'),
        ];

        if ($title) {
            $pageList[] = $title;
        }

        $pageList['my-orders'] = __('My Orders', 'woocommerce-gateway-placetopay');

        return $pageList;
    }

    /**
     * Return redirect url
     *
     * @param \WC_Order $order
     * @return false|string
     */
    public function getRedirectUrl($order)
    {
        if ($this->redirect_page_id == 'default' || !$this->redirect_page_id) {
            return $order->get_checkout_order_received_url();
        }

        if ($this->redirect_page_id === 'my-orders') {
            return wc_get_account_endpoint_url(get_option('woocommerce_myaccount_orders_endpoint', 'orders'));
        }

        return get_permalink($this->redirect_page_id);
    }

    /**
     * @param $orderId
     * @return bool
     */
    public static function isPendingStatusOrder($orderId)
    {
        $statusP2P = get_post_meta($orderId, self::META_STATUS, true);
        return \Dnetix\Redirection\Entities\Status::ST_PENDING === $statusP2P;
    }

    /**
     * Return list of environments for selection
     *
     * @return array
     */
    protected function getEnvironments()
    {
        return [
            'dev' => __('Development', 'woocommerce-gateway-placetopay'),
            'test' => __('Test', 'woocommerce-gateway-placetopay'),
            'prod' => __('Production', 'woocommerce-gateway-placetopay'),
        ];
    }

    /**
     * Return the payment endpoint for url request-back
     * @return string
     */
    public static function getPaymentEndpoint()
    {
        return self::PAYMENT_ENDPOINT_NAMESPACE . self::PAYMENT_ENDPOINT_CALLBACK;
    }

    /**
     * @param $order
     * @param int $len
     * @param string $symbol
     * @return string
     */
    public static function getOrderNumber($order, $len = 4, $symbol = '0')
    {
        /** @var \WC_Order $order */
        return str_pad($order->get_order_number(), $len, $symbol, STR_PAD_LEFT);
    }

    /**
     * @param $orderId
     * @param $requestId
     */
    public static function processPendingOrder($orderId, $requestId)
    {
        $gatewayMethod = new self();
        $transactionInfo = $gatewayMethod->placetopay->query($requestId);
        $gatewayMethod->returnProcess(['order_id' => $orderId], $transactionInfo, true);
        $gatewayMethod->logger('Processed order with ID = ' . $orderId, 'cron');
    }

    /**
     * Instantiates a PlacetoPay object providing the login and tranKey,
     * also the url that will be used for the service
     */
    private function initPlacetoPay()
    {
        $this->placetopay = new PlacetoPay([
            'login' => $this->login,
            'tranKey' => $this->tran_key,
            'url' => $this->uri_service,
        ]);
    }

    /**
     * Get the class name with namespaces modificated
     *
     * @param  boolean $lowercase
     * @return string
     */
    private function getClassName($lowercase = false)
    {
        return str_replace("\\", "_", $lowercase ? strtolower(get_class($this)) : get_class($this));
    }

    /**
     * @param \WC_Order $order
     * @return bool
     */
    private function validateFields($order)
    {
        $isValid = true;

        if (preg_match(PersonValidator::PATTERN_NAME, trim($order->get_billing_first_name())) !== 1) {
            wc_add_notice(__('<strong>First Name</strong>, does not have a valid format',
                'woocommerce-gateway-placetopay'), 'error');
            $isValid = false;
        }

        if (preg_match(PersonValidator::PATTERN_NAME, trim($order->get_billing_last_name())) !== 1) {
            wc_add_notice(__('<strong>Last Name</strong>, does not have a valid format',
                'woocommerce-gateway-placetopay'), 'error');
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * @param $version
     * @param string $operator
     * @return bool
     */
    private function wooCommerceVersionCompare($version, $operator = '>=')
    {
        return defined('WOOCOMMERCE_VERSION') && version_compare(
                WOOCOMMERCE_VERSION,
                $version,
                $operator
            );
    }
}
