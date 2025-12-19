#!/bin/bash

# Generate White Label Versions of WooCommerce PlacetoPay Gateway
# This script creates customized versions for different clients

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMP_DIR="${BASE_DIR}/temp_builds"
OUTPUT_DIR="${BASE_DIR}/builds"
CONFIG_FILE="${BASE_DIR}/config/clients.php"

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

get_client_config() {
    local client_key="$1"
    
    local config_class_name
    config_class_name=$(get_config_class_name "$client_key")
    
    # Determine which template to use
    local template_file=""
    
    # If we have a specific template, use it
    if [[ -n "$config_class_name" ]]; then
        template_file="${BASE_DIR}/config/templates/${config_class_name}.php"
    fi
    
    # If client-specific template doesn't exist, use original CountryConfig.php
    if [[ -z "$template_file" || ! -f "$template_file" ]]; then
        template_file="${BASE_DIR}/src/CountryConfig.php"
    fi
    
    # Check if template file exists
    if [[ ! -f "$template_file" ]]; then
        return 1
    fi
    
    # Use PHP to extract client configuration from template file
    php -r "
        // Read the template file content
        \$content = file_get_contents('$template_file');
        
        // Extract CLIENT constant
        if (preg_match(\"/public const CLIENT = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'CLIENT=' . \$matches[1] . '|';
        }
        
        // Extract COUNTRY_CODE constant
        if (preg_match(\"/public const COUNTRY_CODE = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'COUNTRY_CODE=' . \$matches[1] . '|';
        }
        
        // Extract COUNTRY_NAME constant
        if (preg_match(\"/public const COUNTRY_NAME = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'COUNTRY_NAME=' . \$matches[1] . '|';
        }
    " 2>/dev/null || echo ""
}

get_all_clients() {
    # First try to get from clients.php (simpler list)
    if [[ -f "$CONFIG_FILE" ]]; then
        php -r "
            \$config = include '$CONFIG_FILE';
            if (is_array(\$config) && isset(\$config[0])) {
                // It's a simple array
                echo implode(' ', \$config);
            } else {
                // It's an associative array (old format)
                echo implode(' ', array_keys(\$config));
            }
        " 2>/dev/null && return
    fi
    
    # Fallback: list template files
    local templates_dir="${BASE_DIR}/config/templates"
    if [[ -d "$templates_dir" ]]; then
        for file in "$templates_dir"/*Config.php; do
            [[ -f "$file" ]] || continue
            local basename=$(basename "$file" .php)
            # Convert Config class name back to client key
            case "$basename" in
                "EcuadorConfig") echo -n "ecuador " ;;
                "BeliceConfig") echo -n "belice " ;;
                "GetnetConfig") echo -n "getnet " ;;
                "HondurasConfig") echo -n "honduras " ;;
                "UruguayConfig") echo -n "uruguay " ;;
                "AvalPayConfig") echo -n "avalpay " ;;
                "BanchileConfig") echo -n "banchile " ;;
            esac
        done
        echo ""
    fi
}

parse_config() {
    local config="$1"
    
    # Reset variables
    CLIENT=""
    COUNTRY_CODE=""
    COUNTRY_NAME=""
    
    IFS='|' read -ra PARTS <<< "$config"
    for part in "${PARTS[@]}"; do
        IFS='=' read -ra KV <<< "$part"
        local key="${KV[0]}"
        local value="${KV[1]}"
        
        case "$key" in
            "CLIENT") CLIENT="$value" ;;
            "COUNTRY_CODE") COUNTRY_CODE="$value" ;;
            "COUNTRY_NAME") COUNTRY_NAME="$value" ;;
        esac
    done
}

get_project_name() {
    local client="$1"
    local country_name="$2"
    
    if [[ "$client" == "Placetopay" ]]; then
        echo "woocommerce-gateway-$(echo "$country_name" | tr '[:upper:]' '[:lower:]')"
    else
        echo "woocommerce-gateway-$(echo "$client" | tr '[:upper:]' '[:lower:]')"
    fi
}

get_config_class_name() {
    local client_key="$1"
    
    # Convert client key to Config class name format
    case "$client_key" in
        "ecuador") echo "EcuadorConfig" ;;
        "belice") echo "BeliceConfig" ;;
        "getnet") echo "GetnetConfig" ;;
        "honduras") echo "HondurasConfig" ;;
        "uruguay") echo "UruguayConfig" ;;
        "avalpay") echo "AvalPayConfig" ;;
        "banchile") echo "BanchileConfig" ;;
        *) echo "" ;;  # Empty means use original CountryConfig.php
    esac
}

# Function to create CountryConfig.php by copying the template file
create_country_config() {
    local target_file="$1"
    local client_key="$2"
    
    # Get the config class name
    local config_class_name
    config_class_name=$(get_config_class_name "$client_key")
    
    # Determine which template to use
    local template_file=""
    
    # If we have a specific template, use it
    if [[ -n "$config_class_name" ]]; then
        template_file="${BASE_DIR}/config/templates/${config_class_name}.php"
    fi
    
    # If client-specific template doesn't exist, use original CountryConfig.php
    if [[ -z "$template_file" || ! -f "$template_file" ]]; then
        template_file="${BASE_DIR}/src/CountryConfig.php"
    fi
    
    # Check if template file exists
    if [[ ! -f "$template_file" ]]; then
        print_error "Template file not found: $template_file"
        return 1
    fi
    
    # Simply copy the template file
    cp "$template_file" "$target_file"
}

update_main_plugin_file() {
    local target_file="$1"
    local client="$2"
    local project_name="$3"
    
    # Create updated main plugin file
    cat > "$target_file" << EOF
<?php
/**
 * Plugin Name: WooCommerce $client Gateway
 * Plugin URI: https://docs-gateway.placetopay.com/docs/webcheckout-docs/9016e976d1ea0-plugins-y-componentes
 * Description: Adds $client Payment Gateway to WooCommerce e-commerce plugin
 * Author: $client
 * Author URI: https://www.evertecinc.com/pasarela-de-pagos-e-commerce/
 * Developer: $client
 * Version: 2.24.7
 *
 * @package PlacetoPay/WC_Gateway_PlacetoPay
 *
 * @author Soporte <soporte@placetopay.com>
 * @copyright (c) 2013-2024 Evertec PlacetoPay S.A.S.
 * @version 2.24.7
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if ( is_admin() ) {
    add_filter( 'all_plugins', 'dynamic_plugin_name' );
}

/**
 * @param array \$plugins
 * @return array
 */
function dynamic_plugin_name( \$plugins ) {
    \$plugin_file = plugin_basename( __FILE__ );

    if ( isset( \$plugins[ \$plugin_file ] ) ) {
        \$settings = get_option( 'woocommerce_placetopay_settings', false );

        \$client = \\PlacetoPay\\PaymentMethod\\CountryConfig::CLIENT;

        \$plugins[ \$plugin_file ]['Name'] = 'WooCommerce '. \$client . ' Gateway';
        \$plugins[ \$plugin_file ]['Description'] = 'Adds ' . \$client  . ' Payment Gateway to WooCommerce e-commerce plugin';
        \$plugins[ \$plugin_file ]['Author'] = \$client;
    }

    return \$plugins;
}

/**
 * @return \\PlacetoPay\\PaymentMethod\\WC_Gateway_PlacetoPay
 */
function wc_gateway_placetopay()
{
    load_plugin_textdomain('woocommerce-gateway-placetopay', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    add_filter('woocommerce_locate_template', 'wooAddonPluginTemplate', 201, 3);

    /**
     * @param \$template
     * @param \$templateName
     * @param \$templatePath
     * @return string
     */
    function wooAddonPluginTemplate(\$template, \$templateName, \$templatePath)
    {
        global \$woocommerce;

        \$_template = \$template;

        if (!\$templatePath) {
            \$templatePath = \$woocommerce->template_url;
        }

        \$pluginPath = untrailingslashit(plugin_dir_path(__FILE__)) . '/woocommerce/';

        \$template = locate_template([
            \$templatePath . \$templateName,
            \$templateName
        ]);

        if (!\$template && file_exists(\$pluginPath . \$templateName)) {
            \$template = \$pluginPath . \$templateName;
        }

        if (!\$template) {
            \$template = \$_template;
        }

        return \$template;
    }

    require_once(__DIR__ . '/src/helpers.php');
    require_once(__DIR__ . '/vendor/autoload.php');
    return \\PlacetoPay\\PaymentMethod\\WC_Gateway_PlacetoPay::getInstance(
        \\PlacetoPay\\PaymentMethod\\GatewayMethod::VERSION,
        __FILE__
    );
}

add_action('plugins_loaded', 'wc_gateway_placetopay', 0);
EOF
}

create_white_label_version() {
    local client_key="$1"
    
    local config
    config=$(get_client_config "$client_key")
    
    if [[ -z "$config" ]]; then
        print_error "Unknown client: $client_key"
        print_error "Available clients: $(get_all_clients | tr ' ' ', ')"
        return 1
    fi
    
    print_status "Processing client: $client_key"
    
    parse_config "$config"
    
    PROJECT_NAME=$(get_project_name "$CLIENT" "$COUNTRY_NAME")
    
    print_status "Creating white-label version: $PROJECT_NAME"
    print_status "Client: $CLIENT, Country: $COUNTRY_NAME ($COUNTRY_CODE)"
    
    # Create temporary working directory
    WORK_DIR="$TEMP_DIR/$PROJECT_NAME"
    mkdir -p "$WORK_DIR"
    
    # Copy all files except builds and temp directories
    print_status "Copying source files..."
    rsync -av --exclude='builds/' --exclude='temp_builds/' --exclude='.git/' --exclude='*.sh' --exclude='/config/' "$BASE_DIR/" "$WORK_DIR/"
    
    # Copy CountryConfig.php from template
    print_status "Copying CountryConfig.php from template..."
    create_country_config "$WORK_DIR/src/CountryConfig.php" "$client_key"
    
    # Update main plugin file
    print_status "Updating main plugin file..."
    update_main_plugin_file "$WORK_DIR/$PROJECT_NAME.php" "$CLIENT" "$PROJECT_NAME"
    
    # Remove the original main plugin file if it has a different name
    if [[ "$PROJECT_NAME.php" != "woocommerce-gateway-placetopay.php" ]]; then
        rm -f "$WORK_DIR/woocommerce-gateway-placetopay.php"
    fi
    
    # Create ZIP file
    print_status "Creating ZIP file..."
    mkdir -p "$OUTPUT_DIR"
    cd "$TEMP_DIR"
    zip -rq "$OUTPUT_DIR/$PROJECT_NAME.zip" "$PROJECT_NAME"
    cd "$BASE_DIR"
    
    print_success "Created: $OUTPUT_DIR/$PROJECT_NAME.zip"
}

create_default_version() {
    print_status "Creating default/base version..."
    
    # Read values from original CountryConfig.php
    local config
    config=$(get_client_config_from_file "${BASE_DIR}/src/CountryConfig.php")
    
    if [[ -z "$config" ]]; then
        print_error "Could not read CountryConfig.php"
        return 1
    fi
    
    # Parse configuration
    parse_config "$config"
    
    # Determine project name
    PROJECT_NAME=$(get_project_name "$CLIENT" "$COUNTRY_NAME")
    
    print_status "Creating default version: $PROJECT_NAME"
    print_status "Client: $CLIENT, Country: $COUNTRY_NAME ($COUNTRY_CODE)"
    
    # Create temporary working directory
    WORK_DIR="$TEMP_DIR/$PROJECT_NAME"
    mkdir -p "$WORK_DIR"
    
    # Copy all files except builds, temp directories, config, and scripts
    print_status "Copying source files (excluding generation folders)..."
    rsync -av --exclude='builds/' --exclude='temp_builds/' --exclude='.git/' --exclude='*.sh' --exclude='/config/' "$BASE_DIR/" "$WORK_DIR/"
    
    # Use original CountryConfig.php (don't copy from template)
    print_status "Using original CountryConfig.php..."
    
    # Update main plugin file
    print_status "Updating main plugin file..."
    update_main_plugin_file "$WORK_DIR/$PROJECT_NAME.php" "$CLIENT" "$PROJECT_NAME"
    
    # Remove the original main plugin file if it has a different name
    if [[ "$PROJECT_NAME.php" != "woocommerce-gateway-placetopay.php" ]]; then
        rm -f "$WORK_DIR/woocommerce-gateway-placetopay.php"
    fi
    
    # Create ZIP file
    print_status "Creating ZIP file..."
    mkdir -p "$OUTPUT_DIR"
    cd "$TEMP_DIR"
    zip -rq "$OUTPUT_DIR/$PROJECT_NAME.zip" "$PROJECT_NAME"
    cd "$BASE_DIR"
    
    print_success "Created: $OUTPUT_DIR/$PROJECT_NAME.zip"
}

# Function to get client config from a specific file
get_client_config_from_file() {
    local template_file="$1"
    
    if [[ ! -f "$template_file" ]]; then
        return 1
    fi
    
    # Use PHP to extract client configuration from template file
    php -r "
        // Read the template file content
        \$content = file_get_contents('$template_file');
        
        // Extract CLIENT constant
        if (preg_match(\"/public const CLIENT = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'CLIENT=' . \$matches[1] . '|';
        }
        
        // Extract COUNTRY_CODE constant
        if (preg_match(\"/public const COUNTRY_CODE = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'COUNTRY_CODE=' . \$matches[1] . '|';
        }
        
        // Extract COUNTRY_NAME constant
        if (preg_match(\"/public const COUNTRY_NAME = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'COUNTRY_NAME=' . \$matches[1] . '|';
        }
    " 2>/dev/null || echo ""
}

# Main execution
main() {
    print_status "Starting white-label generation process..."
    
    # Check if configuration file exists
    if [[ ! -f "$CONFIG_FILE" ]]; then
        print_error "Configuration file not found: $CONFIG_FILE"
        print_error "Please make sure the config/clients.php file exists."
        exit 1
    fi
    
    # Clean up previous builds
    print_status "Cleaning up previous builds..."
    rm -rf "$TEMP_DIR" "$OUTPUT_DIR"
    mkdir -p "$TEMP_DIR" "$OUTPUT_DIR"
    
    # Check if helpers.php exists (it was deleted according to git status)
    if [[ ! -f "$BASE_DIR/src/helpers.php" ]]; then
        print_warning "src/helpers.php not found. Creating empty file..."
        mkdir -p "$BASE_DIR/src"
        touch "$BASE_DIR/src/helpers.php"
        echo "<?php" > "$BASE_DIR/src/helpers.php"
    fi
    
    # Process each client configuration
    for client_key in $(get_all_clients); do
        create_white_label_version "$client_key"
        echo
    done
    
    # Generate default version (Colombia/Placetopay base)
    print_status "Generating default/base version..."
    create_default_version
    echo
    
    # Clean up temporary directory
    print_status "Cleaning up temporary files..."
    rm -rf "$TEMP_DIR"
    
    print_success "White-label generation completed!"
    print_status "Generated files are in: $OUTPUT_DIR"
    
    # List generated files
    echo
    print_status "Generated white-label versions:"
    ls -la "$OUTPUT_DIR"/*.zip 2>/dev/null | while read -r line; do
        echo "  $line"
    done || print_warning "No ZIP files found in output directory"
}

# Show usage information
usage() {
    echo "Usage: $0 [OPTIONS] [CLIENT]"
    echo ""
    echo "Generate white-label versions of WooCommerce PlacetoPay Gateway"
    echo ""
    echo "Options:"
    echo "  -h, --help    Show this help message"
    echo "  -l, --list    List available client configurations"
    echo "  --default     Generate default/base version (without generation folders)"
    echo "  CLIENT        Generate only for specific client (optional)"
    echo ""
    echo "Available clients:"
    for client in $(get_all_clients); do
        config=$(get_client_config "$client")
        if [[ -n "$config" ]]; then
            parse_config "$config"
            echo "  - $client: $CLIENT ($COUNTRY_NAME - $COUNTRY_CODE)"
        fi
    done
}

# Handle command line arguments
case "${1:-}" in
    -h|--help)
        usage
        exit 0
        ;;
    -l|--list)
        echo "Available client configurations:"
        for client_key in $(get_all_clients); do
            config=$(get_client_config "$client_key")
            if [[ -n "$config" ]]; then
                parse_config "$config"
                echo "  $client_key: $CLIENT ($COUNTRY_NAME - $COUNTRY_CODE)"
            fi
        done
        exit 0
        ;;
    --default|default)
        print_status "Generating default/base version..."
        rm -rf "$TEMP_DIR" "$OUTPUT_DIR"
        mkdir -p "$TEMP_DIR" "$OUTPUT_DIR"
        
        # Check if helpers.php exists
        if [[ ! -f "$BASE_DIR/src/helpers.php" ]]; then
            print_warning "src/helpers.php not found. Creating empty file..."
            mkdir -p "$BASE_DIR/src"
            touch "$BASE_DIR/src/helpers.php"
            echo "<?php" > "$BASE_DIR/src/helpers.php"
        fi
        
        create_default_version
        
        # Clean up temporary directory
        print_status "Cleaning up temporary files..."
        rm -rf "$TEMP_DIR"
        
        print_success "Default version generation completed!"
        exit 0
        ;;
    "")
        main
        ;;
    *)
        # Check if it's a valid client
        config=$(get_client_config "$1")
        if [[ -n "$config" ]]; then
            print_status "Generating white-label version for: $1"
            rm -rf "$TEMP_DIR" "$OUTPUT_DIR"
            mkdir -p "$TEMP_DIR" "$OUTPUT_DIR"
            
            # Check if helpers.php exists
            if [[ ! -f "$BASE_DIR/src/helpers.php" ]]; then
                print_warning "src/helpers.php not found. Creating empty file..."
                mkdir -p "$BASE_DIR/src"
                touch "$BASE_DIR/src/helpers.php"
                echo "<?php" > "$BASE_DIR/src/helpers.php"
            fi
            
            create_white_label_version "$1"
            rm -rf "$TEMP_DIR"
            print_success "White-label generation completed for $1!"
        else
            print_error "Unknown client: $1"
            echo ""
            usage
            exit 1
        fi
        ;;
esac