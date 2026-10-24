#!/bin/bash
# ============================================================================
# start.sh -- One-command startup for Wick & Wax Co. (local VM deployment)
# ============================================================================
set -e
cd "$(dirname "$0")"

echo "== Wick & Wax Co. -- Vulnerable Candle Shop Lab =="
echo ""

if [ ! -d "venv" ]; then
    echo "[*] Creating virtual environment..."
    python3 -m venv venv
fi

source venv/bin/activate

echo "[*] Installing dependencies (requirements-run.txt -- runnable pins)..."
pip install -q -r requirements-run.txt

if [ ! -f "instance/candleshop.db" ]; then
    echo "[*] Initializing database with seed data..."
    python3 init_db.py
else
    echo "[*] Database already exists. Delete instance/candleshop.db and re-run to reset."
fi

echo ""
echo "[*] Starting Flask application on http://0.0.0.0:5000 ..."
echo "[*] Default admin login: admin / admin123"
echo "[*] Press Ctrl+C to stop."
echo ""
python3 app.py
