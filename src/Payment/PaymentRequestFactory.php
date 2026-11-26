<?php

namespace PlacetoPay\PaymentMethod\Payment;

use Dnetix\Redirection\Entities\PaymentModifier;
use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Discount;
use PlacetoPay\PaymentMethod\GatewaySettings;
use PlacetoPay\PaymentMethod\RemoteAddress;
use WC_Order;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class PaymentRequestFactory
{
    /**
     * @var GatewaySettings
     */
    private $settings;

    /**
     * @var TaxResolver
     */
    private $taxes;

    public function __construct(GatewaySettings $settings, TaxResolver $taxes)
    {
        $this->settings = $settings;
        $this->taxes = $taxes;
    }

    public function create(WC_Order $order, string $returnUrl): array
    {
        $orderNumber = $order->get_order_number();

        $request = [
            'locale' => get_locale(),
            'expiration' => date('c', strtotime($this->settings->expirationMinutes() . ' minutes')),
            'returnUrl' => $returnUrl,
            'noBuyerFill' => !$this->settings->fillBuyerInformation(),
            'ipAddress' => (new RemoteAddress())->getIpAddress(),
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'skipResult' => $this->settings->skipResult(),
            'buyer' => [
                'name' => $order->get_billing_first_name(),
                'surname' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'company' => $order->get_billing_company(),
                'mobile' => $order->get_billing_phone(),
                'address' => [
                    'street' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'country' => $order->get_billing_country(),
                    'postalCode' => $order->get_billing_postcode()
                ]
            ],
            'payment' => [
                'reference' => $orderNumber,
                'description' => sprintf(__('Payment on %s No: %s', 'woocommerce-gateway-translations'), $this->settings->client(), $orderNumber),
                'amount' => [
                    'currency' => $this->settings->currency(),
                    'total' => $order->get_total()
                ],
                'allowPartial' => $this->settings->allowPartialPayments()
            ],
            'fields' => [
                [
                    'keyword' => 'orderKey',
                    'value' => $order->get_order_key(),
                    'displayOn' => 'none',
                ],
            ],
        ];

        $modifiers = $this->modifiers();

        if ($modifiers !== []) {
            $request['payment']['modifiers'] = $modifiers;
        }

        if (count($order->get_taxes()) > 0) {
            $taxes = $this->taxes->resolve($order);

            if (!empty($taxes)) {
                $request['payment']['amount']['taxes'] = $taxes;
            }
        }

        return $request;
    }

    /**
     * @return PaymentModifier[]
     */
    private function modifiers(): array
    {
        if ($this->settings->countryCode() !== Country::UY) {
            return [];
        }

        $discountCode = $this->settings->discountCode();

        if ($discountCode === Discount::UY_NONE) {
            return [];
        }

        return [
            new PaymentModifier([
                'type' => PaymentModifier::TYPE_FEDERAL_GOVERNMENT,
                'code' => $discountCode,
                'additional' => [
                    'invoice' => $this->settings->invoice()
                ]
            ])
        ];
    }
}
