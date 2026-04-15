#!/usr/bin/env bash
# ── Calendar V3 — First-time Pi install ──────────────────────────────────────
# Supports Raspberry Pi OS Bullseye (11), Bookworm (12), and Trixie (13).
# Run once on a fresh install, from the repo root, after `npm run build`.
#
# Usage:
#   chmod +x install.sh && ./install.sh

set -euo pipefail

WEB_ROOT="/var/www/html/dist"
SCRIPTS_DIR="/usr/local/lib/calendar"
AUTOSTART_DIR="/home/pi/.config/autostart"
WAYFIRE_CFG="/home/pi/.config/wayfire.ini"
REPO_DIR="$(cd "$(dirname "$0")" && pwd)"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()    { echo -e "${GREEN}▶ $*${NC}"; }
warn()    { echo -e "${YELLOW}⚠  $*${NC}"; }
error()   { echo -e "${RED}✖ $*${NC}" >&2; exit 1; }
success() { echo -e "${GREEN}✔ $*${NC}"; }
divider() { echo -e "${GREEN}────────────────────────────────────────${NC}"; }

# ── Preflight ─────────────────────────────────────────────────────────────────
[[ "$(uname -m)" == arm* || "$(uname -m)" == aarch64 ]] \
  || warn "Not running on ARM — this script is intended for a Raspberry Pi."

[[ -d "$REPO_DIR/dist" ]] \
  || error "dist/ not found — run 'npm run build' first."

# ── Banner ────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo -e "${GREEN}   Calendar V3 — Raspberry Pi Installer ${NC}"
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo ""

# ── OS version selection ──────────────────────────────────────────────────────
AUTO_VER_ID=$(grep -oP '(?<=VERSION_ID=")[0-9]+' /etc/os-release 2>/dev/null || echo "")
AUTO_CODENAME=$(grep VERSION_CODENAME /etc/os-release 2>/dev/null \
  | cut -d= -f2 | tr -d '"' || echo "unknown")

[[ -n "$AUTO_VER_ID" ]] && echo "  Detected OS: Debian $AUTO_VER_ID ($AUTO_CODENAME)"
echo ""
echo "  ┌─ Select your Raspberry Pi OS version ─────────────────┐"
echo "  │  1)  Bullseye  (Debian 11)  —  X11 / LXDE            │"
echo "  │  2)  Bookworm  (Debian 12)  —  Wayland / Wayfire      │"
echo "  │  3)  Trixie    (Debian 13)  —  Wayland / Wayfire      │"
echo "  └───────────────────────────────────────────────────────┘"
echo ""

DEFAULT_OS=2
[[ "$AUTO_VER_ID" == "11" ]] && DEFAULT_OS=1
[[ "$AUTO_VER_ID" == "12" ]] && DEFAULT_OS=2
[[ "$AUTO_VER_ID" == "13" ]] && DEFAULT_OS=3

read -r -p "  Choice [1-3, default $DEFAULT_OS]: " VERSION_CHOICE
VERSION_CHOICE="${VERSION_CHOICE:-$DEFAULT_OS}"

case "$VERSION_CHOICE" in
  1)
    OS_LABEL="Bullseye (Debian 11)"
    USE_WAYLAND=false
    CHROMIUM_PKGS="chromium-browser chromium-chromedriver"
    BOOT_CONFIG="/boot/config.txt"
    ;;
  2)
    OS_LABEL="Bookworm (Debian 12)"
    USE_WAYLAND=true
    CHROMIUM_PKGS="chromium chromium-driver"
    BOOT_CONFIG="/boot/firmware/config.txt"
    ;;
  3)
    OS_LABEL="Trixie (Debian 13)"
    USE_WAYLAND=true
    CHROMIUM_PKGS="chromium chromium-driver"
    BOOT_CONFIG="/boot/firmware/config.txt"
    ;;
  *)
    error "Invalid choice — run the script again and select 1, 2, or 3."
    ;;
esac

info "Selected: $OS_LABEL"

# ── Screen rotation ───────────────────────────────────────────────────────────
echo ""
echo "  ┌─ Screen rotation ──────────────────────────────────────┐"
echo "  │  0)  No rotation                                       │"
echo "  │  1)  90°  clockwise   (portrait — cable at bottom)     │"
echo "  │  2)  180° upside down                                  │"
echo "  │  3)  270° clockwise   (portrait — cable at top)        │"
echo "  └───────────────────────────────────────────────────────┘"
echo ""
read -r -p "  Rotation [0-3, default 0]: " ROTATION_CHOICE
ROTATION_CHOICE="${ROTATION_CHOICE:-0}"

case "$ROTATION_CHOICE" in
  0) DISPLAY_ROTATE=0; WAYFIRE_TRANSFORM="normal" ;;
  1) DISPLAY_ROTATE=1; WAYFIRE_TRANSFORM="90"     ;;
  2) DISPLAY_ROTATE=2; WAYFIRE_TRANSFORM="180"    ;;
  3) DISPLAY_ROTATE=3; WAYFIRE_TRANSFORM="270"    ;;
  *) warn "Invalid rotation — defaulting to no rotation."
     DISPLAY_ROTATE=0; WAYFIRE_TRANSFORM="normal" ;;
esac

echo ""
divider
info "Starting installation for $OS_LABEL…"
divider

# ── System packages ───────────────────────────────────────────────────────────
info "Installing system packages…"
sudo apt-get update -qq

# GPIO library: python3-rpi.gpio was renamed to python3-rpi-lgpio in Trixie
GPIO_PKG="python3-rpi.gpio"
if [[ "$VERSION_CHOICE" == "3" ]]; then
  apt-cache show python3-rpi-lgpio &>/dev/null && GPIO_PKG="python3-rpi-lgpio" || true
fi

COMMON_PKGS="apache2 php php-curl php-imagick libheif-dev python3-pip $GPIO_PKG python3-gpiozero"

if $USE_WAYLAND; then
  # xdotool is NOT installed — it does not work under Wayland; the `keyboard`
  # Python library handles keystrokes via /dev/input and works on both sessions.
  sudo apt-get install -y $COMMON_PKGS $CHROMIUM_PKGS

  # wlopm: DPMS on/off for wlroots-based Wayland compositors (replaces xset dpms).
  # Installed separately so a missing package doesn't corrupt the main install.
  if apt-cache show wlopm &>/dev/null; then
    sudo apt-get install -y wlopm
  else
    warn "wlopm not found in apt — screen on/off via PIR sensor will not work."
    warn "You can build it from source: https://github.com/varmd/wlopm"
  fi
else
  # Bullseye / X11
  sudo apt-get install -y $COMMON_PKGS $CHROMIUM_PKGS xdotool
fi

# ── Python packages ───────────────────────────────────────────────────────────
info "Installing Python packages…"
if $USE_WAYLAND; then
  # Bookworm+ requires --break-system-packages for pip outside a venv
  sudo pip3 install --break-system-packages keyboard selenium
else
  sudo pip3 install keyboard selenium 2>/dev/null \
    || sudo pip3 install --break-system-packages keyboard selenium
fi
success "Packages installed."

# ── Web root ──────────────────────────────────────────────────────────────────
info "Deploying dist/ → $WEB_ROOT…"
sudo mkdir -p "$WEB_ROOT"
sudo rsync -av \
  --exclude='env_vars.php' \
  --exclude='toggles.json' \
  --exclude='schedule.json' \
  --exclude='word/wordlist.json' \
  --exclude='notes/note.html' \
  "$REPO_DIR/dist/" "$WEB_ROOT/"

sudo chown -R www-data:www-data "$WEB_ROOT"
sudo find "$WEB_ROOT" -type d -exec chmod 755 {} \;
sudo find "$WEB_ROOT" -type f -exec chmod 644 {} \;
for dir in images images_supports weather calendars ski word notes; do
  sudo mkdir -p "$WEB_ROOT/$dir"
  sudo chmod 775 "$WEB_ROOT/$dir"
done
success "Web root deployed."

# ── Apache ────────────────────────────────────────────────────────────────────
info "Configuring Apache…"

# Enable PHP module — detect the installed version (php8.2, php8.3, etc.)
PHP_MOD=$(ls /etc/apache2/mods-available/php*.conf 2>/dev/null \
  | head -1 | xargs -r basename | sed 's/\.conf//')
if [[ -n "$PHP_MOD" ]]; then
  sudo a2enmod "$PHP_MOD"
  info "Enabled Apache module: $PHP_MOD"
else
  warn "No PHP Apache module found — PHP pages may not work."
fi

# Enable mod_rewrite (required for AllowOverride All in the vhost)
sudo a2enmod rewrite

sudo cp "$REPO_DIR/pi/apache/calendar.conf" /etc/apache2/sites-available/calendar.conf
sudo a2ensite calendar
sudo a2dissite 000-default.conf 2>/dev/null || true

# Enable Apache at boot and start (or restart) it now
sudo systemctl enable apache2
sudo systemctl restart apache2
success "Apache configured and started."

# ── Python scripts ────────────────────────────────────────────────────────────
info "Installing Python scripts → $SCRIPTS_DIR…"
sudo mkdir -p "$SCRIPTS_DIR"
sudo cp "$REPO_DIR/python_helpers/motion.py" "$SCRIPTS_DIR/"
sudo cp "$REPO_DIR/python_helpers/scrape.py"  "$SCRIPTS_DIR/"
sudo chmod 755 "$SCRIPTS_DIR/"*.py

# Patch motion.py display on/off for Wayland (xset dpms → wlopm)
if $USE_WAYLAND; then
  sudo sed -i \
    -e "s|XAUTHORITY=/home/pi/.Xauthority DISPLAY=:0 xset dpms force on|WAYLAND_DISPLAY=wayland-1 XDG_RUNTIME_DIR=/run/user/1000 wlopm --on HDMI-A-1|g" \
    -e "s|XAUTHORITY=/home/pi/.Xauthority DISPLAY=:0 xset dpms force off|WAYLAND_DISPLAY=wayland-1 XDG_RUNTIME_DIR=/run/user/1000 wlopm --off HDMI-A-1|g" \
    "$SCRIPTS_DIR/motion.py"
  info "Patched motion.py: xset dpms → wlopm"
fi

success "Python scripts installed."

# ── Systemd services ──────────────────────────────────────────────────────────
info "Installing systemd services…"
sudo cp "$REPO_DIR/pi/services/"*.service /etc/systemd/system/
sudo cp "$REPO_DIR/pi/services/"*.timer   /etc/systemd/system/

# Patch calendar-motion.service for the correct display environment
if $USE_WAYLAND; then
  sudo sed -i \
    -e 's|# X display access for xset dpms and keyboard injection|# Wayland display environment for wlopm (DPMS) and keyboard injection|' \
    -e 's|Environment=DISPLAY=:0||' \
    -e 's|Environment=XAUTHORITY=/home/pi/.Xauthority|Environment=WAYLAND_DISPLAY=wayland-1\nEnvironment=XDG_RUNTIME_DIR=/run/user/1000|' \
    /etc/systemd/system/calendar-motion.service
fi

sudo systemctl daemon-reload
sudo systemctl enable --now calendar-motion.service
sudo systemctl enable --now calendar-scrape.timer
success "Services enabled and started."

# ── Kiosk autostart ───────────────────────────────────────────────────────────
info "Configuring Chromium kiosk autostart…"
mkdir -p "$AUTOSTART_DIR"
cp "$REPO_DIR/pi/autostart/calendar-kiosk.desktop" "$AUTOSTART_DIR/"

# Bullseye uses chromium-browser; Bookworm/Trixie use chromium
if ! $USE_WAYLAND; then
  sed -i 's|^Exec=chromium |Exec=chromium-browser |' "$AUTOSTART_DIR/calendar-kiosk.desktop"
fi
success "Kiosk autostart configured."

# ── Screen blanking ───────────────────────────────────────────────────────────
info "Disabling screen blanking…"
if $USE_WAYLAND; then
  # Wayfire: set dpms_timeout and screensaver_timeout to -1 in wayfire.ini
  mkdir -p "$(dirname "$WAYFIRE_CFG")"
  touch "$WAYFIRE_CFG"
  chown pi:pi "$WAYFIRE_CFG" 2>/dev/null || true

  if grep -q '^\[idle\]' "$WAYFIRE_CFG" 2>/dev/null; then
    # [idle] section already exists — update in place
    sudo -u pi sed -i \
      '/^\[idle\]/,/^\[/{s/^screensaver_timeout.*/screensaver_timeout = -1/;s/^dpms_timeout.*/dpms_timeout = -1/}' \
      "$WAYFIRE_CFG"
    # Add missing keys if they were absent from the section
    grep -A10 '^\[idle\]' "$WAYFIRE_CFG" | grep -q 'screensaver_timeout' \
      || sudo -u pi sed -i '/^\[idle\]/a screensaver_timeout = -1' "$WAYFIRE_CFG"
    grep -A10 '^\[idle\]' "$WAYFIRE_CFG" | grep -q 'dpms_timeout' \
      || sudo -u pi sed -i '/^\[idle\]/a dpms_timeout = -1' "$WAYFIRE_CFG"
  else
    printf '\n[idle]\nscreensaver_timeout = -1\ndpms_timeout = -1\n' \
      | sudo -u pi tee -a "$WAYFIRE_CFG" > /dev/null
  fi
else
  # X11 / LXDE-pi: append xset commands to session autostart
  LXDE_AUTO="/etc/xdg/lxsession/LXDE-pi/autostart"
  if [[ -f "$LXDE_AUTO" ]] && ! grep -q "xset s off" "$LXDE_AUTO"; then
    echo "@xset s off"     | sudo tee -a "$LXDE_AUTO" > /dev/null
    echo "@xset -dpms"     | sudo tee -a "$LXDE_AUTO" > /dev/null
    echo "@xset s noblank" | sudo tee -a "$LXDE_AUTO" > /dev/null
  fi
fi
success "Screen blanking disabled."

# ── Screen rotation ───────────────────────────────────────────────────────────
if [[ "$DISPLAY_ROTATE" -ne 0 ]]; then
  info "Configuring screen rotation…"

  if $USE_WAYLAND; then
    # Wayland / Wayfire — rotation is handled entirely by the compositor.
    # Do NOT also write display_rotate to config.txt: with the KMS driver the
    # firmware rotation and Wayfire transform both apply, causing double rotation.

    # Detect the connected HDMI output name from sysfs (e.g. HDMI-A-1, HDMI-A-2)
    HDMI_OUTPUT="HDMI-A-1"  # fallback
    for card in /sys/class/drm/card*-HDMI-A-*; do
      if [[ -f "$card/status" ]] && grep -q "^connected" "$card/status" 2>/dev/null; then
        HDMI_OUTPUT="${card##*card*-}"
        break
      fi
    done
    info "Detected Wayland output: $HDMI_OUTPUT"

    mkdir -p "$(dirname "$WAYFIRE_CFG")"
    touch "$WAYFIRE_CFG"
    chown pi:pi "$WAYFIRE_CFG" 2>/dev/null || true

    SECTION="[output:$HDMI_OUTPUT]"
    if grep -q "^\[output:${HDMI_OUTPUT}\]" "$WAYFIRE_CFG" 2>/dev/null; then
      # Section exists — update or insert transform line
      if grep -A5 "^\[output:${HDMI_OUTPUT}\]" "$WAYFIRE_CFG" | grep -q '^transform'; then
        sudo -u pi sed -i \
          "/^\[output:${HDMI_OUTPUT}\]/,/^\[/{s/^transform.*/transform = ${WAYFIRE_TRANSFORM}/}" \
          "$WAYFIRE_CFG"
      else
        sudo -u pi sed -i \
          "/^\[output:${HDMI_OUTPUT}\]/a transform = ${WAYFIRE_TRANSFORM}" "$WAYFIRE_CFG"
      fi
    else
      printf '\n[output:%s]\ntransform = %s\n' "$HDMI_OUTPUT" "$WAYFIRE_TRANSFORM" \
        | sudo -u pi tee -a "$WAYFIRE_CFG" > /dev/null
    fi
    success "Wayfire: [output:$HDMI_OUTPUT] transform=$WAYFIRE_TRANSFORM written to $WAYFIRE_CFG"

  else
    # X11 / Bullseye — firmware-level rotation via config.txt
    if [[ -f "$BOOT_CONFIG" ]]; then
      sudo sed -i '/^display_rotate/d' "$BOOT_CONFIG"
      echo "display_rotate=$DISPLAY_ROTATE" | sudo tee -a "$BOOT_CONFIG" > /dev/null
      success "Firmware: display_rotate=$DISPLAY_ROTATE written to $BOOT_CONFIG"
    else
      warn "$BOOT_CONFIG not found — skipping firmware rotation."
    fi
  fi
fi

# ── Boot to desktop ───────────────────────────────────────────────────────────
info "Setting boot to desktop with autologin…"
sudo raspi-config nonint do_boot_behaviour B4 2>/dev/null \
  || warn "raspi-config not available — set boot behaviour to desktop autologin manually."
success "Boot behaviour set."

# ── Done ──────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo -e "${GREEN}   Install complete!                     ${NC}"
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo ""
echo "  OS:      $OS_LABEL"
echo "  Session: $( $USE_WAYLAND && echo 'Wayland / Wayfire' || echo 'X11 / LXDE' )"
if [[ "$DISPLAY_ROTATE" -ne 0 ]]; then
  if $USE_WAYLAND; then
    echo "  Rotation: $WAYFIRE_TRANSFORM via Wayfire [output:${HDMI_OUTPUT:-HDMI-A-1}]"
  else
    echo "  Rotation: display_rotate=$DISPLAY_ROTATE in $BOOT_CONFIG"
  fi
fi
echo ""
echo "  Next steps:"
echo "  1. Visit http://localhost/ to verify the display loads"
echo "  2. Visit http://localhost/admin.php to configure settings"
echo "  3. Upload background images via the Images tab"
echo "  4. Reboot to launch Chromium in kiosk mode:"
echo "       sudo reboot"
echo ""
echo "  Service status:"
echo "    sudo systemctl status calendar-motion"
echo "    sudo systemctl status calendar-scrape.timer"
echo ""
