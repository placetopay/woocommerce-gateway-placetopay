<?php

namespace PlacetoPay\PaymentMethod;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Dnetix\Redirection\Entities\PaymentModifier;
use Dnetix\Redirection\Entities\Status;
use Dnetix\Redirection\Entities\Transaction;
use Dnetix\Redirection\Exceptions\PlacetoPayServiceException;
use Dnetix\Redirection\Message\Notification;
use Dnetix\Redirection\Message\RedirectInformation;
use Dnetix\Redirection\PlacetoPay;
use Exception;
use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Discount;
use PlacetoPay\PaymentMethod\Constants\Environment;
use PlacetoPay\PaymentMethod\Constants\Rules;
use stdClass;
use WC_HTTPS;
use WC_Order;
use WC_Payment_Gateway;

/**
 * Class GatewayMethod.
 */
class GatewayMethod extends WC_Payment_Gateway
{
    const VERSION = '3.1.4';

    const META_AUTHORIZATION_CUS = '_p2p_authorization';

    const META_REQUEST_ID = '_p2p_request_id';

    const META_STATUS = '_p2p_status';

    const META_STOCK_RESTORED = '_p2p_stock_restored:%s';

    const META_PROCESS_URL = '_p2p_process_url';

    /**
     * URI endpoint namespace via wordpress for the notification of the service
     * @var array
     */
    const PAYMENT_ENDPOINT_NAMESPACE = 'placetopay-payment/v2';

    /**
     * URI endpoint namespace via wordpress for the notification of the service
     * @var array
     */
    const PAYMENT_ENDPOINT_CALLBACK = '/callback/';

    /**
     * Name of action to draw view with order data after payment process
     */
    const NOTIFICATION_RETURN_PAGE = 'placetopay_notification_return_page';

    const EXPIRATION_TIME_MINUTES_LIMIT = 2880;


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
     * @var \WC_Logger
     */
    private $log;

    private $expiration_time_minutes;
    private $currency;
    private $enviroment_mode;
    private $fill_buyer_information;
    private $login;
    private $redirect_page_id;
    private $testmode;
    private $merchant_email;
    private $uri_service;
    private $taxes;
    private $minimum_amount;
    private $maximum_amount;
    private $allow_to_pay_with_pending_orders;
    private $allow_partial_payments;
    private $skip_result;
    private $custom_connection_url;
    private $payment_button_image;
    private $version;
    private $use_lightbox;
    private $endpoint;
    private $form_method;

    /**
     * GatewayMethod constructor.
     */
    function __construct()
    {
        $this->version = self::VERSION;
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
        $this->id = CountryConfig::CLIENT_ID;
        $this->title = CountryConfig::CLIENT;
        $this->method_title = CountryConfig::CLIENT;
        $this->method_description = __('Sells online safely and agile', 'woocommerce-gateway-translations');
        $this->has_fields = false;

        $this->initFormFields();
        $this->settings['endpoint'] = home_url('/wp-json/') . self::getPaymentEndpoint();

        $this->endpoint = $this->settings['endpoint'];

        $this->enviroment_mode = $this->get_option('enviroment_mode');
        $this->description = sprintf(__('Pay securely through %s.', 'woocommerce-gateway-translations'), $this->getClient());
        $this->login = $this->get_option('login');
        $this->tran_key = $this->get_option('tran_key');
        $this->redirect_page_id = $this->get_option('redirect_page_id');
        $this->form_method = $this->get_option('form_method');

        $this->use_lightbox = $this->get_option('use_lightbox') === 'yes';
        $this->skip_result = $this->get_option('skip_result') === "yes";
        $this->custom_connection_url = $this->get_option('custom_connection_url');
        $this->payment_button_image = $this->get_option('payment_button_image');
        $this->merchant_email = get_option('woocommerce_email_from_address');
        $this->icon = $this->getImageUrl();
        $this->currency = get_woocommerce_currency();
        $this->currency = $this->currency ?? 'COP';

        $configurations = CountryConfig::getConfiguration($this);

        foreach ($configurations as $key => $value) {
            $this->$key = $value;
        }

        $this->configureEnvironment();
    }

    public function getScheduleTaskPath(): string
    {
        return WP_PLUGIN_DIR . '/woocommerce-gateway-translations/cron/ProcessPendingOrderCron.php';
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

        if (self::wooCommerceVersionCompare('2.0.0')) {
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

    public function getImageUrl(): ?string
    {
        $url = $this->payment_button_image;

        if (empty($url)) {
            $image = CountryConfig::IMAGE;
        } elseif ($this->checkValidUrl($url)) {
            // format: https://www.domain.test/image.svg
            $image = $url;
        } elseif ($this->checkDirectory($url)) {
            // format: /folder/image.svg
            $image = home_url('/wp-content/uploads/') . $url;
        } else {
            // format: image
            $image = 'https://static.placetopay.com/' . $url . '.svg';
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
            'pending' => __('Pending', 'woocommerce-gateway-translations'),
            'processing' => __('Approved', 'woocommerce-gateway-translations'),
            'on-hold' => __('Pending', 'woocommerce-gateway-translations'),
            'completed' => __('Approved', 'woocommerce-gateway-translations'),
            'refunded' => __('Refunded', 'woocommerce-gateway-translations'),
            'cancelled' => __('Cancelled', 'woocommerce-gateway-translations'),
            'failed' => __('Failed', 'woocommerce-gateway-translations'),
        ];

        if ($status) {
            return $labels[$status];
        }

        return $labels;
    }

    /**
     * Endpoint for the notification
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
            $expectedSignature = sprintf(
                '%s%s%s%s',
                $data['requestId'],
                $data['status']['status'],
                $data['status']['date'],
                $this->tran_key,
            );

            if (strpos($data['signature'], ':') === false) {
                $data['signature'] = 'sha1:' . $data['signature'];
            }

            [$algo, $signature] = explode(':', $data['signature'], 2);

            $this->logger('signature alghorithm: ' . $algo, 'endpointPlacetoPay');

            if (hash($algo, $expectedSignature) !== $signature) {
                if ($this->testmode === 'yes') {
                    return hash($algo, $expectedSignature);
                }

                return [
                    'success' => $success,
                    'message' => $message,
                ];
            }

            $transactionInfo = $this->placetopay->query((int)$data['requestId']);

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

    public static function validateVersionSupportBlocks(): bool
    {
        return self::wooCommerceVersionCompare(8.3) &&
            class_exists('\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry');
    }

    /**
     * Before process submit, check fields
     */
    public function checkoutFieldProcess()
    {
        $this->validateFields($_POST);
    }

    /**
     * @return array|stdClass|WC_Order[]
     */
    private function getPendingOrders($order)
    {
        $userId = $order->get_customer_id();

        if (!$userId) {
            return [];
        }

        return wc_get_orders([
            'customer_id' => $userId,
            'status' => ['wc-pending', 'wc-on-hold'],
            'limit' => 1,
        ]);
    }

    private function validateBlocksFields($order)
    {
        if (!$this->allow_to_pay_with_pending_orders && !empty($this->getPendingOrders($order))) {
            throw new Exception(__(
                '<strong>Pending order</strong>, the payment could not be continued because a pending order has been found.',
                'woocommerce-gateway-translations'
            ));
        }

        if (!preg_match(Rules::PATTERN_NAME, trim($order->get_billing_first_name()))) {
            throw new Exception(__('<strong>First Name</strong>, does not have a valid format', 'woocommerce-gateway-translations'));
        }

        if (!preg_match(Rules::PATTERN_NAME, trim($order->get_billing_last_name()))) {
            throw new Exception(__('<strong>Last Name</strong>, does not have a valid format', 'woocommerce-gateway-translations'));
        }

        if (!preg_match(Rules::PATTERN_PHONE, trim($order->get_billing_phone()))) {
            throw new Exception(__('<strong>Phone</strong>, does not have a valid format', 'woocommerce-gateway-translations'));
        }

        if (!preg_match(Rules::PATTERN_EMAIL, trim($order->get_billing_email()))) {
            throw new Exception(__('<strong>Email</strong>, does not have a valid format', 'woocommerce-gateway-translations'));
        }

        if ($this->minimum_amount != null && $order->get_total() < $this->minimum_amount) {
            throw new Exception(sprintf(__(
                '<strong>Minimum amount</strong>, does not meet the minimum amount to process the order, the minimum amount must be greater or equal to %s to use this payment gateway.'
                , 'woocommerce-gateway-translations'), number_format($this->minimum_amount, 2, '.', ',')
            ));
        }

        if ($this->maximum_amount != null && $order->get_total() > $this->maximum_amount) {
            throw new Exception(sprintf(__(
                '<strong>Maximum amount</strong>, exceeds the maximum amount allowed to process the order, it must be less or equal to %s to use this payment gateway.'
                , 'woocommerce-gateway-translations'), number_format($this->maximum_amount, 2, '.', ',')
            ));
        }

        return true;
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

        if (self::validateVersionSupportBlocks()) {
            $this->validateBlocksFields($order);
        }

        if ($paymentStatus && $paymentStatus == 'APPROVED_PARTIAL' && $order->get_status() == 'pending') {
            $processUrl = get_post_meta($order->get_id(), GatewayMethod::META_PROCESS_URL, true);

            return [
                'result' => 'success',
                'redirect' => urldecode($processUrl)
            ];
        }

        $timeExpiration = $this->expiration_time_minutes
            ? $this->expiration_time_minutes . ' minutes'
            : '30 minutes';

        $orderNumber = self::getOrderNumber($order);

        $req = [
            'locale' => get_locale(),
            'expiration' => date('c', strtotime($timeExpiration)),
            'returnUrl' => $this->getPaymentReturnUrl($order),
            'noBuyerFill' => !$this->fill_buyer_information,
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
                'reference' => $orderNumber,
                'description' => sprintf(__('Payment on %s No: %s', 'woocommerce-gateway-translations'), $this->getClient(), $orderNumber),
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

        if ($this->getCountry() === Country::UY) {
            $discountCode = $this->get_option('discount');

            if ($discountCode !== Discount::UY_NONE) {
                $req['payment']['modifiers'] = [
                    new PaymentModifier([
                        'type' => PaymentModifier::TYPE_FEDERAL_GOVERNMENT,
                        'code' => $discountCode,
                        'additional' => [
                            'invoice' => $this->get_option('invoice')
                        ]
                    ])
                ];
            }
        }

        if (count($order->get_taxes()) > 0) {
            $req['payment']['amount']['taxes'] = $this->getOrderTaxes($order);
        }

        try {
            $this->logger('Payment URI: ' . $this->uri_service, __METHOD__);

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

            $this->logger('Payment error: ' . $res->status()->message(), 'error');

            throw new PlacetoPayServiceException($res->status()->message());
        } catch (PlacetoPayServiceException $exception) {
            throw new Exception(__('Payment error: ', 'woocommerce-gateway-translations'). $exception->getMessage());
        } catch (Exception $ex) {
            $this->logger($ex->getMessage(), 'error');
            wc_add_notice(__('Payment error: Server error internal.', 'woocommerce-gateway-translations'), 'error');
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
                $shippingTax = (float)$order->get_shipping_tax();
                $totalTax = $shippingTax + $taxData['tax_total'];
                $totalBase = $shippingTax > 0 ? ((float)$order->get_shipping_total() + $subTotal) : $subTotal;

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
     * After of process_payment, generate the payment block modal with form datas to sending
     *
     * @param $orderId
     */
    public function receiptPage($orderId): void
    {
        try {
            $requestId = get_post_meta($orderId, self::META_REQUEST_ID, true);
            $transactionInfo = $this->placetopay->query($requestId);

            if (!is_null($transactionInfo->payment())) {
                $authorizationCode = count($transactionInfo->payment()) > 0
                    ? array_map(function (Transaction $trans) {
                        return $trans->authorization();
                    }, $transactionInfo->payment())
                    : [];

                // Payment Details
                if (count($authorizationCode) > 0) {
                    $this->logger('Adding authorization code $auth = ' . $authorizationCode, 'receiptPage');
                    update_post_meta($orderId, self::META_AUTHORIZATION_CUS, implode(',', $authorizationCode));
                }
            }

            // Add information to the order to notify that exit to payment
            // and invalidates the shopping cart
            $order = new WC_Order($orderId);
            $order->update_status('on-hold', sprintf(__('Redirecting to %s', 'woocommerce-gateway-translations'), $this->getClient()));

            $this->resolveWebCheckout($order);

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

        wp_die(sprintf(__("%s Request Failure", 'woocommerce-gateway-translations'), $this->getClient()));
    }

    /**
     * After checkResponse, Process response and update order information
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
            'msg' => urlencode(__(
                'There was an error on the request. please contact the website administrator.',
                'woocommerce-gateway-translations'
            )),
            'type' => 'woocommerce-info'
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
        $currentPaymentStatus = get_post_meta($order->get_id(), self::META_STATUS, true);

        if ($isCallback && $currentPaymentStatus === Status::ST_APPROVED) {
            $this->logger('Returning method is already ' . $currentPaymentStatus, __METHOD__);
            return;
        }

        // Register status payment for the order
        update_post_meta($order->get_id(), self::META_STATUS, $status);

        $this->logger([
            'Processing order #%s with status %s',
            $order->get_id(),
            $status
        ], __METHOD__);

        if ($sessionStatusInstance->status() !== $sessionStatusInstance::ST_PENDING) {
            $authorizationCode = $this->getAuthorizationCode($transactionInfo);
            $authorizationCode = is_array($authorizationCode)
                ? implode(',', $authorizationCode)
                : $authorizationCode;
        }

        // Payment Details
        if ($status === Status::ST_APPROVED || $status === Status::ST_APPROVED_PARTIAL) {
            update_post_meta(
                $order->get_id(),
                self::META_AUTHORIZATION_CUS,
                $authorizationCode
            );
        }

        $paymentFirstStatus = count($transactionInfo->payment()) > 0
            ? $transactionInfo->payment()[0]->status()
            : null;

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
                        }, $transactionInfo->payment())
                        : [];

                    if (count($paymentMethodName) > 0) {
                        update_post_meta(
                            $order->get_id(),
                            __('Payment type', 'woocommerce-gateway-translations'),
                            implode(",", $paymentMethodName)
                        );
                    }
                }

                // Validate Amount
                if ($order->get_total() != floatval($totalAmount)) {
                    $message = sprintf(
                        __('Validation error: %s amounts do not match (gross %s).', 'woocommerce-gateway-translations'),
                        $this->getClient(),
                        $totalAmount
                    );

                    $order->update_status('on-hold', $message);
                }

                if (!empty($payerEmail)) {
                    update_post_meta(
                        $order->get_id(),
                        sprintf(__('Payer %s email', 'woocommerce-gateway-translations'), $this->getClient()),
                        $payerEmail
                    );
                }

                if ($status == $sessionStatusInstance::ST_APPROVED) {
                    if ($currentPaymentStatus !== Status::ST_APPROVED) {
                        $payment = $transactionInfo->lastApprovedTransaction();

                        if ($this->isRefunded($payment)) {
                            $this->resolveRefundedPayment($order);

                        } else {
                            $order->add_order_note($this->getOrderNote($order->get_id(), $payment, $status, $totalAmount));
                            $order->add_meta_data('placetopay_response', json_encode($payment->toArray()));
                            $order->payment_complete();
                            $this->logger('Payment approved for order # ' . $order->get_id(), __METHOD__);
                        }
                    }
                } else {
                    if ($paymentFirstStatus && $paymentFirstStatus->status() === $paymentFirstStatus::ST_APPROVED) {
                        if ($this->isRefunded($transactionInfo->lastApprovedTransaction())) {
                            $this->resolveRefundedPayment($order);
                        } else {
                            update_post_meta(
                                $order->get_id(),
                                self::META_STATUS,
                                $sessionStatusInstance::ST_APPROVED_PARTIAL
                            );

                            $order->update_status(
                                'pending',
                                __('Payment pending', 'woocommerce-gateway-translations') . ': ' . $status
                            );
                        }

                        break;
                    }

                    $order->add_order_note(__('Payment pending', 'woocommerce-gateway-translations'));

                }

                break;
            case $sessionStatusInstance::ST_REJECTED:
                $order->update_status(
                    'cancelled',
                    sprintf(__('Payment rejected.', 'woocommerce-gateway-translations'), $status)
                );

                if ($paymentFirstStatus) {
                    $this->logger($paymentFirstStatus->message(), $status);
                }

                if (!self::versionCheck()) {
                    $this->restoreOrderStock($order->get_id());
                }

                break;
            case $sessionStatusInstance::ST_FAILED:
                update_post_meta($order->get_id(), self::META_STATUS, $sessionStatusInstance::ST_PENDING);

                $this->logger('Payment failed for order # ' . $order->get_id(), __METHOD__);

                break;
            case $sessionStatusInstance::ST_ERROR:
            default:
                $order->update_status(
                    'failed',
                    sprintf(__('Payment rejected.', 'woocommerce-gateway-translations'), $status)
                );

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

    private function getOrderNote($id, Transaction $payment, string $status, $total)
    {
        $installmentType = $this->getInstallments($payment->additionalData()) > 0
            ? sprintf(__('%s installments', 'woocommerce-gateway-translations'), $this->getInstallments($payment->additionalData()))
            : __('No installments', 'woocommerce-gateway-translations');
        $message = '<p>' . __('Payment approved', 'woocommerce-gateway-translations') . '</p>';

        $details = [
            [
                'key' => __('Buying order: ', 'woocommerce-gateway-translations'),
                'value' => $id,
            ],
            [
                'key' => __('Status: ', 'woocommerce-gateway-translations'),
                'value' => $status,
            ],
            [
                'key' => __('Receipt: ', 'woocommerce-gateway-translations'),
                'value' => $payment->receipt(),
            ],
            [
                'key' => __('Authorization Code: ', 'woocommerce-gateway-translations'),
                'value' => $payment->authorization(),
            ],
            [
                'key' => __('Card last Digits: ', 'woocommerce-gateway-translations'),
                'value' => str_replace('*', '', $payment->additionalData()['lastDigits']),
            ],
            [
                'key' => __('Amount: ', 'woocommerce-gateway-translations'),
                'value' => '$' . number_format($total, '0', ',', '.'),
            ],
            [
                'key' => __('Response code: ', 'woocommerce-gateway-translations'),
                'value' => $payment->status()->reason(),
            ],
            [
                'key' => __('Payment Type: ', 'woocommerce-gateway-translations'),
                'value' => $payment->paymentMethodName(),
            ],
            [
                'key' => __('Installments Type: ', 'woocommerce-gateway-translations'),
                'value' => $installmentType,
            ],
            [
                'key' => __('Installments: ', 'woocommerce-gateway-translations'),
                'value' => $this->getInstallments($payment->additionalData()),
            ],
            [
                'key' => __('Transaction Date: ', 'woocommerce-gateway-translations'),
                'value' => $payment->status()->date(),
            ],
            [
                'key' => __('Internal id: ', 'woocommerce-gateway-translations'),
                'value' => $payment->internalReference(),
            ],
        ];

        $body = '';

        foreach ($details as $detail) {
            $body .= "<strong>{$detail['key']}</strong>{$detail['value']}<br/>";
        }

        return "
            <div class='placetopay-order-datails'>
                <p><h3>{$message}</h3></p>

                {$body}
            </div>
        ";
    }

    /**
     * @param RedirectInformation $transaction
     * @return array|string
     */
    private function getAuthorizationCode(RedirectInformation $transaction)
    {
        if (!$this->allow_partial_payments && $transaction->payment() !== []) {
            return $transaction->payment()[0]->authorization();
        }

        $transactions = [];

        foreach ($transaction->payment() as $transaction) {
            $transactions[] = $transaction;
        }

        return !empty($transactions)
            ? array_map(function (Transaction $trans) {
                return $trans->authorization();
            }, $transactions)
            : [];
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
            $this->logger(__('Error: Order Key does not match invoice.', 'woocommerce-gateway-translations'), 'getOrder');
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

        $message = sprintf(
            __(
                'At this time your order #%s display a checkout transaction which is pending receipt of confirmation from your financial institution,
                please wait a few minutes and check back later to see if your payment was successfully confirmed. For more information about the current
                state of your operation you may contact our customer service line or send your concerns to the email %s and ask for the status of the transaction',
                'woocommerce-gateway-translations'
            ),
            (string)$order->get_id(),
            $this->merchant_email
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
            if ($woocommerce->version < '1.5.8') {
                return false;
            }

            if ($this->testmode !== 'yes' && (!$this->login || !$this->tran_key)) {
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
        if ($this->testmode !== 'yes') {
            return;
        }

        if (is_array($message) && count($message) > 1) {
            $format = $message[0];
            array_shift($message);

            $message = vsprintf($format, $message);
        }

        $this->log->add(
            CountryConfig::CLIENT_ID,
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
        $gatewayMethod->initPlacetoPay();
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
                '<img src="%s" style="max-height: 24px; max-width: 200px;" alt="%s"/>',
                WC_HTTPS::force_https_url($this->icon),
                esc_attr($this->get_title())
            );
        }

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    /**
     * Get pages for return page setting
     *
     * @param boolean $title Title of the page
     * @return array
     */
    public function getPages($title = false)
    {
        $pageList = [
            'default' => __('Default Page', 'woocommerce-gateway-translations'),
        ];

        if ($title) {
            $pageList[] = $title;
        }

        $pageList['my-orders'] = __('My Orders', 'woocommerce-gateway-translations');

        return $pageList;
    }

    public function getDiscounts(): array
    {
        return [
            Discount::UY_NONE => __(Discount::UY_NONE, 'woocommerce-gateway-translations'),
            Discount::UY_IVA_REFUND => __(Discount::UY_IVA_REFUND, 'woocommerce-gateway-translations'),
            Discount::UY_IMESI_REFUND => __(Discount::UY_IMESI_REFUND, 'woocommerce-gateway-translations'),
            Discount::UY_FINANCIAL_INCLUSION => __(Discount::UY_FINANCIAL_INCLUSION, 'woocommerce-gateway-translations'),
            Discount::UY_AFAM_REFUND => __(Discount::UY_AFAM_REFUND, 'woocommerce-gateway-translations'),
            Discount::UY_TAX_REFUND => __(Discount::UY_TAX_REFUND, 'woocommerce-gateway-translations'),
        ];
    }

    public function getEnvironments(): array
    {
        $options = [
            Environment::DEV => __('Development', 'woocommerce-gateway-translations'),
            Environment::TEST => __('Test', 'woocommerce-gateway-translations'),
            Environment::UAT => __('UAT', 'woocommerce-gateway-translations'),
            Environment::PROD => __('Production', 'woocommerce-gateway-translations'),
        ];

        $endpoints = CountryConfig::getEndpoints();

        foreach (array_keys($options) as $key) {
            if (!array_key_exists($key, $endpoints)) {
                unset($options[$key]);
            }
        }

        if ((defined('WP_DEBUG') && WP_DEBUG) || CountryConfig::COUNTRY_CODE === Country::CO) {
            $options[Environment::CUSTOM] = __('Custom', 'woocommerce-gateway-translations');
        }

        return $options;
    }

    public function getListOptionExpirationMinutes(): array
    {
        $options = [];
        $format = '%d %s';
        $minutes = 10;

        while ($minutes <= self::EXPIRATION_TIME_MINUTES_LIMIT) {
            if ($minutes < 60) {
                $options[$minutes] = sprintf($format, $minutes, __('Minutes', 'woocommerce-gateway-translations'));
                $minutes += 10;
            } elseif ($minutes >= 60 && $minutes < 1440) {
                $options[$minutes] = sprintf($format, $minutes / 60, __('Hour(s)', 'woocommerce-gateway-translations'));
                $minutes += 60;
            } else {
                $options[$minutes] = sprintf($format, $minutes / 1440, __('Day(s)', 'woocommerce-gateway-translations'));
                $minutes += 1440;
            }
        }

        return $options;
    }

    public function getListTaxes(): array
    {
        $formatTaxItem = '%s( %s ) - %s - %s %%';
        $taxList = [];

        $taxes = \WC_Tax::find_rates(['country' => CountryConfig::COUNTRY_CODE]);
        foreach ($taxes as $taxId => $tax) {
            $taxList[$taxId . '_'] = sprintf(
                $formatTaxItem,
                CountryConfig::COUNTRY_NAME,
                CountryConfig::COUNTRY_CODE,
                $tax['label'],
                $tax['rate']
            );
        }

        return $taxList;
    }

    public function getClient(): string
    {
        return $this->title;
    }

    public function getCountry(): string
    {
        return CountryConfig::COUNTRY_CODE;
    }

    private function getHeaders(): array
    {
        $domain = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

        return [
            'User-Agent' => "woocommerce-gateway-translations/{$this->version} (origin:$domain; vr:" . WOOCOMMERCE_VERSION . ')',
            'X-Source-Platform' => 'woocommerce',
        ];
    }

    /**
     * Instantiates a PlacetoPay object providing the login and tranKey,
     * also the url that will be used for the service
     */
    private function initPlacetoPay()
    {
        $settings = [
            'login' => $this->login,
            'tranKey' => $this->tran_key,
            'baseUrl' => $this->uri_service,
            'headers' => $this->getHeaders(),
        ];

        try {
            $this->placetopay = new PlacetoPay($settings);
        } catch (Exception $ex) {
            $this->logger($ex->getMessage());
        }
    }

    /**
     * Get the class name with namespaces modificated
     *
     * @param boolean $lowercase
     * @return string
     */
    private function getClassName($lowercase = false)
    {
        return str_replace("\\", "_", $lowercase ? strtolower(get_class($this)) : get_class($this));
    }

    private function validateFields($request)
    {
        $isValid = true;

        if (!$this->allow_to_pay_with_pending_orders && $this->getLastPendingOrder() !== null) {
            wc_add_notice(__(
                '<strong>Pending order</strong>, the payment could not be continued because a pending order has been found.',
                'woocommerce-gateway-translations'
            ), 'error');

            $isValid = false;
        }


        if (preg_match(Rules::PATTERN_NAME, trim($request['billing_first_name'])) !== 1) {
            wc_add_notice(__(
                '<strong>First Name</strong>, does not have a valid format',
                'woocommerce-gateway-translations'
            ), 'error');

            $isValid = false;
        }

        if (preg_match(Rules::PATTERN_NAME, trim($request['billing_last_name'])) !== 1) {
            wc_add_notice(__(
                '<strong>Last Name</strong>, does not have a valid format',
                'woocommerce-gateway-translations'
            ), 'error');

            $isValid = false;
        }

        if (preg_match(Rules::PATTERN_PHONE, trim($request['billing_phone'])) !== 1) {
            wc_add_notice(__(
                '<strong>Phone</strong>, does not have a valid format',
                'woocommerce-gateway-translations'
            ), 'error');

            $isValid = false;
        }

        if (preg_match(Rules::PATTERN_EMAIL, trim($request['billing_email'])) !== 1) {
            wc_add_notice(__(
                '<strong>Email</strong>, does not have a valid format',
                'woocommerce-gateway-translations'
            ), 'error');

            $isValid = false;
        }

        if ($this->minimum_amount != null && WC()->cart->total < $this->minimum_amount) {
            wc_add_notice(sprintf(__(
                '<strong>Minimum amount</strong>, does not meet the minimum amount to process the order, the minimum amount must be greater or equal to %s to use this payment gateway.',
                'woocommerce-gateway-translations'
            ), number_format($this->minimum_amount, 2, '.', ',')), 'error');

            $isValid = false;
        }

        if ($this->maximum_amount != null && WC()->cart->total > $this->maximum_amount) {
            wc_add_notice(sprintf(__(
                '<strong>Maximum amount</strong>, exceeds the maximum amount allowed to process the order, it must be less or equal to %s to use this payment gateway.',
                'woocommerce-gateway-translations'
            ), number_format($this->maximum_amount, 2, '.', ',')), 'error');

            $isValid = false;
        }

        return $isValid;
    }

    /**
     * @param $version
     * @param string $operator
     * @return bool
     */
    public static function wooCommerceVersionCompare($version, $operator = '>=')
    {
        return defined('WOOCOMMERCE_VERSION') && version_compare(
                WOOCOMMERCE_VERSION,
                $version,
                $operator
            );
    }

    private function configureEnvironment()
    {
        $environments = CountryConfig::getEndpoints();

        $nonProductionModes = [Environment::TEST, Environment::DEV, Environment::UAT];
        $this->testmode = in_array($this->enviroment_mode, $nonProductionModes, true) || (defined('WP_DEBUG') && WP_DEBUG)
            ? 'yes'
            : 'no';

        if ($this->testmode === 'yes') {
            $this->log = self::wooCommerceVersionCompare('2.1')
                ? new \WC_Logger()
                : WC()->logger();
        }

        if ($this->enviroment_mode === Environment::CUSTOM) {
            $this->uri_service = empty($this->custom_connection_url) ? null : $this->custom_connection_url;
        } else {
            $mode = $this->enviroment_mode;
            $this->uri_service = $environments[$mode] ?? $environments[Environment::TEST] ?? null;
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

    private function getInstallments(array $additionalData): int
    {
        $installmentKeys = ['installments', 'installment'];

        foreach ($installmentKeys as $key) {
            if (isset($additionalData[$key]) && is_numeric($additionalData[$key])) {
                return (int) $additionalData[$key];
            }
        }

        if (isset($additionalData['processorFields']) && is_array($additionalData['processorFields'])) {
            foreach ($additionalData['processorFields'] as $field) {
                if (isset($field['value']) && is_array($field['value'])) {
                    foreach ($installmentKeys as $key) {
                        if (isset($field['value'][$key]) && is_numeric($field['value'][$key])) {
                            return (int) $field['value'][$key];
                        }
                    }
                }
            }
        }

        foreach ($additionalData as $value) {
            if (is_array($value)) {
                foreach ($installmentKeys as $key) {
                    if (isset($value[$key]) && is_numeric($value[$key])) {
                        return (int) $value[$key];
                    }
                }
            }
        }

        return 0;
    }

    private function isRefunded(Transaction $payment): bool
    {
        return $payment->refunded();
    }

    private function resolveRefundedPayment($order): void
    {
        $order->update_status('refunded', __('Payment refunded', 'woocommerce-gateway-translations'));
        $this->logger('Payment refunded for order # ' . $order->get_id(), __METHOD__);
    }

    private function resolveWebCheckout(WC_Order $order)
    {
        static $codeCalled = false;

        if ($codeCalled) {
            return;
        }

        $code = $this->getWebCheckoutScript($order);

        if (self::wooCommerceVersionCompare('2.1')) {
            wc_enqueue_js($code);
        } else {
            WC()->add_inline_js($code);
        }

        $codeCalled = true;
    }

    private function getWebCheckoutScript(WC_Order $order): string
    {
        if ($this->use_lightbox) {
            wp_enqueue_script('lightbox-script', $this->getLightboxScriptSource(), [], null);

            return '
                P.init("' . $_REQUEST['redirect-url'] . '", { opacity: 0.4 });

                P.on(\'response\', function() {
                    window.location = "' . $this->getPaymentReturnUrl($order) . '"
                });';
        }


        return 'jQuery("body").block({
                message: "' . esc_js(sprintf(__(
                'We are now redirecting you to %s to make payment, if you are not redirected please press the bottom.',
                'woocommerce-gateway-translations'
            ), $this->getClient())) . '",
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
            }, 1000 );';
    }

    private function getLightboxScriptSource(): string
    {
        return 'https://checkout.placetopay.com/lightbox.min.js';
    }

    private function getPaymentReturnUrl(WC_Order $order): string
    {
        $redirectUrl = $this->getRedirectUrl($order);

        return add_query_arg([
            'wc-api' => $this->getClassName(),
            'order_id' => $order->get_id(),
        ], $redirectUrl);
    }
}
