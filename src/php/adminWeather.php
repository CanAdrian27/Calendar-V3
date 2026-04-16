<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('adminEnvHelper.php');

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Location
	$new_lat = round((float)($_POST['weather_lat']      ?? $weather_lat), 6);
	$new_lon = round((float)($_POST['weather_lon']      ?? $weather_lon), 6);
	$new_tz  = trim($_POST['weather_timezone'] ?? $weather_timezone);
	$location_changed = ($new_lat !== (float)$weather_lat || $new_lon !== (float)$weather_lon || $new_tz !== $weather_timezone);
	$weather_lat      = $new_lat;
	$weather_lon      = $new_lon;
	$weather_timezone = $new_tz;

	// Current weather
	$showcurrentweather  = isset($_POST['showcurrentweather']);
	$showwindspeed       = isset($_POST['showwindspeed']);
	$showweathericon     = isset($_POST['showweathericon']);
	$showtemperature     = isset($_POST['showtemperature']);
	$showfeelslike_box   = isset($_POST['showfeelslike_box']);
	$showfeelslike_combo = isset($_POST['showfeelslike_combo']);
	$allowedModes        = ['apparent', 'humidex', 'auto'];
	$feelslike_mode      = in_array($_POST['feelslike_mode'] ?? '', $allowedModes) ? $_POST['feelslike_mode'] : 'apparent';

	// Hourly strip
	$showhourlyweather   = isset($_POST['showhourlyweather']);
	$showhourlywind      = isset($_POST['showhourlywind']);

	// Ski
	$showski             = isset($_POST['showski']);

	// Daily boxes
	$showsunrisesunset   = isset($_POST['showsunrisesunset']);
	$showmoonphase       = isset($_POST['showmoonphase']);
	$showprecipqty       = isset($_POST['showprecipqty']);
	$showprecipprob      = isset($_POST['showprecipprob']);
	$precip_prob_round   = isset($_POST['precip_prob_round']);
	$showpreciphours     = isset($_POST['showpreciphours']);
	$showuvindex         = isset($_POST['showuvindex']);
	$showdailywind       = isset($_POST['showdailywind']);
	$weather_stacked     = isset($_POST['weather_stacked']);

	if (writeEnvVars()) {
		$saved = true;
		if ($location_changed) @unlink('weather/report.json');
	} else {
		$error = 'Could not write env_vars.php — check file permissions.';
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin — Weather</title>
	<?php include('adminSharedStyles.php'); ?>
</head>
<body>
<?php include('adminNav.php'); ?>

<h1>Weather</h1>

<?php if ($saved): ?>
	<div class="notice success">✓ Settings saved successfully.</div>
<?php elseif ($error): ?>
	<div class="notice error">⚠ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

	<div class="card">
		<h2>Location</h2>
		<p class="card-hint">Search for a city to set the forecast location. Changing the location clears the weather cache so it refreshes immediately.</p>
		<label for="city-search">Search city</label>
		<div style="display:flex;gap:8px;margin-bottom:12px">
			<input type="text" id="city-search" placeholder="e.g. Quebec City" style="margin-bottom:0;flex:1"
			       onkeydown="if(event.key==='Enter'){event.preventDefault();searchWeatherCity();}">
			<button type="button" class="btn-secondary" onclick="searchWeatherCity()">Search</button>
		</div>
		<div id="city-results" style="display:none;margin-bottom:12px"></div>
		<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
			<div>
				<label for="weather_lat">Latitude</label>
				<input type="text" id="weather_lat" name="weather_lat"
				       value="<?= htmlspecialchars($weather_lat) ?>" style="margin-bottom:0">
			</div>
			<div>
				<label for="weather_lon">Longitude</label>
				<input type="text" id="weather_lon" name="weather_lon"
				       value="<?= htmlspecialchars($weather_lon) ?>" style="margin-bottom:0">
			</div>
		</div>
		<label for="weather_timezone" style="margin-top:12px">Timezone</label>
		<input type="text" id="weather_timezone" name="weather_timezone"
		       value="<?= htmlspecialchars($weather_timezone) ?>" style="margin-bottom:0">
		<p class="card-hint" style="margin-top:6px">Timezone aligns the daily forecast with local midnight. Format: <code>America/New_York</code></p>
	</div>

	<div class="card">
		<h2>Current Weather</h2>
		<div class="toggle-row">
			<input type="checkbox" id="showcurrentweather" name="showcurrentweather" <?= $showcurrentweather ? 'checked' : '' ?>>
			<label for="showcurrentweather">Show current weather</label>
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
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showwindspeed" name="showwindspeed" <?= $showwindspeed ? 'checked' : '' ?>>
			<label for="showwindspeed">Wind speed</label>
		</div>
	</div>

	<div class="card">
		<h2>Hourly Strip</h2>
		<div class="toggle-row">
			<input type="checkbox" id="showhourlyweather" name="showhourlyweather" <?= $showhourlyweather ? 'checked' : '' ?>>
			<label for="showhourlyweather">Show hourly weather strip</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showhourlywind" name="showhourlywind" <?= $showhourlywind ? 'checked' : '' ?>>
			<label for="showhourlywind">Wind speed in hourly strip</label>
		</div>
	</div>

	<div class="card">
		<h2>Daily Forecast Boxes</h2>
		<p class="card-hint">Controls what appears in each day's box on the calendar.</p>
		<div class="toggle-row">
			<input type="checkbox" id="showsunrisesunset" name="showsunrisesunset" <?= $showsunrisesunset ? 'checked' : '' ?>>
			<label for="showsunrisesunset">Sunrise &amp; sunset</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showmoonphase" name="showmoonphase" <?= $showmoonphase ? 'checked' : '' ?>>
			<label for="showmoonphase">Moon phase icons</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showprecipqty" name="showprecipqty" <?= $showprecipqty ? 'checked' : '' ?>>
			<label for="showprecipqty">Precipitation quantity</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showprecipprob" name="showprecipprob" <?= $showprecipprob ? 'checked' : '' ?>>
			<label for="showprecipprob">Precipitation probability</label>
		</div>
		<div class="toggle-row" style="margin-left:52px">
			<input type="checkbox" id="precip_prob_round" name="precip_prob_round" <?= $precip_prob_round ? 'checked' : '' ?>>
			<label for="precip_prob_round" style="font-weight:400">Round to nearest 10% <small style="color:#888">(e.g. 73% → 70%)</small></label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showpreciphours" name="showpreciphours" <?= $showpreciphours ? 'checked' : '' ?>>
			<label for="showpreciphours">Precipitation hours</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showuvindex" name="showuvindex" <?= $showuvindex ? 'checked' : '' ?>>
			<label for="showuvindex">UV index</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showdailywind" name="showdailywind" <?= $showdailywind ? 'checked' : '' ?>>
			<label for="showdailywind">Wind speed &amp; gust</label>
		</div>
		<hr class="section-divider">
		<div class="toggle-row">
			<input type="checkbox" id="weather_stacked" name="weather_stacked" <?= $weather_stacked ? 'checked' : '' ?>>
			<label for="weather_stacked">Stacked layout <small style="font-weight:400;color:#888">— precipitation probability shown below rain amount; gust shown below wind speed</small></label>
		</div>
	</div>

	<div class="card">
		<h2>Ski Conditions</h2>
		<div class="toggle-row">
			<input type="checkbox" id="showski" name="showski" <?= $showski ? 'checked' : '' ?>>
			<label for="showski">Show ski conditions <small style="font-weight:400;color:#888">(Nov. – Mar. only)</small></label>
		</div>
	</div>

	<button type="submit" class="btn-save">Save Settings</button>
</form>

<script>
function searchWeatherCity() {
	var q = document.getElementById('city-search').value.trim();
	if (!q) return;
	var resultsDiv = document.getElementById('city-results');
	resultsDiv.innerHTML = '<span style="font-size:13px;color:#888">Searching…</span>';
	resultsDiv.style.display = 'block';
	fetch('https://geocoding-api.open-meteo.com/v1/search?name=' + encodeURIComponent(q) + '&count=6&language=en&format=json')
		.then(function(r) { return r.json(); })
		.then(function(data) {
			var results = data.results || [];
			if (results.length === 0) {
				resultsDiv.innerHTML = '<p style="font-size:13px;color:#888;margin:0">No results found.</p>';
				return;
			}
			var html = '<div style="display:flex;flex-direction:column;gap:6px">';
			results.forEach(function(r) {
				var label = r.name
					+ (r.admin1 ? ', ' + r.admin1 : '')
					+ ', ' + r.country;
				html += '<button type="button" class="btn-secondary" style="text-align:left;padding:8px 12px;cursor:pointer"'
					+ ' onclick="selectWeatherCity(' + r.latitude + ',' + r.longitude + ','
					+ JSON.stringify(r.timezone) + ',' + JSON.stringify(label) + ')">'
					+ '<span style="font-weight:600">' + label + '</span>'
					+ '<small style="display:block;color:#888;font-size:11px;margin-top:2px">'
					+ 'Lat: ' + r.latitude + ' &nbsp; Lon: ' + r.longitude + ' &nbsp; ' + r.timezone
					+ '</small></button>';
			});
			html += '</div>';
			resultsDiv.innerHTML = html;
		})
		.catch(function() {
			resultsDiv.innerHTML = '<p style="font-size:13px;color:#c00;margin:0">Search failed — check your internet connection.</p>';
		});
}
function selectWeatherCity(lat, lon, timezone, name) {
	document.getElementById('weather_lat').value = lat;
	document.getElementById('weather_lon').value = lon;
	document.getElementById('weather_timezone').value = timezone;
	document.getElementById('city-search').value = name;
	document.getElementById('city-results').style.display = 'none';
}
</script>

</body>
</html>
