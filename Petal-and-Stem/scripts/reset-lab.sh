#!/usr/bin/env bash
# Restore the lab to a clean state (fresh database, invoices and logs).
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ "${1:-docker}" == "docker" ]]; then
  docker compose down -v
  docker compose up -d --build
  echo "Lab reset. Waiting for http://127.0.0.1:8080 ..."
elif [[ "$1" == "k8s" ]]; then
  kubectl delete namespace petalstem-lab --wait=true
  kubectl apply -k k8s/
else
  echo "Usage: $0 [docker|k8s]"; exit 1
fi
