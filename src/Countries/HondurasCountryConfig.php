<?php

namespace PlacetoPay\PaymentMethod\Countries;

use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Environment;

abstract class HondurasCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return Country::HN === $countryCode;
    }

    public static function getEndpoints(): array
    {
        return array_merge(parent::getEndpoints(), [
            Environment::PROD => 'https://pagoenlinea.bancatlan.hn',
        ]);
    }
}
