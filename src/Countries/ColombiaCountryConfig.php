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

    public static function getEndpoints(string $client): array
    {
        if ($client === unmaskString(Client::GOU)) {
            return array_merge(parent::getEndpoints($client), [
                Environment::PROD => unmaskString('uggcf://purpxbhg.ninycnlpragre.pbz'),
                Environment::TEST => unmaskString('uggcf://purpxbhg.grfg.ninycnlpragre.pbz'),
            ]);
        }

        return parent::getEndpoints($client);
    }

    public static function getClients(): array
    {
        return [
            unmaskString(Client::PTP) => __(unmaskString(Client::PTP), 'woocommerce-gateway-placetopay'),
            unmaskString(Client::GOU) => __(unmaskString(Client::GOU), 'woocommerce-gateway-placetopay'),
        ];
    }
}
