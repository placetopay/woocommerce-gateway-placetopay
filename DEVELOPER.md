## Install in production environment
Run `composer install --no-dev`

## Start with Docker
### Requirements
- Docker >= v17.04.0+
- docker-compose >= v1.17.0

### Running with docker directly
```
> docker-compose up -d
> docker exec -u 1000:1000 -it wp_plugin_wordpress composer install -d ./wp-content/plugins/woocommerce-gateway-placetopay
```

#### If support for Makefile exists
```
> make install
```
> The container listen in port 6969: `http://127.0.0.1:6969/`

#### Admin Backend
```
http://127.0.0.1:6969/wp-login.php
```


### Useful file paths
Log for the plugin in development or testing environment
> wp-content/uploads/wc-logs/PlacetoPay-xxx.log

Path to the cron file is:
> wp-content/plugins/woocommerce-gateway-placetopay/cron/ProcessPendingOrderCron.php


## Request example for the notification url:

``Note: It is only possible if you are in a test or development environment``

1. Make an example purchase
2. Copy the session number and the purchase order number on the PlacetoPay platform
3. Paste in **requestId** and **reference** respectively
4. Execute the request and it will respond with a code, it is the signature
5. Use the signature to execute the request again and simulate the process

```rest
Request: https://mysite.com/wp-json/placetopay-payment/v2/callback/
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

### Compress Plugin As Zip File

In terminal run
```bash
make compile
```

Or adding version number in filename to use
```bash
make compile PLUGIN_VERSION=_X.Y.Z
```

## Translations

### Supported languages
- Spanish (es_ES)
- Spanish Colombian (es_CO)
- Spanish Costa Rican (es_CR)
- Spanish Chile (es_CL)
- Spanish Puerto Rican (es_PR)
- English (en)

### Conversion
Because WordPress handles languages by regionalization, we must support individual languages.
The changes must be made in the woocommerce-gateway-placetopay-es_ES.po file,
which will apply the changes for the languages
  - es_ES
  - es_CO
  - es_CR
  - es_PR

In addition, the changes must also be supported for woocommerce-gateway-placetopay-es_CL.po
In this way, we handle only two files (woocommerce-gateway-placetopay-es_ES.po,
and woocommerce-gateway-placetopay-es_CL.po) and still support all other languages by localization.

Make the respective changes to the files specified above and run the following command in the root of the project
```
> php -f commands/ResolveTranslationsCommand.php
```
