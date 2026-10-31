#!/usr/bin/env bash
# Drop and reload the PolyVuln Bank database (restores all seed data and balances).
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
echo "[*] Reloading database from schema + seed…"
sudo mysql -e "DROP DATABASE IF EXISTS polybank;"
sudo mysql < "${HERE}/db/init/01_schema.sql"
sudo mysql < "${HERE}/db/init/02_seed.sql"
echo "[+] Database reset complete."
