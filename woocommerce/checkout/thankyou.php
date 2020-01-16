<?php
/**
 * Thankyou page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/thankyou.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see        https://docs.woocommerce.com/document/template-structure/
 * @author        WooThemes
 * @package    WooCommerce/Templates
 * @version     3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use PlacetoPay\PaymentMethod\GatewayMethod;

/** @var WC_Order $order */
?>

<div class="woocommerce-order">

    <?php if ($order) : ?>

        <?php if ($order->has_status('failed')) : ?>

            <p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed">
                <?php _e('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.',
                    'woocommerce-gateway-placetopay'); ?>

                <br>

                <?php _e('Authorization/CUS', 'woocommerce-gateway-placetopay'); ?>
                <strong><?php echo get_post_meta($order->get_id(), GatewayMethod::META_AUTHORIZATION_CUS,
                        true); ?></strong>
            </p>

            <p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
                <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="button pay">
                    <?php _e('Pay', 'woocommerce-gateway-placetopay') ?>
                </a>

                <?php if (is_user_logged_in()) : ?>
                    <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"
                       class="button pay"><?php _e('My account', 'woocommerce-gateway-placetopay'); ?></a>
                <?php endif; ?>
            </p>

        <?php else : ?>

            <p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">
                <?php echo apply_filters('woocommerce_thankyou_order_received_text',
                    __('Thank you. Your order has been received.', 'woocommerce-gateway-placetopay'), $order); ?>

                <?php
                $processUrl = get_post_meta($order->get_id(), GatewayMethod::META_PROCESS_URL, true);

                if (!empty($processUrl)) { ?>

                    <?php echo sprintf(
                        __('<br>For more information about the status of your order: <a href="%s" target="_blank">view order detail in placetopay</a>',
                            'woocommerce-gateway-placetopay'),
                        urldecode($processUrl)
                    ); ?>

                <?php } ?>
            </p>

            <ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">

                <li class="woocommerce-order-overview__order order">
                    <?php _e('Order status:', 'woocommerce-gateway-placetopay'); ?>
                    <strong><?php echo GatewayMethod::getOrderStatusLabels($order->get_status()); ?></strong>
                </li>

                <li class="woocommerce-order-overview__order order">
                    <?php _e('Order Number:', 'woocommerce-gateway-placetopay'); ?>
                    <strong><?php echo GatewayMethod::getOrderNumber($order); ?></strong>
                </li>

                <li class="woocommerce-order-overview__date date">
                    <?php _e('Date:', 'woocommerce-gateway-placetopay'); ?>
                    <strong><?php echo wc_format_datetime($order->get_date_created()); ?></strong>
                </li>

                <?php
                $metaStatus = get_post_meta($order->get_id(), \PlacetoPay\PaymentMethod\GatewayMethod::META_STATUS, true );

                if ($metaStatus == 'APPROVED_PARTIAL' && $order->get_status() == 'pending') {
                    $total = get_post_meta($order->get_id(), '_order_total', true);
                    $balance = get_post_meta($order->get_id(), '_order_total_partial', true);
                ?>

                    <li class="woocommerce-order-overview__total total">
                        <?php _e('Total Paid:', 'woocommerce-gateway-placetopay'); ?>
                        <strong><?php echo wc_price($total - $balance); ?></strong>
                    </li>


                    <li class="woocommerce-order-overview__total total">
                        <?php _e('Balance:', 'woocommerce-gateway-placetopay'); ?>
                        <strong><?php echo wc_price($balance); ?></strong>
                    </li>

                <?php } ?>

                <li class="woocommerce-order-overview__total total">
                    <?php _e('Total:', 'woocommerce-gateway-placetopay'); ?>
                    <strong><?php echo $order->get_formatted_order_total(); ?></strong>
                </li>

                <?php if ($order->get_payment_method_title()) : ?>

                    <li class="woocommerce-order-overview__payment-method method">
                        <?php _e('Payment method:', 'woocommerce-gateway-placetopay'); ?>
                        <strong><?php echo wp_kses_post($order->get_payment_method_title()); ?></strong>
                    </li>

                <?php endif; ?>

                <?php if (get_post_meta($order->get_id(), GatewayMethod::META_AUTHORIZATION_CUS, true)) : ?>

                    <li class="order">
                        <?php _e('Authorization/CUS', 'woocommerce-gateway-placetopay'); ?>
                        <strong><?php echo get_post_meta($order->get_id(), GatewayMethod::META_AUTHORIZATION_CUS,
                                true); ?></strong>
                    </li>

                <?php endif; ?>

            </ul>

        <?php endif; ?>

        <?php do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id()); ?>
        <?php do_action('woocommerce_thankyou', $order->get_id()); ?>

    <?php else : ?>

        <p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received"><?php echo apply_filters('woocommerce_thankyou_order_received_text',
                __('Thank you. Your order has been received.', 'woocommerce-gateway-placetopay'), null); ?></p>

    <?php endif; ?>
</div>
