<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once('env_vars.php');
require_once('incs/stats.php');

$debug = isset($_GET['debug']);
if ($debug) {
	echo debugPageHeader('fetchQuote');
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

if (!$showquote) {
	dbg('Skipped', '$showquote is false — returning null');
	if (!$debug) echo 'null';
	if ($debug) echo debugPageFooter();
	exit;
}

$cacheFile = 'quote/report.json';
@mkdir('quote', 0755, true);

$json   = @file_get_contents($cacheFile);
$cached = $json ? json_decode($json, true) : null;

$force      = !empty($_POST['force']);
$needsFetch = $force || !$cached || ($cached['date'] ?? '') !== date('Y-m-d');

dbg('Cache date', $cached['date'] ?? '(none)');
dbg('Today', date('Y-m-d'));
dbg('Needs fetch', $needsFetch ? 'Yes' . ($force ? ' (forced)' : '') : 'No — using cache');

stats_record('quote',
	['requests' => 1, 'cache_hits' => $needsFetch ? 0 : 1, 'forced' => $force ? 1 : 0],
	['last_request' => stats_now()]
);

if ($needsFetch) {
	$curl = curl_init();
	curl_setopt_array($curl, [
		CURLOPT_URL            => 'https://dummyjson.com/quotes/random',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 10,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_SSL_VERIFYPEER => 0,
		CURLOPT_USERAGENT      => 'Mozilla/5.0',
	]);
	$response = curl_exec($curl);
	$curlErr  = curl_error($curl);
	$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close($curl);

	dbg('API HTTP status', $httpCode);

	$success = false;
	if ($curlErr) {
		dbg('Curl error', $curlErr);
		error_log('fetchQuote curl error: ' . $curlErr);
	} elseif ($response) {
		$quote = json_decode($response);
		if ($quote && isset($quote->quote)) {
			$cached = [
				'q'    => $quote->quote,
				'a'    => $quote->author,
				'date' => date('Y-m-d'),
			];
			file_put_contents($cacheFile, json_encode($cached));
			$success = true;
			dbg('Fetched quote', '"' . $quote->quote . '" — ' . $quote->author);
		} else {
			dbg('Parse error', 'Unexpected response: ' . $response);
			error_log('fetchQuote unexpected response: ' . $response);
		}
	}

	stats_record('quote',
		['api_calls' => 1, 'errors' => $success ? 0 : 1],
		['last_api_call' => stats_now(), 'last_http_code' => $httpCode ?? 0]
	);
}

if ($debug) {
	dbg('Cached quote', $cached['q'] ?? '(none)');
	dbg('Author', $cached['a'] ?? '(none)');
	echo debugPageFooter();
} else {
	echo $cached ? json_encode($cached) : 'null';
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
