# Gateway PlacetoPay for Woocommerce
Un plugin para agregar un nuevo método de pago a tu tienda por PlacetoPay.

## Requerimientos
- WordPress >= 4.4.1
- WooCommerce >= 2.6.0
- php >= 5.6
- php Soap extensión

## Compatibility Version

| Wordpress | WooCommerce     | PHP         | Plugin    |
|-----------|-----------------|-------------|-----------|
| 4.4.x     | ~2.6.0          | >=5.2 <=7.0 | <=2.18.x  |
| 4.5.x     | >=2.6.4         | >=5.2 <=7.0 | <=2.18.x  |
| 4.6.x     | >=2.6.4         | >=5.2 <=7.0 | <=2.18.x  |
| 4.7.x     | 3.6.x           | >=5.2 <=7.1 | <=2.18.x  |
| 4.8.x     | ~3.6.x          | >=5.2 <=7.1 | <=2.18.x  |
| 4.9.x     | 3.8.x           | >=5.6 <=7.2 | <=2.18.x  |
| 5.0.x     | >=3.9.x <=4.0.x | >=7.0       | >=2.18.x  |
| 5.1.x     | 4.3.x           | >=7.0       | >=2.18.x  |
| 5.2.x     | ~4.3.x          | >=7.0       | >=2.18.x  |
| 5.3.x     | >=4.5.x <=4.9.x | >=7.0       | >=2.18.x  |
| 5.4.x     | 5.0.x           | >=7.0       | >=2.18.x  |
| 5.5.x     | ~5.0.x          | >=7.0       | >=2.18.x  |
| 5.6.x     | >=5.3.x <=6.1.x | >=7.0       | >=2.18.x  |
| 5.7.x     | >=6.2.x <=6.5.x | >=7.0       | >=2.18.x  |
| 5.8.x     | 6.6.x           | >=7.2       | >=2.18.x  |
| 5.9.x     | ~6.6.x          | >=7.2       | >=2.18.x  |
| 6.0.x     | ~6.6.x          | >=7.2       | >=2.18.x  |

## Install in production environment
Run `composer install --no-dev`

## Idiomas soportados
- Español Colombia (es_CO)
- Español (es)
- Inglés (en)


#### Paths de archivos útiles
Log para el plugin se encuentra en la ruta.
_Solo cuando estas en entorno de desarrollo o pruebas_
> wp-content/uploads/wc-logs/PlacetoPay-xxx.log

La ruta para encontrar el archivo cron es:
> wp-content/plugins/woocommerce-gateway-placetopay/cron/ProcessPendingOrderCron.php


#### Ejemplo de peticion para el notification url:

``Nota: Solo es posible si estas en ambiente de pruebas o desarrollo``

1. Primero haces una compra de ejemplo
2. En la plataforma de placetopay te copias el número de la sesión y el de la orden
3. Pegas en el **requestId** y **reference** respectivamente
4. Ejecutas la petición y te responderá con un código es el signature (la firma)
5. Usala para ejecutar nuevamente la petición y así conseguir simular el proceso

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

## Start with Docker
### Requirements
- docker 17.04.0+
- docker-compose 1.17.0

### Running with docker directly
```
> docker-compose up -d
> docker exec -u 1000:1000 -it wp_plugin_wordpress composer install -d ./wp-content/plugins/woocommerce-gateway-placetopay
```

### If support for Makefile exists

```
> make install
```
> The container listen in port 6969: `http://127.0.0.1:6969/`

### Admin Backend

```
http://127.0.0.1:6969/wp-login.php
```
