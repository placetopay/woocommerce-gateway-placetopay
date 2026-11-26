<?php

namespace PlacetoPay\PaymentMethod;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Dnetix\Redirection\Entities\Status;
use Dnetix\Redirection\Exceptions\PlacetoPayServiceException;
use Dnetix\Redirection\Message\RedirectInformation;
use Dnetix\Redirection\PlacetoPay;
use Exception;
use PlacetoPay\PaymentMethod\Checkout\CheckoutValidator;
use PlacetoPay\PaymentMethod\Checkout\RedirectionScript;
use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Discount;
use PlacetoPay\PaymentMethod\Constants\Environment;
use PlacetoPay\PaymentMethod\Cron\PendingOrdersProcessor;
use PlacetoPay\PaymentMethod\Logger\GatewayLogger;
use PlacetoPay\PaymentMethod\Notification\NotificationHandler;
use PlacetoPay\PaymentMethod\Order\OrderMeta;
use PlacetoPay\PaymentMethod\Order\OrderRepository;
use PlacetoPay\PaymentMethod\Payment\PaymentNoteFormatter;
use PlacetoPay\PaymentMethod\Payment\PaymentRequestFactory;
use PlacetoPay\PaymentMethod\Payment\PaymentResultHandler;
use PlacetoPay\PaymentMethod\Payment\TaxResolver;
use WC_HTTPS;
use WC_Order;
use WC_Payment_Gateway;

class GatewayMethod extends WC_Payment_Gateway
{
    const VERSION = '3.2.1';

    const META_AUTHORIZATION_CUS = OrderMeta::AUTHORIZATION;

    const META_REQUEST_ID = OrderMeta::REQUEST_ID;

    const META_STATUS = OrderMeta::STATUS;

    const META_PROCESS_URL = OrderMeta::PROCESS_URL;

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

    const ALLOWED_SIGNATURE_ALGORITHMS = NotificationHandler::ALLOWED_SIGNATURE_ALGORITHMS;

    /**
     * @var GatewaySettings
     */
    private $gatewaySettings;

    /**
     * @var GatewayLogger
     */
    private $gatewayLogger;

    /**
     * Instance of placetopay to manage the connection with the webservice
     * @var PlacetoPay|null
     */
    private $placetopay;

    /**
     * @var OrderRepository
     */
    private $orders;

    /**
     * @var PaymentResultHandler|null
     */
    private $resultHandler;

    private $payment_button_image;

    /**
     * @var bool
     */
    private static $paymentHooksRegistered = false;

    /**
     * GatewayMethod constructor.
     */
    public function __construct()
    {
        $this->configPaymentMethod();
        $this->init();
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

        $this->description = sprintf(__('Pay securely through %s.', 'woocommerce-gateway-translations'), $this->getClient());
        $this->payment_button_image = $this->get_option('payment_button_image');
        $this->icon = $this->getImageUrl();

        $environmentMode = $this->get_option('enviroment_mode');
        $testMode = in_array($environmentMode, [Environment::TEST, Environment::DEV, Environment::UAT], true)
            || (defined('WP_DEBUG') && WP_DEBUG);

        $this->gatewaySettings = new GatewaySettings(array_merge(CountryConfig::getConfiguration($this), [
            'client' => $this->getClient(),
            'country_code' => $this->getCountry(),
            'currency' => get_woocommerce_currency() ?? 'COP',
            'login' => $this->get_option('login'),
            'tran_key' => $this->get_option('tran_key'),
            'service_url' => $this->resolveServiceUrl($environmentMode),
            'test_mode' => $testMode,
            'skip_result' => $this->get_option('skip_result') === 'yes',
            'use_lightbox' => $this->get_option('use_lightbox') === 'yes',
            'redirect_page_id' => $this->get_option('redirect_page_id'),
            'discount' => $this->get_option('discount'),
            'invoice' => $this->get_option('invoice'),
            'merchant_email' => get_option('woocommerce_email_from_address'),
        ]));

        $this->gatewayLogger = new GatewayLogger(CountryConfig::CLIENT_ID, $testMode);
        $this->orders = new OrderRepository();
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
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        // The gateway is instantiated several times per request, the payment hooks must run only once.
        if (self::$paymentHooksRegistered) {
            return;
        }

        self::$paymentHooksRegistered = true;

        add_action('woocommerce_receipt_' . $this->id, [$this, 'receiptPage']);
        add_action('woocommerce_api_' . $this->getClassName(true), [$this, 'checkResponse']);
        add_action('placetopay_init', [$this, 'successfulRequest']);
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
     * @return mixed
     */
    public function endpointPlacetoPay(\WP_REST_Request $req)
    {
        $handler = new NotificationHandler(
            $this->gatewaySettings,
            $this->gatewayLogger,
            $this->orders,
            $this->resultHandler(),
            [$this, 'client']
        );

        return $handler->handle($req->get_params());
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
        if (($_POST['payment_method'] ?? '') !== $this->id) {
            return;
        }

        $this->checkoutValidator()->validateCheckoutRequest($_POST, (float) WC()->cart->total);
    }

    /**
     * Process the payment for an order
     *
     * @param int $orderId
     * @return array|null
     */
    public function process_payment($orderId)
    {
        $order = wc_get_order($orderId);
        $requestId = OrderMeta::get($order, OrderMeta::REQUEST_ID);
        $paymentStatus = OrderMeta::get($order, OrderMeta::STATUS);

        if (self::validateVersionSupportBlocks()) {
            $this->checkoutValidator()->validateOrder($order);
        }

        if ($paymentStatus === Status::ST_APPROVED_PARTIAL && $order->get_status() === 'pending') {
            return [
                'result' => 'success',
                'redirect' => urldecode((string) OrderMeta::get($order, OrderMeta::PROCESS_URL))
            ];
        }

        $request = (new PaymentRequestFactory($this->gatewaySettings, new TaxResolver($this->gatewaySettings)))
            ->create($order, $this->getPaymentReturnUrl($order));

        try {
            $this->logger(
                'Sending payment request to: ' . $this->gatewaySettings->serviceUrl(),
                'request',
                'info',
                [
                    'event' => 'payment.request_start',
                    'order_id' => $order->get_id(),
                ]
            );

            $response = $this->client()->request($request);

            if ($response->isSuccessful()) {
                // Redirect the client to the processUrl or display it on the JS extension
                $processUrl = urlencode($response->processUrl());

                $order->update_meta_data(OrderMeta::REQUEST_ID, $response->requestId());
                $order->update_meta_data(OrderMeta::STATUS, $response->status()->status());
                $order->update_meta_data(OrderMeta::PROCESS_URL, $processUrl);
                $order->save();

                if (!$requestId || !OrderMeta::hasPendingPayment($order)) {
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

            $this->logger(
                'Payment request failed',
                'request',
                'error',
                [
                    'event' => 'payment.request_failed',
                    'order_id' => $order->get_id(),
                    'status' => $response->status()->status(),
                    'message' => $response->status()->message(),
                ]
            );

            throw new PlacetoPayServiceException($response->status()->message());
        } catch (PlacetoPayServiceException $exception) {
            throw new Exception(__('Payment error: ', 'woocommerce-gateway-translations'). $exception->getMessage());
        } catch (Exception $ex) {
            $this->logger(
                'Unhandled payment exception',
                'request',
                'error',
                [
                    'event' => 'payment.request_exception',
                    'order_id' => $order->get_id(),
                    'message' => $ex->getMessage(),
                ]
            );

            wc_add_notice(__('Payment error: Server error internal.', 'woocommerce-gateway-translations'), 'error');
        }

        return null;
    }

    /**
     * After of process_payment, generate the payment block modal with form datas to sending
     *
     * @param $orderId
     */
    public function receiptPage($orderId): void
    {
        try {
            $order = $this->orders->find($orderId);

            if (!$order) {
                return;
            }

            $transactionInfo = $this->client()->query((string) OrderMeta::get($order, OrderMeta::REQUEST_ID));
            $authorizationCodes = array_map(function ($transaction) {
                return $transaction->authorization();
            }, $transactionInfo->payment() ?? []);

            // Payment Details
            if ($authorizationCodes !== []) {
                $this->logger(
                    'Authorization codes captured',
                    'receipt',
                    'info',
                    [
                        'event' => 'payment.authorization_saved',
                        'order_id' => $orderId,
                    ]
                );

                $order->update_meta_data(OrderMeta::AUTHORIZATION, implode(',', $authorizationCodes));
            }

            // Add information to the order to notify that exit to payment
            // and invalidates the shopping cart
            $order->update_status('on-hold', sprintf(__('Redirecting to %s', 'woocommerce-gateway-translations'), $this->getClient()));

            $this->enqueueRedirectionScript($order);
        } catch (Exception $ex) {
            $this->logger(
                'Receipt page exception',
                'receipt',
                'error',
                [
                    'event' => 'payment.authorization_exception',
                    'order_id' => $orderId,
                    'message' => $ex->getMessage(),
                ]
            );
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
     */
    public function successfulRequest(array $params): void
    {
        // When the user is returned to the page specified by redirectUrl
        if (!empty($params['key']) && !empty($params['wc-api']) && !empty($params['order_id'])) {
            $order = $this->orders->find($params['order_id']);
            $requestId = $order ? OrderMeta::get($order, OrderMeta::REQUEST_ID) : '';

            if ($requestId) {
                $transactionInfo = $this->client()->query((string) $requestId);

                $this->returnProcess($params, $transactionInfo);
            }
        }

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

    public function returnProcess(array $params, RedirectInformation $transactionInfo, $isNotificationRequest = false)
    {
        $order = $this->findReturnOrder($params);

        if (!$order) {
            return;
        }

        $result = $this->resultHandler()->handle($order, $transactionInfo, (bool) $isNotificationRequest);

        if ($isNotificationRequest) {
            $this->logger(
                'Return completed for notification',
                'response',
                'info',
                [
                    'event' => 'return.completed',
                    'order_id' => $order->get_id(),
                    'status' => $transactionInfo->status()->status(),
                    'result' => $result,
                ]
            );

            return;
        }

        $redirectUrl = add_query_arg([
            'order_key' => $order->get_order_key(),
            'payment_method' => CountryConfig::CLIENT_ID,
        ], $this->getRedirectUrl($order));

        wp_redirect($redirectUrl);

        exit;
    }

    /**
     * Check if it has transactions with status pending and generate a message warning
     * @return void
     */
    public function checkoutMessage()
    {
        $order = $this->checkoutValidator()->findLastPendingOrder(get_current_user_id());

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
            $this->gatewaySettings->merchantEmail()
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
        if ($this->enabled !== 'yes') {
            return false;
        }

        if (!$this->gatewaySettings->isTestMode()
            && (!$this->gatewaySettings->login() || !$this->gatewaySettings->tranKey())
        ) {
            return false;
        }

        return true;
    }

    public function logger(string $message, string $type, string $level = 'info', array $context = []): void
    {
        $this->gatewayLogger->log($message, $type, $level, $context);
    }

    /**
     * Return redirect url
     *
     * @param \WC_Order $order
     * @return false|string
     */
    public function getRedirectUrl($order)
    {
        $redirectPageId = $this->gatewaySettings->redirectPageId();

        if ($redirectPageId == 'default' || !$redirectPageId) {
            return $order->get_checkout_order_received_url();
        }

        if ($redirectPageId === 'my-orders') {
            return wc_get_account_endpoint_url(get_option('woocommerce_myaccount_orders_endpoint', 'orders'));
        }

        return get_permalink($redirectPageId);
    }

    /**
     * @param int|WC_Order $order
     * @return bool
     */
    public static function isPendingStatusOrder($order)
    {
        $order = $order instanceof WC_Order ? $order : wc_get_order($order);

        return $order instanceof WC_Order && OrderMeta::hasPendingPayment($order);
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

    public static function processPendingOrders(): void
    {
        (new self())->pendingOrdersProcessor()->processAll();
    }

    /**
     * @param $orderId
     * @param $requestId
     */
    public static function processPendingOrder($orderId, $requestId)
    {
        $gatewayMethod = new self();
        $order = $gatewayMethod->orders->find($orderId);

        if ($order) {
            $gatewayMethod->pendingOrdersProcessor()->process($order, (string) $requestId);
        }
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

    /**
     * @param $version
     * @param string $operator
     * @return bool
     */
    public static function wooCommerceVersionCompare($version, $operator = '>=')
    {
        return defined('WC_VERSION') && version_compare(
                WC_VERSION,
                $version,
                $operator
            );
    }

    /**
     * @throws Exception When the credentials or the service url are not configured.
     */
    public function client(): PlacetoPay
    {
        if ($this->placetopay instanceof PlacetoPay) {
            return $this->placetopay;
        }

        try {
            $this->placetopay = new PlacetoPay([
                'login' => $this->gatewaySettings->login(),
                'tranKey' => $this->gatewaySettings->tranKey(),
                'baseUrl' => $this->gatewaySettings->serviceUrl(),
                'headers' => $this->getHeaders(),
                'logger' => $this->gatewayLogger->sdkLogger(),
            ]);
        } catch (Exception $ex) {
            $this->logger(
                'PlacetoPay initialization failed',
                'init',
                'error',
                [
                    'event' => 'placetopay.init_failed',
                    'message' => $ex->getMessage(),
                ]
            );

            throw $ex;
        }

        return $this->placetopay;
    }

    private function getHeaders(): array
    {
        $domain = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $version = self::VERSION;
        $wooCommerceVersion = defined('WC_VERSION') ? WC_VERSION : '';

        return [
            'User-Agent' => "woocommerce-gateway-placetopay/{$version} (origin:$domain; vr:" . $wooCommerceVersion . ')',
            'X-Source-Platform' => 'woocommerce',
        ];
    }

    private function resolveServiceUrl(string $environmentMode): ?string
    {
        if ($environmentMode === Environment::CUSTOM) {
            $customUrl = $this->get_option('custom_connection_url');

            return empty($customUrl) ? null : $customUrl;
        }

        $environments = CountryConfig::getEndpoints();

        return $environments[$environmentMode] ?? $environments[Environment::TEST] ?? null;
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

    private function findReturnOrder(array $params): ?WC_Order
    {
        $orderKey = explode('-', (string) ($params['key'] ?? ''))[0];
        $order = $this->orders->find($params['order_id'] ?? null) ?: $this->orders->findByOrderKey($orderKey);

        if ($order && ($orderKey === '' || $order->get_order_key() === $orderKey)) {
            return $order;
        }

        $this->logger(
            'Order Key does not match invoice.',
            'response',
            'error',
            [
                'event' => 'order.key_mismatch',
                'order_id' => $order ? $order->get_id() : null,
            ]
        );

        return null;
    }

    private function enqueueRedirectionScript(WC_Order $order): void
    {
        $processUrl = $this->getProcessUrl($order);

        if ($processUrl === '') {
            $this->logger(
                'Process url not available to build the web checkout script',
                'receipt',
                'error',
                [
                    'event' => 'payment.process_url_missing',
                    'order_id' => $order->get_id(),
                ]
            );

            return;
        }

        (new RedirectionScript($this->gatewaySettings))->enqueue($processUrl, $this->getPaymentReturnUrl($order));
    }

    private function getProcessUrl(WC_Order $order): string
    {
        $processUrl = urldecode((string) OrderMeta::get($order, OrderMeta::PROCESS_URL));

        if ($processUrl === '' || !filter_var($processUrl, FILTER_VALIDATE_URL)) {
            return '';
        }

        $scheme = strtolower((string) parse_url($processUrl, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        return esc_url_raw($processUrl);
    }

    private function getPaymentReturnUrl(WC_Order $order): string
    {
        $redirectUrl = $this->getRedirectUrl($order);

        return add_query_arg([
            'wc-api' => $this->getClassName(),
            'order_id' => $order->get_id(),
            'key' => $order->get_order_key(),
        ], $redirectUrl);
    }

    private function checkoutValidator(): CheckoutValidator
    {
        return new CheckoutValidator($this->gatewaySettings, $this->orders);
    }

    private function resultHandler(): PaymentResultHandler
    {
        if ($this->resultHandler === null) {
            $this->resultHandler = new PaymentResultHandler(
                $this->gatewaySettings,
                $this->gatewayLogger,
                new PaymentNoteFormatter()
            );
        }

        return $this->resultHandler;
    }

    private function pendingOrdersProcessor(): PendingOrdersProcessor
    {
        return new PendingOrdersProcessor(
            $this->orders,
            $this->resultHandler(),
            $this->gatewayLogger,
            [$this, 'client'],
            $this->id
        );
    }
}
