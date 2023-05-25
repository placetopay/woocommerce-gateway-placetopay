<?php

namespace PlacetoPay\PaymentMethod\Countries;

use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Environment;

abstract class EcuadorCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return Country::EC === $countryCode;
    }

    public static function getEndpoints(string $client = ''): array
    {
        return array_merge(parent::getEndpoints(), [
            Environment::PROD => 'https://checkout.placetopay.ec',
            Environment::TEST => 'https://checkout-test.placetopay.ec',
            Environment::DEV => 'https://dev.placetopay.ec/redirection',
        ]);
    }
}
