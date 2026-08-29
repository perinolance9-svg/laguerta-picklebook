FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads/payment-receipts \
    && chown -R www-data:www-data /var/www/html/uploads/payment-receipts \
    && chmod 775 /var/www/html/uploads/payment-receipts

ENV PORT=8080

CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
