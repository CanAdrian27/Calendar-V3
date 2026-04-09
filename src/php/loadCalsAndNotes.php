<?php
$showweekly = false;
$showmealie = false;
$shownotes  = false;
if (file_exists('env_vars.php')) include('env_vars.php');

$dir    = 'calendars';
$retArr['calendars'] = scandir($dir);
$dir    = 'notes';
$retArr['notes'] = scandir($dir);

$views = ['dayGridMonth'];
if (!empty($showweekly)) $views[] = 'timeGridWeek';
if (!empty($showmealie)) $views[] = 'recipe';
if (!empty($shownotes))  $views[] = 'notes';
$retArr['validviews'] = implode(',', $views);

echo json_encode($retArr);
?>