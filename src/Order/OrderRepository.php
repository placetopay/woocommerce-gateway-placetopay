<?php

namespace PlacetoPay\PaymentMethod\Order;

use WC_Order;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class OrderRepository
{
    const PENDING_STATUSES = ['wc-pending', 'wc-on-hold'];

    /**
     * @param int|string|null $orderId
     */
    public function find($orderId): ?WC_Order
    {
        if (empty($orderId)) {
            return null;
        }

        $order = wc_get_order($orderId);

        return $order instanceof WC_Order ? $order : null;
    }

    public function findByOrderKey(string $orderKey): ?WC_Order
    {
        if ($orderKey === '') {
            return null;
        }

        return $this->find(wc_get_order_id_by_order_key($orderKey));
    }

    /**
     * @return WC_Order[]
     */
    public function findPendingByCustomer(int $customerId, int $limit): array
    {
        if (!$customerId) {
            return [];
        }

        return wc_get_orders([
            'customer_id' => $customerId,
            'status' => self::PENDING_STATUSES,
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
    }

    /**
     * @return WC_Order[]
     */
    public function findPendingWithPaymentSession(string $paymentMethod): array
    {
        $orders = wc_get_orders([
            'payment_method' => $paymentMethod,
            'status' => self::PENDING_STATUSES,
            'limit' => -1,
        ]);

        return array_values(array_filter($orders, function ($order) {
            return $order instanceof WC_Order && !empty(OrderMeta::get($order, OrderMeta::REQUEST_ID));
        }));
    }
}
