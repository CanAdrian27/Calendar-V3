<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once('env_vars.php');

$debug = isset($_GET['debug']);

$json    = @file_get_contents('weather/report.json');
$weather = $json ? json_decode($json) : null;

if (!$weather) {
  if ($debug) {
    echo debugPageHeader('loadWeather');
    echo '<div class="dbg-row"><span class="dbg-label">Error</span><span class="dbg-val">weather/report.json not found or empty — run fetchWeather.php first</span></div>';
    echo debugPageFooter();
  } else {
    echo 'null';
  }
  exit;
}

// Normalize new API format (current/weather_code) to old format (current_weather/weathercode)
if (isset($weather->current) && !isset($weather->current_weather)) {
  $weather->current_weather = (object)[
    'time'                 => $weather->current->time,
    'temperature'          => $weather->current->temperature_2m,
    'apparent_temperature' => $weather->current->apparent_temperature ?? null,
    'dew_point'            => $weather->current->dew_point_2m ?? null,
    'windspeed'            => $weather->current->wind_speed_10m,
    'winddirection'        => $weather->current->wind_direction_10m ?? null,
    'weathercode'          => $weather->current->weather_code,
  ];
}
if (isset($weather->hourly->weather_code)) {
  $weather->hourly->weathercode = $weather->hourly->weather_code;
}
if (isset($weather->daily->weather_code)) {
  $weather->daily->weathercode = $weather->daily->weather_code;
}
if (isset($weather->daily_units->wind_speed_10m_max)) {
  $weather->daily_units->windspeed_10m_max = $weather->daily_units->wind_speed_10m_max;
}

if (!isset($weather->current_weather) || !isset($weather->daily)) {
  if ($debug) {
    echo debugPageHeader('loadWeather');
    echo '<div class="dbg-row"><span class="dbg-label">Error</span><span class="dbg-val">Weather JSON missing current_weather or daily fields</span></div>';
    echo debugPageFooter();
  } else {
    echo 'null';
  }
  exit;
}

// ── Feels-like calculation ────────────────────────────────────────────────────
// Humidex formula (Environment Canada): meaningful only when T ≥ 20°C.
// Falls back to apparent_temperature (wind chill) when dew point unavailable.
function calcHumidex($temp, $dewpoint) {
  if ($dewpoint === null) return null;
  $e = 6.11 * exp(5417.7530 * (1/273.16 - 1/(273.15 + $dewpoint)));
  return round($temp + 0.5555 * ($e - 10), 1);
}

$feelslike_mode = isset($feelslike_mode) ? $feelslike_mode : 'apparent';
$temp      = $weather->current_weather->temperature ?? null;
$apparent  = $weather->current_weather->apparent_temperature ?? null;
$dewpoint  = $weather->current_weather->dew_point ?? null;
$humidex   = ($temp !== null && $dewpoint !== null) ? calcHumidex($temp, $dewpoint) : null;

if ($feelslike_mode === 'humidex' && $humidex !== null) {
  $feels_like = $humidex;
} elseif ($feelslike_mode === 'auto') {
  // Humidex in warm/humid conditions (T ≥ 20°C), apparent temp (wind chill) otherwise
  $feels_like = ($temp !== null && $temp >= 20 && $humidex !== null) ? $humidex : $apparent;
} else {
  // 'apparent' — Open-Meteo apparent temperature (default)
  $feels_like = $apparent;
}

$weather->current_weather->feels_like = $feels_like;
$weather->feelslike_mode              = $feelslike_mode;

$weather = convertWeatherCodesToIcons($weather);
$weather->showclock            = !empty($showclock);
$weather->showcurrentweather  = isset($showcurrentweather)  ? (bool)$showcurrentweather  : true;
$weather->showwindspeed        = isset($showwindspeed)       ? (bool)$showwindspeed       : true;
$weather->showweathericon      = isset($showweathericon)     ? (bool)$showweathericon     : true;
$weather->showtemperature      = isset($showtemperature)     ? (bool)$showtemperature     : true;
$weather->showfeelslike_box    = isset($showfeelslike_box)   ? (bool)$showfeelslike_box   : false;
$weather->showfeelslike_combo  = isset($showfeelslike_combo) ? (bool)$showfeelslike_combo : false;
// feelslike_mode already set above; re-expose on the response object for the frontend
$weather->feelslike_mode       = $feelslike_mode;
$weather->showhourlyweather    = isset($showhourlyweather)   ? (bool)$showhourlyweather   : true;
$weather->showsunrisesunset    = isset($showsunrisesunset)   ? (bool)$showsunrisesunset   : true;
$weather->showmoonphase        = isset($showmoonphase)       ? (bool)$showmoonphase       : true;
$weather->showprecipqty        = isset($showprecipqty)       ? (bool)$showprecipqty       : true;
$weather->showprecipprob       = isset($showprecipprob)      ? (bool)$showprecipprob      : false;
$weather->showpreciphours      = isset($showpreciphours)     ? (bool)$showpreciphours     : false;
$weather->showuvindex          = isset($showuvindex)         ? (bool)$showuvindex         : false;
$weather->showdailywind        = isset($showdailywind)       ? (bool)$showdailywind       : false;
$weather->showhourlywind       = isset($showhourlywind)      ? (bool)$showhourlywind      : false;

if ($debug) {
	echo debugPageHeader('loadWeather');
	echo '<div class="dbg-row"><span class="dbg-label">Cache time</span><span class="dbg-val">' . htmlspecialchars($weather->current_weather->time ?? '(unknown)') . '</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Temperature</span><span class="dbg-val">' . htmlspecialchars($weather->current_weather->temperature ?? '?') . '°C</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Dew point</span><span class="dbg-val">' . htmlspecialchars($weather->current_weather->dew_point ?? '(not yet cached — run fetchWeather)') . '°C</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Apparent temp</span><span class="dbg-val">' . htmlspecialchars($weather->current_weather->apparent_temperature ?? '?') . '°C</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Humidex</span><span class="dbg-val">' . htmlspecialchars($humidex ?? '(dew point unavailable)') . ($humidex !== null ? '°C' : '') . '</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Feels like (resolved)</span><span class="dbg-val">' . htmlspecialchars($feels_like ?? '?') . '°C — mode: ' . htmlspecialchars($feelslike_mode) . '</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Weather code</span><span class="dbg-val">' . htmlspecialchars($weather->current_weather->weathercode ?? '?') . '</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Daily forecast days</span><span class="dbg-val">' . count((array)($weather->daily->time ?? [])) . '</span></div>';
	echo '<h2>Show Flags</h2>';
	$flags = ['showclock','showcurrentweather','showwindspeed','showweathericon','showtemperature','showfeelslike_box','showfeelslike_combo','feelslike_mode','showhourlyweather','showsunrisesunset','showmoonphase','showprecipqty','showprecipprob','showpreciphours','showuvindex'];
	foreach ($flags as $f) {
		echo '<div class="dbg-row"><span class="dbg-label">' . $f . '</span><span class="dbg-val">' . ($weather->$f ? 'true' : 'false') . '</span></div>';
	}
	echo '<h2>Full Response</h2><pre>' . htmlspecialchars(json_encode($weather, JSON_PRETTY_PRINT)) . '</pre>';
	echo debugPageFooter();
} else {
	echo json_encode($weather);
}


function convertWeatherCodesToIcons($weather)
{
	$time    = $weather->{'current_weather'}->{'time'};
	$sunrise = $weather->{'daily'}->{'sunrise'}[0];
	$sunset  = $weather->{'daily'}->{'sunset'}[0];

	// Current weather
	$day = getDayNight($time, $sunrise, $sunset);
	$weather->{'current_weather'}->{'icon'} = convertWeatherCodeToIcon($weather->{'current_weather'}->{'weathercode'}, $day);

	// Hourly
	for ($i = 0; $i < count($weather->{'hourly'}->{'weathercode'}); $i++) {
		$time = $weather->{'hourly'}->{'time'}[$i];
		$day  = getDayNight($time, $sunrise, $sunset);
		$weather->{'hourly'}->{'icon'}[$i] = convertWeatherCodeToIcon($weather->{'hourly'}->{'weathercode'}[$i], $day);
	}

	// Daily (always daytime icons)
	for ($i = 0; $i < 7; $i++) {
		$weather->{'daily'}->{'icon'}[$i] = convertWeatherCodeToIcon($weather->{'daily'}->{'weathercode'}[$i], true);
	}
	return $weather;
}

function convertWeatherCodeToIcon($weatherCode, $day)
{
	switch ($weatherCode) {
		case 0:   return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-day-sunny"></i>'            : '<i date-code="'.$weatherCode.'" class="wi wi-night-clear"></i>';
		case 1:   return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-day-sunny-overcast"></i>'   : '<i date-code="'.$weatherCode.'" class="wi wi-night-partly-cloudy"></i>';
		case 2:   return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-day-cloudy"></i>'           : '<i date-code="'.$weatherCode.'" class="wi wi-night-alt-cloudy"></i>';
		case 3:   return '<i date-code="'.$weatherCode.'" class="wi wi-cloudy"></i>';
		case 45:  return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-day-fog"></i>'              : '<i date-code="'.$weatherCode.'" class="wi wi-night-fog"></i>';
		case 48:  return '<i date-code="'.$weatherCode.'" class="wi wi-fog"></i>';
		case 51:
		case 53:
		case 55:  return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-day-sprinkle"></i>'         : '<i date-code="'.$weatherCode.'" class="wi wi-night-sprinkle"></i>';
		case 56:
		case 57:  return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-snowflake-cold"></i><i class="wi wi-day-sprinkle"></i>'   : '<i date-code="'.$weatherCode.'" class="wi wi-snowflake-cold"></i><i class="wi wi-night-sprinkle"></i>';
		case 61:
		case 63:
		case 65:  return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-day-rain"></i>'             : '<i date-code="'.$weatherCode.'" class="wi wi-night-rain"></i>';
		case 66:
		case 67:  return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-snowflake-cold"></i><i class="wi wi-day-rain"></i>'       : '<i date-code="'.$weatherCode.'" class="wi wi-snowflake-cold"></i><i class="wi wi-night-rain"></i>';
		case 71:
		case 73:
		case 75:  return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-day-snow"></i>'             : '<i date-code="'.$weatherCode.'" class="wi wi-night-snow"></i>';
		case 77:  return '<i date-code="'.$weatherCode.'" class="wi wi-hail"></i>';
		case 80:
		case 81:
		case 82:  return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-day-sprinkle"></i>'         : '<i date-code="'.$weatherCode.'" class="wi wi-night-sprinkle"></i>';
		case 85:
		case 86:  return '<i date-code="'.$weatherCode.'" class="wi wi-snow"></i>';
		case 95:  return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-day-thunderstorm"></i>'     : '<i date-code="'.$weatherCode.'" class="wi wi-night-alt-thunderstorm"></i>';
		case 96:  return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-day-snow-thunderstorm"></i>' : '<i date-code="'.$weatherCode.'" class="wi wi-night-alt-snow-thunderstorm"></i>';
		case 99:  return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-day-sleet-storm"></i>'      : '<i date-code="'.$weatherCode.'" class="wi wi-night-alt-sleet-storm"></i>';
		default:  return $day ? '<i date-code="'.$weatherCode.'" class="wi wi-na"></i><i class="wi wi-day-sunny"></i>' : '<i date-code="'.$weatherCode.'" class="wi wi-na"></i><i class="wi wi-night-clear"></i>';
	}
}

function getDayNight($time, $sunrise, $sunset)
{
	$dateobj    = date_create_from_format('Y-m-d\TH:i', $time);
	$sunriseobj = date_create_from_format('Y-m-d\TH:i', $sunrise);
	$sunsetobj  = date_create_from_format('Y-m-d\TH:i', $sunset);
	return ($dateobj > $sunriseobj && $dateobj < $sunsetobj);
}

function debugPageHeader($title) {
	return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Debug: ' . htmlspecialchars($title) . '</title>' . debugStyles() . '</head><body>'
		. '<a class="back" href="admin.php">&#8592; Back to Admin</a>'
		. '<h1>Debug: <code>' . htmlspecialchars($title) . '.php</code></h1>';
}
function debugPageFooter() { return '</body></html>'; }
function debugStyles() {
	return '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f2f5;color:#1a1a2e;margin:0;padding:24px}h1{font-size:20px;margin:0 0 16px}h2{font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:#555;margin:20px 0 8px}pre{background:#fff;border:1px solid #d0d5dd;border-radius:8px;padding:16px;white-space:pre-wrap;word-break:break-all;font-size:13px;overflow:auto}.dbg-row{background:#fff;border:1px solid #d0d5dd;border-radius:8px;padding:10px 14px;margin-bottom:8px;font-size:14px}.dbg-label{font-weight:600;margin-right:10px}.dbg-val{font-family:monospace;font-size:13px}a.back{display:inline-block;margin-bottom:16px;color:#4f6ef7;text-decoration:none;font-size:14px}</style>';
}
