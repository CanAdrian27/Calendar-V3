"""
Ski condition scraper — scrapes Le Relais, Stoneham, and Mont Sainte-Anne
and writes the results to /var/www/html/dist/ski/ski_hills.json.

Run once daily via the calendar-scrape.timer systemd timer.
Requires: chromium-chromedriver (apt), selenium (pip)
"""

import re
import json
import sys
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By

OUTPUT_FILE = '/var/www/html/dist/ski/ski_hills.json'
CHROMEDRIVER  = '/usr/bin/chromedriver'

options = Options()
options.add_argument('--headless')
options.add_argument('--no-sandbox')
options.add_argument('--disable-dev-shm-usage')
# www-data has no home dir, so give Chromium an explicit writable profile location
options.add_argument('--user-data-dir=/tmp/chromium-scrape')

def get_driver():
    service = Service(CHROMEDRIVER)
    return webdriver.Chrome(service=service, options=options)

def strip_tags(html):
    """Return the text before the first HTML tag."""
    m = re.search(r'^(.*?)(?=<|$)', html, re.DOTALL)
    return m.group(1).strip() if m else html.strip()

def scrape_relais(driver):
    driver.get('https://www.skirelais.com/en/mountain/trails-snow-conditions/')

    base = "//article/div/div[1]/div[1]/div/table/tbody/tr"
    def cell(row, col): return f"{base}[{row}]/td[{col}]"

    day_trails_html  = driver.find_element(By.XPATH, f"{cell(2,2)}/div/span").get_attribute("innerHTML")
    day_trail_of     = driver.find_element(By.XPATH, f"{cell(2,2)}/div/span/span/sup").get_attribute("innerHTML")
    day_lifts_html   = driver.find_element(By.XPATH, f"{cell(2,5)}/div/span").get_attribute("innerHTML")
    day_lifts_of     = driver.find_element(By.XPATH, f"{cell(2,5)}/div/span/span/sup").get_attribute("innerHTML")
    night_trails     = driver.find_element(By.XPATH, f"{cell(3,2)}/span").get_attribute("innerHTML")
    night_lifts      = driver.find_element(By.XPATH, f"{cell(3,5)}/span").get_attribute("innerHTML")

    snow_base = "//article/div/div[1]/div[2]/div/table/tbody/tr"
    snowfall_24h    = driver.find_element(By.XPATH, f"{snow_base}[2]/td[2]").get_attribute("innerHTML")
    snowfall_week   = driver.find_element(By.XPATH, f"{snow_base}[3]/td[2]").get_attribute("innerHTML")
    snowfall_season = driver.find_element(By.XPATH, f"{snow_base}[4]/td[2]").get_attribute("innerHTML")

    return {
        "name": "Le Relais",
        "day_trails_open":  (strip_tags(day_trails_html) + day_trail_of).replace(" ", ""),
        "day_lifts_open":   (strip_tags(day_lifts_html)  + day_lifts_of).replace(" ", ""),
        "night_trail_open": strip_tags(night_trails).replace(" ", ""),
        "night_lifts_open": strip_tags(night_lifts).replace(" ", ""),
        "snowfall_24h":     snowfall_24h.strip(),
        "snowfall_week":    snowfall_week.strip(),
        "snowfall_season":  snowfall_season.strip(),
    }

def scrape_stoneham(driver):
    driver.get('https://ski-stoneham.com/en/skiing-riding/snow-report/snow-conditions/')

    def xp(path): return driver.find_element(By.XPATH, path).get_attribute("innerHTML")

    raw_24h    = xp('//*[@id="dataSnow"]/div/div/div/div[1]/div[2]')
    raw_week   = xp('//*[@id="dataSnow"]/div/div/div/div[3]/div[2]')
    raw_season = xp('//*[@id="dataSnow"]/div/div/div/div[5]/div[2]')

    return {
        "name": "Stoneham",
        "day_trails_open":  xp('//*[@id="conditionsDay"]/div[1]/div/div[1]/div[2]').replace(" ", ""),
        "day_lifts_open":   xp('//*[@id="liftStatusToggle"]/div[2]').replace(" ", ""),
        "night_trail_open": xp('//*[@id="conditionsNight"]/div[1]/div/div[1]/div[2]').replace(" ", ""),
        "night_lifts_open": xp('//*[@id="liftStatusToggle-night"]/div[2]').replace(" ", ""),
        "snowfall_24h":     strip_tags(raw_24h) + " cm",
        "snowfall_week":    strip_tags(raw_week) + " cm",
        "snowfall_season":  strip_tags(raw_season) + " cm",
    }

def scrape_mont_sainte_anne(driver):
    driver.get('https://mont-sainte-anne.com/en/alpine-skiing-snow-conditions/')

    def xp(path): return driver.find_element(By.XPATH, path).get_attribute("innerHTML")

    raw_24h    = xp('//*[@id="dataSnow"]/div/div/div/div[1]/div[2]')
    raw_week   = xp('//*[@id="dataSnow"]/div/div/div/div[3]/div[2]')
    raw_season = xp('//*[@id="dataSnow"]/div/div/div/div[4]/div[2]')

    return {
        "name": "Mont Sainte-Anne",
        "day_trails_open":  xp('//*[@id="conditionsDay"]/div[1]/div/div[1]/div[2]').replace(" ", ""),
        "day_lifts_open":   xp('//*[@id="conditionsDay"]/div[1]/div/div[1]/div[10]').replace(" ", ""),
        "night_trail_open": xp('//*[@id="conditionsNight"]/div[1]/div/div[1]/div[2]').replace(" ", ""),
        "night_lifts_open": xp('//*[@id="conditionsNight"]/div[1]/div/div[1]/div[10]').replace(" ", ""),
        "snowfall_24h":     strip_tags(raw_24h) + " cm",
        "snowfall_week":    strip_tags(raw_week) + " cm",
        "snowfall_season":  strip_tags(raw_season) + " cm",
    }

# ── Main ──────────────────────────────────────────────────────────────────────
scrapers = [scrape_relais, scrape_stoneham, scrape_mont_sainte_anne]
hills    = []
driver   = get_driver()

try:
    for scrape in scrapers:
        try:
            hill = scrape(driver)
            hills.append(hill)
            print(f"OK: {hill['name']}", flush=True)
        except Exception as e:
            print(f"ERROR scraping {scrape.__name__}: {e}", file=sys.stderr, flush=True)
finally:
    driver.quit()

with open(OUTPUT_FILE, 'w') as f:
    json.dump(hills, f, indent=2)

print(f"Wrote {len(hills)} hill(s) to {OUTPUT_FILE}", flush=True)
