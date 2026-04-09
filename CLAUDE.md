# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A full-screen wall-mounted calendar dashboard for a Raspberry Pi, combining family calendars, real-time weather, ski conditions, and meal planning (via self-hosted Mealie) into a kiosk-mode display.

## Commands

```bash
npm run build    # Single webpack build → dist/
npm run watch    # Continuous rebuild on file changes
npm run clean    # Remove dist/
```

There are no tests. The built `dist/` folder is deployed to the Raspberry Pi web server (PHP/Apache).

## Architecture

This is a hybrid **Webpack/JS frontend + PHP backend** project. Source lives in `src/`, compiled output goes to `dist/`. PHP backend files live in `src/php/` and are copied to `dist/` by `copy-webpack-plugin` during the build — edit them in `src/php/`, never in `dist/` directly.

### Frontend (`src/`)

**Entry point**: `src/js/index.js`

All logic is in a single file. Key functions and their roles:

| Function | Purpose |
|---|---|
| `makeCalendar()` | Initializes FullCalendar v6 with event sources from loaded .ics files |
| `loadWeather()` | Fetches and renders weather data (current + 7-day + hourly) |
| `SelectImages()` | Picks a random background image, triggers color scheme generation |
| `makeRecipe()` | Renders today's meal plan from Mealie API |
| `makeNotes()` | Displays family notes |
| `loadSkiData()` | Renders ski hill snow conditions (November–April only) |

**Initialization order**: Image load → Calendar load → Fetch calendars & weather → Render UI.

**Keyboard shortcuts**: `T` = toggle view mode (month/week/recipe/notes), `R` = full refresh with new image, `S` = calendar-only refresh, number keys = toggle individual calendars.

**GPIO switches**: JavaScript polls `dist/toggles.json` every 1 second. Keys are GPIO pin numbers; value `1` = show calendar, `0` = hide. This file is updated externally by a hardware script on the Pi.

**Refresh intervals**: GPIO: 1s, calendars & weather: 60s, hard page reload: 24h.

### Backend (`dist/*.php`)

PHP files are deployed directly; they are never rebuilt by webpack.

All PHP files below are in `src/php/` (source) and copied to `dist/` on build.

| File | Purpose |
|---|---|
| `env_vars.php` | Central config: calendar URLs, Mealie credentials, `$showski` flag for ski display. **Not in git; must be placed manually on the Pi after deploy.** |
| `fetchCalendar.php` | Downloads .ics files from configured URLs into `dist/calendars/` |
| `loadCalsAndNotes.php` | Lists available calendar files for the frontend |
| `fetchWeather.php` | Calls Open-Meteo API (lat: 46.81, lon: -71.21 — Quebec) and caches to `dist/weather/report.json` |
| `loadWeather.php` | Reads cached weather JSON and serves to frontend |
| `SelectImages.php` | Picks a random image, generates blurred background and dominant color via ImageMagick |
| `processImages.php` | One-time preprocessing: renames images with random hex names, creates blur/color crops |
| `fetchRecipe.php` | Authenticates with Mealie API and fetches today's meal plan |
| `loadSki.php` | Renders ski hill HTML from `dist/ski/ski_hills.json` |
| `toggles.php` | Reads/writes the GPIO toggle state file |

### Key Configuration

- `dist/env_vars.php` — excluded from git; must be configured manually on deployment. Contains calendar iCloud URLs, Mealie credentials (`$mealieUsername`, `$mealiePassword`, `$mealieUrl`), and `$showski` (boolean to show ski data).
- `dist/toggles.json` — GPIO pin states, e.g. `{"17": 1, "27": 0, ...}`.
- `webpack.config.js` — compiles `src/js/index.js` + CSS/SCSS/fonts into `dist/`.

### Deployment

1. `npm run build` locally
2. Copy `dist/` to the Raspberry Pi web server
3. Ensure `env_vars.php` exists on the Pi (not in git)
4. Run `processImages.php` once if adding new background images
