#!/bin/bash

CONTAINER_WP = wp_plugin_wordpress
CONTAINER_DB = wp_plugin_db
CURRENT_FOLDER=$(shell pwd)
UID=$(shell id -u)
MODULE_NAME=woocommerce-gateway-placetopay

up:
	docker-compose up -d

down:
	docker-compose down

rebuild: down
	docker-compose up -d --build

bash:
	docker exec -it $(CONTAINER_WP) bash

mysql:
	docker exec -it $(CONTAINER_DB) mysql --user=wordpress --password=wordpress wordpress

install: up
	docker exec -u 1000:1000 -it $(CONTAINER_WP) composer install -d ./wp-content/plugins/woocommerce-gateway-placetopay

compile:
	$(eval MODULE_NAME_VR=$(MODULE_NAME)$(PLUGIN_VERSION))
	@touch ~/Downloads/woocommerce-gateway-placetopay-test \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay* \
        && cp -pr $(CURRENT_FOLDER) ~/Downloads/woocommerce-gateway-placetopay \
        && cd ~/Downloads/woocommerce-gateway-placetopay \
        && composer install --no-dev \
        && find ~/Downloads/woocommerce-gateway-placetopay/ -type d -name ".git*" -exec rm -Rf {} + \
        && find ~/Downloads/woocommerce-gateway-placetopay/ -type d -name "squizlabs" -exec rm -Rf {} + \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/.git* \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/.idea \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/tmp \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/Dockerfile \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/Makefile \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/.env* \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/docker* \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/composer.* \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/.php_cs.cache \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/*.md \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/bin \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/dnetix/redirection/tests \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/dnetix/redirection/examples \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/guzzlehttp/guzzle/docs \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/guzzlehttp/guzzle/tests \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/guzzlehttp/streams/tests \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/symfony/var-dumper \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/symfony/polyfill-* \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/larapack/dd \
        && cd ~/Downloads \
        && zip -r -q -o $(MODULE_NAME_VR).zip woocommerce-gateway-placetopay \
        && chown $(UID):$(UID) $(MODULE_NAME_VR).zip \
        && chmod 644 $(MODULE_NAME_VR).zip \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay
	@echo "Compile file complete: ~/Downloads/$(MODULE_NAME_VR).zip"
