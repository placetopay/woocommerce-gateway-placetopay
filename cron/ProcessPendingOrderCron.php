<?php

use PlacetoPay\PaymentMethod\GatewayMethod;

require_once(dirname(__FILE__) . '/../../../../wp-blog-header.php');

$customerOrders = wc_get_orders(apply_filters('woocommerce_my_account_my_orders_query', [
    'limit' => -1,
    'shop_order_status' => 'on-hold',
    'status' => [
        'wc-pending',
        'wc-on-hold',
    ],
]));

if ($customerOrders) {
    foreach ($customerOrders as $orderPost) {
        $requestId = get_post_meta(
            $orderPost->get_id(),
            GatewayMethod::META_REQUEST_ID,
            true
        );

        if (!$requestId) {
            continue;
        }

        $order = wc_get_order($orderPost->get_id());

        if (!GatewayMethod::isPendingStatusOrder($order->get_id())) {
            continue;
        }

        GatewayMethod::processPendingOrder($order->get_id(), $requestId);
    }
}