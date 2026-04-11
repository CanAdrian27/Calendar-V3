<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$debug = isset($_GET['debug']);

function dbg($label, $value = null) {
  global $debug;
  if (!$debug) return;
  echo '<p><strong>' . htmlspecialchars($label) . '</strong>';
  if ($value !== null) {
    echo ': <pre style="display:inline;white-space:pre-wrap">' . htmlspecialchars(is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT)) . '</pre>';
  }
  echo '</p>';
}

require_once('incs/stats.php');

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
  $url = 'https://api.open-meteo.com/v1/forecast?latitude=46.81&longitude=-71.21&current=temperature_2m,apparent_temperature,weather_code,wind_speed_10m,wind_direction_10m&hourly=temperature_2m,apparent_temperature,rain,showers,snowfall,weather_code&daily=weather_code,temperature_2m_max,temperature_2m_min,apparent_temperature_max,apparent_temperature_min,sunrise,sunset,precipitation_sum,rain_sum,showers_sum,snowfall_sum,precipitation_hours,precipitation_probability_max,wind_speed_10m_max,wind_gusts_10m_max,wind_direction_10m_dominant,uv_index_max&timezone=America%2FNew_York';

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