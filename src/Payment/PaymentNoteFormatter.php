<?php

namespace PlacetoPay\PaymentMethod\Payment;

use Dnetix\Redirection\Entities\Transaction;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class PaymentNoteFormatter
{
    /**
     * @param int|string $orderId
     * @param float|string $total
     */
    public function approved($orderId, Transaction $payment, string $status, $total): string
    {
        $additionalData = $payment->additionalData();
        $installments = $this->installments($additionalData);
        $installmentType = $installments > 0
            ? sprintf(__('%s installments', 'woocommerce-gateway-translations'), $installments)
            : __('No installments', 'woocommerce-gateway-translations');
        $message = '<p>' . __('Payment approved', 'woocommerce-gateway-translations') . '</p>';

        $details = [
            [
                'key' => __('Buying order: ', 'woocommerce-gateway-translations'),
                'value' => $orderId,
            ],
            [
                'key' => __('Status: ', 'woocommerce-gateway-translations'),
                'value' => $status,
            ],
            [
                'key' => __('Receipt: ', 'woocommerce-gateway-translations'),
                'value' => $payment->receipt(),
            ],
            [
                'key' => __('Authorization Code: ', 'woocommerce-gateway-translations'),
                'value' => $payment->authorization(),
            ],
            [
                'key' => __('Card last Digits: ', 'woocommerce-gateway-translations'),
                'value' => str_replace('*', '', (string) ($additionalData['lastDigits'] ?? '')),
            ],
            [
                'key' => __('Amount: ', 'woocommerce-gateway-translations'),
                'value' => '$' . number_format((float) $total, 0, ',', '.'),
            ],
            [
                'key' => __('Response code: ', 'woocommerce-gateway-translations'),
                'value' => $payment->status()->reason(),
            ],
            [
                'key' => __('Payment Type: ', 'woocommerce-gateway-translations'),
                'value' => $payment->paymentMethodName(),
            ],
            [
                'key' => __('Installments Type: ', 'woocommerce-gateway-translations'),
                'value' => $installmentType,
            ],
            [
                'key' => __('Installments: ', 'woocommerce-gateway-translations'),
                'value' => $installments,
            ],
            [
                'key' => __('Transaction Date: ', 'woocommerce-gateway-translations'),
                'value' => $payment->status()->date(),
            ],
            [
                'key' => __('Internal id: ', 'woocommerce-gateway-translations'),
                'value' => $payment->internalReference(),
            ],
        ];

        $body = '';

        foreach ($details as $detail) {
            $body .= "<strong>{$detail['key']}</strong>{$detail['value']}<br/>";
        }

        return "
            <div class='placetopay-order-datails'>
                <p><h3>{$message}</h3></p>

                {$body}
            </div>
        ";
    }

    public function installments(array $additionalData): int
    {
        $installmentKeys = ['installments', 'installment'];

        foreach ($installmentKeys as $key) {
            if (isset($additionalData[$key]) && is_numeric($additionalData[$key])) {
                return (int) $additionalData[$key];
            }
        }

        if (isset($additionalData['processorFields']) && is_array($additionalData['processorFields'])) {
            foreach ($additionalData['processorFields'] as $field) {
                if (isset($field['value']) && is_array($field['value'])) {
                    foreach ($installmentKeys as $key) {
                        if (isset($field['value'][$key]) && is_numeric($field['value'][$key])) {
                            return (int) $field['value'][$key];
                        }
                    }
                }
            }
        }

        foreach ($additionalData as $value) {
            if (is_array($value)) {
                foreach ($installmentKeys as $key) {
                    if (isset($value[$key]) && is_numeric($value[$key])) {
                        return (int) $value[$key];
                    }
                }
            }
        }

        return 0;
    }
}
