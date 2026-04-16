FROM php:7.4-fpm AS base

RUN apt-get update && \
    apt-get install -y sqlite3 libsqlite3-dev && \
    docker-php-ext-install pdo_mysql

COPY site /var/www/html