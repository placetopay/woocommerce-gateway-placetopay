#!/usr/bin/env bash

# Generar versiones de marca blanca del plugin WooCommerce PlacetoPay
# Este script crea versiones personalizadas para diferentes clientes

set -e

# Colores para la salida
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # Sin Color

# Directorio base
BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMP_DIR="${BASE_DIR}/temp_builds"
OUTPUT_DIR="${BASE_DIR}/builds"
CONFIG_FILE="${BASE_DIR}/config/clients.php"

# Versiones de PHP para generar
PHP_VERSIONS=("7.4" "8.0")
PHP_VERSION_LABELS=("php-7.4.x" "php-8.x")

# Funciones para imprimir con colores
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCC]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Función para obtener el nombre de la clase de configuración desde la clave del cliente
# Formato esperado: "cliente-país" (ej: "getnet-chile", "placetopay-colombia")
get_config_class_name() {
    local client_key="$1"

    # Convertir clave del cliente (formato "cliente-país") al nombre de la clase de template
    # Ejemplo: "getnet-chile" -> "GetnetChileConfig"
    case "$client_key" in
        "placetopay-colombia") echo "PlacetopayColombiaConfig" ;;
        "placetopay-ecuador") echo "PlacetopayEcuadorConfig" ;;
        "placetopay-belice") echo "PlacetopayBeliceConfig" ;;
        "placetopay-honduras") echo "PlacetopayHondurasConfig" ;;
        "placetopay-uruguay") echo "PlacetopayUruguayConfig" ;;
        "getnet-chile") echo "GetnetChileConfig" ;;
        "banchile-chile") echo "BanchileChileConfig" ;;
        "avalpay-colombia") echo "AvalpayColombiaConfig" ;;
        *)
            # Intentar construir el nombre desde la clave
            # Convertir "cliente-país" a "ClientePaisConfig"
            echo "$client_key" | awk -F'-' '{
                result = ""
                for (i=1; i<=NF; i++) {
                    word = $i
                    if (length(word) > 0) {
                        first = toupper(substr(word,1,1))
                        rest = tolower(substr(word,2))
                        result = result first rest
                    }
                }
                print result "Config"
            }'
            ;;
    esac
}

# Función para obtener configuración de cliente desde el template
get_client_config() {
    local client_key="$1"

    # Obtener nombre de la clase de configuración
    local config_class_name
    config_class_name=$(get_config_class_name "$client_key")

    # Determinar qué template usar
    local template_file=""

    # Si tenemos un template específico, usarlo
    if [[ -n "$config_class_name" ]]; then
        template_file="${BASE_DIR}/config/templates/${config_class_name}.php"
    fi

    # Verificar que el archivo existe
    if [[ -z "$template_file" || ! -f "$template_file" ]]; then
        return 1
    fi

    # Usar PHP para extraer la configuración del template
    php -r "
        // Leer el contenido del archivo template
        \$content = file_get_contents('$template_file');

        // Extraer constante CLIENT_ID
        if (preg_match(\"/public const CLIENT_ID = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'CLIENT_ID=' . \$matches[1] . '|';
        }

        // Extraer constante CLIENT_URI
        if (preg_match(\"/public const CLIENT_URI = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'CLIENT_URI=' . \$matches[1] . '|';
        }

        // Extraer constante CLIENT
        if (preg_match(\"/public const CLIENT = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'CLIENT=' . \$matches[1] . '|';
        }

        // Extraer constante COUNTRY_CODE
        if (preg_match(\"/public const COUNTRY_CODE = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'COUNTRY_CODE=' . \$matches[1] . '|';
        }

        // Extraer constante COUNTRY_NAME
        if (preg_match(\"/public const COUNTRY_NAME = ['\\\"]([^'\\\"]+)['\\\"]/\", \$content, \$matches)) {
            echo 'COUNTRY_NAME=' . \$matches[1] . '|';
        }

        // Agregar nombre del template si existe
        if ('$config_class_name' !== '') {
            echo 'TEMPLATE_FILE=' . '$config_class_name' . '|';
        }
    " 2>/dev/null || echo ""
}

# Función para obtener todos los clientes disponibles desde archivo PHP
get_all_clients() {
    # Primero intentar obtener desde clients.php (lista simple)
    if [[ -f "$CONFIG_FILE" ]]; then
        php -r "
            \$config = include '$CONFIG_FILE';
            if (is_array(\$config) && isset(\$config[0])) {
                // Es un array simple
                echo implode(' ', \$config);
            } else {
                // Es un array asociativo (formato antiguo)
                echo implode(' ', array_keys(\$config));
            }
        " 2>/dev/null && return
    fi

    # Fallback: listar archivos de templates y construir claves en formato cliente-país
    local templates_dir="${BASE_DIR}/config/templates"
    if [[ -d "$templates_dir" ]]; then
        for file in "$templates_dir"/*Config.php; do
            [[ -f "$file" ]] || continue
            local basename=$(basename "$file" .php)
            # Convertir nombre de clase de vuelta a clave de cliente en formato cliente-país
            # Ejemplo: "GetnetChileConfig" -> "getnet-chile"
            case "$basename" in
                "PlacetopayColombiaConfig") echo -n "placetopay-colombia " ;;
                "PlacetopayEcuadorConfig") echo -n "placetopay-ecuador " ;;
                "PlacetopayBeliceConfig") echo -n "placetopay-belice " ;;
                "GetnetChileConfig") echo -n "getnet-chile " ;;
                "PlacetopayHondurasConfig") echo -n "placetopay-honduras " ;;
                "PlacetopayUruguayConfig") echo -n "placetopay-uruguay " ;;
                "AvalpayColombiaConfig") echo -n "avalpay-colombia " ;;
                "BanchileChileConfig") echo -n "banchile-chile " ;;
                *)
                    # Intentar convertir desde el nombre de archivo
                    # "ClientePaisConfig" -> "cliente-pais"
                    echo -n "$(echo "$basename" | sed 's/Config$//' | sed 's/\([A-Z]\)/-\1/g' | sed 's/^-//' | tr '[:upper:]' '[:lower:]') "
                    ;;
            esac
        done
        echo ""
    fi
}

# Función para parsear configuración
parse_config() {
    local config="$1"

    # Reset variables
    CLIENT_ID=""
    CLIENT_URI=""
    CLIENT=""
    COUNTRY_CODE=""
    COUNTRY_NAME=""
    TEMPLATE_FILE=""

    IFS='|' read -ra PARTS <<< "$config"

    for part in "${PARTS[@]}"; do
        IFS='=' read -ra KV <<< "$part"
        local key="${KV[0]}"
        local value="${KV[1]}"

        case "$key" in
            "CLIENT_ID") CLIENT_ID="$value" ;;
            "CLIENT_URI") CLIENT_URI="$value" ;;
            "CLIENT") CLIENT="$value" ;;
            "COUNTRY_CODE") COUNTRY_CODE="$value" ;;
            "COUNTRY_NAME") COUNTRY_NAME="$value" ;;
            "TEMPLATE_FILE") TEMPLATE_FILE="$value" ;;
        esac
    done
}

# Función para generar CLIENT_ID en formato "cliente-país" (con guión)
get_client_id() {
    local client="$1"
    local country_name="$2"

    # Convertir cliente y país a minúsculas, normalizar espacios a guiones y combinarlos con guión
    local client_lower=$(echo "$client" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')
    local country_lower=$(echo "$country_name" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')

    echo "${client_lower}-${country_lower}"
}

# Función para obtener nombre del proyecto
# Ahora usa el formato "cliente-país" (CLIENT_ID)
get_project_name() {
    local client_id="$1"
    # Usar CLIENT_ID directamente en formato "cliente-país"
    echo "woocommerce-gateway-${client_id}"
}

# Función para convertir CLIENT_ID a formato válido para nombres de funciones PHP
# Convierte "getnet-chile" -> "getnet_chile" (reemplaza guiones con guiones bajos)
get_php_function_id() {
    local client_id="$1"
    # Reemplazar guiones con guiones bajos para nombres de funciones PHP válidos
    echo "$client_id" | tr '-' '_'
}

# Función para obtener el nombre del namespace desde CLIENT_ID
# Convierte "getnet-chile" -> "GetnetChile" (capitaliza cada palabra después del guión)
get_namespace_name() {
    local client_id="$1"

    # Convertir formato "cliente-país" a "ClientePais" (PascalCase)
    # Dividir por guiones, capitalizar primera letra de cada palabra, unir sin espacios
    echo "$client_id" | awk -F'-' '{
        result = ""
        for (i=1; i<=NF; i++) {
            word = $i
            if (length(word) > 0) {
                first = toupper(substr(word,1,1))
                rest = tolower(substr(word,2))
                result = result first rest
            }
        }
        print result
    }'
}

# Función para convertir CLIENT_ID a camelCase para JavaScript
# Convierte "getnet-chile" -> "getnetChile" (primera palabra minúscula, resto en PascalCase)
get_camel_case_name() {
    local client_id="$1"

    # Convertir formato "cliente-país" a "clientePais" (camelCase)
    # Primera palabra en minúsculas, resto en PascalCase
    echo "$client_id" | awk -F'-' '{
        result = ""
        for (i=1; i<=NF; i++) {
            word = $i
            if (length(word) > 0) {
                if (i == 1) {
                    # Primera palabra: toda en minúsculas
                    result = result tolower(word)
                } else {
                    # Resto: PascalCase
                    first = toupper(substr(word,1,1))
                    rest = tolower(substr(word,2))
                    result = result first rest
                }
            }
        }
        print result
    }'
}

# Función para reemplazar namespaces en archivos PHP
replace_namespaces() {
    local work_dir="$1"
    local old_namespace="PlacetoPay\\PaymentMethod"
    local new_namespace="$2\\PaymentMethod"

    print_status "Reemplazando namespaces: $old_namespace -> $new_namespace"

    # Buscar y reemplazar en todos los archivos PHP
    # Escapar correctamente para sed
    local old_ns_escaped="PlacetoPay\\\\PaymentMethod"
    local new_ns_escaped="$2\\\\PaymentMethod"

    for dir in src woocommerce; do
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s|namespace $old_ns_escaped|namespace $new_ns_escaped|g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s|use $old_ns_escaped|use $new_ns_escaped|g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s|\\\\$old_ns_escaped|\\\\$new_ns_escaped|g" {} \;

        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s|'$old_ns_escaped|'$new_ns_escaped|g" {} \;
    done

    # También actualizar el namespace en CountryConfig.php (ya copiado)
    if [[ -f "$work_dir/src/CountryConfig.php" ]]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s|namespace $old_ns_escaped|namespace $new_ns_escaped|g" "$work_dir/src/CountryConfig.php"
        else
            sed -i "s|namespace $old_ns_escaped|namespace $new_ns_escaped|g" "$work_dir/src/CountryConfig.php"
        fi
    fi

    # Limpiar archivos .bak
    find "$work_dir/src" -type f -name "*.bak" -delete

    # También reemplazar en el archivo principal del plugin
    if [[ -f "$work_dir"/*.php ]]; then
        local main_file=$(find "$work_dir" -maxdepth 1 -name "*.php" -type f | head -1)

        if [[ -n "$main_file" ]]; then
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s|\\\\$old_ns_escaped|\\\\$new_ns_escaped|g" "$main_file"
            else
                sed -i "s|\\\\$old_ns_escaped|\\\\$new_ns_escaped|g" "$main_file"
            fi
        fi
    fi
}

# Función para reemplazar nombres de clases
replace_class_names() {
    local work_dir="$1"
    local client_id="$2"
    local namespace_name
    namespace_name=$(get_namespace_name "$client_id")

    # Debug: Verificar que namespace_name esté limpio
    if [[ "$DEBUG" == "1" ]]; then
        echo "DEBUG INFO:"
        echo "Client ID recibido: '$client_id'"
        echo "Namespace generado: '$namespace_name'"
        echo "Namespace (raw):"
        echo "$namespace_name" | cat -v
    fi

    print_status "Reemplazando nombres de clases para cliente: $client_id"

    # Reemplazar GatewayMethod -> GatewayMethod{Client}
    # Usar namespace_name directamente como suffix (ya viene con primera letra mayúscula)
    # Limpiar cualquier carácter oculto o espacios
    local class_suffix=$(echo "$namespace_name" | tr -d '[:space:]' | sed 's/[^a-zA-Z0-9]//g')

    # Verificar que class_suffix no esté vacío
    if [[ -z "$class_suffix" ]]; then
        print_error "class_suffix está vacío después de la limpieza. client_id: '$client_id', namespace_name: '$namespace_name'"
        return 1
    fi

    # Primero renombrar el archivo GatewayMethod.php -> GatewayMethod{Client}.php
    if [[ -f "$work_dir/src/GatewayMethod.php" ]]; then
        mv "$work_dir/src/GatewayMethod.php" "$work_dir/src/GatewayMethod${class_suffix}.php"
    fi

    for dir in src woocommerce; do
        # Reemplazar declaración de clase
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/^class GatewayMethod /class GatewayMethod${class_suffix} /g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/^class GatewayMethod$/class GatewayMethod${class_suffix}/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/^class GatewayMethod extends/class GatewayMethod${class_suffix} extends/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/GatewayMethod::/GatewayMethod${class_suffix}::/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/new GatewayMethod()/new GatewayMethod${class_suffix}()/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/new GatewayMethod(/new GatewayMethod${class_suffix}(/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/GatewayMethod /GatewayMethod${class_suffix} /g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/\[GatewayMethod()\]/[GatewayMethod${class_suffix}()]/g" {} \;

        # Reemplazar referencias al archivo en require/include/require_once para GatewayMethod
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/'GatewayMethod\.php'/'GatewayMethod${class_suffix}.php'/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/\"GatewayMethod\.php\"/\"GatewayMethod${class_suffix}.php\"/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/\. 'GatewayMethod\.php'/\. 'GatewayMethod${class_suffix}.php'/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/\. \"GatewayMethod\.php\"/\. \"GatewayMethod${class_suffix}.php\"/g" {} \;

        # Reemplazar WC_Gateway_PlacetoPay -> WC_Gateway_{Client}
        # Primero renombrar el archivo
        if [[ -f "$work_dir/$dir/WC_Gateway_PlacetoPay.php" ]]; then
            mv "$work_dir/$dir/WC_Gateway_PlacetoPay.php" "$work_dir/$dir/WC_Gateway_${class_suffix}.php"
        fi

        # Reemplazar declaración de clase y referencias (usar patrones más específicos)
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/^class WC_Gateway_PlacetoPay$/class WC_Gateway_${class_suffix}/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/^class WC_Gateway_PlacetoPay /class WC_Gateway_${class_suffix} /g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/WC_Gateway_PlacetoPay::/WC_Gateway_${class_suffix}::/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/new WC_Gateway_PlacetoPay(/new WC_Gateway_${class_suffix}(/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/WC_Gateway_PlacetoPay /WC_Gateway_${class_suffix} /g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/@var WC_Gateway_PlacetoPay/@var WC_Gateway_${class_suffix}/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/return WC_Gateway_PlacetoPay/return WC_Gateway_${class_suffix}/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/instanceof WC_Gateway_PlacetoPay/instanceof WC_Gateway_${class_suffix}/g" {} \;

        # Reemplazar GatewayMethodBlocks -> GatewayMethodBlocks{Client}
        # Primero renombrar el archivo
        if [[ -f "$work_dir/$dir/GatewayMethodBlocks.php" ]]; then
            mv "$work_dir/$dir/GatewayMethodBlocks.php" "$work_dir/$dir/GatewayMethodBlocks${class_suffix}.php"
        fi

        # Reemplazar declaración de clase
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/^class GatewayMethodBlocks /class GatewayMethodBlocks${class_suffix} /g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/^class GatewayMethodBlocks$/class GatewayMethodBlocks${class_suffix}/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/^class GatewayMethodBlocks extends/class GatewayMethodBlocks${class_suffix} extends/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/new GatewayMethodBlocks()/new GatewayMethodBlocks${class_suffix}()/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/new GatewayMethodBlocks(/new GatewayMethodBlocks${class_suffix}(/g" {} \;

        # Reemplazar referencias al archivo en require/include/require_once
        # Manejar diferentes formatos: 'GatewayMethodBlocks.php', "GatewayMethodBlocks.php", . 'GatewayMethodBlocks.php', etc.
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/'GatewayMethodBlocks\.php'/'GatewayMethodBlocks${class_suffix}.php'/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/\"GatewayMethodBlocks\.php\"/\"GatewayMethodBlocks${class_suffix}.php\"/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/\. 'GatewayMethodBlocks\.php'/\. 'GatewayMethodBlocks${class_suffix}.php'/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/\. \"GatewayMethodBlocks\.php\"/\. \"GatewayMethodBlocks${class_suffix}.php\"/g" {} \;
        # También manejar concatenación sin comillas (caso especial)
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/ GatewayMethodBlocks\.php/ GatewayMethodBlocks${class_suffix}.php/g" {} \;

        # Reemplazar nombres de métodos en WC_Gateway_PlacetoPay
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/addPlacetoPayGatewayMethod/add${class_suffix}GatewayMethod/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/actionLinksPlacetopay/actionLinks${class_suffix}/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/->addPlacetoPayGatewayMethod/->add${class_suffix}GatewayMethod/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/->actionLinksPlacetopay/->actionLinks${class_suffix}/g" {} \;

        # Reemplazar referencias a métodos estáticos y constantes
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/\[GatewayMethod::class\]/[GatewayMethod${class_suffix}::class]/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/\[GatewayMethodBlocks::class\]/[GatewayMethodBlocks${class_suffix}::class]/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/GatewayMethod::class/GatewayMethod${class_suffix}::class/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/GatewayMethod::NOTIFICATION_RETURN_PAGE/GatewayMethod${class_suffix}::NOTIFICATION_RETURN_PAGE/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/GatewayMethod::validateVersionSupportBlocks/GatewayMethod${class_suffix}::validateVersionSupportBlocks/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/GatewayMethod::VERSION/GatewayMethod${class_suffix}::VERSION/g" {} \;

        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/\\\\GatewayMethod\\\\/\\\\GatewayMethod${class_suffix}\\\\/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/\\\\GatewayMethod::/\\\\GatewayMethod${class_suffix}::/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/\\\\GatewayMethod;/\\\\GatewayMethod${class_suffix};/g" {} \;
        find "$work_dir/$dir" -type f -name "*.php" -exec sed -i.bak "s/ GatewayMethod::/ GatewayMethod${class_suffix}::/g" {} \;

        # Limpiar archivos .bak
        find "$work_dir/$dir" -type f -name "*.bak" -delete
    done
}

# Función para reemplazar payment method IDs y endpoints
replace_payment_method_ids() {
    local work_dir="$1"
    local client_id="$2"
    local namespace_name
    namespace_name=$(get_namespace_name "$client_id")

    print_status "Reemplazando payment method IDs y endpoints para: $client_id"

    # Reemplazar payment method ID: 'placetopay' -> '{client_id}'
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\\\$this->id = 'placetopay'/\\\$this->id = '${client_id}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'placetopay'/'${client_id}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"placetopay\"/\"${client_id}\"/g" {} \;

    # Reemplazar ucfirst($client_id) y ucfirst(CountryConfig::CLIENT_ID) con el namespace_name correcto
    # Esto corrige el problema donde ucfirst('banchile-chile') genera 'Banchile-chile' en lugar de 'BanchileChile'
    # Reemplazar en concatenaciones de strings: 'GatewayMethod' . ucfirst(...) -> 'GatewayMethod${namespace_name}'
    # Hacer múltiples reemplazos para cubrir variaciones de espacios alrededor del punto
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'GatewayMethod' \. ucfirst(\\\$client_id)/'GatewayMethod${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'GatewayMethod'\.ucfirst(\\\$client_id)/'GatewayMethod${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"GatewayMethod\" \. ucfirst(\\\$client_id)/\"GatewayMethod${namespace_name}\"/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"GatewayMethod\"\.ucfirst(\\\$client_id)/\"GatewayMethod${namespace_name}\"/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'GatewayMethod' \. ucfirst(CountryConfig::CLIENT_ID)/'GatewayMethod${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'GatewayMethod'\.ucfirst(CountryConfig::CLIENT_ID)/'GatewayMethod${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"GatewayMethod\" \. ucfirst(CountryConfig::CLIENT_ID)/\"GatewayMethod${namespace_name}\"/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"GatewayMethod\"\.ucfirst(CountryConfig::CLIENT_ID)/\"GatewayMethod${namespace_name}\"/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'GatewayMethod' \. ucfirst(\\\\PlacetoPay\\\\PaymentMethod\\\\CountryConfig::CLIENT_ID)/'GatewayMethod${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"GatewayMethod\" \. ucfirst(\\\\PlacetoPay\\\\PaymentMethod\\\\CountryConfig::CLIENT_ID)/\"GatewayMethod${namespace_name}\"/g" {} \;
    # Reemplazar 'GatewayMethodBlocks' . ucfirst(...) -> 'GatewayMethodBlocks${namespace_name}'
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'GatewayMethodBlocks' \. ucfirst(\\\$client_id)/'GatewayMethodBlocks${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'GatewayMethodBlocks'\.ucfirst(\\\$client_id)/'GatewayMethodBlocks${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"GatewayMethodBlocks\" \. ucfirst(\\\$client_id)/\"GatewayMethodBlocks${namespace_name}\"/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"GatewayMethodBlocks\"\.ucfirst(\\\$client_id)/\"GatewayMethodBlocks${namespace_name}\"/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'GatewayMethodBlocks' \. ucfirst(CountryConfig::CLIENT_ID)/'GatewayMethodBlocks${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"GatewayMethodBlocks\" \. ucfirst(CountryConfig::CLIENT_ID)/\"GatewayMethodBlocks${namespace_name}\"/g" {} \;
    # Reemplazar 'gatewayData' . ucfirst(...) -> 'gatewayData${namespace_name}' (para JavaScript)
    # PHP genera: 'gatewayData' . ucfirst($client_id) pero ucfirst('banchile-chile') = 'Banchile-chile' (inválido)
    # Necesitamos reemplazarlo con 'gatewayDataBanchileChile' (PascalCase completo después de gatewayData)
    # El namespace_name ya está en PascalCase (BanchileChile), así que solo lo concatenamos
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'gatewayData' \. ucfirst(\\\$client_id)/'gatewayData${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'gatewayData'\.ucfirst(\\\$client_id)/'gatewayData${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"gatewayData\" \. ucfirst(\\\$client_id)/\"gatewayData${namespace_name}\"/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'gatewayData' \. ucfirst(CountryConfig::CLIENT_ID)/'gatewayData${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"gatewayData\" \. ucfirst(CountryConfig::CLIENT_ID)/\"gatewayData${namespace_name}\"/g" {} \;
    # Reemplazar casos simples de ucfirst(...) sin concatenación (menos común pero posible)
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/ucfirst(\\\$client_id)/'${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/ucfirst(CountryConfig::CLIENT_ID)/'${namespace_name}'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/ucfirst(\\\\PlacetoPay\\\\PaymentMethod\\\\CountryConfig::CLIENT_ID)/'${namespace_name}'/g" {} \;

    # Reemplazar endpoints REST: 'placetopay-payment/v2' -> '{client_id}-payment/v2'
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'placetopay-payment\/v2'/'${client_id}-payment\/v2'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"placetopay-payment\/v2\"/\"${client_id}-payment\/v2\"/g" {} \;

    # Reemplazar NOTIFICATION_RETURN_PAGE específicamente primero (antes de reemplazos genéricos)
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/const NOTIFICATION_RETURN_PAGE = 'placetopay_notification_return_page'/const NOTIFICATION_RETURN_PAGE = '${client_id}_notification_return_page'/g" {} \;

    # Reemplazar hooks y acciones específicos ANTES del reemplazo genérico de 'placetopay_'
    # Esto evita que se reemplacen strings que ya tienen el client_id
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'placetopay_init'/'${client_id}_init'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"placetopay_init\"/\"${client_id}_init\"/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'placetopay_response'/'${client_id}_response'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"placetopay_response\"/\"${client_id}_response\"/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/'placetopay_notification'/'${client_id}_notification'/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/\"placetopay_notification\"/\"${client_id}_notification\"/g" {} \;
    # También reemplazar en do_action y add_action sin comillas (ya procesados arriba, pero por si acaso)
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/placetopay_init/${client_id}_init/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/placetopay_response/${client_id}_response/g" {} \;
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/placetopay_notification/${client_id}_notification/g" {} \;

    # NO hacer reemplazo genérico de 'placetopay_' porque ya reemplazamos todos los casos específicos arriba
    # Si hay otros casos de 'placetopay_' que necesiten reemplazo, deben agregarse específicamente arriba

    # Reemplazar sección de configuración en URLs: section=placetopay -> section={client_id}
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/section=placetopay/section=${client_id}/g" {} \;

    # Reemplazar en archivo principal (solo settings, NO el dominio de texto)
    if [[ -f "$work_dir"/*.php ]]; then
        local main_file=$(find "$work_dir" -maxdepth 1 -name "*.php" -type f | head -1)
        if [[ -n "$main_file" ]]; then
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s/woocommerce_placetopay_settings/woocommerce_${client_id}_settings/g" "$main_file"
                # NO reemplazar el dominio de texto, se mantiene como 'woocommerce-gateway-translations'
                sed -i '' "s/placetopay/${client_id}/g" "$main_file"
            else
                sed -i "s/woocommerce_placetopay_settings/woocommerce_${client_id}_settings/g" "$main_file"
                # NO reemplazar el dominio de texto, se mantiene como 'woocommerce-gateway-translations'
                sed -i "s/placetopay/${client_id}/g" "$main_file"
            fi
        fi
    fi

    # Reemplazar también en archivos PHP de src (solo settings, NO el dominio de texto)
    find "$work_dir/src" -type f -name "*.php" -exec sed -i.bak "s/woocommerce_placetopay_settings/woocommerce_${client_id}_settings/g" {} \;
    find "$work_dir/src" -type f -name "*.bak" -delete

    # Limpiar archivos .bak
    find "$work_dir/src" -type f -name "*.bak" -delete
}

# Función para renombrar archivos de cron
rename_cron_files() {
    local work_dir="$1"
    local client_id="$2"
    local namespace_name
    namespace_name=$(get_namespace_name "$client_id")

    print_status "Renombrando archivos de cron para: $client_id"

    # Renombrar ProcessPendingOrderCron.php
    if [[ -f "$work_dir/cron/ProcessPendingOrderCron.php" ]]; then
        mv "$work_dir/cron/ProcessPendingOrderCron.php" "$work_dir/cron/ProcessPendingOrderCron${namespace_name}.php"

        # Actualizar referencias en el archivo renombrado
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s/use PlacetoPay\\\\PaymentMethod\\\\GatewayMethod;/use ${namespace_name}\\\\PaymentMethod\\\\GatewayMethod${namespace_name};/g" "$work_dir/cron/ProcessPendingOrderCron${namespace_name}.php"
            sed -i '' "s/GatewayMethod::/GatewayMethod${namespace_name}::/g" "$work_dir/cron/ProcessPendingOrderCron${namespace_name}.php"
        else
            sed -i "s/use PlacetoPay\\\\PaymentMethod\\\\GatewayMethod;/use ${namespace_name}\\\\PaymentMethod\\\\GatewayMethod${namespace_name};/g" "$work_dir/cron/ProcessPendingOrderCron${namespace_name}.php"
            sed -i "s/GatewayMethod::/GatewayMethod${namespace_name}::/g" "$work_dir/cron/ProcessPendingOrderCron${namespace_name}.php"
        fi
    fi

    # Actualizar referencia en GatewayMethod.php
    if [[ -f "$work_dir/src/GatewayMethod${namespace_name}.php" ]]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s|ProcessPendingOrderCron\.php|ProcessPendingOrderCron${namespace_name}.php|g" "$work_dir/src/GatewayMethod${namespace_name}.php"
            sed -i '' "s|woocommerce-gateway-translations/cron|woocommerce-gateway-${client_id}/cron|g" "$work_dir/src/GatewayMethod${namespace_name}.php"
        else
            sed -i "s|ProcessPendingOrderCron\.php|ProcessPendingOrderCron${namespace_name}.php|g" "$work_dir/src/GatewayMethod${namespace_name}.php"
            sed -i "s|woocommerce-gateway-translations/cron|woocommerce-gateway-${client_id}/cron|g" "$work_dir/src/GatewayMethod${namespace_name}.php"
        fi
    fi
}

# Función para asegurar que los archivos de traducciones estén compilados
# WordPress solo carga archivos .mo (binarios); los .po son solo fuente.
# Todos los plugins usan el mismo dominio 'woocommerce-gateway-translations'
ensure_translation_files() {
    local work_dir="$1"

    print_status "Compilando archivos de traducciones..."

    mkdir -p "${work_dir}/languages"

    # Copiar .mo existentes del repo al work_dir (por si no hay msgfmt o faltan .po)
    if [[ -d "${BASE_DIR}/languages" ]]; then
        for base_mo in "${BASE_DIR}"/languages/woocommerce-gateway-translations-*.mo; do
            if [[ -f "$base_mo" ]]; then
                cp "$base_mo" "${work_dir}/languages/$(basename "$base_mo")" 2>/dev/null || true
            fi
        done
    fi

    # Compilar cada .po a .mo (sobrescribe .mo si msgfmt está disponible)
    local found_po=0
    for po_file in "${work_dir}"/languages/woocommerce-gateway-translations-*.po; do
        if [[ -f "$po_file" ]]; then
            found_po=1
            local mo_file="${po_file%.po}.mo"

            # Compilar .po a .mo si msgfmt está disponible
            if command -v msgfmt >/dev/null 2>&1; then
                print_status "Compilando: $(basename "$po_file") -> $(basename "$mo_file")"
                msgfmt -o "$mo_file" "$po_file" 2>/dev/null || print_warning "No se pudo compilar $(basename "$po_file")"
            else
                print_warning "msgfmt no está disponible. Verificando si existe .mo..."
                # Si no existe el .mo, intentar copiarlo del original
                if [[ ! -f "$mo_file" ]]; then
                    local base_mo="${BASE_DIR}/languages/$(basename "$mo_file")"
                    if [[ -f "$base_mo" ]]; then
                        cp "$base_mo" "$mo_file"
                        print_status "Copiado: $(basename "$mo_file")"
                    else
                        print_warning "No se encontró $(basename "$mo_file") y msgfmt no está disponible"
                    fi
                fi
            fi
        fi
    done

    if [[ "$found_po" -eq 0 ]]; then
        print_warning "No se encontraron archivos .po en languages/"
        # Copiar archivos de traducciones desde el directorio base si no existen
        if [[ -d "${BASE_DIR}/languages" ]]; then
            print_status "Copiando archivos de traducciones desde el directorio base..."
            cp -r "${BASE_DIR}"/languages/* "${work_dir}/languages/" 2>/dev/null || true
        fi
    fi
}

# Función para renombrar archivos de traducciones según el cliente (NO USADA - MANTENIDA POR COMPATIBILIDAD)
# Estrategia: Usa es_ES como "molde maestro" y genera automáticamente variantes por país
rename_translation_files() {
    local work_dir="$1"
    local client_id="$2"
    local text_domain="woocommerce-gateway-${client_id}"

    # 1. Detectar si el país destino habla español
    # Usamos COUNTRY_CODE que ya está disponible desde parse_config
    local target_locale=""
    if [[ -n "$COUNTRY_CODE" ]]; then
        target_locale="es_${COUNTRY_CODE}" # Ej: es_CL, es_CO, es_UY
    fi

    print_status "Procesando traducciones para: $client_id (Locale objetivo: $target_locale)"

    # 2. Encontrar el archivo maestro de español (es_ES)
    local base_po="${work_dir}/languages/woocommerce-gateway-translations-es_ES.po"

    # Si no existe exactamente ese, buscamos cualquiera que empiece por es_
    if [[ ! -f "$base_po" ]]; then
        base_po=$(find "${work_dir}/languages" -name "woocommerce-gateway-translations-es_*.po" | head -n 1)
    fi

    # 3. La Magia: Duplicación Automática ("Usa el general")
    if [[ -f "$base_po" && -n "$target_locale" ]]; then
        local target_po_legacy="${work_dir}/languages/woocommerce-gateway-translations-${target_locale}.po"

        # Si NO existe el archivo específico (ej: es_CL), creamos una copia del es_ES
        if [[ ! -f "$target_po_legacy" ]]; then
            print_status "Generando traducción para $target_locale basada en el Español General..."
            cp "$base_po" "$target_po_legacy"
            # Actualizar el locale en el header del archivo .po
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s/Language: es_ES/Language: ${target_locale}/" "$target_po_legacy"
            else
                sed -i "s/Language: es_ES/Language: ${target_locale}/" "$target_po_legacy"
            fi
        fi
    fi

    # 4. Renombrado y Compilación Masiva
    # Ahora procesamos todos los archivos .po que existan (originales + el que acabamos de clonar)
    local found_files=0
    for po_file in "${work_dir}"/languages/woocommerce-gateway-translations-*.po; do
        if [[ -f "$po_file" ]]; then
            found_files=1
            # Extraer el locale del nombre actual (ej: es_CL, es_ES, en_US)
            local locale=$(basename "$po_file" | sed 's/woocommerce-gateway-translations-\(.*\)\.po/\1/')

            local new_po="${work_dir}/languages/${text_domain}-${locale}.po"
            local new_mo="${work_dir}/languages/${text_domain}-${locale}.mo"

            # A. Mover archivo al nuevo nombre (con el nuevo text-domain)
            mv "$po_file" "$new_po"

            # B. Actualizar el Text Domain dentro del archivo .po (Header)
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s/woocommerce-gateway-translations/${text_domain}/g" "$new_po"
            else
                sed -i "s/woocommerce-gateway-translations/${text_domain}/g" "$new_po"
            fi

            # C. Compilar a binario .mo (Vital para WordPress)
            if command -v msgfmt >/dev/null 2>&1; then
                print_status "Compilando traducciones: ${new_po} -> ${new_mo}"
                msgfmt -o "$new_mo" "$new_po" 2>/dev/null || print_warning "No se pudo compilar ${new_po}"
            else
                # Fallback de emergencia: Copiar el .mo original si existía
                local old_mo="${po_file%.po}.mo"
                if [[ -f "$old_mo" ]]; then
                    cp "$old_mo" "$new_mo"
                    print_warning "msgfmt no instalado. Se copió el .mo antiguo."
                else
                    print_warning "No se pudo generar .mo para $locale (falta msgfmt)"
                fi
            fi
        fi
    done

    # Limpieza final de archivos viejos
    rm -f "${work_dir}"/languages/woocommerce-gateway-translations-*.mo
    rm -f "${work_dir}"/languages/woocommerce-gateway-translations-*.po

    if [[ "$found_files" -eq 0 ]]; then
        print_warning "No se encontraron archivos de traducción base (.po)"
        # Crear directorio si no existe
        mkdir -p "${work_dir}/languages"
        # Crear un archivo .po básico
        cat > "${work_dir}/languages/${text_domain}-es_ES.po" << 'POEOF'
msgid ""
msgstr ""
"Project-Id-Version: WooCommerce Gateway\n"
"Language: es_ES\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
POEOF
    fi
}

# Función para reemplazar el dominio de texto en todos los archivos PHP
replace_text_domain() {
    local work_dir="$1"
    local client_id="$2"
    local old_domain="woocommerce-gateway-translations"
    local new_domain="woocommerce-gateway-${client_id}"

    print_status "Reemplazando dominio de texto: $old_domain -> $new_domain"

    # Usar un enfoque más simple y robusto: reemplazar todas las ocurrencias del dominio
    # en contextos donde sabemos que es seguro (dentro de comillas)
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # Reemplazar 'woocommerce-gateway-translations' con comillas simples
        find "$work_dir" -type f -name "*.php" -exec sed -i '' "s/'${old_domain}'/'${new_domain}'/g" {} \;
        # Reemplazar "woocommerce-gateway-translations" con comillas dobles
        find "$work_dir" -type f -name "*.php" -exec sed -i '' "s/\"${old_domain}\"/\"${new_domain}\"/g" {} \;
    else
        # Reemplazar 'woocommerce-gateway-translations' con comillas simples
        find "$work_dir" -type f -name "*.php" -exec sed -i "s/'${old_domain}'/'${new_domain}'/g" {} \;
        # Reemplazar "woocommerce-gateway-translations" con comillas dobles
        find "$work_dir" -type f -name "*.php" -exec sed -i "s/\"${old_domain}\"/\"${new_domain}\"/g" {} \;
    fi

    # Verificar que el reemplazo funcionó (solo para debug)
    if [[ "$DEBUG" == "1" ]]; then
        local remaining=$(grep -r "'${old_domain}'" "$work_dir" --include="*.php" 2>/dev/null | wc -l | tr -d ' ')
        if [[ "$remaining" -gt 0 ]]; then
            print_warning "Aún quedan $remaining ocurrencias del dominio antiguo"
        else
            print_status "✓ Todas las ocurrencias del dominio fueron reemplazadas"
        fi
    fi
}

# Función para renombrar el archivo JavaScript del checkout
rename_checkout_js() {
    local work_dir="$1"
    local client_id="$2"
    local namespace_name
    namespace_name=$(get_namespace_name "$client_id")

    print_status "Renombrando archivo checkout.js para cliente: $client_id"

    # Renombrar checkout.js a checkout_{client_id}.js
    if [[ -f "$work_dir/block/checkout.js" ]]; then
        # Primero inyectar el client_id en el código JavaScript para identificación única
        # Esto asegura que cada script se identifique correctamente incluso si WordPress los deduplica
        local js_file="$work_dir/block/checkout_${client_id}.js"
        cp "$work_dir/block/checkout.js" "$js_file"

        # Agregar un comentario único al inicio del archivo con el client_id
        # Esto hace que cada archivo tenga contenido único y evita deduplicación
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            sed -i '' "1s|^|// CLIENT_ID: ${client_id}\n|" "$js_file"
            # También reemplazar el log genérico con uno específico del cliente
            sed -i '' "s|Script iniciado - buscando datos del gateway|Script iniciado para ${client_id} - buscando datos del gateway|g" "$js_file"
        else
            # Linux
            sed -i "1s|^|// CLIENT_ID: ${client_id}\n|" "$js_file"
            sed -i "s|Script iniciado - buscando datos del gateway|Script iniciado para ${client_id} - buscando datos del gateway|g" "$js_file"
        fi

        # Eliminar el archivo original
        rm -f "$work_dir/block/checkout.js"

        # Actualizar referencias en GatewayMethodBlocks.php
        if [[ -f "$work_dir/src/GatewayMethodBlocks${namespace_name}.php" ]]; then
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s|'\.\./block/checkout\.js'|\"../block/checkout_${client_id}.js\"|g" "$work_dir/src/GatewayMethodBlocks${namespace_name}.php"
                sed -i '' "s|\"\.\./block/checkout\.js\"|\"../block/checkout_${client_id}.js\"|g" "$work_dir/src/GatewayMethodBlocks${namespace_name}.php"
            else
                sed -i "s|'\.\./block/checkout\.js'|\"../block/checkout_${client_id}.js\"|g" "$work_dir/src/GatewayMethodBlocks${namespace_name}.php"
                sed -i "s|\"\.\./block/checkout\.js\"|\"../block/checkout_${client_id}.js\"|g" "$work_dir/src/GatewayMethodBlocks${namespace_name}.php"
            fi
        fi
    fi
}

# Función para crear CountryConfig.php copiando el template
create_country_config() {
    local target_file="$1"
    local client_key="$2"

    # Obtener nombre de la clase de configuración
    local config_class_name
    config_class_name=$(get_config_class_name "$client_key")

    # Determinar qué template usar
    local template_file=""

    # Si tenemos un template específico, usarlo
    if [[ -n "$config_class_name" ]]; then
        template_file="${BASE_DIR}/config/templates/${config_class_name}.php"
    fi

    # Verificar que el archivo existe
    if [[ -z "$template_file" || ! -f "$template_file" ]]; then
        print_error "Template no encontrado para cliente: $client_key"
        print_error "Archivo esperado: ${BASE_DIR}/config/templates/${config_class_name}.php"
        return 1
    fi

    print_status "Copiando template: $config_class_name"

    # Copiar el template
    cp "$template_file" "$target_file"
}

# Función para instalar dependencias con una versión específica de PHP (siguiendo Makefile de WooCommerce)
install_composer_dependencies() {
    local work_dir="$1"
    local php_version="$2"

    print_status "Instalando dependencias de Composer con PHP $php_version..."

    # Actualizar la versión de PHP en composer.json (siguiendo línea 19 del Makefile de WooCommerce)
    local composer_file="$work_dir/composer.json"
    if [[ -f "$composer_file" ]]; then
        # Actualizar versión de PHP usando sed (compatible con macOS y Linux)
        # WooCommerce usa: sed -i 's/"php": ".*"/"php": "^$(PHP_VERSION)"/'
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS usa -i '' para sed
            sed -i '' 's/"php": ".*"/"php": "^'"${php_version}"'"/' "$composer_file"
        else
            # Linux usa -i sin argumento
            sed -i 's/"php": ".*"/"php": "^'"${php_version}"'"/' "$composer_file"
        fi
    fi

    # Eliminar composer.lock si existe (línea 20 del Makefile)
    rm -rf "$work_dir/composer.lock"

    # Instalar dependencias con la versión específica de PHP (línea 21 del Makefile)
    cd "$work_dir"

    hash=`head -c 32 /dev/urandom | md5sum | awk '{print $1}'`

    # Actualizar el nombre del paquete en composer.json para evitar conflictos de autoloader
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s/prestashop-gateway/prestashop-gateway-$hash/g" "$work_dir/composer.json"
    else
        sed -i "s/prestashop-gateway/prestashop-gateway-$hash/g" "$work_dir/composer.json"
    fi

    # Verificar si existe el comando php con la versión específica
    if command -v "php${php_version}" >/dev/null 2>&1; then
        print_status "Usando php${php_version} para instalar dependencias..."
        php${php_version} "$(which composer)" install --no-dev 2>&1 | grep -v "^$" || true
    else
        print_warning "php${php_version} no encontrado, usando php por defecto..."
        php "$(which composer)" install --no-dev 2>&1 | grep -v "^$" || true
    fi

    # Evitar conflictos de spl_autoload
    sed -i -E "s/ComposerAutoloaderInit([a-zA-Z0-9])/ComposerAutoloaderInit$hash/g" $work_dir/vendor/composer/autoload_real.php
    sed -i -E "s/ComposerStaticInit([a-zA-Z0-9])/ComposerStaticInit$hash/g" $work_dir/vendor/composer/autoload_real.php
    sed -i -E "s/'ComposerStaticInit([a-zA-Z0-9])'/'ComposerStaticInit$hash'/g" $work_dir/vendor/composer/autoload_real.php

    sed -i -E "s/ComposerAutoloaderInit([a-zA-Z0-9])/ComposerAutoloaderInit$hash/g" $work_dir/vendor/composer/autoload_static.php
    sed -i -E "s/ComposerStaticInit([a-zA-Z0-9])/ComposerStaticInit$hash/g" $work_dir/vendor/composer/autoload_static.php

    sed -i -E "s/ComposerAutoloaderInit([a-zA-Z0-9])/ComposerAutoloaderInit$hash/g" $work_dir/vendor/autoload.php

    cd "$BASE_DIR"
}

# Función para limpiar archivos innecesarios del build (siguiendo Makefile de WooCommerce líneas 22-42)
cleanup_build_files() {
    local work_dir="$1"

    print_status "Limpiando archivos de desarrollo innecesarios..."

    # Eliminar directorios .git* y squizlabs (usando find como en el Makefile líneas 22-23)
    find "$work_dir" -type d -name ".git*" -exec rm -rf {} + 2>/dev/null || true
    find "$work_dir" -type d -name "squizlabs" -exec rm -rf {} + 2>/dev/null || true

    # Eliminar .git* (línea 24)
    rm -rf "$work_dir/.git"*

    # Eliminar según el Makefile de WooCommerce (líneas 25-33)
    rm -rf "$work_dir/.idea"
    rm -rf "$work_dir/tmp"
    rm -rf "$work_dir/Dockerfile"
    rm -rf "$work_dir/Makefile"
    rm -rf "$work_dir/.env"*
    rm -rf "$work_dir/docker"*
    rm -rf "$work_dir/composer."*
    rm -rf "$work_dir/.php_cs.cache"
    rm -rf "$work_dir"/*.md

    # Limpiar builds, config y scripts (adicionales para white label)
    rm -rf "$work_dir/builds"
    rm -rf "$work_dir/temp_builds"
    rm -rf "$work_dir/config"*
    rm -rf "$work_dir"/*.sh

    # Limpiar archivos de desarrollo adicionales
    rm -Rf "$work_dir/.phpactor.json"
    rm -Rf "$work_dir/.php-cs-fixer.cache"
    rm -Rf "$work_dir/.vimrc.setup"
    rm -Rf "$work_dir/.hasts"
    rm -Rf "$work_dir/.hasaia"
    rm -Rf "$work_dir/"*.sql
    rm -Rf "$work_dir/"*.log
    rm -Rf "$work_dir/"*.diff

    # Limpiar vendor según el Makefile de WooCommerce (líneas 34-42)
    if [[ -d "$work_dir/vendor" ]]; then
        print_status "Limpiando vendor de archivos innecesarios..."

        rm -rf "$work_dir/vendor/bin"
        rm -rf "$work_dir/vendor/dnetix/redirection/tests"
        rm -rf "$work_dir/vendor/dnetix/redirection/examples"
        rm -rf "$work_dir/vendor/guzzlehttp/guzzle/docs"
        rm -rf "$work_dir/vendor/guzzlehttp/guzzle/tests"
        rm -rf "$work_dir/vendor/guzzlehttp/streams/tests"
        rm -rf "$work_dir/vendor/symfony/var-dumper"
        rm -rf "$work_dir/vendor/symfony/polyfill-"*
        rm -rf "$work_dir/vendor/larapack/dd"
    fi
}

# Función para actualizar el archivo principal del plugin
update_main_plugin_file() {
    local target_file="$1"
    local client="$2"
    local project_name="$3"
    local client_id="${4:-}"
    local namespace_name="${5:-}"
    local client_uri="${6:-}"
    local plugin_version="${7:-}"

    # El namespace_name es el mismo que el nombre de clase
    local class_name="$namespace_name"

    # Crear archivo principal del plugin actualizado
    cat > "$target_file" < woocommerce-gateway-placetopay.php

    # Reemplazar placeholders (compatible con macOS y Linux)
    # IMPORTANTE: Reemplazar CLIENTNAMESPACE antes que CLIENTNAME para evitar conflictos
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS usa -i '' para sed
        # Reemplazar CLIENTNAMESPACE con patrón específico para evitar reemplazos parciales
        # Primero reemplazar en contextos de namespace (con backslashes)
        sed -i '' "s|\\\\CLIENTNAMESPACE\\\\|\\\\$namespace_name\\\\|g" "$target_file"
        sed -i '' "s|CLIENTNAMESPACE\\\\|$namespace_name\\\\|g" "$target_file"
        # Luego reemplazar cualquier ocurrencia restante de CLIENTNAMESPACE (sin backslashes)
        sed -i '' "s/CLIENTNAMESPACE/$namespace_name/g" "$target_file"
        # Ahora reemplazar los demás placeholders
        sed -i '' "s/CLIENTNAME/$client/g" "$target_file"
        sed -i '' "s/CLIENTID/$client_id/g" "$target_file"
        sed -i '' "s|CLIENTURI|$client_uri|g" "$target_file"
        sed -i '' "s/PLUGINVERSION/$plugin_version/g" "$target_file"
        sed -i '' "s/CLIENTCLASSNAME/$class_name/g" "$target_file"
    else
        # Linux usa -i sin argumento
        # Reemplazar CLIENTNAMESPACE con patrón específico para evitar reemplazos parciales
        # Primero reemplazar en contextos de namespace (con backslashes)
        sed -i "s|\\\\CLIENTNAMESPACE\\\\|\\\\$namespace_name\\\\|g" "$target_file"
        sed -i "s|CLIENTNAMESPACE\\\\|$namespace_name\\\\|g" "$target_file"
        # Luego reemplazar cualquier ocurrencia restante de CLIENTNAMESPACE (sin backslashes)
        sed -i "s/CLIENTNAMESPACE/$namespace_name/g" "$target_file"
        # Ahora reemplazar los demás placeholders
        sed -i "s/CLIENTNAME/$client/g" "$target_file"
        sed -i "s/CLIENTID/$client_id/g" "$target_file"
        sed -i "s|CLIENTURI|$client_uri|g" "$target_file"
        sed -i "s/PLUGINVERSION/$plugin_version/g" "$target_file"
        sed -i "s/CLIENTCLASSNAME/$class_name/g" "$target_file"
    fi
}

# Función para crear versión de marca blanca con una versión específica de PHP
create_white_label_version_with_php() {
    local client_key="$1"
    local php_version="$2"
    local php_label="$3"
    local plugin_version="$4"
    local config=$(get_client_config "$client_key")

    if [[ -z "$config" ]]; then
        print_error "Cliente desconocido: $client_key"
        return 1
    fi

    # Parsear configuración
    parse_config "$config"

    # Generar CLIENT_ID en formato "cliente-país" (con guión)
    # Si CLIENT_ID ya existe en el template, normalizarlo (eliminar espacios)
    # Si no existe o está vacío, generarlo desde CLIENT y COUNTRY_NAME
    if [[ -n "$CLIENT_ID" ]]; then
        # Normalizar CLIENT_ID existente: convertir espacios a guiones y a minúsculas
        CLIENT_ID=$(echo "$CLIENT_ID" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')
    else
        # Generar CLIENT_ID desde CLIENT y COUNTRY_NAME
        CLIENT_ID=$(get_client_id "$CLIENT" "$COUNTRY_NAME")
    fi

    # Determinar nombre del proyecto usando CLIENT_ID en formato "cliente-país"
    local project_name_base=$(get_project_name "$CLIENT_ID")
    local project_name="${project_name_base}-${plugin_version}-${php_label}"

    print_status "Creando versión de marca blanca: $project_name"
    print_status "Cliente: $CLIENT, País: $COUNTRY_NAME ($COUNTRY_CODE), PHP: $php_version"

    # Crear directorio de trabajo temporal
    local work_dir="$TEMP_DIR/$project_name_base"

    mkdir -p "$work_dir"

    # Copiar todos los archivos excepto builds, temp directories, config y vendor
    # IMPORTANTE: Excluir vendor para forzar una instalación limpia y generar hash único
    # También excluir ResolveTranslations.php y commands/ que no deben estar en el build
    print_status "Copiando archivos fuente..."
    rsync -a \
        --exclude='builds/' \
        --exclude='temp_builds/' \
        --exclude='.git/' \
        --exclude='*.sh' \
        --exclude='/config/' \
        --exclude='/vendor/' \
        --exclude='src/ResolveTranslations.php' \
        --exclude='commands/' \
        "$BASE_DIR/" "$work_dir/" 2>/dev/null || true

    # Copiar template de CountryConfig.php
    print_status "Copiando CountryConfig.php desde template..."
    create_country_config "$work_dir/src/CountryConfig.php" "$client_key"

    # Verificar que tenemos CLIENT_ID
    if [[ -z "$CLIENT_ID" ]]; then
        print_error "CLIENT_ID no encontrado en la configuración del cliente"

        return 1
    fi

    # Obtener nombre del namespace
    local namespace_name=$(get_namespace_name "$CLIENT_ID")

    # Reemplazar namespaces en todos los archivos
    replace_namespaces "$work_dir" "$namespace_name"

    # Reemplazar nombres de clases
    replace_class_names "$work_dir" "$CLIENT_ID"

    # Reemplazar payment method IDs y endpoints
    replace_payment_method_ids "$work_dir" "$CLIENT_ID"

    # Renombrar archivos de cron
    rename_cron_files "$work_dir" "$CLIENT_ID"

    # Renombrar archivo JavaScript del checkout (debe hacerse después de renombrar clases)
    rename_checkout_js "$work_dir" "$CLIENT_ID"

    # Asegurar que los archivos de traducciones estén presentes y compilados
    # Todos los plugins usan el mismo dominio 'woocommerce-gateway-translations' y los mismos archivos
    print_status "Verificando archivos de traducciones..."
    ensure_translation_files "$work_dir"

    # Actualizar archivo principal del plugin
    print_status "Actualizando archivo principal del plugin..."

    # Convertir CLIENT_ID a formato válido para nombres de funciones PHP (reemplazar guiones con guiones bajos)
    local php_function_id=$(get_php_function_id "$CLIENT_ID")

    update_main_plugin_file "$work_dir/${project_name_base}.php" "$CLIENT" "$project_name_base" "$php_function_id" "$namespace_name" "$CLIENT_URI" "$plugin_version"

    # Eliminar archivos originales que no corresponden a este cliente
    # Eliminar woocommerce-gateway-placetopay.php (archivo original del template)
    rm -f "$work_dir/woocommerce-gateway-placetopay.php"

    # Eliminar woocommerce-gateway-translations.php si existe (nombre temporal)
    rm -f "$work_dir/woocommerce-gateway-translations.php"

    # Asegurarse de que solo quede el archivo con el nombre correcto del cliente
    if [[ -f "$work_dir/${project_name_base}.php" ]] && [[ "$project_name_base.php" != "woocommerce-gateway-placetopay.php" ]]; then
        # Verificar que el archivo nuevo existe antes de eliminar otros posibles archivos
        print_status "Archivo principal del plugin creado: ${project_name_base}.php"
    fi

    # Actualizar composer.json con el nuevo namespace y nombre único por cliente
    # IMPORTANTE: El campo "name" debe ser único para generar un hash único del autoloader
    # Esto debe hacerse ANTES de instalar dependencias para que Composer genere un hash único
    print_status "Actualizando composer.json con namespace y nombre único del cliente..."
    if [[ -f "$work_dir/composer.json" ]]; then
        local composer_file="$work_dir/composer.json"

        if [[ "$OSTYPE" == "darwin"* ]]; then
            # Actualizar el namespace en composer.json (necesario para PSR-4)
            sed -i '' "s|\"PlacetoPay\\\\\\\\PaymentMethod\\\\\\\\\":|\"${namespace_name}\\\\\\\\PaymentMethod\\\\\\\\\":|g" "$composer_file"
            # Cambiar el nombre del paquete para que sea único por cliente (genera hash único del autoloader)
            # Usar un patrón más específico para asegurar que coincida
            sed -i '' "s|\"name\": \"placetopay/gateway-method\",|\"name\": \"placetopay/gateway-method-${CLIENT_ID}\",|g" "$composer_file"
        else
            # Actualizar el namespace en composer.json (necesario para PSR-4)
            sed -i "s|\"PlacetoPay\\\\\\\\PaymentMethod\\\\\\\\\":|\"${namespace_name}\\\\\\\\PaymentMethod\\\\\\\\\":|g" "$composer_file"
            # Cambiar el nombre del paquete para que sea único por cliente (genera hash único del autoloader)
            # Usar un patrón más específico para asegurar que coincida
            sed -i "s|\"name\": \"placetopay/gateway-method\",|\"name\": \"placetopay/gateway-method-${CLIENT_ID}\",|g" "$composer_file"
        fi

        # Verificar que el cambio se aplicó correctamente
        if grep -q "\"name\": \"placetopay/gateway-method-${CLIENT_ID}\"" "$composer_file"; then
            print_status "✓ composer.json actualizado correctamente con nombre único: placetopay/gateway-method-${CLIENT_ID}"
        else
            print_warning "⚠ No se pudo verificar el cambio en composer.json. Verificando contenido..."

            if [[ "$DEBUG" == "1" ]]; then
                grep "\"name\":" "$composer_file" | head -1
            fi
        fi
    fi

    # Instalar dependencias de Composer con la versión específica de PHP
    install_composer_dependencies "$work_dir" "$php_version"

    # Regenerar autoloader después de renombrar archivos para que encuentre las clases con nuevos namespaces
    print_status "Regenerando autoloader de Composer..."
    cd "$work_dir"
    if command -v "php${php_version}" >/dev/null 2>&1; then
        php${php_version} "$(which composer)" dump-autoload --no-dev --optimize 2>&1 | grep -v "^$" || true
    else
        php "$(which composer)" dump-autoload --no-dev --optimize 2>&1 | grep -v "^$" || true
    fi
    cd "$BASE_DIR"

    # Limpiar archivos innecesarios
    cleanup_build_files "$work_dir"

    # Crear archivo ZIP
    print_status "Creando archivo ZIP..."
    mkdir -p "$OUTPUT_DIR"
    cd "$TEMP_DIR"
    zip -rq "$OUTPUT_DIR/$project_name.zip" "$project_name_base"
    cd "$BASE_DIR"

    # Limpiar directorio temporal de este build
    rm -rf "$work_dir"

    print_success "Creado: $OUTPUT_DIR/$project_name.zip"
}

# Función para crear todas las versiones de marca blanca para un cliente
create_white_label_version() {
    local client_key="$1"
    local plugin_version="$2"

    print_status "========================================="
    print_status "Procesando cliente: $client_key"
    print_status "========================================="
    echo

    # Generar una versión para cada versión de PHP
    local i=0
    for php_version in "${PHP_VERSIONS[@]}"; do
        local php_label="${PHP_VERSION_LABELS[$i]}"
        create_white_label_version_with_php "$client_key" "$php_version" "$php_label" "$plugin_version"
        echo
        i=$((i + 1))
    done
}


# Función principal
main() {
    local plugin_version="$1"
    print_status "Iniciando proceso de generación de marca blanca..."

    # Verificar que existe el archivo de configuración
    if [[ ! -f "$CONFIG_FILE" ]]; then
        print_error "Archivo de configuración no encontrado: $CONFIG_FILE"
        print_error "Por favor asegúrate de que el archivo config/clients.php existe."
        exit 1
    fi

    # Limpiar builds anteriores
    print_status "Limpiando builds anteriores..."
    rm -rf "$TEMP_DIR" "$OUTPUT_DIR"
    mkdir -p "$TEMP_DIR" "$OUTPUT_DIR"

    # Procesar cada configuración de cliente
    for client_key in $(get_all_clients); do
        create_white_label_version "$client_key" "$plugin_version"

        echo
    done

    # Limpiar directorio temporal
    print_status "Limpiando archivos temporales..."
    rm -rf "$TEMP_DIR"

    print_success "¡Generación de marca blanca completada!"
    print_status "Los archivos generados están en: $OUTPUT_DIR"

    # Listar archivos generados
    echo
    print_status "Versiones de marca blanca generadas:"
    ls -la "$OUTPUT_DIR"/*.zip 2>/dev/null | while read -r line; do
        echo "  $line"
    done || print_warning "No se encontraron archivos ZIP en el directorio de salida: $OUTPUT_DIR"
}

# Mostrar información de uso
usage() {
    echo "Uso: $0 [OPCIONES] [CLIENTE] [VERSION]"
    echo ""
    echo "Generar versiones de marca blanca del plugin WooCommerce PlacetoPay"
    echo ""
    echo "Opciones:"
    echo "  -h, --help    Mostrar este mensaje de ayuda"
    echo "  -l, --list    Listar configuraciones de clientes disponibles"
    echo "  CLIENTE       Generar solo para un cliente específico (opcional)"
    echo "  VERSION       Generar .zip para cargar en GitHub tag (opcional)"
    echo ""
    echo "Clientes disponibles:"
    for client in $(get_all_clients); do
        config=$(get_client_config "$client")

        if [[ -n "$config" ]]; then
            parse_config "$config"
            echo "  - $client: $CLIENT ($COUNTRY_NAME - $COUNTRY_CODE)"
        fi
    done
}

# Manejar argumentos de línea de comandos
case "${1:-}" in
    -h|--help)
        usage
        exit 0
        ;;
    -l|--list)
        echo "Configuraciones de clientes disponibles:"
        for client_key in $(get_all_clients); do
            config=$(get_client_config "$client_key")
            if [[ -n "$config" ]]; then
                parse_config "$config"
                echo "  $client_key: $CLIENT ($COUNTRY_NAME - $COUNTRY_CODE)"
            fi
        done
        exit 0
        ;;
    "")
        main
        ;;
    *)
        if [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            main "$1"
        else
            # Verificar si es un cliente válido
            config=$(get_client_config "$1")

            if [[ -n "$config" ]]; then
                print_status "Generando versión de marca blanca para: $1"
                rm -rf "$TEMP_DIR" "$OUTPUT_DIR"
                mkdir -p "$TEMP_DIR" "$OUTPUT_DIR"

                create_white_label_version "$1" "${2-untagged}"

                rm -rf "$TEMP_DIR"
                print_success "¡Generación de marca blanca completada para $1!"
            else
                print_error "Opción desconocida: $1"
                echo ""
                usage

                exit 1
            fi
        fi
        ;;
esac
