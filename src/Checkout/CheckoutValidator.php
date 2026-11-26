<?php

namespace PlacetoPay\PaymentMethod\Checkout;

use Exception;
use PlacetoPay\PaymentMethod\Constants\Rules;
use PlacetoPay\PaymentMethod\GatewaySettings;
use PlacetoPay\PaymentMethod\Order\OrderMeta;
use PlacetoPay\PaymentMethod\Order\OrderRepository;
use WC_Order;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class CheckoutValidator
{
    /**
     * @var GatewaySettings
     */
    private $settings;

    /**
     * @var OrderRepository
     */
    private $orders;

    public function __construct(GatewaySettings $settings, OrderRepository $orders)
    {
        $this->settings = $settings;
        $this->orders = $orders;
    }

    public function validateCheckoutRequest(array $request, float $cartTotal): bool
    {
        $errors = [];

        if (!$this->settings->allowPendingOrders() && $this->findLastPendingOrder(get_current_user_id()) !== null) {
            $errors[] = $this->pendingOrderMessage();
        }

        $errors = array_merge($errors, $this->billingErrors(
            (string) ($request['billing_first_name'] ?? ''),
            (string) ($request['billing_last_name'] ?? ''),
            (string) ($request['billing_phone'] ?? ''),
            (string) ($request['billing_email'] ?? '')
        ));

        $errors = array_merge($errors, $this->amountErrors($cartTotal));

        foreach ($errors as $error) {
            wc_add_notice($error, 'error');
        }

        return $errors === [];
    }

    /**
     * @throws Exception With the first error found.
     */
    public function validateOrder(WC_Order $order): void
    {
        $errors = [];

        if (!$this->settings->allowPendingOrders()
            && $this->orders->findPendingByCustomer($order->get_customer_id(), 1) !== []
        ) {
            $errors[] = $this->pendingOrderMessage();
        }

        $errors = array_merge($errors, $this->billingErrors(
            $order->get_billing_first_name(),
            $order->get_billing_last_name(),
            $order->get_billing_phone(),
            $order->get_billing_email()
        ));

        $errors = array_merge($errors, $this->amountErrors((float) $order->get_total()));

        if ($errors !== []) {
            throw new Exception($errors[0]);
        }
    }

    public function findLastPendingOrder(int $customerId): ?WC_Order
    {
        foreach ($this->orders->findPendingByCustomer($customerId, 5) as $order) {
            if (OrderMeta::hasPendingPayment($order)) {
                return $order;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function billingErrors(string $firstName, string $lastName, string $phone, string $email): array
    {
        $rules = [
            [$firstName, Rules::PATTERN_NAME, __('<strong>First Name</strong>, does not have a valid format', 'woocommerce-gateway-translations')],
            [$lastName, Rules::PATTERN_NAME, __('<strong>Last Name</strong>, does not have a valid format', 'woocommerce-gateway-translations')],
            [$phone, Rules::PATTERN_PHONE, __('<strong>Phone</strong>, does not have a valid format', 'woocommerce-gateway-translations')],
            [$email, Rules::PATTERN_EMAIL, __('<strong>Email</strong>, does not have a valid format', 'woocommerce-gateway-translations')],
        ];

        $errors = [];

        foreach ($rules as [$value, $pattern, $message]) {
            if (preg_match($pattern, trim($value)) !== 1) {
                $errors[] = $message;
            }
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private function amountErrors(float $total): array
    {
        $errors = [];
        $minimum = $this->settings->minimumAmount();
        $maximum = $this->settings->maximumAmount();

        if ($minimum !== null && $total < $minimum) {
            $errors[] = sprintf(__(
                '<strong>Minimum amount</strong>, does not meet the minimum amount to process the order, the minimum amount must be greater or equal to %s to use this payment gateway.',
                'woocommerce-gateway-translations'
            ), number_format($minimum, 2, '.', ','));
        }

        if ($maximum !== null && $total > $maximum) {
            $errors[] = sprintf(__(
                '<strong>Maximum amount</strong>, exceeds the maximum amount allowed to process the order, it must be less or equal to %s to use this payment gateway.',
                'woocommerce-gateway-translations'
            ), number_format($maximum, 2, '.', ','));
        }

        return $errors;
    }

    private function pendingOrderMessage(): string
    {
        return __(
            '<strong>Pending order</strong>, the payment could not be continued because a pending order has been found.',
            'woocommerce-gateway-translations'
        );
    }
}
