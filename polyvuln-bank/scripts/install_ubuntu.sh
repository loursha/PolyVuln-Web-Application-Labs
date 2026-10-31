#!/usr/bin/env bash
#
# PolyVuln Bank - bare-metal / VM installer for Ubuntu 22.04+ / Debian 12
# Installs Apache, PHP and MariaDB, loads the schema + seed data, and serves the app.
# FOR ISOLATED LAB USE ONLY. Do not run on an internet-facing host.
#
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEBROOT="/var/www/polyvuln-bank"
DB_NAME="polybank"
DB_USER="pvb_app"
DB_PASS="Pvb_App_2026!"

echo "[*] Installing packages (apache2, php, mariadb)…"
export DEBIAN_FRONTEND=noninteractive
sudo apt-get update -q
sudo apt-get install -y apache2 mariadb-server \
     php libapache2-mod-php php-mysql php-curl php-gd php-mbstring iputils-ping

echo "[*] Starting services…"
sudo systemctl enable --now mariadb apache2

echo "[*] Creating database and application user…"
sudo mysql <<SQL
SOURCE ${HERE}/db/init/01_schema.sql;
SOURCE ${HERE}/db/init/02_seed.sql;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT SELECT, INSERT, UPDATE, DELETE ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "[*] Deploying application to ${WEBROOT}…"
sudo mkdir -p "${WEBROOT}"
sudo cp -r "${HERE}/src/." "${WEBROOT}/"
sudo mkdir -p /var/www/polyvuln-storage
sudo cp -r "${HERE}/storage/." /var/www/polyvuln-storage/
sudo chown -R www-data:www-data "${WEBROOT}/uploads" /var/www/polyvuln-storage

echo "[*] Writing Apache vhost…"
sudo tee /etc/apache2/sites-available/polyvuln.conf >/dev/null <<APACHE
<VirtualHost *:8080>
    DocumentRoot ${WEBROOT}
    SetEnv DB_HOST 127.0.0.1
    SetEnv DB_USER ${DB_USER}
    SetEnv DB_PASS ${DB_PASS}
    SetEnv DOC_STORE /var/www/polyvuln-storage/documents
    SetEnv GATEWAY_SIGNING_KEY pvpay_live_6d1b2f0e94a7c3
    <Directory ${WEBROOT}>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>
</VirtualHost>
APACHE

grep -q "Listen 8080" /etc/apache2/ports.conf || echo "Listen 8080" | sudo tee -a /etc/apache2/ports.conf
sudo a2dissite 000-default >/dev/null 2>&1 || true
sudo a2ensite polyvuln >/dev/null
sudo a2enmod rewrite headers >/dev/null
sudo systemctl reload apache2

echo
echo "[+] PolyVuln Bank is live at http://localhost:8080/"
echo "[+] Start the internal ledger service in a second terminal with:"
echo "      php -S 127.0.0.1:8081 ${HERE}/core-ledger/router.php"
echo "[+] Sample customer login:  dcarter / Carter@1987"
