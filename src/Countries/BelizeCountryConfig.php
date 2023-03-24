<?php

namespace PlacetoPay\PaymentMethod\Countries;

use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Environment;

abstract class BelizeCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode):bool
    {
        return Country::BZ === $countryCode;
    }

    public static function getEndpoints(): array
    {
        return array_merge(parent::getEndpoints(), [
            Environment::PROD => 'https://abgateway.atlabank.com',
        ]);
    }
}
