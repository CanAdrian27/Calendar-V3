<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$debug = isset($_GET['debug']);
if ($debug) {
  echo debugPageHeader('fetchWeather');
}

function dbg($label, $value = null) {
  global $debug;
  if (!$debug) return;
  echo '<div class="dbg-row"><span class="dbg-label">' . htmlspecialchars($label) . '</span>';
  if ($value !== null) {
    $str = is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT);
    echo '<span class="dbg-val">' . nl2br(htmlspecialchars($str)) . '</span>';
  }
  echo '</div>';
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

require_once('incs/stats.php');

$weather_lat      = 46.81;
$weather_lon      = -71.21;
$weather_timezone = 'America/New_York';
if (file_exists('env_vars.php')) include('env_vars.php');

// ── Check if update is needed ─────────────────────────────────────────────────
$updateNeeded = false;
$json    = @file_get_contents('weather/report.json');
$weather = $json ? json_decode($json) : null;
$time    = $weather->current->time ?? $weather->current_weather->time ?? null;
$lastUpdate = $time ? date_create_from_format('Y-m-d\TH:i', $time) : null;
$now        = date_create_from_format('Y-m-d\TH:i', date_format(date_create(), 'Y-m-d\TH:00'));

dbg('Cached report.json time', $time);
dbg('Last update', $lastUpdate ? date_format($lastUpdate, 'Y-m-d H:i') : 'none');
dbg('Now (hour boundary)', date_format($now, 'Y-m-d H:i'));

if (!$lastUpdate || $now > $lastUpdate) {
  $updateNeeded = true;
  dbg('Decision', 'Update needed');
} else {
  dbg('Decision', 'Up to date — skipping fetch');
}

stats_record('weather',
  ['requests' => 1, 'cache_hits' => $updateNeeded ? 0 : 1],
  ['last_request' => stats_now()]
);

if (!$debug) {
  echo $updateNeeded ? 'Update Needed' : 'Update NOT Needed';
}

// ── Fetch from Open-Meteo ─────────────────────────────────────────────────────
if ($updateNeeded) {
  $url = 'https://api.open-meteo.com/v1/forecast'
       . '?latitude='  . urlencode($weather_lat)
       . '&longitude=' . urlencode($weather_lon)
       . '&current=temperature_2m,apparent_temperature,dew_point_2m,weather_code,wind_speed_10m,wind_direction_10m'
       . '&hourly=temperature_2m,apparent_temperature,wind_speed_10m,rain,showers,snowfall,weather_code'
       . '&daily=weather_code,temperature_2m_max,temperature_2m_min,apparent_temperature_max,apparent_temperature_min,sunrise,sunset,precipitation_sum,rain_sum,showers_sum,snowfall_sum,precipitation_hours,precipitation_probability_max,wind_speed_10m_max,wind_gusts_10m_max,wind_direction_10m_dominant,uv_index_max'
       . '&timezone=' . urlencode($weather_timezone);

  dbg('Fetching URL', $url);

  $curl = curl_init();
  curl_setopt_array($curl, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING       => '',
    CURLOPT_MAXREDIRS      => 10,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST  => 'GET',
    CURLOPT_SSL_VERIFYPEER => 0,
  ]);

  $response = curl_exec($curl);
  $curlErr  = curl_errno($curl) ? curl_error($curl) : null;
  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);

  dbg('HTTP status', $httpCode);

  stats_record('weather',
    ['api_calls' => 1, 'errors' => ($curlErr || $httpCode !== 200) ? 1 : 0],
    ['last_api_call' => stats_now(), 'last_http_code' => $httpCode ?: ($curlErr ? 'curl_err' : 0)]
  );

  if ($curlErr) {
    dbg('Curl error', $curlErr);
    if (!$debug) echo 'Curl error: ' . $curlErr;
  } else {
    dbg('HTTP status', $httpCode);
    dbg('Response (raw)', $response);
    $decoded = json_decode($response);
    dbg('Parsed keys', $decoded ? array_keys((array)$decoded) : 'JSON parse failed');

    $validTime = $decoded->current->time ?? $decoded->current_weather->time ?? null;
    if ($httpCode === 200 && $validTime) {
      file_put_contents('weather/report.json', $response);
      if (!$debug) echo 'Updated';
    } else {
      dbg('Skipped cache write', 'Response missing valid time field (HTTP ' . $httpCode . ')');
      if (!$debug) echo 'Fetch failed — cache preserved';
    }
  }
}

if ($debug) echo debugPageFooter();