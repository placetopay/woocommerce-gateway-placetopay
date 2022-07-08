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
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay* \
        && sudo cp $(CURRENT_FOLDER) ~/Downloads/woocommerce-gateway-placetopay -R \
        && sudo find ~/Downloads/woocommerce-gateway-placetopay/ -type d -name ".git*" -exec rm -Rf {} + \
        && sudo find ~/Downloads/woocommerce-gateway-placetopay/ -type d -name "squizlabs" -exec rm -Rf {} + \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/.git* \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/.idea \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/tmp \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/Dockerfile \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/Makefile \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/.env* \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/docker* \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/composer.* \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/.php_cs.cache \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/*.md \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/bin \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/dnetix/redirection/tests \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/dnetix/redirection/examples \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/guzzlehttp/guzzle/docs \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/guzzlehttp/guzzle/tests \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/guzzlehttp/streams/tests \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/symfony/var-dumper \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/symfony/polyfill-* \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay/vendor/larapack/dd \
        && cd ~/Downloads \
        && sudo zip -r -q -o $(MODULE_NAME_VR).zip woocommerce-gateway-placetopay \
        && sudo chown $(UID):$(UID) $(MODULE_NAME_VR).zip \
        && sudo chmod 644 $(MODULE_NAME_VR).zip \
        && sudo rm -Rf ~/Downloads/woocommerce-gateway-placetopay
	@echo "Compile file complete: ~/Downloads/$(MODULE_NAME_VR).zip"
