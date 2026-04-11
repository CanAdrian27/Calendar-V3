<?php
include_once('env_vars.php');

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

header('Content-Type: application/json');
echo json_encode($result);
