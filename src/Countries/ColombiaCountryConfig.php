<?php

namespace PlacetoPay\PaymentMethod\Countries;

use PlacetoPay\PaymentMethod\Constants\Country;

class ColombiaCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return Country::CO === $countryCode;
    }

    public static function getClient(): array
    {
        return [
            'Placetopay' => __('Placetopay', 'woocommerce-gateway-placetopay'),
            unmaskString('TBH') => __(unmaskString('TBH'), 'woocommerce-gateway-placetopay'),
        ];
    }
}