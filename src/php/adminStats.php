<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once('incs/stats.php');

// Handle reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
	stats_reset();
	header('Location: adminStats.php');
	exit;
}

$stats = stats_read();
$now   = time();

function since($ts) {
	if (!$ts) return '—';
	$diff = time() - strtotime($ts);
	if ($diff < 0)   return 'just now';
	if ($diff < 60)  return $diff . 's ago';
	if ($diff < 3600) return round($diff / 60) . 'm ago';
	if ($diff < 86400) return round($diff / 3600, 1) . 'h ago';
	return round($diff / 86400, 1) . 'd ago';
}

function sval($stats, $group, $key, $default = 0) {
	return $stats[$group][$key] ?? $default;
}

function sts($stats, $group, $key) {
	$v = $stats[$group][$key] ?? null;
	return $v ?: '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin — Stats</title>
	<?php include('adminSharedStyles.php'); ?>
	<style>
		.stats-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
			gap: 16px;
			margin-top: 8px;
		}
		.stat-card {
			background: #f8f9fa;
			border: 1px solid #e0e0e0;
			border-radius: 8px;
			padding: 16px 20px;
		}
		.stat-card h3 {
			margin: 0 0 12px;
			font-size: 14px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: #555;
			border-bottom: 1px solid #ddd;
			padding-bottom: 8px;
		}
		.stat-row {
			display: flex;
			justify-content: space-between;
			align-items: baseline;
			padding: 4px 0;
			font-size: 14px;
			border-bottom: 1px solid #f0f0f0;
		}
		.stat-row:last-child { border-bottom: none; }
		.stat-label { color: #555; }
		.stat-value { font-weight: 600; font-variant-numeric: tabular-nums; }
		.stat-value.ts { font-weight: 400; font-size: 12px; color: #444; }
		.stat-value.err { color: #c00; }
		.stat-value.ok  { color: #2a7a2a; }
		.stat-since { font-size: 11px; color: #888; margin-left: 6px; }
		.no-data { color: #aaa; font-size: 13px; font-style: italic; }
		.reset-form { margin-top: 20px; }
		.btn-danger { background: #c62828; color: #fff; border: none; padding: 9px 20px; border-radius: 6px; font-size: 14px; cursor: pointer; }
		.btn-danger:hover { background: #b71c1c; }
		.section-note { font-size: 12px; color: #888; margin: 0 0 6px; }
	</style>
</head>
<body>
<?php include('adminNav.php'); ?>

<h1>API Stats</h1>

<?php if (empty($stats)): ?>
<div class="card">
	<p class="no-data">No stats recorded yet. Stats are collected as the display runs.</p>
</div>
<?php else: ?>

<!-- ── Weather ────────────────────────────────────────────────────────────── -->
<?php $w = $stats['weather'] ?? []; if ($w): ?>
<div class="card">
	<h2>Weather — Open-Meteo</h2>
	<p class="section-note">Fetched at most once per hour. Frontend polls every <?= sval($stats,'weather','requests') ? '' : '' ?>30 min but only calls the API when the cached hour has passed.</p>
	<div class="stats-grid">
		<div class="stat-card">
			<h3>Requests</h3>
			<div class="stat-row"><span class="stat-label">Total requests</span><span class="stat-value"><?= sval($stats,'weather','requests') ?></span></div>
			<div class="stat-row"><span class="stat-label">API calls made</span><span class="stat-value"><?= sval($stats,'weather','api_calls') ?></span></div>
			<div class="stat-row"><span class="stat-label">Cache hits</span><span class="stat-value ok"><?= sval($stats,'weather','cache_hits') ?></span></div>
			<div class="stat-row"><span class="stat-label">Errors</span><span class="stat-value <?= sval($stats,'weather','errors') ? 'err' : 'ok' ?>"><?= sval($stats,'weather','errors') ?></span></div>
		</div>
		<div class="stat-card">
			<h3>Timing</h3>
			<div class="stat-row">
				<span class="stat-label">Last request</span>
				<span class="stat-value ts"><?= sts($stats,'weather','last_request') ?>
					<span class="stat-since"><?= since(sts($stats,'weather','last_request')) ?></span>
				</span>
			</div>
			<div class="stat-row">
				<span class="stat-label">Last API call</span>
				<span class="stat-value ts"><?= sts($stats,'weather','last_api_call') ?>
					<span class="stat-since"><?= since(sts($stats,'weather','last_api_call')) ?></span>
				</span>
			</div>
			<div class="stat-row"><span class="stat-label">Last HTTP status</span><span class="stat-value <?= (sval($stats,'weather','last_http_code',0) == 200) ? 'ok' : 'err' ?>"><?= sval($stats,'weather','last_http_code','—') ?></span></div>
		</div>
	</div>
</div>
<?php endif; ?>

<!-- ── Quote ──────────────────────────────────────────────────────────────── -->
<?php $q = $stats['quote'] ?? []; if ($q): ?>
<div class="card">
	<h2>Quote of the Day — dummyjson.com</h2>
	<p class="section-note">Fetched once per day at scheduled time. Can be forced via Word of Day admin page.</p>
	<div class="stats-grid">
		<div class="stat-card">
			<h3>Requests</h3>
			<div class="stat-row"><span class="stat-label">Total requests</span><span class="stat-value"><?= sval($stats,'quote','requests') ?></span></div>
			<div class="stat-row"><span class="stat-label">API calls made</span><span class="stat-value"><?= sval($stats,'quote','api_calls') ?></span></div>
			<div class="stat-row"><span class="stat-label">Cache hits</span><span class="stat-value ok"><?= sval($stats,'quote','cache_hits') ?></span></div>
			<div class="stat-row"><span class="stat-label">Force refreshes</span><span class="stat-value"><?= sval($stats,'quote','forced') ?></span></div>
			<div class="stat-row"><span class="stat-label">Errors</span><span class="stat-value <?= sval($stats,'quote','errors') ? 'err' : 'ok' ?>"><?= sval($stats,'quote','errors') ?></span></div>
		</div>
		<div class="stat-card">
			<h3>Timing</h3>
			<div class="stat-row">
				<span class="stat-label">Last request</span>
				<span class="stat-value ts"><?= sts($stats,'quote','last_request') ?>
					<span class="stat-since"><?= since(sts($stats,'quote','last_request')) ?></span>
				</span>
			</div>
			<div class="stat-row">
				<span class="stat-label">Last API call</span>
				<span class="stat-value ts"><?= sts($stats,'quote','last_api_call') ?>
					<span class="stat-since"><?= since(sts($stats,'quote','last_api_call')) ?></span>
				</span>
			</div>
			<div class="stat-row"><span class="stat-label">Last HTTP status</span><span class="stat-value <?= (sval($stats,'quote','last_http_code',0) == 200) ? 'ok' : 'err' ?>"><?= sval($stats,'quote','last_http_code','—') ?></span></div>
		</div>
	</div>
</div>
<?php endif; ?>

<!-- ── Word ───────────────────────────────────────────────────────────────── -->
<?php $wd = $stats['word'] ?? []; if ($wd): ?>
<div class="card">
	<h2>Word of the Day — dictionaryapi.dev + MyMemory</h2>
	<p class="section-note">Each word refresh makes up to 7 external calls: 1 EN definition + 4 MyMemory translations + 2 FR/ES phonetics.</p>
	<div class="stats-grid">
		<div class="stat-card">
			<h3>Requests</h3>
			<div class="stat-row"><span class="stat-label">Total requests</span><span class="stat-value"><?= sval($stats,'word','requests') ?></span></div>
			<div class="stat-row"><span class="stat-label">Full word fetches</span><span class="stat-value"><?= sval($stats,'word','api_calls') ?></span></div>
			<div class="stat-row"><span class="stat-label">Cache hits</span><span class="stat-value ok"><?= sval($stats,'word','cache_hits') ?></span></div>
			<div class="stat-row"><span class="stat-label">Force refreshes</span><span class="stat-value"><?= sval($stats,'word','forced') ?></span></div>
		</div>
		<div class="stat-card">
			<h3>External API breakdown</h3>
			<div class="stat-row"><span class="stat-label">dictionaryapi.dev (EN def)</span><span class="stat-value"><?= sval($stats,'word','dictionary_en_calls') ?></span></div>
			<div class="stat-row"><span class="stat-label">MyMemory translations</span><span class="stat-value"><?= sval($stats,'word','mymemory_calls') ?></span></div>
			<div class="stat-row"><span class="stat-label">dictionaryapi.dev (phonetics)</span><span class="stat-value"><?= sval($stats,'word','dictionary_phonetic_calls') ?></span></div>
			<div class="stat-row"><span class="stat-label">Total external calls</span><span class="stat-value"><?= sval($stats,'word','dictionary_en_calls') + sval($stats,'word','mymemory_calls') + sval($stats,'word','dictionary_phonetic_calls') ?></span></div>
		</div>
		<div class="stat-card">
			<h3>Timing</h3>
			<div class="stat-row">
				<span class="stat-label">Last request</span>
				<span class="stat-value ts"><?= sts($stats,'word','last_request') ?>
					<span class="stat-since"><?= since(sts($stats,'word','last_request')) ?></span>
				</span>
			</div>
			<div class="stat-row">
				<span class="stat-label">Last API call</span>
				<span class="stat-value ts"><?= sts($stats,'word','last_api_call') ?>
					<span class="stat-since"><?= since(sts($stats,'word','last_api_call')) ?></span>
				</span>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

<!-- ── Mealie ─────────────────────────────────────────────────────────────── -->
<?php $m = $stats['mealie'] ?? []; if ($m): ?>
<div class="card">
	<h2>Meal Plan — Mealie</h2>
	<p class="section-note">Each recipe fetch makes 3 calls: login → today's meal plan → recipe details.</p>
	<div class="stats-grid">
		<div class="stat-card">
			<h3>Requests</h3>
			<div class="stat-row"><span class="stat-label">Total requests</span><span class="stat-value"><?= sval($stats,'mealie','requests') ?></span></div>
			<div class="stat-row"><span class="stat-label">Login calls</span><span class="stat-value"><?= sval($stats,'mealie','login_calls') ?></span></div>
			<div class="stat-row"><span class="stat-label">Meal plan calls</span><span class="stat-value"><?= sval($stats,'mealie','mealplan_calls') ?></span></div>
			<div class="stat-row"><span class="stat-label">Recipe detail calls</span><span class="stat-value"><?= sval($stats,'mealie','recipe_calls') ?></span></div>
			<div class="stat-row"><span class="stat-label">Errors</span><span class="stat-value <?= sval($stats,'mealie','errors') ? 'err' : 'ok' ?>"><?= sval($stats,'mealie','errors') ?></span></div>
		</div>
		<div class="stat-card">
			<h3>Timing</h3>
			<div class="stat-row">
				<span class="stat-label">Last request</span>
				<span class="stat-value ts"><?= sts($stats,'mealie','last_request') ?>
					<span class="stat-since"><?= since(sts($stats,'mealie','last_request')) ?></span>
				</span>
			</div>
			<div class="stat-row">
				<span class="stat-label">Last recipe fetch</span>
				<span class="stat-value ts"><?= sts($stats,'mealie','last_api_call') ?>
					<span class="stat-since"><?= since(sts($stats,'mealie','last_api_call')) ?></span>
				</span>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- ── Reset ──────────────────────────────────────────────────────────────── -->
<div class="card">
	<h2>Reset</h2>
	<p style="font-size:13px;color:#555;margin:0 0 12px">Clears all counters back to zero. Useful after configuration changes or to start a fresh baseline.</p>
	<form method="post" class="reset-form" onsubmit="return confirm('Reset all stats?')">
		<input type="hidden" name="action" value="reset">
		<button type="submit" class="btn-danger">↺ Reset All Stats</button>
	</form>
</div>

<script>
// Auto-refresh every 30 seconds so "X ago" timestamps stay current
setTimeout(() => location.reload(), 30000);
</script>

</body>
</html>
