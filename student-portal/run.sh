#!/usr/bin/env bash
set -e
pip install -r requirements.txt
python3 init_db.py
echo "Starting EduCore on http://127.0.0.1:5000"
python3 app.py
