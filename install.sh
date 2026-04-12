#!/usr/bin/env bash
# ── Calendar V3 — First-time Pi install ──────────────────────────────────────
# Run once on a fresh Raspberry Pi OS (Bullseye or Bookworm) install.
# Must be run from the repo root after `npm run build`.
#
# Usage:
#   chmod +x install.sh
#   ./install.sh
#
set -euo pipefail

WEB_ROOT="/var/www/html/dist"
SCRIPTS_DIR="/usr/local/lib/calendar"
AUTOSTART_DIR="/home/pi/.config/autostart"
REPO_DIR="$(cd "$(dirname "$0")" && pwd)"

# Colours for output
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()    { echo -e "${GREEN}▶ $*${NC}"; }
warn()    { echo -e "${YELLOW}⚠ $*${NC}"; }
error()   { echo -e "${RED}✖ $*${NC}" >&2; exit 1; }
success() { echo -e "${GREEN}✔ $*${NC}"; }

# ── Preflight ──────────────────────────────────────────────────────────────────
[[ "$(uname -m)" == arm* || "$(uname -m)" == aarch64 ]] \
  || warn "Not running on ARM — this script is intended for a Raspberry Pi."

[[ -d "$REPO_DIR/dist" ]] \
  || error "dist/ not found. Run 'npm run build' first."

# ── System packages ────────────────────────────────────────────────────────────
info "Installing system packages…"
sudo apt-get update -qq
sudo apt-get install -y \
  apache2 \
  php php-curl php-imagick \
  libheif-dev \
  chromium-browser chromium-chromedriver \
  python3-pip python3-rpi.gpio python3-gpiozero \
  xdotool

# Python packages not in apt
info "Installing Python packages…"
sudo pip3 install --break-system-packages keyboard selenium 2>/dev/null \
  || sudo pip3 install keyboard selenium

success "Packages installed."

# ── Web root ────────────────────────────────────────────────────────────────────
info "Deploying dist/ → $WEB_ROOT…"
sudo mkdir -p "$WEB_ROOT"

# Rsync dist/ but preserve any existing user data files
sudo rsync -av \
  --exclude='env_vars.php' \
  --exclude='toggles.json' \
  --exclude='schedule.json' \
  --exclude='word/wordlist.json' \
  --exclude='notes/note.html' \
  "$REPO_DIR/dist/" "$WEB_ROOT/"

# Apache needs write access for PHP-written files (env_vars, uploads, caches)
sudo chown -R www-data:www-data "$WEB_ROOT"
sudo find "$WEB_ROOT" -type d -exec chmod 755 {} \;
sudo find "$WEB_ROOT" -type f -exec chmod 644 {} \;
# Directories that PHP writes into need group-write for www-data
for dir in images images_supports weather calendars ski word notes; do
  sudo mkdir -p "$WEB_ROOT/$dir"
  sudo chmod 775 "$WEB_ROOT/$dir"
done

success "Web root deployed."

# ── Apache config ────────────────────────────────────────────────────────────────
info "Configuring Apache…"
sudo cp "$REPO_DIR/pi/apache/calendar.conf" /etc/apache2/sites-available/calendar.conf
sudo a2ensite calendar
sudo a2dissite 000-default.conf 2>/dev/null || true
sudo systemctl reload apache2

success "Apache configured."

# ── Python scripts ────────────────────────────────────────────────────────────────
info "Installing Python scripts → $SCRIPTS_DIR…"
sudo mkdir -p "$SCRIPTS_DIR"
sudo cp "$REPO_DIR/python_helpers/motion.py" "$SCRIPTS_DIR/"
sudo cp "$REPO_DIR/python_helpers/scrape.py"  "$SCRIPTS_DIR/"
sudo chmod 755 "$SCRIPTS_DIR/"*.py

success "Python scripts installed."

# ── Systemd services ──────────────────────────────────────────────────────────────
info "Installing systemd services…"
sudo cp "$REPO_DIR/pi/services/"*.service /etc/systemd/system/
sudo cp "$REPO_DIR/pi/services/"*.timer   /etc/systemd/system/
sudo systemctl daemon-reload

sudo systemctl enable --now calendar-motion.service
sudo systemctl enable --now calendar-scrape.timer

success "Services enabled and started."

# ── Chromium kiosk autostart ──────────────────────────────────────────────────────
info "Configuring Chromium kiosk autostart…"
mkdir -p "$AUTOSTART_DIR"
cp "$REPO_DIR/pi/autostart/calendar-kiosk.desktop" "$AUTOSTART_DIR/"

# Disable screen blanking and DPMS via LXDE autostart (Bullseye / X11)
LXDE_AUTO="/etc/xdg/lxsession/LXDE-pi/autostart"
if [[ -f "$LXDE_AUTO" ]]; then
  if ! grep -q "xset s off" "$LXDE_AUTO"; then
    echo "@xset s off" | sudo tee -a "$LXDE_AUTO" > /dev/null
    echo "@xset -dpms" | sudo tee -a "$LXDE_AUTO" > /dev/null
    echo "@xset s noblank" | sudo tee -a "$LXDE_AUTO" > /dev/null
  fi
fi

success "Kiosk autostart configured."

# ── Boot to desktop (not CLI) ──────────────────────────────────────────────────
info "Ensuring Pi boots to desktop…"
sudo raspi-config nonint do_boot_behaviour B4   # Desktop autologin

success "Boot behaviour set to desktop autologin."

# ── Done ──────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo -e "${GREEN}  Install complete!${NC}"
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo ""
echo "  Next steps:"
echo "  1. Open http://localhost/ in a browser to verify the display loads"
echo "  2. Visit http://localhost/admin.php to configure settings"
echo "  3. Upload background images via the Images tab"
echo "  4. Reboot to launch Chromium in kiosk mode: sudo reboot"
echo ""
echo "  Service status:"
echo "    sudo systemctl status calendar-motion"
echo "    sudo systemctl status calendar-scrape.timer"
echo ""
