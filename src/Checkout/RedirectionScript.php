<?php

namespace PlacetoPay\PaymentMethod\Checkout;

use PlacetoPay\PaymentMethod\CountryConfig;
use PlacetoPay\PaymentMethod\GatewaySettings;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RedirectionScript
{
    const LIGHTBOX_SCRIPT_URL = 'https://checkout.placetopay.com/lightbox.min.js';

    /**
     * @var GatewaySettings
     */
    private $settings;

    public function __construct(GatewaySettings $settings)
    {
        $this->settings = $settings;
    }

    public function enqueue(string $processUrl, string $returnUrl): void
    {
        $useLightbox = $this->settings->useLightbox();
        $handle = CountryConfig::CLIENT_ID . ($useLightbox ? '-lightbox' : '-redirection');

        // The receipt hook can run more than once per request, the script must be added only once.
        if (wp_script_is($handle, 'enqueued')) {
            return;
        }

        if ($useLightbox) {
            wp_enqueue_script($handle, self::LIGHTBOX_SCRIPT_URL, [], null, true);
            $code = $this->lightboxCode($processUrl, $returnUrl);
        } else {
            wp_register_script($handle, '', ['jquery', $this->blockUiHandle()], null, true);
            wp_enqueue_script($handle);
            $code = $this->redirectionCode($processUrl);
        }

        wp_add_inline_script($handle, 'jQuery(function($) { ' . $code . ' });');
    }

    private function lightboxCode(string $processUrl, string $returnUrl): string
    {
        return '
                P.init(' . wp_json_encode($processUrl) . ', { opacity: 0.4 });

                P.on(\'response\', function() {
                    window.location = ' . wp_json_encode(esc_url_raw($returnUrl)) . ';
                });';
    }

    private function redirectionCode(string $processUrl): string
    {
        return 'jQuery("body").block({
                message: "' . esc_js(sprintf(__(
                'We are now redirecting you to %s to make payment, if you are not redirected please press the bottom.',
                'woocommerce-gateway-translations'
            ), $this->settings->client())) . '",
                baseZ: 99999,
                overlayCSS: { background: "#fff", opacity: 0.6 },
                css: {
                    padding:        "20px",
                    zindex:         "9999999",
                    textAlign:      "center",
                    color:          "#555",
                    border:         "3px solid #aaa",
                    backgroundColor:"#fff",
                    cursor:         "wait",
                    lineHeight:		"24px",
                }
            });

            setTimeout( function() {
                window.location.href = ' . wp_json_encode($processUrl) . ';
            }, 1000 );';
    }

    private function blockUiHandle(): string
    {
        return wp_script_is('wc-jquery-blockui', 'registered') ? 'wc-jquery-blockui' : 'jquery-blockui';
    }
}
