#!/bin/sh
set -eu

mkdir -p /var/www/html/uploads/payment-receipts
cp /usr/local/share/payment-receipts.htaccess /var/www/html/uploads/payment-receipts/.htaccess
chown -R www-data:www-data /var/www/html/uploads/payment-receipts
chmod 775 /var/www/html/uploads/payment-receipts
exec php -S "0.0.0.0:${PORT:-8080}" -t /var/www/html /var/www/html/railway-router.php
