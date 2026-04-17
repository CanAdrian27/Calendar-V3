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
LABWC_CFG_DIR="/home/pi/.config/labwc"
LABWC_AUTOSTART="/home/pi/.config/labwc/autostart"
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
echo "  │  3)  Trixie    (Debian 13)  —  Wayland / labwc        │"
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

# Compositor: Bookworm uses Wayfire; Trixie uses labwc
COMPOSITOR="wayfire"
[[ "$VERSION_CHOICE" == "3" ]] && COMPOSITOR="labwc"

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

COMMON_PKGS="apache2 php libapache2-mod-php php-curl php-imagick libheif-dev python3-pip $GPIO_PKG python3-gpiozero"

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

  # wlr-randr: output configuration for labwc (screen rotation)
  if [[ "$COMPOSITOR" == "labwc" ]]; then
    if apt-cache show wlr-randr &>/dev/null; then
      sudo apt-get install -y wlr-randr
    else
      warn "wlr-randr not found — screen rotation will not be configured."
    fi
  fi
else
  # Bullseye / X11
  sudo apt-get install -y $COMMON_PKGS $CHROMIUM_PKGS xdotool
fi

# ── Python packages ───────────────────────────────────────────────────────────
info "Installing Python packages…"
# Fall back to --no-deps if pip conflicts with an apt-managed package (e.g. urllib3)
# --ignore-installed bypasses the uninstall step, avoiding conflicts with
# apt-managed packages that have no pip RECORD file (e.g. urllib3 on Trixie)
sudo pip3 install --break-system-packages --ignore-installed keyboard selenium
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

# ── Default configuration ─────────────────────────────────────────────────────
# Create env_vars.php with sensible defaults if it doesn't already exist.
# This is skipped on re-deploys so existing settings are never overwritten.
if [[ ! -f "$WEB_ROOT/env_vars.php" ]]; then
  PI_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
  PI_URL="${PI_IP:+http://${PI_IP}}"
  sudo tee "$WEB_ROOT/env_vars.php" > /dev/null <<EOF
<?php
\$weather_lat      = 46.8139;
\$weather_lon      = -71.208;
\$weather_timezone = 'America/Toronto';
\$pi_base_url      = '${PI_URL}';
EOF
  sudo chown www-data:www-data "$WEB_ROOT/env_vars.php"
  sudo chmod 644 "$WEB_ROOT/env_vars.php"
  info "Created default env_vars.php — location: Quebec City, Pi URL: ${PI_URL:-not detected}"
else
  info "env_vars.php already exists — skipping default creation."
fi

# ── PHP configuration ─────────────────────────────────────────────────────────
info "Configuring PHP upload limits…"

# Detect the installed PHP version (8.2, 8.3, etc.)
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "")
if [[ -n "$PHP_VER" ]]; then
  PHP_INI_DIR="/etc/php/$PHP_VER/apache2/conf.d"
  sudo mkdir -p "$PHP_INI_DIR"
  sudo tee "$PHP_INI_DIR/99-calendar.ini" > /dev/null <<EOF
; Calendar V3 — increased limits for image uploads via the admin panel
upload_max_filesize = 50M
post_max_size       = 55M
max_file_uploads    = 20
memory_limit        = 256M
EOF
  success "PHP limits set (upload 50M, post 55M, memory 256M) in $PHP_INI_DIR/99-calendar.ini"
else
  warn "Could not detect PHP version — set upload_max_filesize and post_max_size manually in php.ini"
fi

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

# Enable mod_rewrite
sudo a2enmod rewrite

# Point the default site at dist/ rather than creating a new vhost
# (avoids a2ensite/a2dissite ordering issues on fresh installs)
DEFAULT_CONF="/etc/apache2/sites-available/000-default.conf"
sudo sed -i "s|DocumentRoot /var/www/html$|DocumentRoot $WEB_ROOT|" "$DEFAULT_CONF"
# Add or replace the Directory block to grant access and allow .htaccess
if grep -q "<Directory /var/www/html" "$DEFAULT_CONF"; then
  sudo sed -i "s|<Directory /var/www/html[^>]*>|<Directory $WEB_ROOT>|" "$DEFAULT_CONF"
else
  sudo sed -i "/<\/VirtualHost>/i\\
\\t<Directory $WEB_ROOT>\\
\\t\\tOptions -Indexes +FollowSymLinks\\
\\t\\tAllowOverride All\\
\\t\\tRequire all granted\\
\\t<\\/Directory>" "$DEFAULT_CONF"
fi
sudo a2ensite 000-default

# Validate config before restarting
sudo apache2ctl configtest 2>&1 | grep -v "^AH00558" \
  || error "Apache config test failed — check the output above."

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
    -e "s|XAUTHORITY=/home/pi/.Xauthority DISPLAY=:0 xset dpms force on|WAYLAND_DISPLAY=wayland-1 XDG_RUNTIME_DIR=/run/user/1000 wlopm --on|g" \
    -e "s|XAUTHORITY=/home/pi/.Xauthority DISPLAY=:0 xset dpms force off|WAYLAND_DISPLAY=wayland-1 XDG_RUNTIME_DIR=/run/user/1000 wlopm --off|g" \
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

CHROMIUM_BIN="chromium"
$USE_WAYLAND || CHROMIUM_BIN="chromium-browser"   # Bullseye uses chromium-browser

# Flags common to all platforms
CHROMIUM_FLAGS=(
  --kiosk
  --noerrdialogs
  --disable-infobars
  --disable-session-crashed-bubble
  --disable-restore-session-state
  --no-first-run
  --disable-sync
  --password-store=basic
  --user-data-dir=/tmp/chromium-kiosk
  --check-for-update-interval=31536000
)
$USE_WAYLAND && CHROMIUM_FLAGS+=(--ozone-platform=wayland)

if [[ "$COMPOSITOR" == "labwc" ]]; then
  # labwc: write chromium directly to the labwc shell autostart — more reliable
  # than XDG .desktop files which may not be processed by the Pi's labwc session.
  mkdir -p "$LABWC_CFG_DIR"
  chown pi:pi "$LABWC_CFG_DIR" 2>/dev/null || true
  touch "$LABWC_AUTOSTART"
  chown pi:pi "$LABWC_AUTOSTART" 2>/dev/null || true
  # Remove any existing chromium line, then append — use a variable so the
  # command is guaranteed to land on one line regardless of terminal width
  sudo -u pi sed -i '/chromium/d' "$LABWC_AUTOSTART" 2>/dev/null || true
  _chromium_cmd="(sleep 8 && $CHROMIUM_BIN ${CHROMIUM_FLAGS[*]} http://localhost/) &"
  echo "$_chromium_cmd" | sudo -u pi tee -a "$LABWC_AUTOSTART" > /dev/null

  # Hide cursor: set XCURSOR_SIZE=0 in labwc environment
  LABWC_ENV="$LABWC_CFG_DIR/environment"
  sudo -u pi touch "$LABWC_ENV"
  grep -q '^XCURSOR_SIZE' "$LABWC_ENV" 2>/dev/null \
    || echo "XCURSOR_SIZE=0" | sudo -u pi tee -a "$LABWC_ENV" > /dev/null

  success "labwc autostart: Chromium kiosk → $LABWC_AUTOSTART"
else
  # Wayfire / X11: XDG .desktop autostart
  mkdir -p "$AUTOSTART_DIR"
  cp "$REPO_DIR/pi/autostart/calendar-kiosk.desktop" "$AUTOSTART_DIR/"
  [[ "$CHROMIUM_BIN" != "chromium" ]] && \
    sed -i "s|^Exec=chromium |Exec=$CHROMIUM_BIN |" "$AUTOSTART_DIR/calendar-kiosk.desktop"
  $USE_WAYLAND && \
    sed -i "s|--disable-sync|--disable-sync \\\\\n  --ozone-platform=wayland|" \
      "$AUTOSTART_DIR/calendar-kiosk.desktop"
  success "XDG autostart: Chromium kiosk → $AUTOSTART_DIR/calendar-kiosk.desktop"
fi

# ── Screen blanking ───────────────────────────────────────────────────────────
info "Disabling screen blanking…"
if $USE_WAYLAND; then
  if [[ "$COMPOSITOR" == "labwc" ]]; then
    # labwc: screen blanking is managed by the PIR sensor service via wlopm — no static config needed
    info "labwc: screen blanking managed by PIR sensor service (wlopm)."
  else
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
  fi  # end compositor == wayfire
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
    # Detect the connected HDMI output name from sysfs (e.g. HDMI-A-1, HDMI-A-2)
    HDMI_OUTPUT="HDMI-A-1"  # fallback
    for card in /sys/class/drm/card*-HDMI-A-*; do
      if [[ -f "$card/status" ]] && grep -q "^connected" "$card/status" 2>/dev/null; then
        _card_base="${card##*/}"          # strip path → card1-HDMI-A-2
        HDMI_OUTPUT="${_card_base#*-}"    # strip cardN- → HDMI-A-2
        break
      fi
    done
    info "Detected Wayland output: $HDMI_OUTPUT"

    if [[ "$COMPOSITOR" == "labwc" ]]; then
      # labwc: apply rotation via wlr-randr in the labwc autostart file.
      # Do NOT write display_rotate to config.txt — KMS + compositor both applying
      # rotation causes double rotation.
      mkdir -p "$LABWC_CFG_DIR"
      chown pi:pi "$LABWC_CFG_DIR" 2>/dev/null || true
      touch "$LABWC_AUTOSTART"
      chown pi:pi "$LABWC_AUTOSTART" 2>/dev/null || true
      # Remove any existing rotation line, then append the new one
      sudo -u pi sed -i '/wlr-randr.*--transform/d' "$LABWC_AUTOSTART" 2>/dev/null || true
      echo "wlr-randr --output $HDMI_OUTPUT --transform $WAYFIRE_TRANSFORM" \
        | sudo -u pi tee -a "$LABWC_AUTOSTART" > /dev/null
      success "labwc: wlr-randr --output $HDMI_OUTPUT --transform $WAYFIRE_TRANSFORM → $LABWC_AUTOSTART"

    else
      # Wayfire — rotation is handled entirely by the compositor.
      # Do NOT also write display_rotate to config.txt: with the KMS driver the
      # firmware rotation and Wayfire transform both apply, causing double rotation.
      mkdir -p "$(dirname "$WAYFIRE_CFG")"
      touch "$WAYFIRE_CFG"
      chown pi:pi "$WAYFIRE_CFG" 2>/dev/null || true

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
    fi

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
if $USE_WAYLAND; then
  echo "  Session: Wayland / $COMPOSITOR"
else
  echo "  Session: X11 / LXDE"
fi
if [[ "$DISPLAY_ROTATE" -ne 0 ]]; then
  if $USE_WAYLAND; then
    if [[ "$COMPOSITOR" == "labwc" ]]; then
      echo "  Rotation: transform=$WAYFIRE_TRANSFORM via wlr-randr (labwc) [output:${HDMI_OUTPUT:-HDMI-A-1}]"
    else
      echo "  Rotation: transform=$WAYFIRE_TRANSFORM via Wayfire [output:${HDMI_OUTPUT:-HDMI-A-1}]"
    fi
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
