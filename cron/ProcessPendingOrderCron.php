<?php

require_once(dirname(__FILE__) . '/../../../../wp-blog-header.php');

$customerOrders = get_posts(apply_filters('woocommerce_my_account_my_orders_query', [
    'numberposts' => -1,
    'post_type' => 'shop_order',
    'post_status' => 'publish',
    'shop_order_status' => 'on-hold'
]));

if ($customerOrders) {
    foreach ($customerOrders as $orderPost) {
        $requestId = get_post_meta($orderPost->ID, \PlacetoPay\GatewayMethod::META_REQUEST_ID, true);

        if (!$requestId) {
            continue;
        }

        $order = new WC_Order();
        $order->populate($orderPost);

        if ($order->status == 'pending' || $order->status == 'on-hold') {
            \PlacetoPay\GatewayMethod::processPendingOrder($order->id, $requestId);
        }
    }
}