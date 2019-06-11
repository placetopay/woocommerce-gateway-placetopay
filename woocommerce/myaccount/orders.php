<?php

/**
 * Orders
 *
 * Shows orders on the account page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/orders.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see    https://docs.woocommerce.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 2.6.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

do_action(\PlacetoPay\PaymentMethod\GatewayMethod::NOTIFICATION_RETURN_PAGE);
do_action('woocommerce_before_account_orders', $has_orders);

if ($has_orders) : ?>

    <h2><?php echo apply_filters('woocommerce_my_account_my_orders_title',
            __('Recent Orders', 'woocommerce-gateway-placetopay')); ?></h2>

    <table class="shop_table shop_table_responsive my_account_orders  woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">

        <thead>
        <tr>
            <th class="order-number"><span class="nobr"><?php _e('Order', 'woocommerce-gateway-placetopay'); ?></span>
            </th>
            <th class="order-date"><span class="nobr"><?php _e('Date', 'woocommerce-gateway-placetopay'); ?></span></th>
            <th class="order-status"><span class="nobr"><?php _e('Status', 'woocommerce-gateway-placetopay'); ?></span>
            </th>
            <th class="order-status"><span
                        class="nobr"><?php _e('Authorization/CUS', 'woocommerce-gateway-placetopay'); ?></span></th>
            <th class="order-total"><span class="nobr"><?php _e('Total', 'woocommerce-gateway-placetopay'); ?></span>
            </th>
            <th class="order-actions">&nbsp;</th>
        </tr>
        </thead>

        <tbody>
        <?php

        $statuses = \PlacetoPay\PaymentMethod\GatewayMethod::getOrderStatusLabels();

        foreach ($customer_orders->orders as $customer_order) {
            $order = wc_get_order($customer_order);
            $item_count = $order->get_item_count();
            $status = $statuses[$order->get_status()];
            $authorizationCodes = get_post_meta($order->get_id(), \PlacetoPay\PaymentMethod\GatewayMethod::META_AUTHORIZATION_CUS, true);
            $metaStatus = get_post_meta($order->get_id(), \PlacetoPay\PaymentMethod\GatewayMethod::META_STATUS, true );

            if (! $metaStatus == 'APPROVED_PARTIAL' && $order->get_status() == 'pending')
                $authorizationCodes = explode(',', $authorizationCodes);
            else
                $authorizationCodes = [$authorizationCodes];

            foreach ($authorizationCodes as $code) { ?>
                <tr class="order">
                <td class="order-number"
                    data-title="<?php _e('Order Number', 'woocommerce-gateway-placetopay'); ?>">
                    <a href="<?php echo esc_url($order->get_view_order_url()); ?>">
                        <?php echo _x('#', 'hash before order number',
                                'woocommerce-gateway-placetopay') . \PlacetoPay\PaymentMethod\GatewayMethod::getOrderNumber($order); ?>
                    </a>
                </td>

                <td class="order-date" data-title="<?php _e('Date', 'woocommerce-gateway-placetopay'); ?>">
                    <time datetime="<?php echo date('Y-m-d H:i:s', strtotime($order->order_date)); ?>"
                          title="<?php echo esc_attr(strtotime($order->order_date)); ?>">
                        <?php echo esc_html(
                            wc_format_datetime(
                                $order->get_date_created()
                            )
                        ) ?>
                    </time>
                </td>

                <td class="order-status" data-title="<?php _e('Status', 'woocommerce-gateway-placetopay'); ?>"
                    style="text-align:left; white-space:nowrap;">
                    <?php echo (!empty($status)) ? $status : __('Rejected', 'woocommerce-gateway-placetopay'); ?>
                </td>

                <td class="order-status"
                    data-title="<?php _e('Authorization/CUS', 'woocommerce-gateway-placetopay'); ?>">
                    <?php echo $code; ?>
                </td>

                <?php if ($metaStatus == 'APPROVED_PARTIAL' && $order->get_status() == 'pending') { ?>

                    <td class="order-total" data-title="<?php _e('Total', 'woocommerce-gateway-placetopay'); ?>">
                        <?php echo $order->get_currency() . ' ' . sprintf(_n('%s (%s) for %s item', '%s (%s) for %s items',
                                $item_count, 'woocommerce-gateway-placetopay'), wc_price(get_post_meta($order->get_id(), '_order_total_partial', true)), $order->get_formatted_order_total(),
                                $item_count); ?>
                    </td>

                <?php } else { ?>

                    <td class="order-total" data-title="<?php _e('Total', 'woocommerce-gateway-placetopay'); ?>">
                        <?php echo $order->get_currency() . ' ' . sprintf(_n('%s for %s item', '%s for %s items',
                                $item_count, 'woocommerce-gateway-placetopay'), $order->get_formatted_order_total(),
                                $item_count); ?>
                    </td>

                <?php } ?>

                <td class="order-actions">
                    <?php
                    $actions = array(
                        'pay' => array(
                            'url' => $order->get_checkout_payment_url(),
                            'name' => __('Pay', 'woocommerce'),
                        ),
                        'view' => array(
                            'url' => $order->get_view_order_url(),
                            'name' => __('View', 'woocommerce'),
                        ),
                        'cancel' => array(
                            'url' => $order->get_cancel_order_url(wc_get_page_permalink('myaccount')),
                            'name' => __('Cancel', 'woocommerce'),
                        ),
                    );

                    if (!$order->needs_payment()) {
                        unset($actions['pay']);
                    }

                    if (!in_array($order->get_status(),
                        apply_filters('woocommerce_valid_order_statuses_for_cancel', array('pending', 'failed'),
                            $order))
                    ) {
                        unset($actions['cancel']);
                    }

                    $actions = apply_filters('woocommerce_my_account_my_orders_actions', $actions, $order);

                    if ($actions) {
                        foreach ($actions as $key => $action) {
                            echo '<a href="' . esc_url($action['url']) . '" class="button ' . sanitize_html_class($key) . '">' . esc_html($action['name']) . '</a>';
                        }
                    }
                    ?>
                </td>
                </tr><?php
            }
        }
        ?></tbody>
    </table>

    <?php do_action('woocommerce_before_account_orders_pagination'); ?>

    <?php if (1 < $customer_orders->max_num_pages) : ?>
        <div class="woocommerce-Pagination">
            <?php if (1 !== $current_page) : ?>
                <a class="woocommerce-Button woocommerce-Button--previous button"
                   href="<?php echo esc_url(wc_get_endpoint_url('orders', $current_page - 1)); ?>"><?php _e('Previous',
                        'woocommerce-gateway-placetopay'); ?></a>
            <?php endif; ?>

            <?php if (intval($customer_orders->max_num_pages) !== $current_page) : ?>
                <a class="woocommerce-Button woocommerce-Button--next button"
                   href="<?php echo esc_url(wc_get_endpoint_url('orders', $current_page + 1)); ?>"><?php _e('Next',
                        'woocommerce-gateway-placetopay'); ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>


<?php else : ?>
    <div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
        <a class="woocommerce-Button button"
           href="<?php echo esc_url(apply_filters('woocommerce_return_to_shop_redirect',
               wc_get_page_permalink('shop'))); ?>">
            <?php _e('Go shop', 'woocommerce-gateway-placetopay') ?>
        </a>
        <?php _e('No order has been made yet.', 'woocommerce-gateway-placetopay'); ?>
    </div>
<?php endif; ?>

<?php do_action('woocommerce_after_account_orders', $has_orders); ?>
