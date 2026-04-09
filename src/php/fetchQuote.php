<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once('env_vars.php');
require_once('incs/stats.php');

if (!$showquote) {
	echo 'null';
	exit;
}

$cacheFile = 'quote/report.json';

@mkdir('quote', 0755, true);

$json   = @file_get_contents($cacheFile);
$cached = $json ? json_decode($json, true) : null;

$force = !empty($_POST['force']);
$needsFetch = $force || !$cached || ($cached['date'] ?? '') !== date('Y-m-d');

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

	$success = false;
	if ($curlErr) {
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
		} else {
			error_log('fetchQuote unexpected response: ' . $response);
		}
	}

	stats_record('quote',
		['api_calls' => 1, 'errors' => $success ? 0 : 1],
		['last_api_call' => stats_now(), 'last_http_code' => $httpCode ?? 0]
	);
}

echo $cached ? json_encode($cached) : 'null';
?>
