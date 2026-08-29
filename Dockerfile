FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads/payment-receipts \
    && cp /var/www/html/uploads/payment-receipts/.htaccess /usr/local/share/payment-receipts.htaccess \
    && chown -R www-data:www-data /var/www/html/uploads/payment-receipts \
    && chmod 775 /var/www/html/uploads/payment-receipts

ENV PORT=8080

COPY railway-start.sh /usr/local/bin/railway-start
RUN chmod +x /usr/local/bin/railway-start
ENTRYPOINT ["/usr/local/bin/railway-start"]

CMD ["sh", "-c", "mkdir -p /var/www/html/uploads/payment-receipts && cp /usr/local/share/payment-receipts.htaccess /var/www/html/uploads/payment-receipts/.htaccess && chown -R www-data:www-data /var/www/html/uploads/payment-receipts && chmod 775 /var/www/html/uploads/payment-receipts && sed -i \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
