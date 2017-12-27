FROM wordpress:php7.0-apache

ENV WORDPRESS_VERSION 4.8.4
ENV WOOCOMMERCE_VERSION 3.2.1

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libxml++2.6-dev php-soap \
    \
    && curl -B https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer \
    \
    && cd /usr/src/wordpress/wp-content/plugins \
    && curl -B https://downloads.wordpress.org/plugin/woocommerce.3.2.1.zip -o woocommerce.zip \
    && unzip woocommerce.zip \
    && rm woocommerce.zip \
    \
    && rm -rf /var/lib/apt/lists/*;

RUN docker-php-ext-install soap
