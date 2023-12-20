<?php

use PlacetoPay\PaymentMethod\Constants\Country;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * @var \PlacetoPay\PaymentMethod\GatewayMethod $this
 */

foreach (Country::COUNTRIES_CONFIG as $config) {
    if (!$config::resolve($this->getCountry())) {
        continue;
    }

    return $config::getFields($this);
}
