<?php
include_once('env_vars.php');

$debug = isset($_GET['debug']);

// Coordinates — keep in sync with fetchWeather.php
$lat      = isset($lat) ? (float)$lat : 46.81;
$lon      = isset($lon) ? (float)$lon : -71.21;
$timezone = 'America/New_York';

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

$tz_obj        = new DateTimeZone($timezone);
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$ref           = gmmktime(18, 14, 0, 1, 6, 2000); // reference new moon 2000-01-06 18:14 UTC
$synodic       = 29.530588853;

// ── Step 1: build per-day sunrise/sunset and raw phase values ────────────────

$result     = [];
$phase_vals = []; // date_str => phase (0..synodic)

for ($d = 1; $d <= $days_in_month; $d++) {
  $date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
  $ts       = gmmktime(12, 0, 0, $month, $d, $year); // noon UTC — avoids DST edge cases

  $sun        = date_sun_info($ts, $lat, $lon);
  $sunrise_dt = (new DateTime('@' . $sun['sunrise']))->setTimezone($tz_obj);
  $sunset_dt  = (new DateTime('@' . $sun['sunset']))->setTimezone($tz_obj);

  $elapsed = ($ts - $ref) / 86400.0;
  $phase   = fmod($elapsed, $synodic);
  if ($phase < 0) $phase += $synodic;

  $result[$date_str]     = [
    'sunrise'    => $sunrise_dt->format('g:i a'),
    'sunset'     => $sunset_dt->format('g:i a'),
    'moon_phase' => null,
  ];
  $phase_vals[$date_str] = $phase;
}

// ── Step 2: for each major phase, mark only the closest day ──────────────────
// This guarantees exactly one day per phase regardless of where in the day it falls.

$major_phases = [
  0              => '<i class="wi wi-moon-new"></i>',
  $synodic*0.25  => '<i class="wi wi-moon-first-quarter"></i>',
  $synodic*0.50  => '<i class="wi wi-moon-full"></i>',
  $synodic*0.75  => '<i class="wi wi-moon-third-quarter"></i>',
];

foreach ($major_phases as $target => $icon) {
  $min_dist = PHP_FLOAT_MAX;
  $min_date = null;
  foreach ($phase_vals as $date => $phase) {
    $dist = abs($phase - $target);
    if ($dist > $synodic / 2) $dist = $synodic - $dist; // wrap (e.g. new moon near 0 vs near 29.5)
    if ($dist < $min_dist) {
      $min_dist = $dist;
      $min_date = $date;
    }
  }
  // Only assign if the closest day is actually within 2 days (handles months with no occurrence)
  if ($min_date !== null && $min_dist < 2.0) {
    $result[$min_date]['moon_phase'] = $icon;
  }
}

if ($debug) {
	echo debugPageHeader('loadSunData');
	echo '<div class="dbg-row"><span class="dbg-label">Month</span><span class="dbg-val">' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . '</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Days calculated</span><span class="dbg-val">' . $days_in_month . '</span></div>';
	$moonDays = array_filter($result, fn($r) => $r['moon_phase'] !== null);
	echo '<div class="dbg-row"><span class="dbg-label">Moon phases this month</span><span class="dbg-val">' . count($moonDays) . '</span></div>';
	foreach ($moonDays as $date => $row) {
		echo '<div class="dbg-row"><span class="dbg-label">' . $date . '</span><span class="dbg-val">' . strip_tags($row['moon_phase']) . ' — rise: ' . $row['sunrise'] . ', set: ' . $row['sunset'] . '</span></div>';
	}
	echo '<h2>Full Response</h2><pre>' . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) . '</pre>';
	echo debugPageFooter();
} else {
	header('Content-Type: application/json');
	echo json_encode($result);
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
