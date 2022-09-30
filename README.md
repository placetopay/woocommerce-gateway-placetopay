# Woocommerce Gateway to PlacetoPay

[PlacetoPay](https://www.placetopay.com) Plugin Payment for [WooCommerce](https://woocommerce.com/)

## Prerequisites

- `wordpress` >= 4.4.1 _recommended: >= 6.0.1_
- `woocommerce` >= 2.6.0 _recommended: >= 6.7.0_
- `php` >= 5.6 _recommended: >= 7.4_
- `ext-soap`

## Compatibility Version

| Wordpress | WooCommerce     | PHP         | Plugin   | Comments      |
|-----------|-----------------|-------------|----------|---------------|
| 4.4.x     | ~2.6.0          | >=5.2 <=7.0 | <=2.18.x | `@unmanteined` |
| 4.5.x     | >=2.6.4         | >=5.2 <=7.0 | <=2.18.x | `@unmanteined` |
| 4.6.x     | >=2.6.4         | >=5.2 <=7.0 | <=2.18.x | `@unmanteined` |
| 4.7.x     | 3.6.x           | >=5.2 <=7.1 | <=2.18.x | `@unmanteined` |
| 4.8.x     | ~3.6.x          | >=5.2 <=7.1 | <=2.18.x | `@unmanteined` |
| 4.9.x     | 3.8.x           | >=5.6 <=7.2 | <=2.18.x | `@unmanteined` |
| 5.0.x     | >=3.9.x <=4.0.x | >=7.0       | >=2.18.x | `@unmanteined` |
| 5.1.x     | 4.3.x           | >=7.0       | >=2.18.x | `@unmanteined` |
| 5.2.x     | ~4.3.x          | >=7.0       | >=2.18.x | `@unmanteined` |
| 5.3.x     | >=4.5.x <=4.9.x | >=7.0       | >=2.18.x | `@deprecated`  |
| 5.4.x     | 5.0.x           | >=7.0       | >=2.18.x | `@deprecated`  |
| 5.5.x     | ~5.0.x          | >=7.0       | >=2.18.x | `@deprecated`  |
| 5.6.x     | >=5.3.x <=6.1.x | >=7.0       | >=2.18.x | `@deprecated`  |
| 5.7.x     | >=6.2.x <=6.5.x | >=7.0       | >=2.18.x | `@deprecated`  |
| 5.8.x     | 6.6.x           | >=7.2       | >=2.18.x | `@deprecated`  |
| 5.9.x     | ~6.6.x          | >=7.4       | >=2.19.x | `@manteined`   |
| 6.0.x     | ~6.6.x          | >=7.4       | >=2.19.x | `@manteined`   |

## Installation in Production

### Without CLI

Get module .zip from [https://dev.placetopay.com/web/plugins/](https://dev.placetopay.com/web/plugins/) and [see process in WordPress](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation-1)

### With CLI and composer

```bash
composer install --no-dev
```

## Supported Languages

- Español Colombia (es_CO)
- Español (es)
- Inglés (en)

## Installation in Development

Log files path: `wp-content/uploads/wc-logs/PlacetoPay-xxx.log`

> _Only in [debug mode](https://wordpress.org/support/article/debugging-in-wordpress/) enable_

Cron task path: `wp-content/plugins/woocommerce-gateway-placetopay/cron/ProcessPendingOrderCron.php`

### Notification Example

1. Do a purchase
2. Catch session id number and order id number from current purchase
3. Replace **requestId** and **reference**
4. Do request, the response is a signature
5. Replace **signature**
4. Do request, again!

```rest
Request: http://my-store.local/wp-json/placetopay-payment/v2/callback/
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

## With Docker

```bash
docker-compose up -d
docker exec -u 1000:1000 -it wp_plugin_wordpress composer install -d ./wp-content/plugins/woocommerce-gateway-placetopay
```

### If support for Makefile exists

```bash
make install
```
> The container listen in port 6969: `http://127.0.0.1:6969/`
### Admin Backend

```bash
http://127.0.0.1:6969/wp-login.php
```
> The container listen in port 6969: `http://127.0.0.1:6969/`

### Admin Backend

```
http://127.0.0.1:6969/wp-login.php
```

### Compress Plugin As Zip File

In terminal run

```bash
make compile
```

Or adding version number in filename to use

```bash
make compile PLUGIN_VERSION=_X.Y.Z
```

#### Manejo de las traducciones

### Compress Plugin As Zip File

In terminal run

```bash
make compile
```

Or adding version number in filename to use

```bash
make compile PLUGIN_VERSION=-X.Y.Z
```

## Translations

You should add/chage translations in .po files from *languages* directory
after, you need covert this in .mo files to replace current files (languages(*.mo)
> If don't convertion, cannot see any changes
