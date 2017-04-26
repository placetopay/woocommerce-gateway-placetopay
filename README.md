# Gateway PlacetoPay for Woocommerce
Un plugin para agregar un nuevo método de pago a tu tienda por PlacetoPay.

## Requerimientos
- WordPress >= 4.4.1
- WooCommerce >= 2.6.0
- php >= 5.5
- php Soap extensión

## Install in production environment
Run `composer install --no-dev`

## Lenguajes soportados
- Español Colombia (es_CO)
- Español (es)
- Inglés (en)


#### Nota: El erchivo de log para el plugin se encuentra en la ruta
> wp-content/uploads/wc-logs/PlacetoPay-xxx.log

#### Nota: La ruta para encontrar el archivo cron es:
> wp-content/plugins/woocommerce-gateway-placetopay/cron/ProcessPendingOrderCron.php