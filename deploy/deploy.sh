#!/usr/bin/env bash
# Pemakaian:
#   deploy/deploy.sh             jalur Docker (perintah dijalankan di container app)
#   RUN= deploy/deploy.sh        jalur native (perintah dijalankan langsung di server)
set -euo pipefail
cd "$(dirname "$0")/.."

RUN="${RUN-docker compose exec -T app}"

trap 'echo "DEPLOY GAGAL: situs masih dalam maintenance mode. Lihat bagian rollback." >&2' ERR

$RUN php artisan down --retry=30
git pull --ff-only
$RUN composer install --no-dev --optimize-autoloader --no-interaction
$RUN php artisan migrate --force
$RUN php artisan optimize
$RUN php artisan queue:restart
$RUN php artisan up

echo "Deploy selesai: $(git rev-parse --short HEAD)"
