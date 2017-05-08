<?php

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
        $requestId = get_post_meta($orderPost->ID, \PlacetoPay\GatewayMethod::META_REQUEST_ID, true);

        if (!$requestId) {
            continue;
        }

        $order = new WC_Order();
        $order->populate($orderPost);

        if (!\PlacetoPay\GatewayMethod::isPendingStatusOrder($order->get_id())) {
            continue;
        }

        \PlacetoPay\GatewayMethod::processPendingOrder($order->get_id(), $requestId);
    }
}