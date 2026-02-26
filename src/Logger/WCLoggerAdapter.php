<?php

namespace PlacetoPay\PaymentMethod\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class WCLoggerAdapter implements LoggerInterface
{
    private $logger;

    private $source;

    public function __construct(\WC_Logger $logger, string $source = 'placetopay')
    {
        $this->logger = $logger;
        $this->source = $source;
    }

    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $message = $this->normalizeMessage($message);
        $message = $this->interpolate($message, $context);

        if ($context !== []) {
            $message .= ' | context=' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $this->logger->add($this->source, strtoupper((string) $level) . ': ' . $message);
    }

    private function normalizeMessage($message): string
    {
        if (is_string($message)) {
            return $message;
        }

        return json_encode($message, JSON_UNESCAPED_UNICODE);
    }

    private function interpolate(string $message, array $context): string
    {
        if (strpos($message, '{') === false) {
            return $message;
        }

        $replace = [];

        foreach ($context as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $replace['{' . $key . '}'] = (string) $value;
        }

        return strtr($message, $replace);
    }
}
