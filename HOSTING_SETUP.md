# Laguerta Picklebook public hosting setup

## Hosting requirements

- PHP 8.1 or newer with PDO MySQL, fileinfo, cURL, JSON, and OpenSSL
- MySQL 8 or a compatible MariaDB version
- Apache with `.htaccess` support
- HTTPS/SSL
- A writable `uploads/payment-receipts` directory

## Install

1. Create a database and database user in the hosting control panel.
2. Import `INSTALL_DATABASE.sql` into that empty database using phpMyAdmin.
3. Upload and extract the contents of the deployment ZIP into the domain document root, normally `public_html`.
4. Copy `config/private.example.php` to `config/private.php`.
5. Replace every placeholder in `config/private.php` with the real database, domain, GCash, and optional Google credentials.
6. Set `APP_BASE_URL` to the final HTTPS site address and keep `FORCE_HTTPS` set to `1`.
7. Confirm that `uploads/payment-receipts` is writable by PHP. Do not remove its `.htaccess` file.
8. Visit `https://YOUR-DOMAIN/setup-admin.php` once and create a strong administrator account.
9. Open `https://YOUR-DOMAIN/login.php` and test Player registration, booking, receipt upload, and Admin verification.

## Google login (optional)

Create a Google OAuth Web application. Set its authorized redirect URI to:

`https://YOUR-DOMAIN/google-callback.php`

Add the Client ID, Client Secret, and exact callback address to `config/private.php`. Google accounts are always created as Players and never as Administrators.

## Before sharing on Facebook

- Confirm the browser shows HTTPS without a certificate warning.
- Use a unique Admin password of at least 12 characters.
- Never upload a local `config/private.php`, demo database, or real payment receipts.
- Test the public link from a phone using mobile data, not the same Wi-Fi.
- Post the final URL, such as `https://YOUR-DOMAIN/login.php`, on the Facebook page.
