<?php

namespace PlacetoPay\PaymentMethod\Payment;

use PlacetoPay\PaymentMethod\GatewaySettings;
use WC_Order;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TaxResolver
{
    /**
     * @var GatewaySettings
     */
    private $settings;

    public function __construct(GatewaySettings $settings)
    {
        $this->settings = $settings;
    }

    public function resolve(WC_Order $order): array
    {
        $valueAddedTaxType = $this->settings->taxRateIds('taxes_others');
        $exciseDutyType = $this->settings->taxRateIds('taxes_ico');
        $iceType = $this->settings->taxRateIds('taxes_ice');

        if (!$valueAddedTaxType && !$exciseDutyType && !$iceType) {
            return [];
        }

        $subTotal = $this->taxableSubtotal($order->get_items());
        $taxes = [];

        foreach ($order->get_taxes() as $tax) {
            $taxData = $tax->get_data();

            if (in_array($taxData['rate_id'], $valueAddedTaxType)) {
                $shippingTax = (float) $order->get_shipping_tax();
                $totalTax = $shippingTax + $taxData['tax_total'];
                $totalBase = $shippingTax > 0 ? ((float) $order->get_shipping_total() + $subTotal) : $subTotal;

                $taxes[] = [
                    'kind' => 'valueAddedTax',
                    'amount' => $totalTax,
                    'base' => $totalBase,
                ];
            }

            if (in_array($taxData['rate_id'], $exciseDutyType)) {
                $taxes[] = [
                    'kind' => 'exciseDuty',
                    'amount' => floatval($taxData['tax_total']),
                    'base' => $subTotal,
                ];
            }

            if (in_array($taxData['rate_id'], $iceType)) {
                $taxes[] = [
                    'kind' => 'ice',
                    'amount' => floatval($taxData['tax_total']),
                    'base' => $subTotal,
                ];
            }
        }

        return $taxes;
    }

    /**
     * @param \WC_Order_Item[] $items
     */
    public function taxableSubtotal(array $items): float
    {
        $subtotal = 0.00;

        foreach ($items as $item) {
            $data = $item->get_data();

            if ($data['total_tax'] !== '0') {
                $subtotal += $data['total'];
            }
        }

        return $subtotal;
    }
}
