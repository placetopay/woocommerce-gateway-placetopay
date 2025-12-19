<?php

namespace PlacetoPay\PaymentMethod;

class ResolveTranslations
{
    private const LANGUAGES = [
        'es_CO',
        'es_CR',
        'es_CL',
        'es_ES',
        'es_PR',
        'es_UY'
    ];

    function __construct()
    {
        $this->execute();
    }

    public function execute(): void
    {
        foreach (self::LANGUAGES as $lang) {
            $langPath = 'languages/woocommerce-gateway-translations-' . $lang . '.po';

            if (!in_array($lang, ['es_CL', 'es_ES'])) {
                copy(__DIR__ . '/../languages/woocommerce-gateway-translations-es_ES.po', $langPath);
                $this->executeWPCommand($lang, $langPath);
                unlink($langPath);

                continue;
            }

            $this->executeWPCommand($lang, $langPath);
        }
    }

    private function executeWPCommand(string $lang, string $path): void
    {
        $command = 'wp i18n make-mo ' . $path;
        $resultFile = 'woocommerce-gateway-translations-' . $lang . '.mo';

        $execute = shell_exec($command);
        var_dump($execute . $resultFile . PHP_EOL);
    }
}
