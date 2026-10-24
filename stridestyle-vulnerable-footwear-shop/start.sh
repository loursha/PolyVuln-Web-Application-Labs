#!/bin/bash
# ============================================================================
# start.sh -- One-command startup for StrideStyle Footwear Co. (local, no Docker)
#
# Assumes MySQL or MariaDB is already installed and running locally, and
# that you can connect as root with no password (adjust the mysql command
# below if your setup differs).
# ============================================================================
set -e
cd "$(dirname "$0")"

echo "== StrideStyle Footwear Co. -- Vulnerable Footwear Shop Lab =="
echo ""

echo "[*] Ensuring database + app user exist..."
mysql -u root -e "
  CREATE DATABASE IF NOT EXISTS footwear_shop;
  CREATE USER IF NOT EXISTS 'footwear_app'@'localhost' IDENTIFIED BY 'FootwearApp2024!';
  GRANT ALL PRIVILEGES ON footwear_shop.* TO 'footwear_app'@'localhost';
  FLUSH PRIVILEGES;
" || { echo "Could not reach MySQL/MariaDB as root -- make sure it's installed and running."; exit 1; }

echo "[*] Installing backend dependencies..."
cd backend
npm install --silent

echo "[*] Seeding database..."
node seed.js

echo "[*] Starting backend on http://localhost:4000 ..."
node server.js &
BACKEND_PID=$!

cd ../frontend
echo "[*] Installing frontend dependencies..."
npm install --silent

echo ""
echo "[*] Starting frontend on http://localhost:3000 ..."
echo "[*] Default admin login: admin / admin123"
echo "[*] Press Ctrl+C to stop both servers."
echo ""

trap "kill $BACKEND_PID 2>/dev/null" EXIT
npm run dev
