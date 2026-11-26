<?php

namespace PlacetoPay\PaymentMethod\Notification;

use Dnetix\Redirection\Entities\Status;
use Dnetix\Redirection\PlacetoPay;
use Exception;
use PlacetoPay\PaymentMethod\GatewaySettings;
use PlacetoPay\PaymentMethod\Logger\GatewayLogger;
use PlacetoPay\PaymentMethod\Order\OrderRepository;
use PlacetoPay\PaymentMethod\Payment\PaymentResultHandler;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class NotificationHandler
{
    const ALLOWED_SIGNATURE_ALGORITHMS = ['sha1', 'sha256'];

    /**
     * @var GatewaySettings
     */
    private $settings;

    /**
     * @var GatewayLogger
     */
    private $logger;

    /**
     * @var OrderRepository
     */
    private $orders;

    /**
     * @var PaymentResultHandler
     */
    private $results;

    /**
     * @var callable(): PlacetoPay
     */
    private $client;

    public function __construct(
        GatewaySettings $settings,
        GatewayLogger $logger,
        OrderRepository $orders,
        PaymentResultHandler $results,
        callable $client
    ) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->orders = $orders;
        $this->results = $results;
        $this->client = $client;
    }

    /**
     * @return array{success: bool, message: string|null}
     */
    public function handle(array $data): array
    {
        $requestId = $data['requestId'] ?? null;

        $this->logger->log('Received notification', 'notification', 'info', [
            'event' => 'notification.received',
            'params' => $data,
            'request_id' => $requestId,
        ]);

        if (empty($data['signature']) || empty($requestId) || !$this->hasValidSignature($data)) {
            return $this->response(false);
        }

        try {
            $transactionInfo = ($this->client)()->query((string) $requestId);
        } catch (Exception $exception) {
            $this->logger->log('Notification session query failed', 'notification', 'error', [
                'event' => 'notification.query_failed',
                'request_id' => $requestId,
                'message' => $exception->getMessage(),
            ]);

            return $this->response(false);
        }

        $status = $transactionInfo->status();

        if (in_array($status->status(), [Status::ST_FAILED, Status::ST_ERROR], true)) {
            $this->logger->log('Notification status failed', 'notification', 'warning', [
                'event' => 'notification.status_failed',
                'status' => $status->status(),
                'message' => $status->message(),
                'request_id' => $requestId,
            ]);

            return $this->response(false, $status->message());
        }

        $fields = $transactionInfo->request()->fieldsToKeyValue();

        if (!isset($fields['orderKey'])) {
            $this->logger->log('Missing orderKey in notification response', 'notification', 'warning', [
                'event' => 'notification.missing_order_key',
                'request_id' => $requestId,
            ]);

            return $this->response(false, 'Not orderKey in response for notificationUrl');
        }

        $order = $this->orders->findByOrderKey((string) $fields['orderKey']);

        if (!$order) {
            $this->logger->log('Order not found for notification', 'notification', 'warning', [
                'event' => 'notification.order_not_found',
                'request_id' => $requestId,
            ]);

            return $this->response(false, 'Order not found for notificationUrl');
        }

        $this->results->handle($order, $transactionInfo, true);

        $this->logger->log('Notification processed successfully', 'notification', 'info', [
            'event' => 'notification.processed',
            'request_id' => $requestId,
        ]);

        return $this->response(true, $status->message());
    }

    private function hasValidSignature(array $data): bool
    {
        $requestId = $data['requestId'];
        $expectedSignature = sprintf(
            '%s%s%s%s',
            $requestId,
            $data['status']['status'] ?? '',
            $data['status']['date'] ?? '',
            $this->settings->tranKey()
        );

        $signature = (string) $data['signature'];

        // Signatures without algorithm prefix are SHA-1.
        if (strpos($signature, ':') === false) {
            $signature = 'sha1:' . $signature;
        }

        [$algo, $hash] = explode(':', $signature, 2);
        $algo = strtolower(trim($algo));

        $this->logger->log('Signature algorithm parsed', 'notification', 'info', [
            'event' => 'notification.signature_algorithm',
            'algorithm' => $algo,
            'request_id' => $requestId,
        ]);

        if (!in_array($algo, self::ALLOWED_SIGNATURE_ALGORITHMS, true)) {
            $this->logger->log('Unsupported notification signature algorithm', 'notification', 'warning', [
                'event' => 'notification.unsupported_algorithm',
                'algorithm' => $algo,
                'request_id' => $requestId,
            ]);

            return false;
        }

        if (!hash_equals(hash($algo, $expectedSignature), $hash)) {
            $this->logger->log('Invalid notification signature', 'notification', 'warning', [
                'event' => 'notification.invalid_signature',
                'request_id' => $requestId,
                'status' => $data['status']['status'] ?? null,
            ]);

            return false;
        }

        return true;
    }

    private function response(bool $success, ?string $message = null): array
    {
        return [
            'success' => $success,
            'message' => $message,
        ];
    }
}
