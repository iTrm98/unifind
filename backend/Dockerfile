# UniFind API: PHP 8.4 + Apache in one container (Render free web service).
FROM php:8.4-apache

# Extensions. mbstring, openssl and fileinfo are already built into the official image;
# install-php-extensions adds the rest with their system libraries.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql zip gd \
    && php -m | grep -qi mbstring && php -m | grep -qi openssl && php -m | grep -qi fileinfo

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# PHP production settings; photos may be up to 3 MB.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'upload_max_filesize=5M\npost_max_size=8M\nexpose_php=Off\n' > "$PHP_INI_DIR/conf.d/unifind.ini"

# Apache: serve public/, allow Laravel's .htaccess, listen on Render's $PORT
# (Apache expands ${PORT} from the environment when it starts).
ENV PORT=10000 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!' /etc/apache2/sites-available/000-default.conf \
    && echo 'Listen ${PORT}' > /etc/apache2/ports.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n</Directory>\nServerName localhost\nServerTokens Prod\nServerSignature Off\n' \
       > /etc/apache2/conf-enabled/unifind.conf

WORKDIR /var/www/html

# Dependencies first for better layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist

# The application (see .dockerignore), including storage/certs/ca.pem for Aiven SSL.
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist \
    && test -f storage/certs/ca.pem \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/start.sh /usr/local/bin/unifind-start
RUN sed -i 's/\r$//' /usr/local/bin/unifind-start && chmod +x /usr/local/bin/unifind-start

EXPOSE 10000
CMD ["unifind-start"]
