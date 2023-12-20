<?php

namespace PlacetoPay\PaymentMethod\Countries;

use PlacetoPay\PaymentMethod\GatewayMethod;

interface CountryConfigInterface
{
    public static function resolve(string $countryCode): bool;
    public static function getConfiguration(GatewayMethod $gatewayMethod): array;
    public static function getEndpoints(string $client): array;
    public static function getClients(): array;
    public static function getFields(GatewayMethod $gatewayMethod): array;
}
