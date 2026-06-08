#!/usr/bin/env bash
# =============================================================================
# BlindBeat — Script de déploiement
# Exécuté sur le VPS par GitHub Actions à chaque push sur main
# Usage : bash scripts/deploy.sh
# =============================================================================
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
log()  { echo -e "${GREEN}[✓]${NC} $*"; }
info() { echo -e "${BLUE}[→]${NC} $*"; }
die()  { echo -e "${RED}[✗] ERREUR : $*${NC}" >&2; exit 1; }

APP_DIR="${APP_DIR:-/var/www/blindbeat}"
PHP="php"

[[ -d "$APP_DIR" ]] || die "Répertoire $APP_DIR introuvable"
[[ -f "$APP_DIR/.env" ]] || die "Fichier .env manquant dans $APP_DIR"

echo -e "\n${BLUE}════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  BlindBeat — Déploiement$(date '+  %d/%m/%Y %H:%M:%S')${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════${NC}\n"

cd "$APP_DIR"

# ─── 1. Maintenance ON ────────────────────────────────────────────────────────
info "Activation du mode maintenance..."
$PHP artisan down --retry=10 --refresh=10 --secret="bb-deploy-bypass"
log "Mode maintenance activé"

# ─── 2. Pull du code ──────────────────────────────────────────────────────────
info "Récupération du code..."
git fetch origin main --quiet
git reset --hard origin/main --quiet
log "Code mis à jour ($(git rev-parse --short HEAD))"

# ─── 3. Dépendances PHP ───────────────────────────────────────────────────────
info "Installation des dépendances Composer..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet
log "Composer OK"

# ─── 4. Build assets ──────────────────────────────────────────────────────────
info "Build des assets Vite..."
npm ci --silent
npm run build --silent
log "Assets compilés"

# ─── 5. Migrations ────────────────────────────────────────────────────────────
info "Exécution des migrations..."
$PHP artisan migrate --force
log "Migrations OK"

# ─── 6. Cache Laravel ─────────────────────────────────────────────────────────
info "Rebuild du cache..."
$PHP artisan config:clear
$PHP artisan route:clear
$PHP artisan view:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
log "Cache reconstruit"

# ─── 7. Storage link ──────────────────────────────────────────────────────────
$PHP artisan storage:link --force 2>/dev/null || true

# ─── 8. Permissions ───────────────────────────────────────────────────────────
chown -R blindbeat:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ─── 9. Redémarrage Supervisor ────────────────────────────────────────────────
info "Redémarrage des workers et Reverb..."
sudo supervisorctl restart blindbeat:* >/dev/null
log "Supervisor redémarré"

# ─── 10. Maintenance OFF ──────────────────────────────────────────────────────
info "Désactivation du mode maintenance..."
$PHP artisan up
log "Application en ligne"

echo ""
echo -e "${GREEN}════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  ✅  Déploiement réussi — $(git log -1 --pretty='%s')${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════${NC}\n"