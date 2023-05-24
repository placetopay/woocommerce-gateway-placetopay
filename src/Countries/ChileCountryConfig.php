<?php

namespace PlacetoPay\PaymentMethod\Countries;

use PlacetoPay\PaymentMethod\Constants\Client;
use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Environment;

abstract class ChileCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return Country::CL === $countryCode;
    }

    public static function getEndpoints(): array
    {
        return array_merge(parent::getEndpoints(), [
            Environment::PROD => unmaskString('uggcf://purpxbhg.trgarg.py'),
            Environment::TEST => unmaskString('uggcf://purpxbhg.grfg.trgarg.py'),
        ]);
    }

    public static function getClient(): array
    {
        return [
            unmaskString(Client::GT) => __(unmaskString(Client::GT), 'woocommerce-gateway-placetopay')
        ];
    }
}
