#!/bin/sh
set -eu

mkdir -p /var/www/html/uploads/payment-receipts
cp /usr/local/share/payment-receipts.htaccess /var/www/html/uploads/payment-receipts/.htaccess
chown -R www-data:www-data /var/www/html/uploads/payment-receipts
chmod 775 /var/www/html/uploads/payment-receipts
sed -i "s/Listen 80/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost [*]:80>/<VirtualHost *:${PORT:-8080}>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
