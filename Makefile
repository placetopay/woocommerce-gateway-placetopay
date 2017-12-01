#!/bin/bash

CONTAINER_WP = wp_plugin_wordpress
CONTAINER_DB = wp_plugin_db

up:
	docker-compose up -d

down:
	docker-compose down

bash:
	docker exec -it $(CONTAINER_WP) bash

mysql:
	docker exec -it $(CONTAINER_DB) mysql --user=wordpress --password=wordpress wordpress

install: up
	docker exec -u 1000:1000 -it $(CONTAINER_WP) composer install -d ./wp-content/plugins/woocommerce-gateway-placetopay