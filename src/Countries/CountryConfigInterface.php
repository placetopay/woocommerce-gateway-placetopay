<?php

namespace PlacetoPay\PaymentMethod\Countries;

interface CountryConfigInterface
{
    public static function resolve(string $countryCode): bool;
    public static function getEndpoints(string $client = ''): array;
    public static function getClient(): array;
}
