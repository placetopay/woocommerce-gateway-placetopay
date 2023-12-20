<?php

namespace PlacetoPay\PaymentMethod\Countries;

use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Environment;
use PlacetoPay\PaymentMethod\GatewayMethod;

abstract class UruguayCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return Country::UY === $countryCode;
    }

    public static function getEndpoints(string $client): array
    {
        return array_merge(parent::getEndpoints($client), [
            Environment::TEST => 'https://uy-uat-checkout.placetopay.com',
            Environment::PROD => 'https://checkout.placetopay.uy',
        ]);
    }

    public static function getFields(GatewayMethod $gatewayMethod): array
    {
        $fields = parent::getFields($gatewayMethod);

        $fields['discount'] = [
            'title' => __('Discount', 'woocommerce-gateway-placetopay'),
            'type' => 'select',
            'class' => 'wc-enhanced-select',
            'options' => $gatewayMethod->getDiscounts(),
        ];

        $fields['invoice'] = [
            'title' => __('Invoice', 'woocommerce-gateway-placetopay'),
            'type' => 'text',
        ];

        return $fields;
    }
}
