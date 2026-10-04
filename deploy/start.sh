#!/usr/bin/env bash
# Démarrage du backend en production (commande [start] de nixpacks.toml).
#
#   1. Migrations (deploy:migrate) AVANT tout trafic : si elles échouent, le
#      conteneur s'arrête, comme avant.
#   2. PHP-FPM (plusieurs workers en parallèle) + Nginx sur $PORT.
#   3. Filet de sécurité : si PHP-FPM ou Nginx est introuvable, ne démarre pas
#      ou ne répond pas sur /up, repli automatique sur `php artisan serve`
#      (l'ancien mode). SERVER_MODE=artisan dans Dokploy force ce repli.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"
PORT="${PORT:-8080}"

log() { echo "[start] $*"; }

php artisan deploy:migrate

FPM_PID=""
NGINX_PID=""

stop_children() {
    [ -n "$NGINX_PID" ] && kill -QUIT "$NGINX_PID" 2>/dev/null || true
    [ -n "$FPM_PID" ] && kill -QUIT "$FPM_PID" 2>/dev/null || true
    wait 2>/dev/null || true
}

fallback() {
    log "$1 — repli sur php artisan serve (une requête à la fois)."
    stop_children
    exec php artisan serve --host=0.0.0.0 --port="$PORT"
}

if [ "${SERVER_MODE:-fpm}" = "artisan" ]; then
    fallback "SERVER_MODE=artisan"
fi

# --- Binaires --------------------------------------------------------------
NGINX_BIN="$(command -v nginx || true)"
[ -n "$NGINX_BIN" ] || fallback "nginx introuvable"
NGINX_CONF_DIR="$(dirname "$(readlink -f "$NGINX_BIN")")/../conf"
[ -f "$NGINX_CONF_DIR/mime.types" ] && [ -f "$NGINX_CONF_DIR/fastcgi_params" ] \
    || fallback "mime.types / fastcgi_params introuvables dans $NGINX_CONF_DIR"

FPM_BIN="$(command -v php-fpm || true)"
if [ -z "$FPM_BIN" ]; then
    PHP_REAL_DIR="$(dirname "$(readlink -f "$(command -v php)")")"
    for candidate in "$PHP_REAL_DIR/php-fpm" "$PHP_REAL_DIR/../sbin/php-fpm"; do
        [ -x "$candidate" ] && FPM_BIN="$candidate" && break
    done
fi
[ -n "$FPM_BIN" ] || fallback "php-fpm introuvable"
# PHP-FPM doit charger les mêmes extensions que la CLI (PostgreSQL surtout).
"$FPM_BIN" -m 2>/dev/null | grep -qi '^pdo_pgsql$' || fallback "php-fpm sans l'extension pdo_pgsql"

# --- Dimensionnement -------------------------------------------------------
# Environ 64 Mo par worker Laravel, sur la moitié de la mémoire du conteneur
# (le reste pour Nginx, PostgreSQL s'il partage la machine, le système).
memory_mb() {
    local total limit
    total=$(awk '/MemTotal/ { print int($2 / 1024) }' /proc/meminfo)
    limit=""
    if [ -r /sys/fs/cgroup/memory.max ]; then
        limit=$(cat /sys/fs/cgroup/memory.max)
    elif [ -r /sys/fs/cgroup/memory/memory.limit_in_bytes ]; then
        limit=$(cat /sys/fs/cgroup/memory/memory.limit_in_bytes)
    fi
    if [[ "$limit" =~ ^[0-9]+$ ]] && [ $((limit / 1048576)) -lt "$total" ]; then
        echo $((limit / 1048576))
    else
        echo "$total"
    fi
}
MEM_MB="$(memory_mb)"
AUTO_CHILDREN=$((MEM_MB / 2 / 64))
[ "$AUTO_CHILDREN" -lt 4 ] && AUTO_CHILDREN=4
[ "$AUTO_CHILDREN" -gt 32 ] && AUTO_CHILDREN=32

export PHP_FPM_MAX_CHILDREN="${PHP_FPM_MAX_CHILDREN:-$AUTO_CHILDREN}"
START=$((PHP_FPM_MAX_CHILDREN / 4)); [ "$START" -lt 2 ] && START=2
export PHP_FPM_START_SERVERS="${PHP_FPM_START_SERVERS:-$START}"
export PHP_FPM_MIN_SPARE="${PHP_FPM_MIN_SPARE:-$PHP_FPM_START_SERVERS}"
MAX_SPARE=$((PHP_FPM_MAX_CHILDREN / 2)); [ "$MAX_SPARE" -lt "$PHP_FPM_START_SERVERS" ] && MAX_SPARE=$PHP_FPM_START_SERVERS
export PHP_FPM_MAX_SPARE="${PHP_FPM_MAX_SPARE:-$MAX_SPARE}"
log "mémoire ${MEM_MB} Mo — PHP-FPM : ${PHP_FPM_MAX_CHILDREN} workers max (démarrage ${PHP_FPM_START_SERVERS})"

# --- Configuration ---------------------------------------------------------
mkdir -p /tmp/nginx
sed -e "s|@PORT@|$PORT|g" -e "s|@APP_DIR@|$APP_DIR|g" -e "s|@NGINX_CONF_DIR@|$NGINX_CONF_DIR|g" \
    deploy/nginx.conf.template > /tmp/nginx/nginx.conf

"$FPM_BIN" -R -t -y deploy/php-fpm.conf || fallback "configuration PHP-FPM invalide"
"$NGINX_BIN" -e stderr -p /tmp/nginx -c /tmp/nginx/nginx.conf -t || fallback "configuration Nginx invalide"

# --- Démarrage -------------------------------------------------------------
rm -f /tmp/php-fpm.sock
"$FPM_BIN" -R -F -y deploy/php-fpm.conf &
FPM_PID=$!
for _ in $(seq 1 30); do [ -S /tmp/php-fpm.sock ] && break; sleep 0.5; done
[ -S /tmp/php-fpm.sock ] || fallback "PHP-FPM n'a pas ouvert son socket"

"$NGINX_BIN" -e stderr -p /tmp/nginx -c /tmp/nginx/nginx.conf &
NGINX_PID=$!

# Vérifie que la chaîne complète répond avant de considérer le démarrage réussi.
healthy=""
for _ in $(seq 1 30); do
    if PORT_CHECK="$PORT" php -r'exit(@file_get_contents("http://127.0.0.1:" . getenv("PORT_CHECK") . "/up") === false ? 1 : 0);' 2>/dev/null; then
        healthy=1
        break
    fi
    sleep 0.5
done 2>/dev/null
[ -n "$healthy" ] || fallback "Nginx + PHP-FPM ne répondent pas sur /up"
log "Nginx + PHP-FPM prêts sur le port $PORT"

# Arrêt propre demandé par Dokploy : on le transmet aux deux processus.
trap 'log "arrêt demandé"; stop_children; exit 0' TERM INT

# Si l'un des deux s'arrête, on arrête tout : Dokploy redémarre le conteneur.
set +e
wait -n "$FPM_PID" "$NGINX_PID"
log "PHP-FPM ou Nginx s'est arrêté — arrêt du conteneur."
stop_children
exit 1
