<?php

namespace PlacetoPay\PaymentMethod\Logger;

use Psr\Log\LoggerInterface;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GatewayLogger
{
    /**
     * @var string
     */
    private $source;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var \WC_Logger_Interface|null
     */
    private $logger;

    public function __construct(string $source, bool $debug)
    {
        $this->source = $source;
        $this->debug = $debug;
    }

    public function log(string $message, string $type, string $level = 'info', array $context = []): void
    {
        if (!$this->debug && !in_array($level, ['warning', 'error'], true)) {
            return;
        }

        if (!empty($context)) {
            $message .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $this->logger()->add(
            $this->source,
            strtoupper($level) . ($type ? " ($type): " : ': ') . $message
        );
    }

    public function sdkLogger(): ?LoggerInterface
    {
        if (!$this->debug) {
            return null;
        }

        $logger = $this->logger();

        return $logger instanceof \WC_Logger ? new WCLoggerAdapter($logger, $this->source) : null;
    }

    /**
     * @return \WC_Logger_Interface
     */
    private function logger()
    {
        if ($this->logger === null) {
            $this->logger = wc_get_logger();
        }

        return $this->logger;
    }
}
