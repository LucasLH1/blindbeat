#!/usr/bin/env bash
# =============================================================================
# BlindBeat — Initialisation VPS OVH (Ubuntu 24.04 LTS)
# Usage   : bash scripts/init-vps.sh
# Prérequis : exécuter en root sur un VPS Ubuntu 24.04 vierge
# Idempotent : peut être relancé sans danger
# =============================================================================
set -euo pipefail

# ─── Couleurs ─────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
log()  { echo -e "${GREEN}[✓]${NC} $*"; }
info() { echo -e "${BLUE}[→]${NC} $*"; }
warn() { echo -e "${YELLOW}[!]${NC} $*"; }
die()  { echo -e "${RED}[✗] ERREUR : $*${NC}" >&2; exit 1; }

# ─── Vérifications préalables ────────────────────────────────────────────────
[[ $EUID -eq 0 ]] || die "Ce script doit être exécuté en root : sudo bash init-vps.sh"
grep -q "24.04" /etc/os-release 2>/dev/null || warn "Ubuntu 24.04 recommandé — continue quand même"

echo -e "\n${BLUE}════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  BlindBeat — Initialisation VPS${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════${NC}\n"

# ─── Variables ────────────────────────────────────────────────────────────────
APP_USER="blindbeat"
APP_DIR="/var/www/blindbeat"
REPO_URL="https://github.com/LucasLH1/blindbeat.git"
PHP_VERSION="8.3"
NODE_VERSION="22"
DB_NAME="blindbeat"
DB_USER="blindbeat"

# Domaine
if [[ -z "${DOMAIN:-}" ]]; then
  read -rp "Nom de domaine (ex: blindbeat.fr) : " DOMAIN
fi
[[ -n "$DOMAIN" ]] || die "Un domaine est requis"

# Mot de passe DB
if [[ -z "${DB_PASSWORD:-}" ]]; then
  DB_PASSWORD=$(openssl rand -base64 32 | tr -dc 'a-zA-Z0-9' | head -c 32)
fi

# ─── 1. Système ───────────────────────────────────────────────────────────────
info "Mise à jour du système..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq
apt-get install -y -qq \
  git curl unzip wget gnupg2 ca-certificates lsb-release \
  nginx supervisor ufw fail2ban \
  software-properties-common apt-transport-https \
  acl openssl
log "Système à jour"

# ─── 2. PHP 8.3 ───────────────────────────────────────────────────────────────
info "Installation de PHP ${PHP_VERSION}..."
if ! grep -rq "ondrej/php" /etc/apt/sources.list.d/ 2>/dev/null; then
  add-apt-repository ppa:ondrej/php -y -q
  apt-get update -qq
fi
apt-get install -y -qq \
  php${PHP_VERSION}-fpm php${PHP_VERSION}-cli \
  php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml php${PHP_VERSION}-curl \
  php${PHP_VERSION}-zip php${PHP_VERSION}-mysql php${PHP_VERSION}-bcmath \
  php${PHP_VERSION}-intl php${PHP_VERSION}-redis php${PHP_VERSION}-pcov \
  php${PHP_VERSION}-tokenizer php${PHP_VERSION}-ctype
# Config PHP production
sed -i 's/^;*pm.max_children.*/pm.max_children = 20/'   /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf
sed -i 's/^;*pm.start_servers.*/pm.start_servers = 4/'  /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf
sed -i 's/^;*pm.min_spare_servers.*/pm.min_spare_servers = 2/' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf
sed -i 's/^;*pm.max_spare_servers.*/pm.max_spare_servers = 6/' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf
log "PHP ${PHP_VERSION} installé"

# ─── 3. Composer ──────────────────────────────────────────────────────────────
info "Installation de Composer..."
if ! command -v composer &>/dev/null; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --quiet
fi
log "Composer $(composer --version --no-ansi | head -1)"

# ─── 4. Node.js ───────────────────────────────────────────────────────────────
info "Installation de Node.js ${NODE_VERSION}..."
if ! command -v node &>/dev/null || [[ "$(node -v | cut -d. -f1 | tr -d 'v')" -lt "$NODE_VERSION" ]]; then
  curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - >/dev/null 2>&1
  apt-get install -y -qq nodejs
fi
log "Node $(node -v) — npm $(npm -v)"

# ─── 5. MariaDB ───────────────────────────────────────────────────────────────
info "Installation de MariaDB..."
apt-get install -y -qq mariadb-server
systemctl enable --now mariadb

mysql -u root <<SQL
ALTER USER IF EXISTS 'root'@'localhost' IDENTIFIED VIA unix_socket;
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost','127.0.0.1','::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
log "MariaDB configuré — base '${DB_NAME}' créée"

# ─── 6. Utilisateur système ───────────────────────────────────────────────────
info "Création de l'utilisateur '${APP_USER}'..."
if ! id "$APP_USER" &>/dev/null; then
  useradd -m -s /bin/bash "$APP_USER"
fi
# Ajout au groupe www-data pour que Nginx puisse lire les fichiers
usermod -aG www-data "$APP_USER"

# Clé SSH pour GitHub Actions
SSH_DIR="/home/${APP_USER}/.ssh"
mkdir -p "$SSH_DIR"
chmod 700 "$SSH_DIR"
if [[ ! -f "${SSH_DIR}/deploy_key" ]]; then
  ssh-keygen -t ed25519 -f "${SSH_DIR}/deploy_key" -N "" -C "github-actions@blindbeat" -q
  cat "${SSH_DIR}/deploy_key.pub" >> "${SSH_DIR}/authorized_keys"
  chmod 600 "${SSH_DIR}/authorized_keys" "${SSH_DIR}/deploy_key"
fi
chown -R "${APP_USER}:${APP_USER}" "$SSH_DIR"
log "Utilisateur '${APP_USER}' et clé SSH prêts"

# ─── 7. Répertoire applicatif ─────────────────────────────────────────────────
info "Préparation du répertoire applicatif..."
mkdir -p "$APP_DIR"
chown "${APP_USER}:www-data" "$APP_DIR"
chmod 750 "$APP_DIR"

# Cloner le repo si vide
if [[ ! -f "${APP_DIR}/.git/HEAD" ]]; then
  sudo -u "$APP_USER" git clone "$REPO_URL" "$APP_DIR"
  log "Repo cloné dans ${APP_DIR}"
else
  log "Repo déjà présent dans ${APP_DIR}"
fi

# Dossiers Laravel avec bonnes permissions
mkdir -p "${APP_DIR}/storage/"{app/public,framework/{cache,sessions,views},logs}
mkdir -p "${APP_DIR}/bootstrap/cache"
chown -R "${APP_USER}:www-data" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
log "Répertoire applicatif configuré"

# ─── 8. Fichier .env ──────────────────────────────────────────────────────────
info "Création du fichier .env..."
if [[ ! -f "${APP_DIR}/.env" ]]; then
  REVERB_APP_ID=$(openssl rand -hex 8)
  REVERB_APP_KEY=$(openssl rand -hex 20)
  REVERB_APP_SECRET=$(openssl rand -hex 20)

  cat > "${APP_DIR}/.env" <<ENV
APP_NAME=BlindBeat
APP_ENV=production
APP_DEBUG=false
APP_URL=https://${DOMAIN}
APP_KEY=

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

REVERB_APP_ID=${REVERB_APP_ID}
REVERB_APP_KEY=${REVERB_APP_KEY}
REVERB_APP_SECRET=${REVERB_APP_SECRET}
REVERB_HOST=${DOMAIN}
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY=${REVERB_APP_KEY}
VITE_REVERB_HOST=${DOMAIN}
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
ENV

  chown "${APP_USER}:${APP_USER}" "${APP_DIR}/.env"
  chmod 600 "${APP_DIR}/.env"

  # Générer APP_KEY
  cd "$APP_DIR"
  sudo -u "$APP_USER" php artisan key:generate --force
  log ".env créé et APP_KEY généré"
else
  warn ".env déjà existant — non écrasé"
fi

# ─── 9. Installation des dépendances ──────────────────────────────────────────
info "Installation des dépendances PHP et Node..."
cd "$APP_DIR"
sudo -u "$APP_USER" composer install --no-dev --optimize-autoloader --quiet
sudo -u "$APP_USER" npm ci --silent
sudo -u "$APP_USER" npm run build
log "Dépendances installées et assets compilés"

# ─── 10. Migrations & optimisations ───────────────────────────────────────────
info "Migrations et optimisations Laravel..."
cd "$APP_DIR"
sudo -u "$APP_USER" php artisan migrate --force
sudo -u "$APP_USER" php artisan storage:link
sudo -u "$APP_USER" php artisan config:cache
sudo -u "$APP_USER" php artisan route:cache
sudo -u "$APP_USER" php artisan view:cache
log "Migrations et cache Laravel OK"

# ─── 11. Nginx ────────────────────────────────────────────────────────────────
info "Configuration de Nginx..."
rm -f /etc/nginx/sites-enabled/default

cat > /etc/nginx/sites-available/blindbeat <<NGINX
# Redirection HTTP → HTTPS + challenge Let's Encrypt
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 301 https://\$host\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ${DOMAIN} www.${DOMAIN};

    root ${APP_DIR}/public;
    index index.php;

    # SSL — certificats générés par Certbot
    ssl_certificate     /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;
    ssl_session_timeout 1d;
    ssl_session_cache   shared:MozSSL:10m;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;

    # Headers sécurité
    add_header X-Frame-Options        "SAMEORIGIN"                    always;
    add_header X-Content-Type-Options "nosniff"                       always;
    add_header Referrer-Policy        "strict-origin-when-cross-origin" always;

    # Assets statiques — cache long terme
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    # WebSocket Reverb — proxy transparent vers port 8080 local
    location /app/ {
        proxy_pass             http://127.0.0.1:8080;
        proxy_http_version     1.1;
        proxy_set_header       Upgrade \$http_upgrade;
        proxy_set_header       Connection "upgrade";
        proxy_set_header       Host \$host;
        proxy_set_header       X-Real-IP \$remote_addr;
        proxy_set_header       X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header       X-Forwarded-Proto \$scheme;
        proxy_cache_bypass     \$http_upgrade;
        proxy_read_timeout     86400s;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass            unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param           SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include                 fastcgi_params;
        fastcgi_hide_header     X-Powered-By;
        fastcgi_read_timeout    60s;
    }

    location ~ /\.(?!well-known).* { deny all; }

    access_log /var/log/nginx/blindbeat_access.log;
    error_log  /var/log/nginx/blindbeat_error.log warn;
}
NGINX

ln -sf /etc/nginx/sites-available/blindbeat /etc/nginx/sites-enabled/blindbeat
nginx -t
log "Nginx configuré"

# ─── 12. Certbot / Let's Encrypt ──────────────────────────────────────────────
info "Installation de Certbot..."
apt-get install -y -qq certbot python3-certbot-nginx
mkdir -p /var/www/certbot

# Tentative de certificat si le domaine est résolu
if host "$DOMAIN" &>/dev/null 2>&1; then
  systemctl reload nginx
  if certbot --nginx -d "$DOMAIN" -d "www.${DOMAIN}" \
      --non-interactive --agree-tos \
      --email "admin@${DOMAIN}" --redirect 2>/dev/null; then
    log "Certificat SSL obtenu pour ${DOMAIN}"
    # Renouvellement automatique
    (crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet --post-hook 'systemctl reload nginx'") | crontab -
  else
    warn "Certbot a échoué — DNS pas encore propagé ? Lance manuellement :"
    warn "  certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"
  fi
else
  warn "Le domaine ${DOMAIN} ne pointe pas encore vers ce serveur."
  warn "Configure les DNS puis lance : certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"
fi

# ─── 13. Supervisor ───────────────────────────────────────────────────────────
info "Configuration de Supervisor..."
mkdir -p /var/log/supervisor

cat > /etc/supervisor/conf.d/blindbeat.conf <<SUPERVISOR
[program:blindbeat-reverb]
command=php ${APP_DIR}/artisan reverb:start --host=127.0.0.1 --port=8080 --no-interaction
directory=${APP_DIR}
autostart=true
autorestart=true
startretries=10
user=${APP_USER}
redirect_stderr=true
stdout_logfile=/var/log/supervisor/reverb.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5

[program:blindbeat-worker]
command=php ${APP_DIR}/artisan queue:work database --sleep=3 --tries=3 --timeout=90 --max-time=3600
directory=${APP_DIR}
process_name=%(program_name)s_%(process_num)02d
numprocs=2
autostart=true
autorestart=true
startretries=5
user=${APP_USER}
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5

[group:blindbeat]
programs=blindbeat-reverb,blindbeat-worker
SUPERVISOR

# ─── 14. Pare-feu UFW ─────────────────────────────────────────────────────────
info "Configuration du pare-feu..."
ufw --force reset >/dev/null
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw allow ssh >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
# Port 8080 Reverb non exposé — passe par Nginx uniquement
ufw --force enable >/dev/null
log "Pare-feu configuré (SSH + 80 + 443)"

# ─── 15. Fail2ban ─────────────────────────────────────────────────────────────
info "Configuration de Fail2ban..."
cat > /etc/fail2ban/jail.local <<F2B
[DEFAULT]
bantime  = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port    = ssh
logpath = %(sshd_log)s

[nginx-http-auth]
enabled = true
F2B
systemctl enable --now fail2ban >/dev/null
log "Fail2ban actif"

# ─── 16. Démarrage des services ───────────────────────────────────────────────
info "Démarrage de tous les services..."
systemctl enable --now nginx php${PHP_VERSION}-fpm mariadb supervisor
systemctl reload nginx php${PHP_VERSION}-fpm
supervisorctl reread >/dev/null
supervisorctl update >/dev/null
supervisorctl start blindbeat:* >/dev/null 2>&1 || true
log "Services démarrés"

# ─── Récupération des valeurs .env pour le résumé ─────────────────────────────
REVERB_APP_ID=$(grep REVERB_APP_ID "${APP_DIR}/.env" | cut -d= -f2)
REVERB_APP_KEY=$(grep "^REVERB_APP_KEY=" "${APP_DIR}/.env" | cut -d= -f2)
REVERB_APP_SECRET=$(grep REVERB_APP_SECRET "${APP_DIR}/.env" | cut -d= -f2)
APP_KEY_VAL=$(grep "^APP_KEY=" "${APP_DIR}/.env" | cut -d= -f2)
SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')
PRIVATE_KEY=$(cat "${SSH_DIR}/deploy_key")

# ─── Résumé final ─────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  ✅  Initialisation terminée avec succès !${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}📋 GitHub Actions Secrets à configurer :${NC}"
echo -e "   (Settings → Secrets and variables → Actions → New repository secret)\n"
echo -e "   ${BLUE}VPS_HOST${NC}           →  ${SERVER_IP}"
echo -e "   ${BLUE}VPS_USER${NC}           →  ${APP_USER}"
echo -e "   ${BLUE}VPS_APP_DIR${NC}        →  ${APP_DIR}"
echo -e "   ${BLUE}DB_PASSWORD${NC}        →  ${DB_PASSWORD}"
echo -e "   ${BLUE}REVERB_APP_ID${NC}      →  ${REVERB_APP_ID}"
echo -e "   ${BLUE}REVERB_APP_KEY${NC}     →  ${REVERB_APP_KEY}"
echo -e "   ${BLUE}REVERB_APP_SECRET${NC}  →  ${REVERB_APP_SECRET}"
echo -e "   ${BLUE}APP_KEY${NC}            →  ${APP_KEY_VAL}"
echo ""
echo -e "${YELLOW}🔑 VPS_SSH_KEY — clé privée à copier intégralement :${NC}"
echo "-------------------------------------------------------"
echo "$PRIVATE_KEY"
echo "-------------------------------------------------------"
echo ""
echo -e "${YELLOW}📋 Prochaines étapes :${NC}"
echo -e "   1. Configurer les DNS : ${DOMAIN} → ${SERVER_IP}"
echo -e "   2. Ajouter les secrets ci-dessus dans GitHub"
echo -e "   3. Pousser sur main → le déploiement se déclenche automatiquement"
if ! host "$DOMAIN" &>/dev/null 2>&1; then
echo -e "   4. Quand les DNS sont propagés : certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"
fi
echo ""
