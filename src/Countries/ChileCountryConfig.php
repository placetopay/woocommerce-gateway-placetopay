<?php

namespace PlacetoPay\PaymentMethod\Countries;

use PlacetoPay\PaymentMethod\Constants\Client;
use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Environment;
use PlacetoPay\PaymentMethod\GatewayMethod;

abstract class ChileCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return Country::CL === $countryCode;
    }

    public static function getEndpoints(string $client): array
    {
        return array_merge(parent::getEndpoints($client), [
            Environment::PROD => unmaskString('uggcf://purpxbhg.trgarg.py'),
            Environment::TEST => unmaskString('uggcf://purpxbhg.grfg.trgarg.py'),
        ]);
    }

    public static function getClients(): array
    {
        return [
            unmaskString(Client::GNT) => __(unmaskString(Client::GNT), 'woocommerce-gateway-placetopay')
        ];
    }

    public static function getConfiguration(GatewayMethod $gatewayMethod): array
    {
        return [
            'allow_to_pay_with_pending_orders' => true,
            'allow_partial_payments' => false,
            'fill_buyer_information' => true,
            'minimum_amount' => '',
            'maximum_amount' => '',
            'expiration_time_minutes' => 10,
            'taxes' => [
                'taxes_others' => '',
                'taxes_ico' => '',
                'taxes_ice' => '',
            ],
        ];
    }

    public static function getFields(GatewayMethod $gatewayMethod): array
    {
        $fields = parent::getFields($gatewayMethod);

        unset($fields['allow_to_pay_with_pending_orders']);
        unset($fields['allow_partial_payments']);
        unset($fields['fill_buyer_information']);
        unset($fields['minimum_amount']);
        unset($fields['maximum_amount']);
        unset($fields['expiration_time_minutes']);
        unset($fields['taxes_others']);
        unset($fields['taxes_ico']);
        unset($fields['taxes_ice']);

        return $fields;
    }
}
