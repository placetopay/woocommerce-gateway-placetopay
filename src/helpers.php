<?php

if (!function_exists('unmaskString')) {
    function unmaskString(string $string): string
    {
        return str_rot13($string);
    }
}
