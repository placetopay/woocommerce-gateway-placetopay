<?php

namespace PlacetoPay\PaymentMethod;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Dnetix\Redirection\Entities\Status;
use Dnetix\Redirection\Entities\Transaction;
use Dnetix\Redirection\Exceptions\PlacetoPayException;
use Dnetix\Redirection\Message\Notification;
use Dnetix\Redirection\Message\RedirectInformation;
use Dnetix\Redirection\PlacetoPay;
use Dnetix\Redirection\Validators\Currency;
use Dnetix\Redirection\Validators\PersonValidator;
use Exception;
use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Environment;
use WC_HTTPS;
use WC_Order;
use WC_Payment_Gateway;

/**
 * Class GatewayMethod.
 */
class GatewayMethod extends WC_Payment_Gateway
{
    const META_AUTHORIZATION_CUS = '_p2p_authorization';

    const META_REQUEST_ID = '_p2p_request_id';

    const META_STATUS = '_p2p_status';

    const META_STOCK_RESTORED = '_p2p_stock_restored:%s';

    const META_PROCESS_URL = '_p2p_process_url';

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

    const EXPIRATION_TIME_MINUTES_LIMIT = 40320;


    /**
     * Instance of placetopay to manage the connection with the webservice
     * @var PlacetoPay
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

    private $expiration_time_minutes;
    private $endpoint;
    private $currency;
    private $country;
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
    private $taxes;
    private $minimum_amount;
    private $maximum_amount;
    private $allow_to_pay_with_pending_orders;
    private $allow_partial_payments;
    private $skip_result;
    private $custom_connection_url;
    private $payment_button_image;

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
        $this->method_title = __('Placetopay', 'woocommerce-gateway-placetopay');
        $this->method_description = __('Sells online safely and agile', 'woocommerce-gateway-placetopay');
        $this->has_fields = false;

        // Init settings
        $this->initFormFields();
        $this->settings['endpoint'] = home_url('/wp-json/') . self::getPaymentEndpoint();

        $this->endpoint = $this->settings['endpoint'];
        $this->expiration_time_minutes = $this->settings['expiration_time_minutes'];
        $this->fill_buyer_information = $this->get_option('fill_buyer_information');
        $this->country = $this->get_option('country');
        $this->enviroment_mode = $this->get_option('enviroment_mode');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->login = $this->get_option('login');
        $this->tran_key = $this->get_option('tran_key');
        $this->redirect_page_id = $this->get_option('redirect_page_id');
        $this->form_method = $this->get_option('form_method');
        $this->allow_to_pay_with_pending_orders = $this->get_option('allow_to_pay_with_pending_orders');
        $this->allow_partial_payments = $this->get_option('allow_partial_payments') == "yes";
        $this->skip_result = $this->get_option('skip_result') == "yes";
        $this->custom_connection_url = $this->get_option('custom_connection_url');
        $this->payment_button_image = $this->get_option('payment_button_image');
        $this->icon = $this->getImageUrl();

        $this->taxes = [
            'taxes_others' => $this->get_option('taxes_others', []),
            'taxes_ico' => $this->get_option('taxes_ico', []),
            'taxes_ice' => $this->get_option('taxes_ice', []),
        ];

        $this->minimum_amount = $this->get_option('minimum_amount');
        $this->maximum_amount = $this->get_option('maximum_amount');
        $this->merchant_phone = $this->get_option('merchant_phone');
        $this->merchant_email = $this->get_option('merchant_email');
        $this->msg_approved = $this->get_option('msg_approved');
        $this->msg_pending = $this->get_option('msg_pending');
        $this->msg_declined = $this->get_option('msg_declined');
        $this->msg_cancel = $this->get_option('msg_cancel');

        $this->currency = get_woocommerce_currency();
        $this->currency = Currency::isValidCurrency($this->currency) ? $this->currency : Currency::CUR_COP;

        $this->configureEnvironment();
    }

    public function getScheduleTaskPath(): string
    {
        return plugin_dir_path(__FILE__).'cron/ProcessPendingOrderCron.php';
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
        $this->init_settings();
    }

    private function getImageUrl(): ?string
    {
        $url = $this->payment_button_image;

        if (is_null($url) || empty($url)) {
            // format: null
            $image = 'https://static.placetopay.com/placetopay-logo.svg';
        } elseif ($this->checkValidUrl($url)) {
            // format: https://www.domain.test/image.svg
            $image = $url;
        } elseif ($this->checkDirectory($url)) {
            // format: /folder/image.svg
            $image = home_url('/wp-content/uploads/').$url;
        } else {
            // format: image
            $image = 'https://static.placetopay.com/'.$url.'.svg';
        }

        return $image;
    }

    protected function checkDirectory(string $path): bool
    {
        return substr($path, 0, 1) === '/';
    }

    protected function checkValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * @param null $status
     * @return array|mixed
     */
    public static function getOrderStatusLabels($status = null)
    {
        $labels = [
            'pending' => __('Pending', 'woocommerce-gateway-placetopay'),
            //Order received (unpaid)
            'processing' => __('Approved', 'woocommerce-gateway-placetopay'),
            //Payment received and stock has been reduced- the order is awaiting fulfillment
            'on-hold' => __('Pending', 'woocommerce-gateway-placetopay'),
            //Awaiting payment – stock is reduced, but you need to confirm payment
            'completed' => __('Approved', 'woocommerce-gateway-placetopay'),
            //Order fulfilled and complete – requires no further action
            'refunded' => __('Rejected', 'woocommerce-gateway-placetopay'),
            'cancelled' => __('Cancelled', 'woocommerce-gateway-placetopay'),
            'failed' => __('Failed', 'woocommerce-gateway-placetopay'),
            //Payment failed or was declined (unpaid). Note that this status may not show immediately and instead show as pending until verified
        ];

        if ($status) {
            return $labels[$status];
        }

        return $labels;
    }

    /**
     * Endpoint for the notification of PlacetoPay
     *
     * @param \WP_REST_Request $req
     * @return mixed
     */
    public function endpointPlacetoPay(\WP_REST_Request $req)
    {
        $message = null;
        $success = false;

        $this->logger('starting request api', 'endpointPlacetoPay');

        $data = $req->get_params();

        if (!empty($data['signature']) && !empty($data['requestId'])) {
            $notification = new Notification($data, $this->tran_key);

            if (!$notification->isValidNotification()) {
                if ($this->testmode == 'yes') {
                    return $notification->makeSignature();
                }

                return [
                    'success' => $success,
                    'message' => $message,
                ];
            }

            $transactionInfo = $this->placetopay->query($notification->requestId());

            switch ($transactionInfo->status()->status()) {
                case Status::ST_FAILED:
                case Status::ST_ERROR:
                    $message = $transactionInfo->status()->message();

                    $this->logger(
                        "status: {$transactionInfo->status()->status()}, message: {$transactionInfo->status()->message()}",
                        'endpointPlacetoPay'
                    );

                    break;
                default:
                    $fields = $transactionInfo->request()->fieldsToKeyValue();

                    if (!isset($fields['orderKey'])) {
                        $message = 'Not orderKey in response for notificationUrl';

                        $this->logger('Not orderKey in response for notificationUrl', 'endpointPlacetoPay');
                    } else {
                        $this->returnProcess(['key' => $fields['orderKey']], $transactionInfo, true);

                        $message = $transactionInfo->status()->message();
                        $success = true;

                        $this->logger('Response successfully', 'endpointPlacetoPay');
                    }

                    break;
            }
        }

        return [
            'success' => $success,
            'message' => $message,
        ];
    }

    /**
     * Before process submit, check fields
     */
    public function checkoutFieldProcess()
    {
        $this->validateFields($_POST);
    }

    /**
     * Process the payment for an order
     *
     * @param int $orderId
     * @return array|null
     */
    public function process_payment($orderId)
    {
        $requestId = get_post_meta($orderId, self::META_REQUEST_ID, true);
        $order = new WC_Order($orderId);

        $paymentStatus = get_post_meta($orderId, self::META_STATUS, true);

        if ($paymentStatus && $paymentStatus == 'APPROVED_PARTIAL' && $order->get_status() == 'pending') {
            $processUrl = get_post_meta($order->get_id(), GatewayMethod::META_PROCESS_URL, true);

            return [
                'result' => 'success',
                'redirect' => urldecode($processUrl)
            ];
        }

        $ref = $order->get_order_key() . '-' . time();
        $productInfo = $this->getDescriptionOrder($orderId);
        $redirectUrl = $this->getRedirectUrl($order);

        //For wooCoomerce 2.0
        $redirectUrl = add_query_arg('wc-api', $this->getClassName(), $redirectUrl);
        $redirectUrl = add_query_arg('order_id', $orderId, $redirectUrl);

        $timeExpiration = $this->expiration_time_minutes
            ? $this->expiration_time_minutes . ' minutes'
            : '+2 days';

        $req = [
            'expiration' => date('c', strtotime($timeExpiration)),
            'returnUrl' => $redirectUrl . '&key=' . $ref,
            'noBuyerFill' => $this->fill_buyer_information !== 'yes',
            'ipAddress' => (new RemoteAddress())->getIpAddress(),
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'skipResult' => $this->skip_result,
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
                    'total' => $order->get_total()
                ],
                'allowPartial' => $this->allow_partial_payments
            ],
            'fields' => [
                [
                    'keyword' => 'orderKey',
                    'value' => $order->get_order_key(),
                    'displayOn' => 'none',
                ],
            ],
        ];

        if (count($order->get_taxes()) > 0) {
            $req['payment']['amount']['taxes'] = $this->getOrderTaxes($order);
        }

        try {
            $res = $this->placetopay->request($req);

            if ($res->isSuccessful()) {
                update_post_meta($order->get_id(), self::META_REQUEST_ID, $res->requestId());
                update_post_meta($order->get_id(), self::META_STATUS, $res->status()->status());

                // Redirect the client to the processUrl or display it on the JS extension
                $processUrl = urlencode($res->processUrl());
                update_post_meta($order->get_id(), self::META_PROCESS_URL, $processUrl);

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

            wc_add_notice(__('Payment error: ', 'woocommerce-gateway-placetopay') . $res->status()->message(), 'error');
            $this->logger('Payment error: ' . $res->status()->message(), 'error');

        } catch (Exception $ex) {
            $this->logger($ex->getMessage(), 'error');
            wc_add_notice(__('Payment error: Server error internal.', 'woocommerce-gateway-placetopay'), 'error');
        }

        return null;
    }

    /**
     * @param $items
     * @return float
     */
    public function calculateSubtotalTax($items)
    {
        $subtotal = 0.00;

        foreach ($items as $item) {
            $data = $item->get_data();

            if ($data['total_tax'] !== '0') {
                $subtotal += $data['total'];
            }
        }

        return $subtotal;
    }

    /**
     * @param WC_Order $order
     * @return array
     */
    public function getOrderTaxes(WC_Order $order): array
    {
        $subTotal = $this->calculateSubtotalTax($order->get_items());
        $valueAddedTaxType = array_map('intval', $this->taxes['taxes_others']);
        $exciseDutyType = array_map('intval', $this->taxes['taxes_ico']);
        $iceType = array_map('intval', $this->taxes['taxes_ice']);
        $taxForP2P = [];

        foreach ($order->get_taxes() as $tax) {
            $taxData = $tax->get_data();

            if (in_array($taxData['rate_id'], $valueAddedTaxType)) {
                $totalTax = floatval((float) $order->get_shipping_tax() + $taxData['tax_total']);
                $totalBase = (float) $order->get_shipping_total() + $subTotal;

                $taxForP2P[] = [
                    'kind' => 'valueAddedTax',
                    'amount' => $totalTax,
                    'base' => $totalBase,
                ];
            }

            if (in_array($taxData['rate_id'], $exciseDutyType)) {
                $taxForP2P[] = [
                    'kind' => 'exciseDuty',
                    'amount' => floatval($taxData['tax_total']),
                    'base' => $subTotal,
                ];
            }

            if (in_array($taxData['rate_id'], $iceType)) {
                $taxForP2P[] = [
                    'kind' => 'ice',
                    'amount' => floatval($taxData['tax_total']),
                    'base' => $subTotal,
                ];
            }
        }

        return $taxForP2P;
    }

    /**
     * After of process_payment, generate the PlacetoPay block modal with form datas to sending
     *
     * @param $orderId
     */
    public function receiptPage($orderId): void
    {
        try {
            $requestId = get_post_meta($orderId, self::META_REQUEST_ID, true);
            $transactionInfo = $this->placetopay->query($requestId);

            if (!is_null($transactionInfo->payment)) {
                $authorizationCode = count($transactionInfo->payment) > 0
                    ? array_map(function (Transaction $trans) {
                        return $trans->authorization();
                    }, $transactionInfo->payment)
                    : [];

                // Payment Details
                if (count($authorizationCode) > 0) {
                    $this->logger('Adding authorization code $auth = ' . $authorizationCode, 'receiptPage');
                    update_post_meta($orderId, self::META_AUTHORIZATION_CUS, implode(',', $authorizationCode));
                }
            }

            // Add information to the order to notify that exit to PlacetoPay
            // and invalidates the shopping cart
            $order = new WC_Order($orderId);
            $order->update_status('on-hold', __('Redirecting to Placetopay', 'woocommerce-gateway-placetopay'));

            $code = 'jQuery("body").block({
                message: "' . esc_js(__('We are now redirecting you to Placetopay to make payment, if you are not redirected please press the bottom.',
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

        wp_die(__("Placetopay Request Failure", 'woocommerce-gateway-placetopay'));
    }

    /**
     * After checkResponse, Process PlacetoPay response and update order information
     *
     * @param array $req Response data in array format
     * @return void
     */
    public function successfulRequest(array $req): void
    {
        // When the user is returned to the page specified by redirectUrl
        if (!empty($req['key']) && !empty($req['wc-api'])) {
            $requestId = get_post_meta($req['order_id'], self::META_REQUEST_ID, true);
            $transactionInfo = $this->placetopay->query($requestId);

            $this->returnProcess($req, $transactionInfo);
        }

        // For WooCommerce 2.0
        $redirectUrl = add_query_arg([
            'msg' => urlencode(__('There was an error on the request. please contact the website administrator.',
                'woocommerce-gateway-placetopay')),
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
        $authorizationCode = [];

        // Register status PlacetoPay for the order
        update_post_meta($order->get_id(), self::META_STATUS, $status);

        $this->logger([
            'Processing order #%s with status %s',
            $order->get_id(),
            $status
        ], __METHOD__);

        if ($sessionStatusInstance->status() !== $sessionStatusInstance::ST_PENDING) {
            $authorizationCode = $this->getAuthorizationCode($transactionInfo);
        }

        // Payment Details
        if ($status === Status::ST_APPROVED || $status === Status::ST_APPROVED_PARTIAL) {
            update_post_meta(
                $order->get_id(),
                self::META_AUTHORIZATION_CUS,
                is_array($authorizationCode)
                    ? implode(',', $authorizationCode)
                    : $authorizationCode
            );
        }

        if ($transactionInfo->payment() !== null) {
            $paymentFirstStatus = count($transactionInfo->payment()) > 0
                ? $transactionInfo->payment()[0]->status()
                : null;
        }

        // Get order updated with metas refreshed
        $order = wc_get_order($order->get_id());

        // We are here so lets check status and do actions
        switch ($status) {
            case $sessionStatusInstance::ST_APPROVED:
            case $sessionStatusInstance::ST_APPROVED_PARTIAL:
            case $sessionStatusInstance::ST_PENDING:
                // Check order not already completed
                if ($order->get_status() == 'completed') {
                    $this->logger('Aborting, Order #' . $order->get_id() . ' is already complete.');

                    if ($isCallback) {
                        return;
                    }

                    exit;
                }

                $totalAmount = $transactionInfo->request()->payment()->amount()->total();

                // We add a field to store the order total for partial payment
                update_post_meta($order->get_id(), '_order_total_partial', $totalAmount);

                if ($status == $sessionStatusInstance::ST_APPROVED_PARTIAL && in_array($order->get_status(), ['pending', 'on-hold'])) {
                    $pendingAmount = 0;

                    foreach ($transactionInfo->payment() as $transaction) {
                        if ($transaction->status()->status() == $sessionStatusInstance::ST_APPROVED) {
                            $pendingAmount += $transaction->amount()->from()->total();
                        }
                    }

                    $totalAmount = $totalAmount - $pendingAmount;

                    update_post_meta($order->get_id(), '_order_total_partial', $totalAmount);
                }

                $payerEmail = $transactionInfo->request()->payer()
                    ? $transactionInfo->request()->payer()->email()
                    : null;

                if (!is_null($transactionInfo->payment())) {
                    $paymentMethodName = count($transactionInfo->payment()) > 0
                        ? array_map(function (Transaction $trans) {
                            return $trans->paymentMethodName();
                        }, $transactionInfo->payment)
                        : [];

                    if (count($paymentMethodName) > 0) {
                        update_post_meta(
                            $order->get_id(), __('Payment type', 'woocommerce-gateway-placetopay'),
                            implode(",", $paymentMethodName)
                        );
                    }
                }

                // Validate Amount
                if ($order->get_total() != floatval($totalAmount)) {
                    $message = sprintf(
                        __('Validation error: Placetopay amounts do not match (gross %s).', 'woocommerce-gateway-placetopay'),
                        $totalAmount
                    );

                    $order->update_status('on-hold', $message);

                    $this->msg['message'] = $message;
                    $this->msg['class'] = 'woocommerce-error';
                }

                if (!empty($payerEmail)) {
                    update_post_meta(
                        $order->get_id(),
                        __('Payer Placetopay email', 'woocommerce-gateway-placetopay'),
                        $payerEmail
                    );
                }

                if ($status == $sessionStatusInstance::ST_APPROVED) {
                    $this->msg['message'] = $this->msg_approved;
                    $this->msg['class'] = 'woocommerce-message';

                    $order->add_order_note(__('Placetopay payment approved', 'woocommerce-gateway-placetopay'));
                    $order->payment_complete();
                    $this->logger('Payment approved for order # ' . $order->get_id(), __METHOD__);
                } else {
                    if ($paymentFirstStatus && $paymentFirstStatus->status() === $paymentFirstStatus::ST_APPROVED) {
                        update_post_meta(
                            $order->get_id(),
                            self::META_STATUS,
                            $sessionStatusInstance::ST_APPROVED_PARTIAL
                        );
                    }

                    // TODO: This is the bug that set order to on-old
                    $statusOrder = ($paymentFirstStatus && $paymentFirstStatus->status() === $paymentFirstStatus::ST_PENDING)
                        ? 'on-hold'
                        : 'pending';

                    $order->update_status(
                        $statusOrder,
                        sprintf(__('Payment pending: %s', 'woocommerce-gateway-placetopay'), $status)
                    );

                    $this->msg['message'] = $this->msg_pending;
                    $this->msg['class'] = 'woocommerce-info';
                }

                break;
            case $sessionStatusInstance::ST_REJECTED:
            case $sessionStatusInstance::ST_REFUNDED:
                if ($status === $sessionStatusInstance::ST_REJECTED) {
                    $order->update_status(
                        'cancelled',
                        sprintf(__('Payment rejected via Placetopay.', 'woocommerce-gateway-placetopay'), $status)
                    );

                    $this->msg['message'] = $this->msg_cancel;

                    if ($paymentFirstStatus) {
                        $this->logger($paymentFirstStatus->message(), $status);
                    }

                    if (!self::versionCheck()) {
                        $this->restoreOrderStock($order->get_id());
                    }

                } else {
                    $order->update_status(
                        'refunded',
                        sprintf(
                            __('Payment rejected via Placetopay. Error type: %s.', 'woocommerce-gateway-placetopay'),
                            $status
                        )
                    );

                    $this->msg['message'] = $this->msg_declined;
                }

                $this->msg['class'] = 'woocommerce-error';

                break;
            case $sessionStatusInstance::ST_FAILED:
                update_post_meta($order->get_id(), self::META_STATUS, $sessionStatusInstance::ST_PENDING);

                $this->logger('Payment failed for order # ' . $order->get_id(), __METHOD__);
                $this->msg['class'] = 'woocommerce-error';

                break;
            case $sessionStatusInstance::ST_ERROR:
            default:
                $order->update_status(
                    'failed',
                    sprintf(__('Payment rejected via Placetopay.', 'woocommerce-gateway-placetopay'), $status)
                );

                $this->msg['message'] = $this->msg_cancel;
                $this->msg['class'] = 'woocommerce-error';

                if (!self::versionCheck()) {
                    $this->restoreOrderStock($order->get_id());
                }

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
            'order_key' => $order->get_order_key(),
            'payment_method' => 'placetopay'
        ], $redirectUrl);

        wp_redirect($redirectUrl);

        exit;
    }

    /**
     * @param RedirectInformation $transaction
     * @return array
     */
    private function getAuthorizationCode(RedirectInformation $transaction)
    {
        if (!$this->allow_partial_payments && !is_null($transaction->payment())) {
            return !is_null($transaction->payment())
                ? $transaction->payment()[0]->authorization()
                : [];
        } else {
            $transactions = [];

            if (!is_null($transaction->payment())) {
                foreach ($transaction->payment() as $transaction) {
                    $transactions[] = $transaction;
                }
            }

            return !empty($transactions)
                ? array_map(function (Transaction $trans) {
                    return $trans->authorization();
                }, $transactions)
                : [];
        }
    }

    /**
     *  Get order instance with a given order key
     *
     * @param mixed $request
     * @return WC_Order
     */
    public function getOrder($request): WC_Order
    {
        $orderId = isset($request['order_id']) ? (int)$request['order_id'] : null;
        $key = $request['key'] ?? '';
        $orderKey = explode('-', $key);
        $orderKey = $orderKey[0] ?: $orderKey;

        $order = new WC_Order($orderId);

        if (!$order->get_id() || $order->get_id() === 0) {
            $orderId = wc_get_order_id_by_order_key($orderKey);
            $order = new WC_Order($orderId);
        }

        // Validate key
        if ($key && $order->get_order_key() !== $orderKey) {
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
        $order = $this->getLastPendingOrder();

        if (!$order) {
            return;
        }

        $authCode = get_post_meta($order->get_id(), self::META_AUTHORIZATION_CUS, true);

        $message = sprintf(
            __(
                'At this time your order #%s display a checkout transaction which is pending receipt of confirmation from your financial institution,
                please wait a few minutes and check back later to see if your payment was successfully confirmed. For more information about the current
                state of your operation you may contact our customer service line at %s or send your concerns to the email %s and ask for the status of the transaction: \'%s\'',
                'woocommerce-gateway-placetopay'
            ),
            (string)$order->get_id(),
            $this->merchant_phone,
            $this->merchant_email,
            (
                $authCode == ''
                    ? ''
                    : sprintf(__('CUS/Authorization', 'woocommerce-gateway-placetopay').' #%s', $authCode)
            )
        );

        echo "<div class='shop_table order_details'>
                <p scope='row'>{$message}</p>
            </div>";
    }

    /**
     * Check if Gateway can be display
     *
     * @return bool
     */
    public function is_available()
    {
        global $woocommerce;

        if ($this->enabled == 'yes') {
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
    public function logger($message, $type = null): void
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
     * @param bool $logger
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

            if ($stockReduced === 'yes') {
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

        return Status::ST_PENDING === $statusP2P
            || Status::ST_OK === $statusP2P
            || empty($statusP2P);
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
     * @return string
     */
    public static function getOrderNumber($order)
    {
        /** @var \WC_Order $order */
        return $order->get_order_number();
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
     * Return the gateway's icon.
     * @return string
     */
    public function get_icon()
    {
        $icon = '';

        if ($this->icon) {
            $icon = sprintf(
                '<a href="%s" target="_blank"><img src="%s" style="max-height: 24px; max-width: 200px;" alt="%s"/></a>',
                'https://www.placetopay.com/',
                WC_HTTPS::force_https_url($this->icon),
                esc_attr($this->get_title())
            );
        }

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
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

    public function getCountryList()
    {
        return [
            Country::CO => __('Colombia', 'woocommerce-gateway-placetopay'),
            Country::EC => __('Ecuador', 'woocommerce-gateway-placetopay'),
            Country::CR => __('Costa Rica', 'woocommerce-gateway-placetopay'),
            Country::CL => __('Chile', 'woocommerce-gateway-placetopay'),
        ];
    }

    /**
     * Return list of environments for selection
     *
     * @return array
     */
    protected function getEnvironments()
    {
        return [
            Environment::DEV => __('Development', 'woocommerce-gateway-placetopay'),
            Environment::TEST => __('Test', 'woocommerce-gateway-placetopay'),
            Environment::PROD => __('Production', 'woocommerce-gateway-placetopay'),
            Environment::CUSTOM => __('Custom', 'woocommerce-gateway-placetopay'),
        ];
    }

    /**
     * Get expiration time minutes list
     *
     * @return array
     */
    protected function getListOptionExpirationMinutes()
    {
        $options = [];
        $format = '%d %s';
        $minutes = 10;

        while ($minutes <= self::EXPIRATION_TIME_MINUTES_LIMIT) {
            if ($minutes < 60) {
                $options[$minutes] = sprintf($format, $minutes, __('Minutes', 'woocommerce-gateway-placetopay'));
                $minutes += 10;

            } elseif ($minutes >= 60 && $minutes < 1440) {
                $options[$minutes] = sprintf($format, $minutes / 60, __('Hour(s)', 'woocommerce-gateway-placetopay'));
                $minutes += 60;

            } elseif ($minutes >= 1440 && $minutes < 10080) {
                $options[$minutes] = sprintf($format, $minutes / 1440, __('Day(s)', 'woocommerce-gateway-placetopay'));
                $minutes += 1440;

            } elseif ($minutes >= 10080 && $minutes < 40320) {
                $options[$minutes] = sprintf($format, $minutes / 10080,
                    __('Week(s)', 'woocommerce-gateway-placetopay'));
                $minutes += 10080;

            } else {
                $options[$minutes] = sprintf($format, $minutes / 40320,
                    __('Month(s)', 'woocommerce-gateway-placetopay'));
                $minutes += 40320;
            }
        }

        return $options;
    }

    /**
     * @return array
     */
    protected function getListTaxes()
    {
        $countries = $this->getCountryList();
        $formatTaxItem = '%s( %s ) - %s - %s %%';
        $taxList = [];

        foreach ($countries as $countryCode => $countryName) {
            $taxes = \WC_Tax::find_rates(['country' => $countryCode]);

            foreach ($taxes as $taxId => $tax) {
                $taxList[$taxId . '_'] = sprintf($formatTaxItem,
                    $countryName,
                    $countryCode,
                    $tax['label'],
                    $tax['rate']
                );
            }
        }

        return $taxList;
    }

    /**
     * Instantiates a PlacetoPay object providing the login and tranKey,
     * also the url that will be used for the service
     */
    private function initPlacetoPay()
    {
        try {
            $this->placetopay = new PlacetoPay([
                'login' => $this->login,
                'tranKey' => $this->tran_key,
                'url' => $this->uri_service,
            ]);

        } catch (PlacetoPayException $ex) {
            $this->logger($ex->getMessage());
        }
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
     * @param $request
     * @return bool
     */
    private function validateFields($request)
    {
        $isValid = true;

        if ($this->allow_to_pay_with_pending_orders === 'no' && $this->getLastPendingOrder() !== null) {
            wc_add_notice(__('<strong>Pending order</strong>, the payment could not be continued because a pending order has been found.',
                'woocommerce-gateway-placetopay'), 'error');

            $isValid = false;
        }

        if (preg_match(PersonValidator::PATTERN_NAME, trim($request['billing_first_name'])) !== 1) {
            wc_add_notice(__('<strong>First Name</strong>, does not have a valid format',
                'woocommerce-gateway-placetopay'), 'error');

            $isValid = false;
        }

        if (preg_match(PersonValidator::PATTERN_SURNAME, trim($request['billing_last_name'])) !== 1) {
            wc_add_notice(__('<strong>Last Name</strong>, does not have a valid format',
                'woocommerce-gateway-placetopay'), 'error');

            $isValid = false;
        }

        if (!PersonValidator::isValidCountryCode($request['billing_country'])) {
            wc_add_notice(__('<strong>Country</strong>, is not valid.',
                'woocommerce-gateway-placetopay'), 'error');

            $isValid = false;
        }

        if (! is_numeric($request['billing_state']) && preg_match(PersonValidator::PATTERN_STATE, trim($request['billing_state'])) !== 1) {
            wc_add_notice(__('<strong>State / Country</strong>, does not have a valid format',
                'woocommerce-gateway-placetopay'), 'error');

            $isValid = false;
        }

        if (! is_numeric($request['billing_state']) && preg_match(PersonValidator::PATTERN_CITY, trim($request['billing_city'])) !== 1) {
            wc_add_notice(__('<strong>Town / City</strong>, does not have a valid format',
                'woocommerce-gateway-placetopay'), 'error');

            $isValid = false;
        }

        if (preg_match(PersonValidator::PATTERN_PHONE, trim($request['billing_phone'])) !== 1) {
            wc_add_notice(__('<strong>Phone</strong>, does not have a valid format',
                'woocommerce-gateway-placetopay'), 'error');

            $isValid = false;
        }

        if (preg_match(PersonValidator::PATTERN_EMAIL, trim($request['billing_email'])) !== 1) {
            wc_add_notice(__('<strong>Email</strong>, does not have a valid format',
                'woocommerce-gateway-placetopay'), 'error');

            $isValid = false;
        }

        if ($this->minimum_amount != null && WC()->cart->total < $this->minimum_amount) {
            wc_add_notice(sprintf(__('<strong>Minimum amount</strong>, does not meet the minimum amount to process the order, the minimum amount must be greater or equal to %s to use this payment gateway.',
                'woocommerce-gateway-placetopay'), number_format($this->minimum_amount, 2, '.', ',')), 'error');

            $isValid = false;
        }

        if ($this->maximum_amount != null && WC()->cart->total > $this->maximum_amount) {
            wc_add_notice(sprintf(__('<strong>Maximum amount</strong>, exceeds the maximum amount allowed to process the order, it must be less or equal to %s to use this payment gateway.',
                'woocommerce-gateway-placetopay'), number_format($this->maximum_amount, 2, '.', ',')), 'error');

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

    /**
     * @param $orderId
     * @return string
     */
    private function getDescriptionOrder($orderId)
    {
        $orderInfo = __('Order %s - Products: %s', 'woocommerce-gateway-placetopay');
        $order = wc_get_order($orderId);
        /** @var \WC_Order_item[] $items */
        $items = $order->get_items();
        $products = [];

        foreach ($items as $item) {
            $products[] = $item->get_name();
        }

        return $this->normalizeDescription(
            sprintf($orderInfo, $orderId, implode(',', $products))
        );
    }

    /**
     * @param $text
     * @return mixed
     */
    private function normalizeDescription($text)
    {
        $array = explode(' - ', $text);
        $title = explode(': ', $array[1]);
        $products = explode(',', $title[1]);
        $final = null;

        foreach ($products as $key => $value) {
            if (strlen($final) < 150) {
                if (count($products) - 1 == $key || $key >= 9) {
                    $final .= "{$value}";

                    break;
                } else {
                    $final .= "{$value},";
                }
            } else {
                $final .= "{$value},etc...";

                break;
            }
        }

        return filter_var(
            "{$array[0]} - {$title[0]}: {$final}",
            FILTER_SANITIZE_STRING,
            FILTER_FLAG_STRIP_LOW
        );
    }

    private function configureEnvironment()
    {
        $environmentByCountry = [
            Country::CO => [
                Environment::PROD => 'https://checkout.placetopay.com',
                Environment::TEST => 'https://test.placetopay.com/redirection',
                Environment::DEV => 'https://dev.placetopay.com/redirection',
            ],
            Country::EC => [
                Environment::PROD => 'https://checkout.placetopay.ec',
                Environment::TEST => 'https://test.placetopay.ec/redirection',
                Environment::DEV => 'https://dev.placetopay.ec/redirection',
            ],
            Country::CR => [
                Environment::PROD => 'https://checkout.placetopay.com',
                Environment::TEST => 'https://test.placetopay.com/redirection',
                Environment::DEV => 'https://dev.placetopay.com/redirection',
            ],
            Country::CL => [
                Environment::PROD => 'https://checkout-getnet-cl.placetopay.com',
                Environment::TEST => 'https://uat-checkout.placetopay.ws',
                Environment::DEV => 'https://dev.placetopay.com/redirection/',
            ]
        ][$this->settings['country']];

        $this->testmode = in_array($this->enviroment_mode, [Environment::TEST, Environment::DEV]) ? 'yes' : 'no';

        if ($this->enviroment_mode == Environment::CUSTOM) {
            $this->uri_service = empty($this->custom_connection_url) ? null : $this->custom_connection_url;
        } else {
            if ($this->testmode == 'yes') {
                $this->debug = 'yes';
                $this->log = $this->wooCommerceVersionCompare('2.1')
                    ? new \WC_Logger()
                    : WC()->logger();

                $this->uri_service = $this->enviroment_mode === Environment::DEV
                    ? $environmentByCountry[Environment::DEV]
                    : $environmentByCountry[Environment::TEST];

            } else {
                if ($this->enviroment_mode === Environment::PROD) {
                    $this->debug = 'no';
                    $this->uri_service = $environmentByCountry[Environment::PROD];
                }
            }
        }

        // By default always it will be environment of development testing
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->settings['enviroment_mode'] = Environment::DEV;
            $this->uri_service = $environmentByCountry[Environment::DEV];
        }
    }

    /**
     * @return null|WC_Order
     */
    private function getLastPendingOrder()
    {
        $userId = get_current_user_id();

        if (!$userId) {
            return null;
        }

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
                $order = new WC_Order($_order);

                if (!self::isPendingStatusOrder($order->get_id())) {
                    continue;
                }

                if ($order->get_status() == 'pending' || $order->get_status() == 'on-hold') {
                    return $order;
                }
            }
        }

        return null;
    }

    /**
     * @param string $version
     *
     * @return bool
     */
    public static function versionCheck(string $version = '3.0'): bool
    {
        if (class_exists('WooCommerce')) {
            global $woocommerce;

            if (version_compare($woocommerce->version, $version, ">=")) {
                return true;
            }
        }

        return false;
    }
}
