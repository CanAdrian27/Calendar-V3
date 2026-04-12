# GPIO5	29
# GPIO6	31
# GPIO12	32
# GPIO13	33
# GPIO19	35
# GPIO16	36
# GPIO26	37

import RPi.GPIO as GPIO
import time
import json

# Use BCM numbering
mode = GPIO.getmode()

if mode == GPIO.BOARD:
	print("GPIO mode is BOARD")
elif mode == GPIO.BCM:
	print("GPIO mode is BCM")
elif mode is None:
	print("GPIO mode has not been set yet")

# Define pins to monitor (excluding GPIO4, 14, 17)
pins = [5, 6, 12, 13, 19, 16, 26,14,17]

# Setup GPIO pins
for pin in pins:
	GPIO.setup(pin, GPIO.IN, pull_up_down=GPIO.PUD_DOWN)

output_file = '/var/www/html/dist/toggles.json'

try:
	print("Monitoring pins... Press Ctrl+C to stop.")
	while True:
		status = {}
		for pin in pins:
			status[f"GPIO{pin}"] = GPIO.input(pin)
		
		with open(output_file, 'w') as f:
			json.dump(status, f, indent=2)
		
		print(f"Updated {output_file}: {status}")
		time.sleep(1)

except KeyboardInterrupt:
	print("Stopping...")

finally:
	GPIO.cleanup()

# 
# 3.3V ----[Button]---- GPIOx
			# 		  |
			# 	   [1kΩ]
			# 		  |
			# 		 GND
# 
# 
# Assume these BCM pins: GPIO5, 6, 12, 13, 19, 16, 26
# 		 You’ll need:
# 		 
# 		 7x buttons
# 		 
# 		 7x 1kΩ resistors (or anything between 1kΩ and 10kΩ)