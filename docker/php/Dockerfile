FROM php:8.1.1-fpm-buster

ARG AMQP_VERSION=1.11.0
ARG XDEBUG_VERSION=3.1.1

RUN apt-get update && \
    apt-get install -y \
        # For extension installation. Remove after use
        ${PHPIZE_DEPS} \
        # common \
        libzip-dev \
        zip \
        # RabbitMQ
        librabbitmq-dev libssh-dev \
        && \
    pecl channel-update pecl.php.net && \
    printf "\n" | pecl install amqp-${AMQP_VERSION} && \
    printf "\n" | pecl install xdebug-${XDEBUG_VERSION} && \
    docker-php-ext-enable amqp xdebug && \
    pecl clear-cache && \
    apt-get purge -y ${PHPIZE_DEPS} && \
    apt-get autoremove -y

RUN ln -s $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

COPY ./docker/php/php.ini /usr/local/etc/php/conf.d/php.custom.ini

COPY --from=composer:2.2.2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
