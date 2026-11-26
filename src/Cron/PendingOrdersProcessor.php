<?php

namespace PlacetoPay\PaymentMethod\Cron;

use Dnetix\Redirection\PlacetoPay;
use Exception;
use PlacetoPay\PaymentMethod\Logger\GatewayLogger;
use PlacetoPay\PaymentMethod\Order\OrderMeta;
use PlacetoPay\PaymentMethod\Order\OrderRepository;
use PlacetoPay\PaymentMethod\Payment\PaymentResultHandler;
use WC_Order;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class PendingOrdersProcessor
{
    /**
     * @var OrderRepository
     */
    private $orders;

    /**
     * @var PaymentResultHandler
     */
    private $results;

    /**
     * @var GatewayLogger
     */
    private $logger;

    /**
     * @var callable(): PlacetoPay
     */
    private $client;

    /**
     * @var string
     */
    private $paymentMethod;

    public function __construct(
        OrderRepository $orders,
        PaymentResultHandler $results,
        GatewayLogger $logger,
        callable $client,
        string $paymentMethod
    ) {
        $this->orders = $orders;
        $this->results = $results;
        $this->logger = $logger;
        $this->client = $client;
        $this->paymentMethod = $paymentMethod;
    }

    public function processAll(): void
    {
        foreach ($this->orders->findPendingWithPaymentSession($this->paymentMethod) as $order) {
            if (!OrderMeta::hasPendingPayment($order)) {
                continue;
            }

            $this->process($order, (string) OrderMeta::get($order, OrderMeta::REQUEST_ID));
        }
    }

    public function process(WC_Order $order, string $requestId): void
    {
        try {
            $transactionInfo = ($this->client)()->query($requestId);
            $this->results->handle($order, $transactionInfo, true);
        } catch (Exception $exception) {
            $this->logger->log('Pending order could not be processed', 'cron', 'error', [
                'event' => 'cron.process_failed',
                'order_id' => $order->get_id(),
                'request_id' => $requestId,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $this->logger->log('Processed pending order', 'cron', 'info', [
            'event' => 'cron.processed_pending_order',
            'order_id' => $order->get_id(),
            'request_id' => $requestId,
        ]);
    }
}
