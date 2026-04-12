from gpiozero import MotionSensor
import time
import os
import subprocess
import datetime
import json
import keyboard
import RPi.GPIO as GPIO

GPIO.setwarnings(False)

# ── GPIO mode ─────────────────────────────────────────────────────────────────
mode = GPIO.getmode()
if mode is None:
    GPIO.setmode(GPIO.BCM)
    print("GPIO mode set to BCM", flush=True)
elif mode == GPIO.BOARD:
    print("GPIO mode is BOARD", flush=True)
elif mode == GPIO.BCM:
    print("GPIO mode is BCM", flush=True)

# Calendar toggle switches (BCM pin numbers)
TOGGLE_PINS = [5, 6, 12, 13, 19, 16, 26]

# Physical button pins
PIN_REFRESH = 8   # → R key (full refresh)
PIN_TOGGLE  = 11  # → T key (cycle view)

# PIR sensor pin
PIR_PIN = 4

# Screen idle timeout (seconds)
MAX_IDLE = 900

OUTPUT_FILE = '/var/www/html/dist/toggles.json'

# ── State ─────────────────────────────────────────────────────────────────────
now = time.time()
last_signaled = now
expire_time   = now + MAX_IDLE
tv_is_on      = True

os.environ['DISPLAY'] = ':0'

# ── Display control ───────────────────────────────────────────────────────────
def turn_display_on():
    global tv_is_on
    subprocess.call(
        'XAUTHORITY=/home/pi/.Xauthority DISPLAY=:0 xset dpms force on',
        shell=True
    )
    print('Display ON  ' + datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'), flush=True)
    tv_is_on = True

def turn_display_off():
    global tv_is_on
    subprocess.call(
        'XAUTHORITY=/home/pi/.Xauthority DISPLAY=:0 xset dpms force off',
        shell=True
    )
    print('Display OFF ' + datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'), flush=True)
    tv_is_on = False

# ── Motion callback ───────────────────────────────────────────────────────────
def on_motion():
    global tv_is_on, last_signaled, expire_time
    now = time.time()
    if not tv_is_on:
        print('Motion — turning display on', flush=True)
        turn_display_on()
    last_signaled = now
    expire_time   = now + MAX_IDLE
    keyboard.press_and_release('y')
    time.sleep(1)
    print(f'Motion detected — idle resets to {datetime.datetime.now().strftime("%H:%M:%S")}', flush=True)

# ── Button callbacks ──────────────────────────────────────────────────────────
def on_refresh(channel):
    print('Button: refresh', flush=True)
    keyboard.press_and_release('r')

def on_toggle(channel):
    print('Button: toggle view', flush=True)
    keyboard.press_and_release('t')

# ── Setup ─────────────────────────────────────────────────────────────────────
print('********************', flush=True)
print('* Initialising…', flush=True)
print('* Waiting for PIR to settle — do not move', flush=True)

try:
    sensor = MotionSensor(PIR_PIN)
    sensor.wait_for_no_motion()
except Exception as e:
    print(f'* PIR init error on GPIO{PIR_PIN}: {e}', flush=True)
    sensor = None

if sensor:
    sensor.when_motion = on_motion
    print('* PIR ready', flush=True)

for pin in TOGGLE_PINS:
    GPIO.setup(pin, GPIO.IN, pull_up_down=GPIO.PUD_DOWN)

GPIO.setup(PIN_REFRESH, GPIO.IN, pull_up_down=GPIO.PUD_DOWN)
GPIO.setup(PIN_TOGGLE,  GPIO.IN, pull_up_down=GPIO.PUD_UP)
GPIO.add_event_detect(PIN_REFRESH, edge=GPIO.RISING, callback=on_refresh, bouncetime=500)
GPIO.add_event_detect(PIN_TOGGLE,  edge=GPIO.RISING, callback=on_toggle,  bouncetime=500)

print('* Buttons ready', flush=True)
print('* Running', flush=True)
print('********************', flush=True)

# ── Main loop ─────────────────────────────────────────────────────────────────
try:
    while True:
        now = time.time()

        # Screen timeout
        if now > expire_time and tv_is_on:
            turn_display_off()

        # Write toggle switch states
        status = {f'GPIO{pin}': GPIO.input(pin) for pin in TOGGLE_PINS}
        with open(OUTPUT_FILE, 'w') as f:
            json.dump(status, f, indent=2)

        print(f'Toggles: {status}', flush=True)
        time.sleep(1)

except KeyboardInterrupt:
    print('Stopping…', flush=True)
finally:
    GPIO.cleanup()
