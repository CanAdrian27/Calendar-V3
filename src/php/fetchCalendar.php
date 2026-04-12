<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include_once('env_vars.php');

$debug = isset($_GET['debug']);
if ($debug) {
	echo debugPageHeader('fetchCalendar');
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

dbg('Calendars configured', count($calendars));

foreach ($calendars as $cal) {
	getCalendar($cal['cal'], $cal['postprocess']);
}

function getCalendar($url, $postProcessCal)
{
	$curl = curl_init();

	curl_setopt_array($curl, [
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING       => '',
		CURLOPT_MAXREDIRS      => 10,
		CURLOPT_TIMEOUT        => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST  => 'GET',
		CURLOPT_HTTPHEADER     => ['Content-Type: text/plain'],
	]);

	$response = curl_exec($curl);
	$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	$curlErr  = curl_errno($curl) ? curl_error($curl) : null;
	curl_close($curl);

	if ($curlErr) {
		dbg('Curl error', $curlErr);
		return;
	}

	dbg('Fetched URL', $url . ' — HTTP ' . $httpCode . ', ' . strlen($response) . ' bytes');

	// Runna post-process: normalise DTSTART/DTEND to DATE-only format
	if ($postProcessCal) {
		$pattern     = '/(?<=DTSTART)(.*?)(?=\d\d\d\d\d\d\d\d\n)/mi';
		$response    = preg_replace($pattern, ';VALUE=DATE:', $response);
		$pattern     = '/(?<=DTEND)(.*?)(?=\d\d\d\d\d\d\d\d\n)/mi';
		$response    = preg_replace($pattern, ';VALUE=DATE:', $response);
		dbg('Post-processed', 'Runna DATE-only normalisation applied');
	}

	$filename = getCalName($response);
	if ($filename === '') {
		dbg('Error', 'Could not extract calendar name — file not saved');
		return;
	}

	file_put_contents("calendars/$filename.ics", $response);
	dbg('Saved', "calendars/$filename.ics");
}

function getCalName($text)
{
	$keyword = 'X-WR-CALNAME:';
	$start   = strpos($text, $keyword);
	if ($start === false) return '';
	$start   += strlen($keyword);
	$end     = strpos($text, "\r\n", $start);
	if (!is_numeric($end)) $end = strpos($text, "\n", $start);
	if (!is_numeric($end)) return '';

	$unsafeName = substr($text, $start, $end - $start);
	$safeName   = str_ireplace([' ', "'", '#'], ['_', '', ''], $unsafeName);
	return $safeName;
}

if ($debug) {
	echo debugPageFooter();
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
