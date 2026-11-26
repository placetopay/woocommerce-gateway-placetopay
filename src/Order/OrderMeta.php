<?php

namespace PlacetoPay\PaymentMethod\Order;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Dnetix\Redirection\Entities\Status;
use WC_Order;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class OrderMeta
{
    const AUTHORIZATION = '_p2p_authorization';

    const REQUEST_ID = '_p2p_request_id';

    const STATUS = '_p2p_status';

    const PROCESS_URL = '_p2p_process_url';

    const TOTAL_PARTIAL = '_order_total_partial';

    /**
     * @return mixed
     */
    public static function get(WC_Order $order, string $key)
    {
        $value = $order->get_meta($key, true);

        if (!self::isEmpty($value) || !self::isHposAuthoritative()) {
            return $value;
        }

        $legacyValue = get_post_meta($order->get_id(), $key, true);

        if (self::isEmpty($legacyValue)) {
            return $value;
        }

        $order->update_meta_data($key, $legacyValue);

        return $legacyValue;
    }

    public static function hasPendingPayment(WC_Order $order): bool
    {
        $status = self::get($order, self::STATUS);

        return Status::ST_PENDING === $status
            || Status::ST_OK === $status
            || empty($status);
    }

    private static function isHposAuthoritative(): bool
    {
        return class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * @param mixed $value
     */
    private static function isEmpty($value): bool
    {
        return $value === '' || $value === null || $value === false || $value === [];
    }
}
