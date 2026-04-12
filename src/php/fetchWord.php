<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once('env_vars.php');
require_once('incs/stats.php');

$debug = isset($_GET['debug']);
if ($debug) {
	echo debugPageHeader('fetchWord');
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

if (!$showword) {
	dbg('Skipped', '$showword is false — returning null');
	if (!$debug) echo 'null';
	if ($debug) echo debugPageFooter();
	exit;
}

$showword_fr = $showword_fr ?? false;
$showword_es = $showword_es ?? false;

$cacheFile    = 'word/report.json';
$wordlistFile = 'word/wordlist.json';

// ── Load word list ────────────────────────────────────────────────────────────
include_once('wordDefaults.php'); // provides $defaultWords
$wljson = @file_get_contents($wordlistFile);
$words  = ($wljson !== false) ? json_decode($wljson, true) : null;
if (!is_array($words) || count($words) === 0) $words = $defaultWords;

$index     = abs(crc32(date('Y-m-d'))) % count($words);
$todayWord = trim($words[$index]);

dbg('Word list', count($words) . ' words');
dbg("Today's word", $todayWord . ' (index ' . $index . ')');

// ── Check cache ───────────────────────────────────────────────────────────────
$json   = @file_get_contents($cacheFile);
$cached = $json ? json_decode($json, true) : null;

$force = !empty($_POST['force']);
if ($force) {
	// Advance past whichever word is currently cached
	$currentKey = $cached['word_key'] ?? $todayWord;
	$currentIdx = array_search($currentKey, array_map('trim', $words));
	if ($currentIdx === false) $currentIdx = $index;
	$nextIdx   = ($currentIdx + 1) % count($words);
	$todayWord = trim($words[$nextIdx]);
	$cached    = null;
	dbg('Force-advance', 'Skipping to word: ' . $todayWord);
}

$needsFetch = !$cached
	|| ($cached['date']     ?? '') !== date('Y-m-d')
	|| ($cached['word_key'] ?? '') !== $todayWord
	|| !isset($cached['word_fr'])
	|| !isset($cached['phonetic_fr']);

dbg('Cache date', $cached['date'] ?? '(none)');
dbg('Needs fetch', $needsFetch ? 'Yes' : 'No — using cache');

stats_record('word',
	['requests' => 1, 'cache_hits' => $needsFetch ? 0 : 1, 'forced' => $force ? 1 : 0],
	['last_request' => stats_now()]
);

if ($needsFetch) {
	// ── Fetch English definition ──────────────────────────────────────────────
	$word = $todayWord;
	$defEn = $phonetic = $pos = '';

	$curl = curl_init();
	curl_setopt_array($curl, [
		CURLOPT_URL            => 'https://api.dictionaryapi.dev/api/v2/entries/en/' . urlencode($todayWord),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 10,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_SSL_VERIFYPEER => 0,
	]);
	$resp = curl_exec($curl);
	$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close($curl);
	stats_record('word', ['api_calls' => 1, 'dictionary_en_calls' => 1], ['last_api_call' => stats_now()]);
	dbg('Dictionary EN HTTP', $httpCode);

	if ($resp) {
		$entries = json_decode($resp, true);
		if (is_array($entries) && isset($entries[0])) {
			$e        = $entries[0];
			$word     = $e['word'] ?? $todayWord;
			$phonetic = $e['phonetic'] ?? ($e['phonetics'][0]['text'] ?? '');
			$pos      = $e['meanings'][0]['partOfSpeech'] ?? '';
			$defEn    = $e['meanings'][0]['definitions'][0]['definition'] ?? '';
			dbg('Definition EN', '"' . $defEn . '"');
		} else {
			dbg('Definition EN', 'Not found in dictionary');
		}
	}

	// ── Translate definition via MyMemory ─────────────────────────────────────
	function translateDef($text, $langPair) {
		if (!$text) return '';
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL            => 'https://api.mymemory.translated.net/get?q=' . urlencode($text) . '&langpair=' . $langPair,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 10,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => 0,
		]);
		$resp = curl_exec($curl);
		curl_close($curl);
		if (!$resp) return '';
		$data = json_decode($resp, true);
		return $data['responseData']['translatedText'] ?? '';
	}

	$wordFr = translateDef($word,  'en|fr');
	$wordEs = translateDef($word,  'en|es');
	$defFr  = translateDef($defEn, 'en|fr');
	$defEs  = translateDef($defEn, 'en|es');
	stats_record('word', ['mymemory_calls' => 4]);
	dbg('Translations', 'FR: ' . $wordFr . ', ES: ' . $wordEs);

	// ── Fetch phonetics for FR and ES words ───────────────────────────────────
	function fetchPhonetic($lookupWord, $lang) {
		if (!$lookupWord) return '';
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL            => 'https://api.dictionaryapi.dev/api/v2/entries/' . $lang . '/' . urlencode($lookupWord),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 10,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => 0,
		]);
		$resp = curl_exec($curl);
		curl_close($curl);
		if (!$resp) return '';
		$entries = json_decode($resp, true);
		if (!is_array($entries) || !isset($entries[0])) return '';
		$e = $entries[0];
		return $e['phonetic'] ?? ($e['phonetics'][0]['text'] ?? '');
	}

	$phoneticFr = fetchPhonetic($wordFr, 'fr');
	$phoneticEs = fetchPhonetic($wordEs, 'es');
	stats_record('word', ['dictionary_phonetic_calls' => 2]);
	dbg('Phonetics', 'FR: ' . $phoneticFr . ', ES: ' . $phoneticEs);

	$cached = [
		'word_key'      => $todayWord,
		'word'          => $word,
		'word_fr'       => $wordFr,
		'word_es'       => $wordEs,
		'phonetic'      => $phonetic,
		'phonetic_fr'   => $phoneticFr,
		'phonetic_es'   => $phoneticEs,
		'partOfSpeech'  => $pos,
		'definition_en' => $defEn,
		'definition_fr' => $defFr,
		'definition_es' => $defEs,
		'date'          => date('Y-m-d'),
	];

	if (!is_dir('word')) mkdir('word', 0755, true);
	file_put_contents($cacheFile, json_encode($cached));
}

// ── Append live show-flags and return ────────────────────────────────────────
$cached['show_fr'] = (bool)$showword_fr;
$cached['show_es'] = (bool)$showword_es;

if ($debug) {
	echo '<h2>Result</h2><pre>' . htmlspecialchars(json_encode($cached, JSON_PRETTY_PRINT)) . '</pre>';
	echo debugPageFooter();
} else {
	echo json_encode($cached);
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
