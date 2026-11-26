<?php

use PlacetoPay\PaymentMethod\GatewayMethod;

$isCli = in_array(PHP_SAPI, ['cli', 'phpdbg'], true)
    || (empty($_SERVER['REQUEST_METHOD']) && empty($_SERVER['REMOTE_ADDR']));

if (!$isCli) {
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
    }

    exit('Forbidden: this task can only be executed from the command line.');
}

$siteUrl = $argv[1] ?? null;
$siteScheme = $siteUrl ? parse_url($siteUrl, PHP_URL_SCHEME) : null;

if (!isset($_SERVER['HTTP_HOST'])) {
    $siteHost = $siteUrl ? parse_url($siteUrl, PHP_URL_HOST) : null;
    $_SERVER['HTTP_HOST'] = $siteHost ?: 'localhost';
}

$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'];
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($siteScheme === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

require_once(dirname(__FILE__) . '/../../../../wp-load.php');

GatewayMethod::processPendingOrders();
