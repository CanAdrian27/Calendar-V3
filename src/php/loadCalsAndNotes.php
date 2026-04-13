<?php
$showweekly         = false;
$showmealie         = false;
$shownotes          = false;
$showhourlyweather  = true;
$cal_languages      = ['en', 'fr'];
$showupcoming       = false;
$upcoming_weeks     = 4;
$showdualmonth      = false;
if (file_exists('env_vars.php')) include('env_vars.php');

$debug = isset($_GET['debug']);

$dir    = 'calendars';
$retArr['calendars'] = scandir($dir);
$dir    = 'notes';
$retArr['notes'] = scandir($dir);

$views = ['dayGridMonth'];
if (!empty($showweekly))    $views[] = 'timeGridWeek';
if (!empty($showupcoming))  $views[] = 'upcomingWeeks';
if (!empty($showdualmonth)) $views[] = 'dualMonth';
if (!empty($showmealie))    $views[] = 'recipe';
if (!empty($shownotes))     $views[] = 'notes';
$retArr['validviews']        = implode(',', $views);
$retArr['showhourlyweather'] = !empty($showhourlyweather);
$retArr['cal_languages']     = is_array($cal_languages) && count($cal_languages) ? $cal_languages : ['en'];
$retArr['upcoming_weeks']    = max(1, min(12, (int)$upcoming_weeks));

if ($debug) {
	echo debugPageHeader('loadCalsAndNotes');
	echo '<div class="dbg-row"><span class="dbg-label">Calendars found</span><span class="dbg-val">' . count($retArr['calendars']) . ' entries (including . and ..)</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Notes files</span><span class="dbg-val">' . count($retArr['notes']) . ' entries</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Valid views</span><span class="dbg-val">' . htmlspecialchars($retArr['validviews']) . '</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Hourly weather</span><span class="dbg-val">' . ($retArr['showhourlyweather'] ? 'shown' : 'hidden') . '</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Calendar languages</span><span class="dbg-val">' . implode(', ', $retArr['cal_languages']) . '</span></div>';
	echo '<h2>Full Response</h2><pre>' . htmlspecialchars(json_encode($retArr, JSON_PRETTY_PRINT)) . '</pre>';
	echo debugPageFooter();
} else {
	echo json_encode($retArr);
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
?>
