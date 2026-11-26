<?php

namespace PlacetoPay\PaymentMethod\Payment;

use Dnetix\Redirection\Entities\Status;
use Dnetix\Redirection\Entities\Transaction;
use Dnetix\Redirection\Message\RedirectInformation;
use PlacetoPay\PaymentMethod\GatewaySettings;
use PlacetoPay\PaymentMethod\Logger\GatewayLogger;
use PlacetoPay\PaymentMethod\Order\OrderMeta;
use WC_Order;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class PaymentResultHandler
{
    const APPLIED = 'applied';

    const ALREADY_APPROVED = 'already_approved';

    const ORDER_ALREADY_COMPLETED = 'order_already_completed';

    /**
     * @var GatewaySettings
     */
    private $settings;

    /**
     * @var GatewayLogger
     */
    private $logger;

    /**
     * @var PaymentNoteFormatter
     */
    private $notes;

    public function __construct(GatewaySettings $settings, GatewayLogger $logger, PaymentNoteFormatter $notes)
    {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->notes = $notes;
    }

    /**
     * @return string One of the APPLIED, ALREADY_APPROVED or ORDER_ALREADY_COMPLETED constants.
     */
    public function handle(WC_Order $order, RedirectInformation $transactionInfo, bool $isNotification): string
    {
        $status = $transactionInfo->status()->status();
        $currentPaymentStatus = OrderMeta::get($order, OrderMeta::STATUS);

        if ($isNotification && $currentPaymentStatus === Status::ST_APPROVED) {
            $this->logger->log('Return already processed for order', 'response', 'info', [
                'event' => 'order.already_processed',
                'order_id' => $order->get_id(),
                'payment_status' => $currentPaymentStatus,
            ]);

            return self::ALREADY_APPROVED;
        }

        $order->update_meta_data(OrderMeta::STATUS, $status);

        $this->logger->log('Processing order status update', 'response', 'info', [
            'event' => 'order.processing',
            'order_id' => $order->get_id(),
            'status' => $status,
        ]);

        if ($status === Status::ST_APPROVED || $status === Status::ST_APPROVED_PARTIAL) {
            $order->update_meta_data(OrderMeta::AUTHORIZATION, $this->authorizationCode($transactionInfo));
        }

        $order->save();

        switch ($status) {
            case Status::ST_APPROVED:
            case Status::ST_APPROVED_PARTIAL:
            case Status::ST_PENDING:
                if ($order->get_status() === 'completed') {
                    $this->logger->log('Order already complete, aborting return', 'response', 'info', [
                        'event' => 'order.abort_complete',
                        'order_id' => $order->get_id(),
                    ]);

                    return self::ORDER_ALREADY_COMPLETED;
                }

                $this->applyApprovedOrPending($order, $transactionInfo, $status, $currentPaymentStatus);
                break;
            case Status::ST_REJECTED:
                $order->update_status('cancelled', __('Payment rejected.', 'woocommerce-gateway-translations'));
                $this->logRejected($order, $transactionInfo, $status);
                break;
            case Status::ST_FAILED:
                // The session can still be paid, keep it as pending to allow the buyer to retry.
                $order->update_meta_data(OrderMeta::STATUS, Status::ST_PENDING);

                $this->logger->log('Payment failed set as pending to allow retry', 'response', 'warning', [
                    'event' => 'order.payment_failed',
                    'order_id' => $order->get_id(),
                    'status' => $status,
                ]);
                break;
            case Status::ST_ERROR:
            default:
                $order->update_status('failed', __('Payment rejected.', 'woocommerce-gateway-translations'));
                break;
        }

        $order->save();

        return self::APPLIED;
    }

    private function applyApprovedOrPending(
        WC_Order $order,
        RedirectInformation $transactionInfo,
        string $status,
        $currentPaymentStatus
    ): void {
        $totalAmount = $transactionInfo->request()->payment()->amount()->total();

        if ($status === Status::ST_APPROVED_PARTIAL && in_array($order->get_status(), ['pending', 'on-hold'], true)) {
            foreach ($transactionInfo->payment() as $transaction) {
                if ($transaction->status()->status() === Status::ST_APPROVED) {
                    $totalAmount -= $transaction->amount()->from()->total();
                }
            }
        }

        // Balance still to pay when the order is paid in partial payments.
        $order->update_meta_data(OrderMeta::TOTAL_PARTIAL, $totalAmount);

        $paymentMethodNames = array_map(function (Transaction $transaction) {
            return $transaction->paymentMethodName();
        }, $transactionInfo->payment() ?? []);

        if ($paymentMethodNames !== []) {
            $order->update_meta_data(__('Payment type', 'woocommerce-gateway-translations'), implode(',', $paymentMethodNames));
        }

        $payerEmail = $transactionInfo->request()->payer()
            ? $transactionInfo->request()->payer()->email()
            : null;

        if (!empty($payerEmail)) {
            $order->update_meta_data(
                sprintf(__('Payer %s email', 'woocommerce-gateway-translations'), $this->settings->client()),
                $payerEmail
            );
        }

        if ($status === Status::ST_APPROVED) {
            if ($currentPaymentStatus !== Status::ST_APPROVED) {
                $this->applyApproved($order, $transactionInfo, $status, $totalAmount);
            }

            return;
        }

        $firstPayment = $transactionInfo->payment()[0] ?? null;

        if ($firstPayment && $firstPayment->status()->status() === Status::ST_APPROVED) {
            if ($transactionInfo->lastApprovedTransaction()->refunded()) {
                $this->markAsRefunded($order);

                return;
            }

            // Part of the order is paid, the buyer can pay the balance with the same session.
            $order->update_meta_data(OrderMeta::STATUS, Status::ST_APPROVED_PARTIAL);
            $order->update_status('pending', __('Payment pending', 'woocommerce-gateway-translations') . ': ' . $status);

            return;
        }

        $order->add_order_note(__('Payment pending', 'woocommerce-gateway-translations'));
    }

    /**
     * @param float|string $totalAmount
     */
    private function applyApproved(WC_Order $order, RedirectInformation $transactionInfo, string $status, $totalAmount): void
    {
        $payment = $transactionInfo->lastApprovedTransaction();

        if ($payment->refunded()) {
            $this->markAsRefunded($order);

            return;
        }

        $order->add_order_note($this->notes->approved($order->get_id(), $payment, $status, $totalAmount));
        $order->update_meta_data('placetopay_response', json_encode($payment->toArray()));
        $order->payment_complete($payment->internalReference());

        $this->logger->log('Payment approved', 'response', 'info', [
            'event' => 'order.payment_approved',
            'order_id' => $order->get_id(),
            'status' => $status,
        ]);
    }

    private function markAsRefunded(WC_Order $order): void
    {
        $order->update_status('refunded', __('Payment refunded', 'woocommerce-gateway-translations'));

        $this->logger->log('Payment refunded', 'response', 'info', [
            'event' => 'payment.refunded',
            'order_id' => $order->get_id(),
        ]);
    }

    private function logRejected(WC_Order $order, RedirectInformation $transactionInfo, string $status): void
    {
        $firstPayment = $transactionInfo->payment()[0] ?? null;

        if (!$firstPayment) {
            return;
        }

        $this->logger->log('Payment rejected', 'response', 'warning', [
            'event' => 'order.payment_rejected',
            'order_id' => $order->get_id(),
            'status' => $status,
            'message' => $firstPayment->status()->message(),
        ]);
    }

    private function authorizationCode(RedirectInformation $transactionInfo): string
    {
        $payments = $transactionInfo->payment() ?? [];

        if ($payments === []) {
            return '';
        }

        if (!$this->settings->allowPartialPayments()) {
            return $payments[0]->authorization();
        }

        return implode(',', array_map(function (Transaction $transaction) {
            return $transaction->authorization();
        }, $payments));
    }
}
