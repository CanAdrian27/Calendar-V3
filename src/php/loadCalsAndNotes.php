<?php
$showweekly         = false;
$showmealie         = false;
$shownotes          = false;
$showhourlyweather  = true;
$cal_languages      = ['en', 'fr'];
if (file_exists('env_vars.php')) include('env_vars.php');

$dir    = 'calendars';
$retArr['calendars'] = scandir($dir);
$dir    = 'notes';
$retArr['notes'] = scandir($dir);

$views = ['dayGridMonth'];
if (!empty($showweekly)) $views[] = 'timeGridWeek';
if (!empty($showmealie)) $views[] = 'recipe';
if (!empty($shownotes))  $views[] = 'notes';
$retArr['validviews']        = implode(',', $views);
$retArr['showhourlyweather'] = !empty($showhourlyweather);
$retArr['cal_languages']     = is_array($cal_languages) && count($cal_languages) ? $cal_languages : ['en'];

echo json_encode($retArr);
?>