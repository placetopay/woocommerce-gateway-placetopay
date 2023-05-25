<?php

namespace PlacetoPay\PaymentMethod\Countries;

use PlacetoPay\PaymentMethod\Constants\Client;
use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\Constants\Environment;

class ColombiaCountryConfig extends CountryConfig
{
    public static function resolve(string $countryCode): bool
    {
        return Country::CO === $countryCode;
    }

    public static function getEndpoints(string $client = ''): array
    {
        if ($client === unmaskString(Client::GO)) {
            return array_merge(parent::getEndpoints(), [
                Environment::PROD => unmaskString('uggcf://purpxbhg.tbhcntbf.pbz.pb'),
                Environment::TEST => unmaskString('uggcf://purpxbhg.grfg.tbhcntbf.pbz.pb'),
            ]);
        }

        return parent::getEndpoints();
    }

    public static function getClient(): array
    {
        return [
            'Placetopay' => __('Placetopay', 'woocommerce-gateway-placetopay'),
            unmaskString('TBH') => __(unmaskString('TBH'), 'woocommerce-gateway-placetopay'),
        ];
    }
}