#!/usr/bin/env bash
# Petal & Stem - local VM installer (Ubuntu 22.04/24.04, Debian 12).
# Installs Node.js 20, MySQL/MariaDB, loads the schema and registers a systemd service.
# By default the app listens on 127.0.0.1:3000 only. Set LAB_BIND=0.0.0.0 to serve an isolated lab network.
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "Run as root: sudo $0"; exit 1; }

APP_DIR=/opt/petalstem
SRC_DIR="$(cd "$(dirname "$0")/.." && pwd)"
LAB_BIND="${LAB_BIND:-127.0.0.1}"

echo "[1/6] Installing packages"
apt-get update -q
apt-get install -y -q curl ca-certificates gnupg iputils-ping iproute2
if ! command -v node >/dev/null || [[ "$(node -v | cut -d. -f1 | tr -d v)" -lt 18 ]]; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y -q nodejs
fi
if ! command -v mysql >/dev/null; then
  apt-get install -y -q mariadb-server || apt-get install -y -q default-mysql-server
fi
systemctl enable --now mariadb 2>/dev/null || systemctl enable --now mysql

echo "[2/6] Creating database (drops any previous petalstem database)"
mysql -uroot -e "DROP DATABASE IF EXISTS petalstem; DROP USER IF EXISTS 'petal_app'@'%';"
mysql -uroot < "$SRC_DIR/db/init.sql"

echo "[3/6] Copying application to $APP_DIR"
id petalstem >/dev/null 2>&1 || useradd --system --home "$APP_DIR" --shell /usr/sbin/nologin petalstem
mkdir -p "$APP_DIR"
cp -r "$SRC_DIR"/{server.js,package.json,lib,routes,views,public,storage,db,scripts,.env} "$APP_DIR"/
[[ -f "$SRC_DIR/package-lock.json" ]] && cp "$SRC_DIR/package-lock.json" "$APP_DIR"/
mkdir -p "$APP_DIR/logs" "$APP_DIR/storage/invoices"

echo "[4/6] Installing Node dependencies"
cd "$APP_DIR" && npm install --omit=dev --no-audit --no-fund
chown -R petalstem:petalstem "$APP_DIR"

echo "[5/6] Registering systemd service"
cat > /etc/systemd/system/petalstem.service << UNIT
[Unit]
Description=Petal & Stem vulnerable training lab
After=network.target mariadb.service mysql.service

[Service]
WorkingDirectory=$APP_DIR
Environment=DB_HOST=127.0.0.1
Environment=HOST=$LAB_BIND
ExecStart=/usr/bin/node server.js
User=petalstem
Restart=on-failure
NoNewPrivileges=true
ProtectSystem=full
ProtectHome=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now petalstem

echo "[6/6] Done. Petal & Stem: http://$LAB_BIND:3000   (logs: journalctl -u petalstem -f)"
