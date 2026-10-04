#!/usr/bin/env bash
# Service Dokploy « sanitaire-worker » (Run Command : bash deploy/worker.sh).
# Même dépôt et même image que l'API, mais ne sert aucun trafic HTTP :
#   - queue:work    traite la file « database » (notifications RDV, résultats,
#                   congés, référencement, lien de paiement DexPay…) ;
#   - schedule:work lance chaque minute notifications:process-due (rappels de
#                   RDV programmés, rappels d'échéance de facture).
# Si l'un des deux s'arrête, le script sort en erreur : Dokploy (Docker Swarm)
# redémarre alors le conteneur entier. queue:work s'arrête volontairement
# toutes les heures (--max-time) pour repartir propre en mémoire.
# Les migrations restent lancées par l'API (deploy:migrate), jamais ici.
set -uo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

php artisan queue:work --tries=3 --sleep=3 --max-time=3600 &
QUEUE_PID=$!
php artisan schedule:work &
SCHEDULE_PID=$!

stop() {
    kill -TERM "$QUEUE_PID" "$SCHEDULE_PID" 2>/dev/null
    wait
}

trap 'echo "[worker] arrêt demandé"; stop; exit 0' TERM INT

wait -n "$QUEUE_PID" "$SCHEDULE_PID"
echo "[worker] queue:work ou schedule:work s'est arrêté — redémarrage du conteneur."
stop
exit 1
