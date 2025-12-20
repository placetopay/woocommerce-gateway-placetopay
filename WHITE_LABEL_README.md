# White Label Generator para WooCommerce PlacetoPay Gateway

Este sistema permite generar automáticamente versiones de marca blanca del plugin WooCommerce PlacetoPay Gateway para diferentes clientes y países.

## 🚀 Características

- **Configuración centralizada**: Todas las configuraciones de clientes en un archivo PHP fácil de leer
- **Plantillas personalizables**: Soporte para configuraciones y campos específicos por cliente
- **Automatización completa**: Genera ZIPs listos para distribuir
- **Naming automático**: Nombres de proyecto basados en reglas (cliente vs país)
- **Extensible**: Fácil agregar nuevos clientes

## 📁 Estructura del Proyecto

```
├── config/
│   ├── clients.php              # Lista simple de clientes disponibles
│   └── templates/
│       ├── GetnetConfig.php      # Template completo para Getnet
│       ├── UruguayConfig.php     # Template completo para Uruguay
│       ├── EcuadorConfig.php     # Template completo para Ecuador
│       └── ...                   # Otros templates
├── src/
│   └── CountryConfig.php        # Template por defecto (Colombia)
├── builds/                       # ZIPs generados (creado automáticamente)
└── generate-white-label.sh      # Script principal
```

## 🎯 Uso Básico

### Generar todas las versiones
```bash
./generate-white-label.sh
```

### Generar versión específica
```bash
./generate-white-label.sh getnet
./generate-white-label.sh uruguay
./generate-white-label.sh ecuador
./generate-white-label.sh avalpay
```

### Generar versión default/base
```bash
./generate-white-label.sh --default
```

La versión default:
- Usa el `CountryConfig.php` original (Colombia/Placetopay)
- **Excluye** las carpetas de generación: `config/`, `builds/`, `temp_builds/`, `*.sh`
- Es la versión que se comparte/distribuye normalmente
- El proyecto base mantiene las carpetas para generar versiones white-label

### Ver clientes disponibles
```bash
./generate-white-label.sh --list
```

### Ver ayuda
```bash
./generate-white-label.sh --help
```

### Opciones disponibles
- `--default` o `default`: Genera la versión base/default sin carpetas de generación
- `--list` o `-l`: Lista todos los clientes disponibles
- `--help` o `-h`: Muestra la ayuda

## ⚙️ Configuración de Clientes

### Archivo Principal: `config/clients.php`

Este archivo ahora solo contiene una lista simple de clientes disponibles:

```php
<?php
return [
    'ecuador',
    'belice',
    'getnet',
    'honduras',
    'uruguay',
    'avalpay',
    'banchile',
];
```

**Todas las configuraciones** (client name, country code, endpoints, image, etc.) están definidas directamente en los archivos de template.

### Reglas de Naming

- **Si client = "Placetopay"**: `woocommerce-gateway-{country_name_lowercase}`
- **Si client ≠ "Placetopay"**: `woocommerce-gateway-{client_lowercase}`

**Ejemplos:**
- Ecuador (Placetopay) → `woocommerce-gateway-ecuador`
- Chile (Getnet) → `woocommerce-gateway-getnet`
- Uruguay (Placetopay) → `woocommerce-gateway-uruguay`

## 🎨 Plantillas Personalizadas

### Estructura de un Template

Cada template es un archivo PHP completo que contiene la clase `CountryConfig` con todos los valores definidos directamente. Los templates se encuentran en `config/templates/{Cliente}Config.php`.

**Ejemplo: `config/templates/GetnetConfig.php`**

```php
<?php

namespace PlacetoPay\PaymentMethod;

use PlacetoPay\PaymentMethod\Constants\Environment;
use function PlacetoPay\PaymentMethod\Countries\__;
use const PlacetoPay\PaymentMethod\Countries\WP_DEBUG;

abstract class CountryConfig
{
    public const CLIENT = 'Getnet';
    public const IMAGE = 'https://banco.santander.cl/uploads/.../Logo_WebCheckout_Getnet.svg';
    public const COUNTRY_CODE = 'CL';
    public const COUNTRY_NAME = 'Chile';

    public static function getEndpoints(): array
    {
        return [
            Environment::DEV => 'https://checkout-co.placetopay.dev',
            Environment::TEST => 'https://checkout.test.getnet.cl',
            Environment::PROD => 'https://checkout.getnet.cl',
        ];
    }

    public static function getConfiguration(GatewayMethod $gatewayMethod): array
    {
        // Configuración específica del cliente
        return [
            'allow_to_pay_with_pending_orders' => true,
            'allow_partial_payments' => false,
            // ... más configuración
        ];
    }

    public static function getFields(GatewayMethod $gatewayMethod): array
    {
        // Campos específicos del cliente
        $fields = [
            // ... campos personalizados
        ];
        return $fields;
    }
}
```

### Si no existe template específico

Si un cliente no tiene template específico, el script usa automáticamente `src/CountryConfig.php` (configuración por defecto de Colombia).

## 📋 Ejemplos de Configuraciones Existentes

### Getnet (Chile) - Configuración Personalizada Completa
- **Template:** `config/templates/GetnetConfig.php`
- Configuración `getConfiguration` con valores hardcodeados
- Campos `getFields` personalizados (solo campos esenciales)
- Campo `payment_button_image` de solo lectura

### Uruguay - Campos Adicionales
- **Template:** `config/templates/UruguayConfig.php`
- Configuración `getConfiguration` estándar (usa valores del gateway)
- Campos `getFields` con todos los campos estándar + campos adicionales: `discount`, `invoice`

### Otros Países - Configuración Estándar
- **Sin template específico** → usa `src/CountryConfig.php`
- Configuración y campos completamente estándar
- Ejemplos: Ecuador, Belice, Honduras (si no tienen template)

## 🔧 Agregar Nuevo Cliente

### 1. Agregar a la lista en `config/clients.php`

```php
<?php
return [
    'ecuador',
    'belice',
    'getnet',
    'nuevo_cliente',  // ← Agregar aquí
];
```

### 2. Crear template (si necesitas configuración específica)

Crear: `config/templates/{Cliente}Config.php`

**Ejemplo: `config/templates/NuevoClienteConfig.php`**

```php
<?php

namespace PlacetoPay\PaymentMethod;

use PlacetoPay\PaymentMethod\Constants\Environment;
use function PlacetoPay\PaymentMethod\Countries\__;
use const PlacetoPay\PaymentMethod\Countries\WP_DEBUG;

abstract class CountryConfig
{
    public const CLIENT = 'NuevoCliente';
    public const IMAGE = 'https://example.com/logo.svg';
    public const COUNTRY_CODE = 'XX';
    public const COUNTRY_NAME = 'NuevoPais';

    public static function getEndpoints(): array
    {
        return [
            Environment::DEV => 'https://dev.placetopay.dev',
            Environment::TEST => 'https://test.cliente.com',
            Environment::PROD => 'https://api.cliente.com',
        ];
    }

    public static function getConfiguration(GatewayMethod $gatewayMethod): array
    {
        // Usar configuración estándar o personalizada
        return [
            'allow_to_pay_with_pending_orders' => $gatewayMethod->get_option('allow_to_pay_with_pending_orders') === "yes",
            // ... más campos
        ];
    }

    public static function getFields(GatewayMethod $gatewayMethod): array
    {
        // Usar campos estándar o personalizados
        $fields = [
            // ... campos
        ];
        return $fields;
    }
}
```

**Nota:** Puedes copiar `src/CountryConfig.php` como base y modificar solo lo necesario.

### 3. (Opcional) Si no necesitas template personalizado

Si el cliente usa la configuración estándar, no necesitas crear template. El script usará automáticamente `src/CountryConfig.php`.

### 4. Probar

```bash
./generate-white-label.sh nuevo_cliente
```

### Mapeo de nombres

El nombre del template debe seguir el formato: `{Cliente}Config.php` donde `{Cliente}` es:
- Primera letra mayúscula
- Resto en minúsculas (excepto si hay palabras compuestas como `AvalPay`)

**Ejemplos:**
- `getnet` → `GetnetConfig.php`
- `avalpay` → `AvalPayConfig.php`
- `banchile` → `BanchileConfig.php`

## 📦 Archivos Generados

Cada ZIP contiene:
- Código completo del plugin
- `CountryConfig.php` personalizado
- Archivo principal renombrado (`woocommerce-gateway-{nombre}.php`)
- Todas las dependencias y assets

## 🛠️ Troubleshooting

### Error: "Configuration file not found"
- Verifica que existe `config/clients.php`
- Revisa la sintaxis PHP del archivo

### Error: "Unknown client"
- Verifica el nombre del cliente en `config/clients.php`
- Usa `--list` para ver clientes disponibles

### Plantilla no se aplica
- Verifica que el archivo esté en `config/templates/{Cliente}Config.php` (con el formato correcto)
- Revisa la sintaxis PHP del template
- Verifica que el nombre del template coincida con el mapeo en `get_config_class_name()` del script
- Si no existe template, el script usará `src/CountryConfig.php` automáticamente

## 🎯 Casos de Uso

### Cliente con configuración estándar

Solo agregar a `config/clients.php`:
```php
'nuevo_cliente',
```

El script usará automáticamente `src/CountryConfig.php` con valores por defecto.

### Cliente con endpoints diferentes

Crear `config/templates/NuevoClienteConfig.php` y definir los endpoints en `getEndpoints()`:
```php
public static function getEndpoints(): array
{
    return [
        Environment::DEV => 'https://dev.placetopay.dev',
        Environment::TEST => 'https://test.cliente.com',
        Environment::PROD => 'https://gateway.cliente.com',
    ];
}
```

### Cliente con configuración personalizada

Crear template completo con `getConfiguration()` y `getFields()` personalizados según las necesidades del cliente.

## 📝 Notas Técnicas

- El script requiere PHP para leer las configuraciones de los templates
- Se excluyen automáticamente: `builds/`, `temp_builds/`, `.git/`, `config/`
- Los archivos temporales se limpian automáticamente
- Compatible con Bash 3+ (macOS incluido)
- Los valores se leen directamente de los archivos de template usando expresiones regulares
- Si un cliente no tiene template, se usa `src/CountryConfig.php` como fallback

---

¡El sistema está listo para usar y es fácilmente extensible para nuevos clientes! 🎉

