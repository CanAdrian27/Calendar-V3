<?php
// ── Shared env_vars defaults + save helper ────────────────────────────────────
// Include this file on every admin page instead of duplicating defaults and the
// write function.  After include, call writeEnvVars() to persist all vars.

$calendars           = [];
$showski             = false;
$showclock           = false;
$showquote           = false;
$showword            = false;
$showword_fr         = false;
$showword_es         = false;
$showweekly          = false;
$showmealie          = false;
$shownotes           = false;
$show_notes_qr       = true;
$show_wifi_qr        = false;
$showcurrentweather  = true;
$showwindspeed       = true;
$showweathericon     = true;
$showtemperature     = true;
$showfeelslike_box   = false;
$showfeelslike_combo = false;
$feelslike_mode      = 'apparent';
$showhourlyweather   = true;
$showsunrisesunset   = true;
$showmoonphase       = true;
$showprecipqty       = true;
$showprecipprob      = false;
$precip_prob_round   = false;
$showpreciphours     = false;
$showuvindex         = false;
$showdailywind       = false;
$showhourlywind      = false;
$weather_stacked     = false;
$showupcoming        = false;
$upcoming_weeks      = 4;
$showdualmonth       = false;
$cal_languages       = ['en', 'fr'];
$ui_font             = 'IBM Plex Sans';
$event_font_size     = 12;
$color_scheme        = 'image_low';
$color_scheme_base   = '#4a90d9';
$image_height        = 750;
$weather_lat         = 46.81;
$weather_lon         = -71.21;
$weather_timezone    = 'America/Toronto';
$wifi_ssid           = '';
$wifi_password       = '';
$pi_base_url         = '';
$mealieUsername      = '';
$mealiePassword      = '';
$mealieUrl           = '';
$unsplash_key        = '';

if (file_exists('env_vars.php')) include('env_vars.php');

function writeEnvVars() {
	global $calendars, $showski, $showclock, $showquote, $showword,
	       $showword_fr, $showword_es, $showweekly, $showmealie, $shownotes,
	       $show_notes_qr, $show_wifi_qr, $showcurrentweather, $showwindspeed,
	       $showweathericon, $showtemperature, $showfeelslike_box, $showfeelslike_combo,
	       $showhourlyweather, $showsunrisesunset, $showmoonphase, $showprecipqty,
	       $showprecipprob, $precip_prob_round, $showpreciphours, $showuvindex, $showdailywind, $showhourlywind,
	       $weather_stacked, $showupcoming, $upcoming_weeks, $showdualmonth,
	       $feelslike_mode, $cal_languages, $ui_font,
	       $event_font_size, $color_scheme, $color_scheme_base, $image_height,
	       $weather_lat, $weather_lon, $weather_timezone,
	       $wifi_ssid, $wifi_password, $pi_base_url, $mealieUsername, $mealiePassword, $mealieUrl,
	       $unsplash_key;

	$php  = "<?php\n";
	$php .= '$calendars = '           . var_export($calendars,        true) . ";\n\n";
	$php .= '$showski = '             . ($showski             ? 'true' : 'false') . ";\n";
	$php .= '$showclock = '           . ($showclock           ? 'true' : 'false') . ";\n";
	$php .= '$showquote = '           . ($showquote           ? 'true' : 'false') . ";\n";
	$php .= '$showword = '            . ($showword            ? 'true' : 'false') . ";\n";
	$php .= '$showword_fr = '         . ($showword_fr         ? 'true' : 'false') . ";\n";
	$php .= '$showword_es = '         . ($showword_es         ? 'true' : 'false') . ";\n";
	$php .= '$showweekly = '          . ($showweekly          ? 'true' : 'false') . ";\n";
	$php .= '$showmealie = '          . ($showmealie          ? 'true' : 'false') . ";\n";
	$php .= '$shownotes = '           . ($shownotes           ? 'true' : 'false') . ";\n";
	$php .= '$show_notes_qr = '       . ($show_notes_qr       ? 'true' : 'false') . ";\n";
	$php .= '$show_wifi_qr = '        . ($show_wifi_qr        ? 'true' : 'false') . ";\n";
	$php .= '$showcurrentweather = '  . ($showcurrentweather  ? 'true' : 'false') . ";\n";
	$php .= '$showwindspeed = '       . ($showwindspeed       ? 'true' : 'false') . ";\n";
	$php .= '$showweathericon = '     . ($showweathericon     ? 'true' : 'false') . ";\n";
	$php .= '$showtemperature = '     . ($showtemperature     ? 'true' : 'false') . ";\n";
	$php .= '$showfeelslike_box = '   . ($showfeelslike_box   ? 'true' : 'false') . ";\n";
	$php .= '$showfeelslike_combo = ' . ($showfeelslike_combo ? 'true' : 'false') . ";\n";
	$php .= '$showhourlyweather = '   . ($showhourlyweather   ? 'true' : 'false') . ";\n";
	$php .= '$showsunrisesunset = '   . ($showsunrisesunset   ? 'true' : 'false') . ";\n";
	$php .= '$showmoonphase = '       . ($showmoonphase       ? 'true' : 'false') . ";\n";
	$php .= '$showprecipqty = '       . ($showprecipqty       ? 'true' : 'false') . ";\n";
	$php .= '$showprecipprob = '      . ($showprecipprob      ? 'true' : 'false') . ";\n";
	$php .= '$precip_prob_round = '   . ($precip_prob_round   ? 'true' : 'false') . ";\n";
	$php .= '$showpreciphours = '     . ($showpreciphours     ? 'true' : 'false') . ";\n";
	$php .= '$showuvindex = '         . ($showuvindex         ? 'true' : 'false') . ";\n";
	$php .= '$showdailywind = '       . ($showdailywind       ? 'true' : 'false') . ";\n";
	$php .= '$showhourlywind = '      . ($showhourlywind      ? 'true' : 'false') . ";\n";
	$php .= '$weather_stacked = '    . ($weather_stacked     ? 'true' : 'false') . ";\n";
	$php .= '$showupcoming = '        . ($showupcoming        ? 'true' : 'false') . ";\n";
	$php .= '$upcoming_weeks = '      . (int)$upcoming_weeks                      . ";\n";
	$php .= '$showdualmonth = '       . ($showdualmonth       ? 'true' : 'false') . ";\n";
	$php .= '$feelslike_mode = '      . var_export($feelslike_mode, true)          . ";\n";
	$php .= '$cal_languages = '       . var_export($cal_languages,    true) . ";\n";
	$php .= '$ui_font = '             . var_export($ui_font,           true) . ";\n";
	$php .= '$event_font_size = '     . (int)$event_font_size               . ";\n";
	$php .= '$color_scheme = '        . var_export($color_scheme,      true) . ";\n";
	$php .= '$color_scheme_base = '   . var_export($color_scheme_base, true) . ";\n";
	$php .= '$image_height = '        . (int)$image_height                   . ";\n";
	$php .= '$weather_lat = '          . (float)$weather_lat                  . ";\n";
	$php .= '$weather_lon = '          . (float)$weather_lon                  . ";\n";
	$php .= '$weather_timezone = '     . var_export($weather_timezone,  true) . ";\n";
	$php .= '$wifi_ssid = '            . var_export($wifi_ssid,         true) . ";\n";
	$php .= '$wifi_password = '        . var_export($wifi_password,     true) . ";\n";
	$php .= '$pi_base_url = '          . var_export($pi_base_url,       true) . ";\n\n";
	$php .= '$mealieUsername = '      . var_export($mealieUsername,    true) . ";\n";
	$php .= '$mealiePassword = '      . var_export($mealiePassword,    true) . ";\n";
	$php .= '$mealieUrl = '           . var_export($mealieUrl,         true) . ";\n";
	$php .= '$unsplash_key = '        . var_export($unsplash_key,      true) . ";\n";

	return file_put_contents('env_vars.php', $php) !== false;
}
