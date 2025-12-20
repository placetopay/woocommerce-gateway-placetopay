<?php

namespace PlacetoPay\PaymentMethod;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class DataMigration
 * 
 * Handles migration of settings from old format (woocommerce_placetopay_settings)
 * to new client-specific format (woocommerce_{client_id}_settings)
 */
class DataMigration
{
    const OLD_SETTINGS_OPTION = 'woocommerce_placetopay_settings';
    
    /**
     * Migrate settings if needed
     * 
     * @param string $client_id The client ID (e.g., 'getnet', 'banchile')
     * @param string $client_name The client name (e.g., 'Getnet', 'Banchile')
     * @return bool True if migration was performed, false otherwise
     */
    public static function migrateIfNeeded($client_id, $client_name)
    {
        $new_option_name = "woocommerce_{$client_id}_settings";
        
        if (get_option($new_option_name, false) !== false) {
            return false;
        }
        
        $old_settings = get_option(self::OLD_SETTINGS_OPTION, false);
        if ($old_settings === false) {
            return false;
        }

        if (is_string($old_settings)) {
            $old_settings = maybe_unserialize($old_settings);
        }

        if (!isset($old_settings['client']) || $old_settings['client'] !== $client_name) {
            return false;
        }

        update_option($new_option_name, $old_settings);

        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info(
                sprintf(
                    'Migrated settings from %s to %s for client %s',
                    self::OLD_SETTINGS_OPTION,
                    $new_option_name,
                    $client_name
                ),
                ['source' => $client_id . '-migration']
            );
        }
        
        return true;
    }
    
    /**
     * Get the current client ID from CountryConfig
     * 
     * @return string|null
     */
    public static function getCurrentClientId()
    {
        try {
            return CountryConfig::CLIENT_ID;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get the current client name from CountryConfig
     * 
     * @return string|null
     */
    public static function getCurrentClientName()
    {
        try {
            return CountryConfig::CLIENT;
        } catch (\Exception $e) {
            return null;
        }
    }
}

