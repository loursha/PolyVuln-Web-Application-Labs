#!/usr/bin/env bash
# Local VM / bare-metal startup script (Ubuntu/Debian/Rocky/CentOS)
set -e
echo "[*] Northwind HR Portal - local setup"
python3 -m venv venv
source venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
python init_db.py
echo "[*] Starting on http://0.0.0.0:5000"
python app.py
