<?php
// ── API Stats tracker ─────────────────────────────────────────────────────────
// All functions use an exclusive file lock to prevent race conditions.

define('STATS_FILE', __DIR__ . '/../stats/api_stats.json');
define('STATS_LOCK', __DIR__ . '/../stats/api_stats.lock');

function stats_read() {
	$json = @file_get_contents(STATS_FILE);
	return $json ? (json_decode($json, true) ?: []) : [];
}

function stats_write($stats) {
	@mkdir(dirname(STATS_FILE), 0755, true);
	file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

// Increment one or more counters and optionally set timestamp/value fields.
// $increments : ['field' => amount, ...]
// $sets       : ['field' => value, ...]
function stats_record($group, $increments = [], $sets = []) {
	@mkdir(dirname(STATS_LOCK), 0755, true);
	$fp = fopen(STATS_LOCK, 'w');
	if (!$fp) return;
	flock($fp, LOCK_EX);

	$stats = stats_read();
	if (!isset($stats[$group])) $stats[$group] = [];

	foreach ($increments as $field => $amount) {
		$stats[$group][$field] = ($stats[$group][$field] ?? 0) + $amount;
	}
	foreach ($sets as $field => $value) {
		$stats[$group][$field] = $value;
	}

	stats_write($stats);
	flock($fp, LOCK_UN);
	fclose($fp);
}

function stats_reset() {
	@mkdir(dirname(STATS_LOCK), 0755, true);
	$fp = fopen(STATS_LOCK, 'w');
	if (!$fp) return;
	flock($fp, LOCK_EX);
	stats_write([]);
	flock($fp, LOCK_UN);
	fclose($fp);
}

function stats_now() {
	return date('Y-m-d H:i:s');
}
?>
