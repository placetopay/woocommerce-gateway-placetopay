# Gateway PlacetoPay for Woocommerce
Un plugin para agregar un nuevo método de pago a tu tienda por PlacetoPay.

## Version 2.6.3

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


Ejemplo de peticion para el notification url:

```rest
Request: http://mi-sitio.com//wp-json/placetopay-payment/v2/callback/
Method: POST
{
  "status": {
    "status": "APPROVED",
    "message": "Se ha aprobado su pago",
    "reason": "00",
    "date": "2016-09-15T13:49:01-05:00"
  },
  "requestId": 58,
  "reference": "ORDER-1000",
  "signature": "feb3e7cc76939c346f9640573a208662f30704ab"
}

```
