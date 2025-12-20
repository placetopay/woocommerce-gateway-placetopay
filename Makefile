#!/bin/sh

CURRENT_FOLDER=$(shell pwd)
UID=$(shell id -u)
MODULE_NAME=woocommerce-gateway-placetopay

# Usage:
# make compile PLUGIN_VERSION=-3.0.0-php-7.4.x PHP_VERSION=7.4
# make compile PLUGIN_VERSION=-3.0.0-php-8.x   PHP_VERSION=8.0

.PHONY: compile
compile:
	$(eval PHP_VERSION=${PHP_VERSION:-7.4})
	$(eval MODULE_NAME_VR=$(MODULE_NAME)$(PLUGIN_VERSION))
	@touch ~/Downloads/woocommerce-gateway-placetopay-test \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay* \
        && cp -pr $(CURRENT_FOLDER) ~/Downloads/woocommerce-gateway-placetopay \
        && cd ~/Downloads/woocommerce-gateway-placetopay \
        && sed -i 's/"php": ".*"/"php": "^$(PHP_VERSION)"/' ~/Downloads/woocommerce-gateway-placetopay/composer.json \
        && rm -Rf ~/Downloads/woocommerce-gateway-placetopay/composer.lock \
        && php$(PHP_VERSION) `which composer` install --no-dev \
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
	@echo "Compile file complete: ~/Downloads/$(MODULE_NAME_VR).zip using PHP $(PHP_VERSION)"
