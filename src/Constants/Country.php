<?php

namespace PlacetoPay\PaymentMethod\Constants;

use PlacetoPay\PaymentMethod\Countries\BelizeCountryConfig;
use PlacetoPay\PaymentMethod\Countries\ChileCountryConfig;
use PlacetoPay\PaymentMethod\Countries\CountryConfig;
use PlacetoPay\PaymentMethod\Countries\EcuadorCountryConfig;
use PlacetoPay\PaymentMethod\Countries\HondurasCountryConfig;
use PlacetoPay\PaymentMethod\Countries\UruguayCountryConfig;

interface Country
{
    const BZ = 'BZ';

    const CL = 'CL';

    const CO = 'CO';

    const CR = 'CR';

    const EC = 'EC';

    const HN = 'HN';

    const PA = 'PA';

    const PR = 'PR';

    const UY = 'UY';

    public const COUNTRIES_CONFIG = [
        EcuadorCountryConfig::class,
        ChileCountryConfig::class,
        HondurasCountryConfig::class,
        BelizeCountryConfig::class,
        UruguayCountryConfig::class,
        CountryConfig::class
    ];
}
