FROM php:8.2-fpm

# Installeer benodigde packages voor LDAP en SQLite3
RUN apt-get update && apt-get install -y \
    libldap2-dev \
    libsqlite3-dev \
    unzip \
    && docker-php-ext-install ldap pdo pdo_sqlite

WORKDIR /var/www/html
