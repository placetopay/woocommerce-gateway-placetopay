<?php

namespace PlacetoPay\PaymentMethod;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GatewaySettings
{
    const DEFAULT_EXPIRATION_MINUTES = 30;

    /**
     * @var array
     */
    private $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function client(): string
    {
        return (string) $this->value('client');
    }

    public function countryCode(): string
    {
        return (string) $this->value('country_code');
    }

    public function currency(): string
    {
        return (string) $this->value('currency', 'COP');
    }

    public function login(): string
    {
        return (string) $this->value('login');
    }

    public function tranKey(): string
    {
        return (string) $this->value('tran_key');
    }

    public function serviceUrl(): ?string
    {
        return $this->value('service_url') ?: null;
    }

    public function isTestMode(): bool
    {
        return (bool) $this->value('test_mode', false);
    }

    public function expirationMinutes(): int
    {
        return (int) $this->value('expiration_time_minutes') ?: self::DEFAULT_EXPIRATION_MINUTES;
    }

    public function fillBuyerInformation(): bool
    {
        return (bool) $this->value('fill_buyer_information', false);
    }

    public function skipResult(): bool
    {
        return (bool) $this->value('skip_result', false);
    }

    public function allowPartialPayments(): bool
    {
        return (bool) $this->value('allow_partial_payments', false);
    }

    public function allowPendingOrders(): bool
    {
        return (bool) $this->value('allow_to_pay_with_pending_orders', false);
    }

    public function minimumAmount(): ?float
    {
        return $this->amount('minimum_amount');
    }

    public function maximumAmount(): ?float
    {
        return $this->amount('maximum_amount');
    }

    /**
     * @return int[]
     */
    public function taxRateIds(string $kind): array
    {
        $value = $this->value('taxes', [])[$kind] ?? null;

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $value)));
    }

    public function discountCode(): string
    {
        return (string) $this->value('discount');
    }

    public function invoice(): string
    {
        return (string) $this->value('invoice');
    }

    public function redirectPageId(): string
    {
        return (string) $this->value('redirect_page_id');
    }

    public function useLightbox(): bool
    {
        return (bool) $this->value('use_lightbox', false);
    }

    public function merchantEmail(): string
    {
        return (string) $this->value('merchant_email');
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private function value(string $key, $default = '')
    {
        return $this->values[$key] ?? $default;
    }

    private function amount(string $key): ?float
    {
        $value = $this->value($key, null);

        // An empty value means the limit is not configured.
        return $value === null || $value === '' ? null : (float) $value;
    }
}
