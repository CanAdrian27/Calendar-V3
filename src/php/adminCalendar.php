<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('adminEnvHelper.php');

// Read last image colour for the colour scheme preview
$lastColorJson = @file_get_contents('images_supports/last_color.json');
$lastColorData = $lastColorJson ? json_decode($lastColorJson, true) : null;
$lastColorHex  = ($lastColorData && isset($lastColorData['r']))
	? sprintf('#%02x%02x%02x', $lastColorData['r'], $lastColorData['g'], $lastColorData['b'])
	: '#4a7bba';

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Calendars
	$newCalendars = [];
	$urls  = $_POST['cal_url']  ?? [];
	$names = $_POST['cal_name'] ?? [];
	$pps   = $_POST['cal_pp']   ?? [];
	foreach ($urls as $i => $url) {
		$url = trim($url);
		if ($url === '') continue;
		$newCalendars[] = [
			'cal'         => $url,
			'name'        => trim($names[$i] ?? ''),
			'postprocess' => isset($pps[$i]),
		];
	}
	$calendars = $newCalendars;

	// Language
	$cal_languages  = array_values(array_filter($_POST['cal_languages'] ?? []));
	if (empty($cal_languages)) $cal_languages = ['en'];

	// Image height
	$image_height = max(200, min(1800, (int)($_POST['image_height'] ?? 750)));

	// Colour scheme
	$allowedSchemes  = ['image_low', 'image_high', 'image_distinct', 'bright', 'mono'];
	$color_scheme    = in_array($_POST['color_scheme'] ?? '', $allowedSchemes) ? $_POST['color_scheme'] : 'image_low';
	$color_scheme_base = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color_scheme_base'] ?? '') ? $_POST['color_scheme_base'] : '#4a90d9';

	// Widgets
	$showclock           = isset($_POST['showclock']);
	$showquote           = isset($_POST['showquote']);
	$showword            = isset($_POST['showword']);
	$showcurrentweather  = isset($_POST['showcurrentweather']);
	$showwindspeed       = isset($_POST['showwindspeed']);
	$showweathericon     = isset($_POST['showweathericon']);
	$showtemperature     = isset($_POST['showtemperature']);
	$showfeelslike_box   = isset($_POST['showfeelslike_box']);
	$showfeelslike_combo = isset($_POST['showfeelslike_combo']);
	$allowedModes        = ['apparent', 'humidex', 'auto'];
	$feelslike_mode      = in_array($_POST['feelslike_mode'] ?? '', $allowedModes) ? $_POST['feelslike_mode'] : 'apparent';
	$showhourlyweather   = isset($_POST['showhourlyweather']);
	$showski             = isset($_POST['showski']);
	$showsunrisesunset   = isset($_POST['showsunrisesunset']);
	$showmoonphase       = isset($_POST['showmoonphase']);
	$showprecipqty       = isset($_POST['showprecipqty']);
	$showprecipprob      = isset($_POST['showprecipprob']);
	$showpreciphours     = isset($_POST['showpreciphours']);
	$showuvindex         = isset($_POST['showuvindex']);
	$showdailywind       = isset($_POST['showdailywind']);
	$showhourlywind      = isset($_POST['showhourlywind']);

	if (writeEnvVars()) {
		$saved = true;
	} else {
		$error = 'Could not write env_vars.php — check file permissions.';
	}
}

// Build calendar names for the colour scheme JS preview
$calNamesForJs = [];
foreach ($calendars as $i => $cal) {
	$calNamesForJs[] = !empty($cal['name']) ? $cal['name'] : ('Calendar ' . ($i + 1));
}
for ($i = count($calNamesForJs); $i < 10; $i++) {
	$calNamesForJs[] = 'Calendar ' . ($i + 1);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin — Calendar</title>
	<?php include('adminSharedStyles.php'); ?>
</head>
<body>
<?php include('adminNav.php'); ?>

<h1>Calendar</h1>

<?php if ($saved): ?>
	<div class="notice success">✓ Settings saved successfully.</div>
<?php elseif ($error): ?>
	<div class="notice error">⚠ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

	<div class="card">
		<h2>Calendars</h2>
		<div id="cal-list">
			<?php foreach ($calendars as $i => $cal): ?>
			<div class="cal-row">
				<input type="url"  name="cal_url[]"  placeholder="Calendar URL"  value="<?= htmlspecialchars($cal['cal']  ?? '') ?>">
				<input type="text" name="cal_name[]" placeholder="Display name"  value="<?= htmlspecialchars($cal['name'] ?? '') ?>">
				<div class="pp-cell">
					<input type="checkbox" name="cal_pp[<?= $i ?>]" <?= !empty($cal['postprocess']) ? 'checked' : '' ?>>
					<span>Runna</span>
				</div>
				<button type="button" class="btn-remove" onclick="removeRow(this)">×</button>
			</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="btn-add" onclick="addRow()">+ Add Calendar</button>
	</div>

	<div class="card">
		<h2>Language Rotation</h2>
		<label style="margin-bottom:8px">Each load picks one language at random with equal chance.</label>
		<div class="lang-grid">
			<?php
			$allLangs = ['en'=>'English','fr'=>'French','es'=>'Spanish','de'=>'German','it'=>'Italian','pt'=>'Portuguese'];
			foreach ($allLangs as $code => $name):
			?>
			<div class="toggle-row">
				<input type="checkbox" id="lang_<?= $code ?>" name="cal_languages[]" value="<?= $code ?>" <?= in_array($code, $cal_languages) ? 'checked' : '' ?>>
				<label for="lang_<?= $code ?>"><?= $name ?></label>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="card">
		<h2>Background Image</h2>
		<label for="image_height">Photo height (px)</label>
		<p class="card-hint">Controls how tall the background photo area is. Increase for a larger image, decrease to give more space to the calendar. Range: 200–1800 px.</p>
		<input type="number" id="image_height" name="image_height"
		       min="200" max="1800" step="10" value="<?= (int)$image_height ?>">
	</div>

	<div class="card">
		<h2>Colour Scheme</h2>
		<p class="card-hint">Controls how calendar event colours are generated. Image-based modes vary with each background photo; preview uses your most recent image.</p>

		<div class="scheme-option">
			<label><input type="radio" name="color_scheme" value="image_low" <?= $color_scheme === 'image_low' ? 'checked' : '' ?>> Image Theme — Slight Variability</label>
			<p class="scheme-desc">Ten lightness steps of the background photo's dominant hue, from dark to pale.</p>
		</div>
		<div class="scheme-option">
			<label><input type="radio" name="color_scheme" value="image_high" <?= $color_scheme === 'image_high' ? 'checked' : '' ?>> Image Theme — High Variability</label>
			<p class="scheme-desc">Five harmonically related hues (72° apart) derived from the photo, each in a dark and a light shade — 10 clearly distinct colours.</p>
		</div>
		<div class="scheme-option">
			<label><input type="radio" name="color_scheme" value="image_distinct" <?= $color_scheme === 'image_distinct' ? 'checked' : '' ?>> Image Theme — Distinctive</label>
			<p class="scheme-desc">Samples the 10 most visually distinct colours that actually appear in the background photo using farthest-point colour extraction.</p>
		</div>
		<div class="scheme-option">
			<label><input type="radio" name="color_scheme" value="bright" <?= $color_scheme === 'bright' ? 'checked' : '' ?>> Bright &amp; Distinguishable</label>
			<p class="scheme-desc">A fixed set of vivid, high-contrast colours spanning the full hue wheel — consistent regardless of background image.</p>
		</div>
		<div class="scheme-option">
			<label><input type="radio" name="color_scheme" value="mono" <?= $color_scheme === 'mono' ? 'checked' : '' ?>> Shades of a Selected Colour</label>
			<p class="scheme-desc">Ten lightness steps of your chosen hue, from deep to pale.</p>
			<div id="mono-picker">
				<label>Base colour</label>
				<input type="color" id="color_scheme_base" name="color_scheme_base" value="<?= htmlspecialchars($color_scheme_base) ?>">
			</div>
		</div>

		<div id="scheme-image-note">Preview approximates image-based colours using your most recent background photo.</div>
		<div class="scheme-preview" id="scheme-preview-boxes"></div>
	</div>

	<script>
	(function () {
		function hslToHex(h, s, l) {
			h /= 360; s /= 100; l /= 100;
			var r, g, b;
			function hue2rgb(p, q, t) {
				if (t < 0) t += 1; if (t > 1) t -= 1;
				if (t < 1/6) return p + (q - p) * 6 * t;
				if (t < 1/2) return q;
				if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
				return p;
			}
			if (s === 0) { r = g = b = l; }
			else {
				var q = l < 0.5 ? l * (1 + s) : l + s - l * s, p = 2 * l - q;
				r = hue2rgb(p, q, h + 1/3); g = hue2rgb(p, q, h); b = hue2rgb(p, q, h - 1/3);
			}
			return '#' + [r, g, b].map(function(x){ return ('0' + Math.round(x * 255).toString(16)).slice(-2); }).join('');
		}
		function hexToHsl(hex) {
			var r = parseInt(hex.slice(1,3),16)/255, g = parseInt(hex.slice(3,5),16)/255, b = parseInt(hex.slice(5,7),16)/255;
			var max = Math.max(r,g,b), min = Math.min(r,g,b), h, s, l = (max+min)/2;
			if (max === min) { h = s = 0; }
			else {
				var d = max - min;
				s = l > 0.5 ? d/(2-max-min) : d/(max+min);
				if (max === r) h = (g-b)/d + (g<b?6:0);
				else if (max === g) h = (b-r)/d + 2;
				else h = (r-g)/d + 4;
				h /= 6;
			}
			return {h: h*360, s: s*100, l: l*100};
		}
		function contrastColor(hex) {
			var r=parseInt(hex.slice(1,3),16), g=parseInt(hex.slice(3,5),16), b=parseInt(hex.slice(5,7),16);
			var L = 0.2126*Math.pow(r/255,2.2) + 0.7152*Math.pow(g/255,2.2) + 0.0722*Math.pow(b/255,2.2);
			return (L+0.05)/0.05 > 5 ? '#000000' : '#ffffff';
		}

		var _seed   = <?= json_encode($lastColorHex) ?>;
		var _names  = <?= json_encode(array_values($calNamesForJs)) ?>;
		var _bright = ['#f44336','#FF9800','#ffc107','#4caf50','#00bcd4','#2196f3','#9c27b0','#e91e63','#795548','#009688'];

		function generateColors(mode, monoHex) {
			var colors = [], hsl, i;
			if (mode === 'image_low') {
				hsl = hexToHsl(_seed);
				var s = Math.max(45, hsl.s);
				for (i = 0; i < 10; i++) {
					var hex = hslToHex(hsl.h, s, 25 + i * 5);
					colors.push({bg: hex, text: contrastColor(hex)});
				}
			} else if (mode === 'image_high') {
				hsl = hexToHsl(_seed);
				var s = Math.max(55, Math.min(85, hsl.s));
				var offsets = [0, 72, 144, 216, 288];
				var passes  = [38, 60];
				for (var pi = 0; pi < passes.length; pi++) {
					for (var oi = 0; oi < offsets.length; oi++) {
						var newH = (hsl.h + offsets[oi]) % 360;
						var hex = hslToHex(newH, s, passes[pi]);
						colors.push({bg: hex, text: contrastColor(hex)});
					}
				}
			} else if (mode === 'image_distinct') {
				for (i = 0; i < 10; i++) colors.push({bg: '#9e9e9e', text: '#ffffff'});
			} else if (mode === 'bright') {
				for (i = 0; i < 10; i++) colors.push({bg: _bright[i], text: contrastColor(_bright[i])});
			} else if (mode === 'mono') {
				hsl = hexToHsl(monoHex);
				var s = Math.max(50, hsl.s);
				for (i = 0; i < 10; i++) {
					var hex = hslToHex(hsl.h, s, 25 + i * 5);
					colors.push({bg: hex, text: contrastColor(hex)});
				}
			}
			return colors;
		}

		function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

		function updatePreview() {
			var mode    = document.querySelector('input[name="color_scheme"]:checked').value;
			var monoHex = document.getElementById('color_scheme_base').value;
			var colors  = generateColors(mode, monoHex);
			var html    = '';
			for (var i = 0; i < 10; i++) {
				var c = colors[i];
				html += '<div class="scheme-preview-box" style="background:' + c.bg + ';color:' + c.text + '">'
				      + esc(_names[i]) + '</div>';
			}
			document.getElementById('scheme-preview-boxes').innerHTML = html;
			document.getElementById('mono-picker').style.display = mode === 'mono' ? 'block' : 'none';
			var note = document.getElementById('scheme-image-note');
			if (mode === 'image_distinct') {
				note.textContent = 'Colours are sampled directly from the background image — preview is a placeholder. Actual colours appear on the next display refresh.';
				note.style.display = 'block';
			} else if (mode === 'image_low' || mode === 'image_high') {
				note.textContent = 'Preview approximates image-based colours using your most recent background photo.';
				note.style.display = 'block';
			} else {
				note.style.display = 'none';
			}
		}

		document.querySelectorAll('input[name="color_scheme"]').forEach(function(r){ r.addEventListener('change', updatePreview); });
		document.getElementById('color_scheme_base').addEventListener('input', updatePreview);
		updatePreview();
	})();
	</script>

	<div class="card">
		<h2>Widgets</h2>
		<p class="card-hint">Show or hide elements on the main calendar view.</p>
		<div class="toggle-row">
			<input type="checkbox" id="showclock" name="showclock" <?= $showclock ? 'checked' : '' ?>>
			<label for="showclock">Clock</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showquote" name="showquote" <?= $showquote ? 'checked' : '' ?>>
			<label for="showquote">Daily quote</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showword" name="showword" <?= $showword ? 'checked' : '' ?>>
			<label for="showword">Word of the day</label>
		</div>
		<hr class="section-divider">
		<div class="toggle-row">
			<input type="checkbox" id="showcurrentweather" name="showcurrentweather" <?= $showcurrentweather ? 'checked' : '' ?>>
			<label for="showcurrentweather">Current weather</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showwindspeed" name="showwindspeed" <?= $showwindspeed ? 'checked' : '' ?>>
			<label for="showwindspeed">Wind speed</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showweathericon" name="showweathericon" <?= $showweathericon ? 'checked' : '' ?>>
			<label for="showweathericon">Weather icon</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showtemperature" name="showtemperature" <?= $showtemperature ? 'checked' : '' ?>>
			<label for="showtemperature">Temperature</label>
		</div>
		<div class="toggle-row" style="margin-left:52px">
			<input type="checkbox" id="showfeelslike_box" name="showfeelslike_box" <?= $showfeelslike_box ? 'checked' : '' ?>>
			<label for="showfeelslike_box">Feels-like — own box</label>
		</div>
		<div class="toggle-row" style="margin-left:52px">
			<input type="checkbox" id="showfeelslike_combo" name="showfeelslike_combo" <?= $showfeelslike_combo ? 'checked' : '' ?>>
			<label for="showfeelslike_combo">Feels-like — combined with temperature <small style="font-weight:400;color:#888">(e.g. 10°C / 8°C)</small></label>
		</div>
		<div style="margin-left:52px;margin-bottom:10px">
			<label style="font-size:13px;font-weight:600;margin-bottom:6px;display:block">Feels-like calculation</label>
			<div class="toggle-row" style="margin-bottom:4px">
				<input type="radio" id="feelslike_apparent" name="feelslike_mode" value="apparent" <?= ($feelslike_mode === 'apparent') ? 'checked' : '' ?>>
				<label for="feelslike_apparent" style="font-weight:400">Apparent temperature <small style="color:#888">(Open-Meteo — wind chill + heat index)</small></label>
			</div>
			<div class="toggle-row" style="margin-bottom:4px">
				<input type="radio" id="feelslike_humidex" name="feelslike_mode" value="humidex" <?= ($feelslike_mode === 'humidex') ? 'checked' : '' ?>>
				<label for="feelslike_humidex" style="font-weight:400">Humidex <small style="color:#888">(Environment Canada formula)</small></label>
			</div>
			<div class="toggle-row">
				<input type="radio" id="feelslike_auto" name="feelslike_mode" value="auto" <?= ($feelslike_mode === 'auto') ? 'checked' : '' ?>>
				<label for="feelslike_auto" style="font-weight:400">Auto <small style="color:#888">(humidex when ≥ 20°C, apparent temperature when colder)</small></label>
			</div>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showhourlyweather" name="showhourlyweather" <?= $showhourlyweather ? 'checked' : '' ?>>
			<label for="showhourlyweather">Hourly weather strip</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showski" name="showski" <?= $showski ? 'checked' : '' ?>>
			<label for="showski">Ski conditions <small style="font-weight:400;color:#888">(Nov. – Mar. only)</small></label>
		</div>
		<hr class="section-divider">
		<label style="margin-bottom:10px;display:block">Calendar day boxes</label>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showsunrisesunset" name="showsunrisesunset" <?= $showsunrisesunset ? 'checked' : '' ?>>
			<label for="showsunrisesunset">Sunrise &amp; sunset</label>
		</div>
		<div class="toggle-row" style="margin-left:52px">
			<input type="checkbox" id="showmoonphase" name="showmoonphase" <?= $showmoonphase ? 'checked' : '' ?>>
			<label for="showmoonphase">Moon phase icons</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showprecipqty" name="showprecipqty" <?= $showprecipqty ? 'checked' : '' ?>>
			<label for="showprecipqty">Precipitation quantity</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showprecipprob" name="showprecipprob" <?= $showprecipprob ? 'checked' : '' ?>>
			<label for="showprecipprob">Precipitation probability</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showpreciphours" name="showpreciphours" <?= $showpreciphours ? 'checked' : '' ?>>
			<label for="showpreciphours">Precipitation hours</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showuvindex" name="showuvindex" <?= $showuvindex ? 'checked' : '' ?>>
			<label for="showuvindex">UV index</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showdailywind" name="showdailywind" <?= $showdailywind ? 'checked' : '' ?>>
			<label for="showdailywind">Wind speed &amp; gust max <small style="font-weight:400;color:#888">(daily forecast row)</small></label>
		</div>
		<hr class="section-divider">
		<label style="margin-bottom:10px;display:block">Hourly weather strip</label>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showhourlywind" name="showhourlywind" <?= $showhourlywind ? 'checked' : '' ?>>
			<label for="showhourlywind">Wind speed <small style="font-weight:400;color:#888">(hourly strip)</small></label>
		</div>
	</div>

	<button type="submit" class="btn-save">Save Settings</button>
</form>

<script>
var calIndex = <?= count($calendars) ?>;
function addRow() {
	var list = document.getElementById('cal-list');
	var row  = document.createElement('div');
	row.className = 'cal-row';
	row.innerHTML =
		'<input type="url"  name="cal_url[]"  placeholder="Calendar URL">' +
		'<input type="text" name="cal_name[]" placeholder="Display name">' +
		'<div class="pp-cell">' +
			'<input type="checkbox" name="cal_pp[' + calIndex + ']">' +
			'<span>Runna</span>' +
		'</div>' +
		'<button type="button" class="btn-remove" onclick="removeRow(this)">×</button>';
	list.appendChild(row);
	calIndex++;
}
function removeRow(btn) { btn.closest('.cal-row').remove(); }
</script>

</body>
</html>
