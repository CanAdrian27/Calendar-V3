# Calendar V3

A full-screen, wall-mounted family dashboard built for a Raspberry Pi in kiosk mode. Displays family calendars, real-time weather, word of the day, quote of the day, ski conditions, meal plans, and family notes on a portrait-oriented display.

---

## Features

- **Family calendars** — Multiple iCal feeds with per-calendar colour coding, rendered via FullCalendar v6
- **Weather** — Current conditions, 7-day forecast, and hourly breakdown via Open-Meteo (cached hourly)
- **Word of the day** — English definition with French and Spanish translations via dictionaryapi.dev and MyMemory
- **Quote of the day** — Daily quote via dummyjson.com
- **Ski conditions** — Snow reports for local hills (November–April only)
- **Meal planning** — Today's recipe from a self-hosted Mealie instance
- **Family notes** — HTML notes page with WiFi and URL QR codes
- **Background images** — Rotating gallery of full-bleed background photos with auto-generated colour schemes
- **GPIO hardware toggles** — Physical switches on the Pi show/hide individual calendars
- **Admin panel** — Web UI to manage options, images, notes, word schedule, and view API stats

## Architecture

This is a **Webpack + PHP** hybrid project.

- `src/` — All source files (JS, CSS, PHP, stubs)
- `dist/` — Build output, deployed to the Pi web server (not in git)

The JavaScript frontend (`src/js/index.js`) is compiled by Webpack. PHP backend files (`src/php/`) are copied into `dist/` by `copy-webpack-plugin` during the build. **Always edit PHP files in `src/php/`, never in `dist/` directly.**

## Requirements

### Local (build machine)

- **Node.js** v16+ and **npm**

### Raspberry Pi (server)

- **Apache** web server
- **PHP 8.2+** with the following extensions:
  - `php-curl` — external API calls
  - `php-imagick` — image processing (upload, rotate, crop)
  - `libheif` / `libheif-dev` — HEIC image support (iPhone photos)
- **ImageMagick** with HEIF/HEIC delegate support

Install on the Pi:
```bash
sudo apt install apache2 php php-curl php-imagick libheif-dev
```

## Installation

### 1. Clone and install dependencies

```bash
git clone git@github.com:CanAdrian27/Calendar-V3.git
cd Calendar-V3
npm install
```

### 2. Build

```bash
npm run build       # single build → dist/
npm run watch       # rebuild on file changes
npm run clean       # delete dist/
```

### 3. Configure

Copy `src/php/env_vars sample.php` to `dist/env_vars.php` on the Pi and fill in your values:

```php
$calendars = [
    'Family'   => 'https://caldav-url/family.ics',
    'Holidays' => 'https://caldav-url/holidays.ics',
];

$showski        = true;   // show ski conditions
$showquote      = true;   // show quote of the day
$showword       = true;   // show word of the day
$showword_fr    = true;   // show French translation
$showword_es    = true;   // show Spanish translation
$showweekly     = false;  // enable week view
$showclock      = true;   // show 24-hour clock
$showmealie     = true;   // enable recipe view
$shownotes      = true;   // enable notes view

$mealieUrl      = '192.168.x.x:9925';   // Mealie host:port
$mealieUsername = 'your@email.com';
$mealiePassword = 'your-password';
```

This file is **not in git** and must be placed manually on the Pi after each deploy.

### 4. Deploy to the Pi

```bash
rsync -av --exclude='env_vars.php' \
          --exclude='schedule.json' \
          --exclude='toggles.json' \
          --exclude='word/wordlist.json' \
          --exclude='notes/note.html' \
          dist/ pi@192.168.x.x:/var/www/html/dist/
```

Files excluded from rsync persist on the Pi across deploys (user config and runtime state).

### 5. First-time Pi setup

Set Apache document root or create a virtual host pointing to `/var/www/html/dist/`. Then visit the admin panel to configure options and upload background images:

```
http://raspberrypi.local/dist/admin.php
```

## Admin Panel

| Page | URL | Purpose |
|---|---|---|
| Options | `admin.php` | Toggle features, configure Mealie/QR/clock |
| Images | `adminGallery.php` | Upload, rotate, and crop background images |
| Notes | `adminNotes.php` | Edit the family notes page |
| Word of Day | `adminWord.php` | Edit word list, force refresh word/quote |
| Schedule | `adminSchedule.php` | Configure refresh intervals |
| Stats | `adminStats.php` | Monitor API call counts and timing |

## Background Images

Images are uploaded via the admin panel. HEIC (iPhone) photos are supported and automatically converted. After upload, use the crop tool to position the image in a 2:1 frame — the output is saved as a transparent PNG so smaller images can be padded rather than stretched.

**Image processing** (blur strip and colour sample for theming) runs automatically on upload and after crop/rotate.

## Keyboard Shortcuts

These work when the display is in focus:

| Key | Action |
|---|---|
| `R` | Full refresh with new background image |
| `S` | Refresh calendars only |
| `T` | Toggle view (month → recipe → notes → month) |
| `0–9` | Toggle individual calendars |

## GPIO Hardware Toggles

The Pi polls `dist/toggles.json` every second. Keys are GPIO pin numbers; `1` = show calendar, `0` = hide. An external script on the Pi updates this file in response to physical switches.

## External APIs

| Service | Purpose | Rate |
|---|---|---|
| Open-Meteo | Weather data | Once per hour (cached) |
| dummyjson.com | Quote of the day | Once per day (cached) |
| dictionaryapi.dev | Word definition + phonetics | Once per day (cached) |
| MyMemory | FR/ES translations | Once per day (cached) |
| Mealie (local) | Today's meal plan | Each recipe page load |

API call counts and timing are visible in the Stats admin page.
