#!/usr/bin/env bash
# ── Calendar V3 — Re-deploy to Pi ────────────────────────────────────────────
# Run after `npm run build` to push an updated dist/ to the Pi.
# Safe to run repeatedly — preserves all user data and runtime config.
#
# Usage (from your Mac):
#   ./deploy.sh [pi-host]          # defaults to raspberrypi.local
#
# Examples:
#   ./deploy.sh
#   ./deploy.sh 192.168.1.42
#   ./deploy.sh pi@raspberrypi.local
#
set -euo pipefail

PI_HOST="${1:-raspberrypi.local}"
PI_USER="pi"
WEB_ROOT="/var/www/html/dist"
REPO_DIR="$(cd "$(dirname "$0")" && pwd)"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()  { echo -e "${GREEN}▶ $*${NC}"; }
warn()  { echo -e "${YELLOW}⚠ $*${NC}"; }
error() { echo -e "${RED}✖ $*${NC}" >&2; exit 1; }

# Strip user@ prefix if provided so we can reconstruct cleanly
if [[ "$PI_HOST" == *@* ]]; then
  PI_USER="${PI_HOST%%@*}"
  PI_HOST="${PI_HOST##*@}"
fi

TARGET="$PI_USER@$PI_HOST"

[[ -d "$REPO_DIR/dist" ]] \
  || error "dist/ not found. Run 'npm run build' first."

# ── Sync dist/ ────────────────────────────────────────────────────────────────
info "Syncing dist/ → $TARGET:${WEB_ROOT}…"
rsync -avz --progress \
  --exclude='env_vars.php' \
  --exclude='toggles.json' \
  --exclude='schedule.json' \
  --exclude='word/wordlist.json' \
  --exclude='notes/note.html' \
  "$REPO_DIR/dist/" \
  "$TARGET:$WEB_ROOT/"

# ── Sync Python scripts ────────────────────────────────────────────────────────
info "Syncing Python scripts…"
rsync -avz \
  "$REPO_DIR/python_helpers/motion.py" \
  "$REPO_DIR/python_helpers/scrape.py" \
  "$TARGET:/usr/local/lib/calendar/" 2>/dev/null \
  || ssh "$TARGET" "sudo rsync -av \
      $WEB_ROOT/../calendar/motion.py \
      $WEB_ROOT/../calendar/scrape.py \
      /usr/local/lib/calendar/" 2>/dev/null || true

# ── Fix permissions ───────────────────────────────────────────────────────────
info "Fixing web root permissions…"
ssh "$TARGET" "sudo chown -R www-data:www-data $WEB_ROOT && \
  sudo find $WEB_ROOT -type d -exec chmod 755 {} \\; && \
  sudo find $WEB_ROOT -type f -exec chmod 644 {} \\; && \
  for d in images images_supports weather calendars ski word notes; do \
    sudo mkdir -p $WEB_ROOT/\$d && sudo chmod 775 $WEB_ROOT/\$d; \
  done"

# ── Restart motion service (picks up script changes) ─────────────────────────
info "Restarting calendar-motion service…"
ssh "$TARGET" "sudo systemctl restart calendar-motion" || warn "Could not restart calendar-motion (is it installed?)"

echo ""
echo -e "${GREEN}✔ Deploy complete — $TARGET${NC}"
echo "  Dashboard: http://$PI_HOST/admin.php"
echo ""
