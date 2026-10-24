#!/usr/bin/env bash
# Restore the lab to a clean state (fresh database, invoices and logs).
set -euo pipefail
cd "$(dirname "$0")/.."
MODE="${1:-docker}"
case "$MODE" in
  docker)
    docker compose down -v
    docker compose up -d --build
    echo "Lab reset. App will be on http://<machine-ip>:8080 once MySQL is ready." ;;
  k8s)
    kubectl delete namespace fetedecadre-lab --ignore-not-found --wait=true
    kubectl apply -k k8s/
    echo "Lab reset on Kubernetes (NodePort 30081)." ;;
  vm)
    mysql -uroot -e "DROP DATABASE IF EXISTS frameshop; DROP USER IF EXISTS 'frame_app'@'%';"
    mysql -uroot < db/init.sql
    rm -f storage/invoices/*.pdf logs/activity.log
    DB_HOST=127.0.0.1 php scripts/bootstrap.php || true
    echo "Lab database reset." ;;
  *) echo "Usage: $0 [docker|k8s|vm]"; exit 1 ;;
esac
