<?php

namespace PlacetoPay\PaymentMethod\Constants;

interface Rules
{
    const PATTERN_NAME = '/^[a-zñáéíóúäëïöüàèìòùÑÁÉÍÓÚÄËÏÖÜÀÈÌÒÙÇçÃã][a-zñáéíóúäëïöüàèìòùÑÁÉÍÓÚÄËÏÖÜÀÈÌÒÙÇçÃã\'\.\&\-\d ]{1,60}$/i';

    const PATTERN_PHONE = '/([0|\+?[0-9]{1,5})?([0-9 \(\)]{7,})([\(\)\w\d\. ]+)?/';

    const PATTERN_EMAIL = '/^([a-zA-Z0-9_\.\-])+[^\.\-\ ]\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})$/';
}
