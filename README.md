# Calendar V3

A full-screen, wall-mounted family dashboard built for a Raspberry Pi in kiosk mode. Displays family calendars, real-time weather, word of the day, quote of the day, ski conditions, meal plans, and family notes on a portrait-oriented display.

---

## Features

- **Family calendars** — Multiple iCal feeds with per-calendar colour coding, rendered via FullCalendar v6
- **Weather** — Current conditions, 7-day forecast, and hourly breakdown via Open-Meteo (cached hourly)
- **Word of the day** — English definition with French and Spanish translations via dictionaryapi.dev and MyMemory
- **Quote of the day** — Daily quote via dummyjson.com
- **Ski conditions** — Snow reports for local hills (November–April only), scraped nightly via Selenium
- **Meal planning** — Today's recipe from a self-hosted Mealie instance
- **Family notes** — HTML notes page with WiFi and URL QR codes
- **Background images** — Rotating gallery of full-bleed background photos with auto-generated colour schemes
- **GPIO hardware toggles** — Physical switches on the Pi show/hide individual calendars
- **Motion sensor** — PIR sensor wakes the display on activity and blanks it after 15 minutes of idle
- **Admin panel** — Web UI to manage options, images, notes, word schedule, and view API stats

---

## Architecture

This is a **Webpack + PHP + Python** project.

- `src/` — All source files (JS, CSS, PHP, stubs)
- `dist/` — Build output, deployed to the Pi web server (not in git)
- `python_helpers/` — Python services that run on the Pi
- `pi/` — Pi-specific config files (systemd units, Apache vhost, kiosk autostart)

The JavaScript frontend (`src/js/index.js`) is compiled by Webpack. PHP backend files (`src/php/`) are copied into `dist/` by `copy-webpack-plugin` during the build. **Always edit PHP files in `src/php/`, never in `dist/` directly.**

### Python services

| Script | Runs as | Purpose |
|---|---|---|
| `python_helpers/motion.py` | systemd service (root) | PIR motion sensor controls display power; physical buttons send R/T keypresses; 7 GPIO toggle switches write `toggles.json` every second |
| `python_helpers/scrape.py` | systemd timer (www-data) | Headless Chromium scrapes ski conditions from 3 hills into `ski_hills.json`, runs daily at 6am |

---

## Requirements

### Build machine (your Mac/PC)

- **Node.js** v16+ and **npm**

### Raspberry Pi

- **Raspberry Pi OS** Bullseye (11), Bookworm (12), or Trixie (13) — Desktop, 32-bit or 64-bit
- **Apache**, **PHP 8.2+**, **ImageMagick**, **Chromium** — all installed by `install.sh`
- **Python 3** with `RPi.GPIO`, `gpiozero`, `keyboard`, `selenium` — all installed by `install.sh`

---

## First-time install

### 1. On your Mac — build the project

```bash
git clone git@github.com:CanAdrian27/Calendar-V3.git
cd Calendar-V3
npm install
npm run build
```

### 2. Copy to the Pi

Transfer the repo to the Pi (USB drive, `scp`, or clone directly on the Pi):

```bash
# From your Mac — copy the whole repo over
rsync -av --exclude='node_modules' --exclude='dist' \
  ./ [PI User]@[PI Address]:~/Calendar-V3/
```

Or clone directly on the Pi and run `npm run build` there (requires Node.js on the Pi).

### 3. On the Pi — run the install script

```bash
cd ~/Calendar-V3
chmod +x install.sh
./install.sh
```

The script is interactive and will ask two questions before doing anything:

**OS version** — auto-detected from `/etc/os-release`, but you can override:

```
1) Bullseye  (Debian 11)  —  X11 / LXDE
2) Bookworm  (Debian 12)  —  Wayland / Wayfire
3) Trixie    (Debian 13)  —  Wayland / Wayfire
```

**Screen rotation** — for portrait wall-mount installs:

```
0) No rotation
1) 90°  clockwise   (portrait — cable at bottom)
2) 180° upside down
3) 270° clockwise   (portrait — cable at top)
```

`install.sh` then does the following:

1. Installs system packages — correct Chromium package per OS version (`chromium-browser` on Bullseye, `chromium` + `chromium-driver` on Bookworm/Trixie), plus `apache2`, `php`, `php-imagick`, `python3-gpiozero`, GPIO library, and display tools (`xdotool` on X11; `wlopm` on Wayland)
2. Installs Python packages (`keyboard`, `selenium`) via pip, with `--break-system-packages` on Bookworm/Trixie
3. Deploys `dist/` to `/var/www/html/dist/` with correct `www-data` permissions
4. Configures the Apache vhost (points to `/var/www/html/dist/`, disables the default site)
5. Copies Python scripts to `/usr/local/lib/calendar/` and patches `motion.py` to use the correct display-control command for the selected session type (`xset dpms` on X11; `wlopm` on Wayland)
6. Installs and enables the `calendar-motion` systemd service and `calendar-scrape` timer, patching the service environment for Wayland if needed
7. Installs the Chromium kiosk autostart entry (`~/.config/autostart/`)
8. Disables screen blanking — via LXDE autostart on Bullseye, or via `wayfire.ini` idle settings on Bookworm/Trixie
9. Applies screen rotation if requested — writes `display_rotate=N` to the boot config, and adds a Wayfire `[output:HDMI-A-1]` transform on Wayland installs
10. Sets the Pi to boot to desktop with autologin

### 4. Configure

Open the admin panel in a browser on the Pi (or from any device on the same network):

```
http://raspberrypi.local/admin.php
```

Configure settings across the admin tabs:

| Tab | What to set |
|---|---|
| **Dashboard** | Enable/disable pages (recipe, notes, weekly view), set appearance font |
| **Calendar** | Add iCal URLs and calendar names, set colour scheme, enable widgets |
| **Images** | Upload background photos (HEIC supported), crop and rotate |
| **Recipe** | Mealie server URL, username, password |
| **Notes** | WiFi credentials for QR code, Pi address for notes QR code |
| **Word / Quote** | Manage word list, enable French/Spanish translations |
| **Schedule** | Adjust daily refresh times and polling intervals |

### 5. Upload background images

Go to **Images** in the admin panel and upload at least one photo. After upload, use the crop tool to frame the image. Processing (blur strip and colour sample) runs automatically.

### 6. Reboot

```bash
sudo reboot
```

Chromium will open in kiosk mode pointing at `http://localhost/` on every boot.

---

## Subsequent deploys (updating from your Mac)

After making changes, build and push to the Pi with a single command:

```bash
npm run build
./deploy.sh                    # defaults to raspberrypi.local
./deploy.sh 192.168.1.42       # or specify IP directly
```

`deploy.sh` rsyncs `dist/` and the Python scripts over SSH, skips all user data files (`env_vars.php`, `toggles.json`, `schedule.json`, `word/wordlist.json`, `notes/note.html`), fixes permissions, and restarts the motion service.

---

## Hardware setup

### GPIO pin map (BCM numbering)

| BCM Pin | Physical Pin | Function |
|---|---|---|
| GPIO4 | 7 | PIR motion sensor |
| GPIO5 | 29 | Calendar toggle switch 1 |
| GPIO6 | 31 | Calendar toggle switch 2 |
| GPIO12 | 32 | Calendar toggle switch 3 |
| GPIO13 | 33 | Calendar toggle switch 4 |
| GPIO19 | 35 | Calendar toggle switch 5 |
| GPIO16 | 36 | Calendar toggle switch 6 |
| GPIO26 | 37 | Calendar toggle switch 7 |
| GPIO8 | 24 | Refresh button (→ R key) |
| GPIO11 | 23 | Toggle view button (→ T key) |

**Toggle switch wiring:**

```
3.3V ──[Button]── GPIOx
                    │
                 [1kΩ]
                    │
                   GND
```

Each toggle switch needs a 1kΩ resistor (1kΩ–10kΩ works). The refresh and toggle view buttons use the internal pull-up resistor (no external resistor needed).

### Motion sensor

Connect a standard HC-SR501 or compatible PIR sensor to GPIO4 (BCM). The sensor controls display power — turning the screen off after 15 minutes of no motion and back on when motion is detected. `install.sh` patches `motion.py` to use the correct method: `xset dpms` on X11 (Bullseye), or `wlopm` on Wayland (Bookworm/Trixie).

---

## Services

### `calendar-motion` (persistent daemon)

Monitors the PIR sensor, physical buttons, and toggle switches. Starts at boot, restarts automatically on failure.

```bash
# Status and live logs
sudo systemctl status calendar-motion
sudo journalctl -u calendar-motion -f

# Restart after script changes
sudo systemctl restart calendar-motion
```

### `calendar-scrape` (daily timer)

Scrapes ski conditions at 6am daily. Runs as `www-data` so it can write to the web root. If the Pi was off at 6am, the scrape runs immediately on next boot (`Persistent=true`).

```bash
# Check timer status
sudo systemctl status calendar-scrape.timer

# See when the next run is scheduled
sudo systemctl list-timers calendar-scrape.timer

# Run the scrape manually right now
sudo systemctl start calendar-scrape.service

# Live output from last run
sudo journalctl -u calendar-scrape -f
```

---

## File permissions

After install, `/var/www/html/dist/` is owned by `www-data:www-data`. The permission layout:

| Path | Permissions | Rationale |
|---|---|---|
| `dist/` and all files | `644` / `755` | Apache can read everything |
| `dist/images/`, `dist/ski/`, `dist/weather/`, `dist/calendars/`, `dist/word/`, `dist/notes/`, `dist/images_supports/` | `775` | PHP (`www-data`) and the scrape service (`www-data`) can write |
| `dist/env_vars.php` | `644`, owned by `www-data` | PHP can write; saved by the admin panel |
| `dist/toggles.json` | `644`, owned by `www-data` | `motion.py` (root) writes it; PHP reads it |

---

## Troubleshooting

**Display doesn't wake on motion**
```bash
sudo journalctl -u calendar-motion -f
# On X11: check DISPLAY=:0 and XAUTHORITY are correct
# On Wayland: check WAYLAND_DISPLAY=wayland-1 and XDG_RUNTIME_DIR=/run/user/1000
```

**Ski data is stale or missing**
```bash
sudo systemctl start calendar-scrape.service
sudo journalctl -u calendar-scrape -n 50
# Chromedriver version must match installed Chromium:
chromium --version        # Bookworm / Trixie
chromium-browser --version  # Bullseye
chromedriver --version
```

**Admin panel can't save settings** (permission error)
```bash
ls -la /var/www/html/dist/
# env_vars.php should be owned by www-data
sudo chown www-data:www-data /var/www/html/dist/env_vars.php
```

**Calendars not loading**
```bash
# Check iCal fetch
curl "http://raspberrypi.local/fetchCalendar.php?debug=1"
# Or open in browser — the debug page shows each URL and HTTP status
```

**General PHP errors**
```bash
sudo tail -f /var/log/apache2/calendar-error.log
```

---

## Admin Panel

| Page | URL | Purpose |
|---|---|---|
| Dashboard | `admin.php` | Page visibility, appearance, debug tools |
| Calendar | `adminCalendar.php` | Calendar URLs, colour scheme, widgets |
| Images | `adminGallery.php` | Upload, rotate, and crop background images |
| Recipe | `adminRecipe.php` | Mealie server credentials |
| Notes | `adminNotes.php` | Edit notes, WiFi and Pi address for QR codes |
| Word / Quote | `adminWord.php` | Word list, FR/ES translations, force refresh |
| Schedule | `adminSchedule.php` | Daily refresh times and polling intervals |
| Stats | `adminStats.php` | Monitor API call counts and timing |

Each admin page has a **Debug Tools** section (Dashboard tab) with links that open individual PHP endpoints in debug mode — useful for diagnosing API failures without reading log files.

---

## Background Images

Images are uploaded via the admin panel. HEIC (iPhone) photos are supported and automatically converted. After upload, use the crop tool to position the image — output is saved as a transparent PNG. Processing (blur strip and dominant colour sample for theming) runs automatically after upload and after crop/rotate.

---

## Keyboard Shortcuts

| Key | Action |
|---|---|
| `R` | Full refresh with new background image |
| `S` | Refresh calendars only |
| `T` | Toggle view (month → recipe → notes → month) |
| `0–9` | Toggle individual calendars |

These also map to the physical buttons: GPIO8 → `R`, GPIO11 → `T`.

---

## External APIs

| Service | Purpose | Rate |
|---|---|---|
| Open-Meteo | Weather data | Once per hour (cached) |
| dummyjson.com | Quote of the day | Once per day (cached) |
| dictionaryapi.dev | Word definition + phonetics | Once per day (cached) |
| MyMemory | FR/ES translations | Once per day (cached) |
| Mealie (local) | Today's meal plan | Each recipe page load |
| Ski hill websites | Snow conditions (Selenium scrape) | Once per day at 6am |

API call counts and timing are visible in the Stats admin page.
