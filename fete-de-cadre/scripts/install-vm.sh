#!/usr/bin/env bash
# Fête de Cadre - local VM installer (Ubuntu 22.04/24.04, Debian 12).
# Installs Apache, PHP 8.x and MySQL/MariaDB, loads the schema and serves the app.
# By default it listens on every IPv4 address (the machine IP). Set LAB_BIND=127.0.0.1 for local-only.
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "Run as root: sudo $0"; exit 1; }

SRC_DIR="$(cd "$(dirname "$0")/.." && pwd)"
APP_DIR=/var/www/fetedecadre
LAB_BIND="${LAB_BIND:-0.0.0.0}"
PORT="${APP_PORT:-8080}"

echo "[1/6] Installing packages"
apt-get update -q
apt-get install -y -q apache2 libapache2-mod-php php php-mysql php-curl php-gd php-mbstring mariadb-server iputils-ping
systemctl enable --now mariadb

echo "[2/6] Creating database (drops any previous frameshop database)"
mysql -uroot -e "DROP DATABASE IF EXISTS frameshop; DROP USER IF EXISTS 'frame_app'@'%';"
mysql -uroot < "$SRC_DIR/db/init.sql"

echo "[3/6] Copying application to $APP_DIR"
mkdir -p "$APP_DIR"
cp -r "$SRC_DIR"/. "$APP_DIR"/
mkdir -p "$APP_DIR/logs" "$APP_DIR/storage/invoices" "$APP_DIR/storage/uploads"
chown -R www-data:www-data "$APP_DIR/logs" "$APP_DIR/storage"

echo "[4/6] Generating invoices"
( cd "$APP_DIR" && DB_HOST=127.0.0.1 php scripts/bootstrap.php || true )

echo "[5/6] Configuring Apache (port $PORT, bind $LAB_BIND)"
a2enmod rewrite headers >/dev/null
if ! grep -q "Listen $PORT" /etc/apache2/ports.conf; then echo "Listen $PORT" >> /etc/apache2/ports.conf; fi
BINDSPEC="*"; [[ "$LAB_BIND" != "0.0.0.0" ]] && BINDSPEC="$LAB_BIND"
cat > /etc/apache2/sites-available/fetedecadre.conf << CONF
<VirtualHost ${BINDSPEC}:${PORT}>
    DocumentRoot ${APP_DIR}/public
    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
        FallbackResource /index.php
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/fetedecadre_error.log
    CustomLog \${APACHE_LOG_DIR}/fetedecadre_access.log combined
</VirtualHost>
CONF
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite fetedecadre >/dev/null
systemctl restart apache2

echo "[6/6] Done. Fête de Cadre: http://${LAB_BIND}:${PORT}  (logs: /var/log/apache2/fetedecadre_error.log)"
echo "Find the machine IP with:  hostname -I | awk '{print \$1}'"
