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
$showhourlyweather   = true;
$showsunrisesunset   = true;
$showmoonphase       = true;
$showprecipqty       = true;
$showprecipprob      = false;
$showpreciphours     = false;
$showuvindex         = false;
$cal_languages       = ['en', 'fr'];
$ui_font             = 'IBM Plex Sans';
$event_font_size     = 12;
$color_scheme        = 'image_low';
$color_scheme_base   = '#4a90d9';
$image_height        = 750;
$wifi_ssid           = '';
$wifi_password       = '';
$pi_base_url         = '';
$mealieUsername      = '';
$mealiePassword      = '';
$mealieUrl           = '';

if (file_exists('env_vars.php')) include('env_vars.php');

function writeEnvVars() {
	global $calendars, $showski, $showclock, $showquote, $showword,
	       $showword_fr, $showword_es, $showweekly, $showmealie, $shownotes,
	       $show_notes_qr, $show_wifi_qr, $showcurrentweather, $showwindspeed,
	       $showweathericon, $showtemperature, $showfeelslike_box, $showfeelslike_combo,
	       $showhourlyweather, $showsunrisesunset, $showmoonphase, $showprecipqty,
	       $showprecipprob, $showpreciphours, $showuvindex, $cal_languages, $ui_font,
	       $event_font_size, $color_scheme, $color_scheme_base, $image_height,
	       $wifi_ssid, $wifi_password, $pi_base_url, $mealieUsername, $mealiePassword, $mealieUrl;

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
	$php .= '$showpreciphours = '     . ($showpreciphours     ? 'true' : 'false') . ";\n";
	$php .= '$showuvindex = '         . ($showuvindex         ? 'true' : 'false') . ";\n";
	$php .= '$cal_languages = '       . var_export($cal_languages,    true) . ";\n";
	$php .= '$ui_font = '             . var_export($ui_font,           true) . ";\n";
	$php .= '$event_font_size = '     . (int)$event_font_size               . ";\n";
	$php .= '$color_scheme = '        . var_export($color_scheme,      true) . ";\n";
	$php .= '$color_scheme_base = '   . var_export($color_scheme_base, true) . ";\n";
	$php .= '$image_height = '        . (int)$image_height                   . ";\n";
	$php .= '$wifi_ssid = '           . var_export($wifi_ssid,         true) . ";\n";
	$php .= '$wifi_password = '       . var_export($wifi_password,     true) . ";\n";
	$php .= '$pi_base_url = '         . var_export($pi_base_url,       true) . ";\n\n";
	$php .= '$mealieUsername = '      . var_export($mealieUsername,    true) . ";\n";
	$php .= '$mealiePassword = '      . var_export($mealiePassword,    true) . ";\n";
	$php .= '$mealieUrl = '           . var_export($mealieUrl,         true) . ";\n";

	return file_put_contents('env_vars.php', $php) !== false;
}
