<?php

use PlacetoPay\PaymentMethod\Constants\Country;
use PlacetoPay\PaymentMethod\CountryConfig;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * @var \PlacetoPay\PaymentMethod\GatewayMethod $this
 */

return CountryConfig::getFields($this);

