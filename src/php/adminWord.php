<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ── Defaults ──────────────────────────────────────────────────────────────────
$showword    = false;
$showword_fr = false;
$showword_es = false;

if (file_exists('env_vars.php')) include('env_vars.php');

$showword_fr = $showword_fr ?? false;
$showword_es = $showword_es ?? false;

include_once('wordDefaults.php'); // $defaultWords

$wordlistFile = 'word/wordlist.json';
$cacheFile    = 'word/report.json';

// ── Load current word list ────────────────────────────────────────────────────
$wljson = @file_get_contents($wordlistFile);
$words  = ($wljson !== false) ? json_decode($wljson, true) : null;
if (!is_array($words) || count($words) === 0) $words = $defaultWords;

$saved = false;
$error = '';
$preview = null;

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$raw = trim($_POST['wordlist'] ?? '');
	$newWords = array_values(array_filter(
		array_map('trim', explode("\n", str_replace("\r", '', $raw))),
		fn($w) => $w !== ''
	));

	if (count($newWords) === 0) {
		$error = 'Word list cannot be empty.';
	} else {
		if (!is_dir('word')) mkdir('word', 0755, true);
		if (file_put_contents($wordlistFile, json_encode($newWords)) !== false) {
			$words = $newWords;
			// Clear cache so definitions are re-fetched for the new word
			@unlink($cacheFile);
			$saved = true;

			// ── Immediately rebuild the cache ─────────────────────────────────
			$index    = abs(crc32(date('Y-m-d'))) % count($words);
			$todayWord = trim($words[$index]);

			function fetchWordPreview($todayWord, $showword_fr, $showword_es) {
				$word = $todayWord;
				$defEn = $phonetic = $pos = '';

				$curl = curl_init();
				curl_setopt_array($curl, [
					CURLOPT_URL            => 'https://api.dictionaryapi.dev/api/v2/entries/en/' . urlencode($todayWord),
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT        => 12,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_SSL_VERIFYPEER => 0,
				]);
				$resp = curl_exec($curl);
				curl_close($curl);

				if ($resp) {
					$entries = json_decode($resp, true);
					if (is_array($entries) && isset($entries[0])) {
						$e        = $entries[0];
						$word     = $e['word'] ?? $todayWord;
						$phonetic = $e['phonetic'] ?? ($e['phonetics'][0]['text'] ?? '');
						$pos      = $e['meanings'][0]['partOfSpeech'] ?? '';
						$defEn    = $e['meanings'][0]['definitions'][0]['definition'] ?? '';
					}
				}

				function mmTranslate($text, $lp) {
					if (!$text) return '';
					$c = curl_init();
					curl_setopt_array($c, [
						CURLOPT_URL            => 'https://api.mymemory.translated.net/get?q=' . urlencode($text) . '&langpair=' . $lp,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_TIMEOUT        => 10,
						CURLOPT_SSL_VERIFYPEER => 0,
					]);
					$r = curl_exec($c); curl_close($c);
					if (!$r) return '';
					$d = json_decode($r, true);
					return $d['responseData']['translatedText'] ?? '';
				}

				$wordFr = mmTranslate($word,  'en|fr');
				$wordEs = mmTranslate($word,  'en|es');
				$defFr  = mmTranslate($defEn, 'en|fr');
				$defEs  = mmTranslate($defEn, 'en|es');

				$data = [
					'word_key'     => $todayWord,
					'word'         => $word,
					'word_fr'      => $wordFr,
					'word_es'      => $wordEs,
					'phonetic'     => $phonetic,
					'partOfSpeech' => $pos,
					'definition_en'=> $defEn,
					'definition_fr'=> $defFr,
					'definition_es'=> $defEs,
					'date'         => date('Y-m-d'),
				];

				if (!is_dir('word')) mkdir('word', 0755, true);
				file_put_contents('word/report.json', json_encode($data));

				$data['show_fr'] = (bool)$showword_fr;
				$data['show_es'] = (bool)$showword_es;
				return $data;
			}

			$preview = fetchWordPreview($todayWord, $showword_fr, $showword_es);
		} else {
			$error = 'Could not write word/wordlist.json — check file permissions.';
		}
	}
}

// ── Load preview from cache if not just built ─────────────────────────────────
if (!$preview) {
	$cjson = @file_get_contents($cacheFile);
	if ($cjson) {
		$preview = json_decode($cjson, true);
		if ($preview) {
			$preview['show_fr'] = (bool)$showword_fr;
			$preview['show_es'] = (bool)$showword_es;
		}
	}
}

// Today's word from current list (for display even without cache)
$todayIndex = abs(crc32(date('Y-m-d'))) % count($words);
$todayWord  = $words[$todayIndex];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin — Word of the Day</title>
	<?php include('adminSharedStyles.php'); ?>
	<style>
		textarea {
			width: 100%;
			font-family: monospace;
			font-size: 13px;
			border: 1px solid #d0d5dd;
			border-radius: 6px;
			padding: 10px;
			resize: vertical;
			min-height: 300px;
			margin-bottom: 14px;
		}
		textarea:focus { outline: none; border-color: #4f6ef7; box-shadow: 0 0 0 3px rgba(79,110,247,.15); }
		.preview-word { font-size: 26px; font-weight: 700; }
		.preview-phonetic { font-size: 13px; color: #666; font-style: italic; margin-left: 8px; }
		.preview-pos { font-size: 12px; color: #888; font-style: italic; margin-left: 4px; }
		.preview-def { font-size: 14px; margin-top: 8px; line-height: 1.5; }
		.preview-lang { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #4f6ef7; margin-bottom: 4px; }
		.preview-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 14px; }
		.lang-card { background: #f8f9fb; border: 1px solid #e4e7ec; border-radius: 8px; padding: 12px 14px; }
		.word-count { font-size: 12px; color: #888; margin-bottom: 6px; }
		.today-badge { display: inline-block; background: #4f6ef7; color: #fff; border-radius: 4px; font-size: 11px; font-weight: 600; padding: 2px 8px; margin-bottom: 10px; }
	</style>
</head>
<body>
<?php include('adminNav.php'); ?>

<h1>Word of the Day</h1>

<?php if (!$showword): ?>
	<div class="notice error">Word of the day is currently <strong>disabled</strong>. Enable it in <a href="admin.php">Options</a>.</div>
<?php endif; ?>

<?php if ($saved): ?>
	<div class="notice success">✓ Word list saved. Definitions reloaded.</div>
<?php elseif ($error): ?>
	<div class="notice error">⚠ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($preview): ?>
<div class="card">
	<h2>Today's Word</h2>
	<span class="today-badge">Today · <?= htmlspecialchars(date('M j, Y')) ?></span>
	<div>
		<span class="preview-word"><?= htmlspecialchars($preview['word'] ?? $todayWord) ?></span>
		<?php if (!empty($preview['phonetic'])): ?>
			<span class="preview-phonetic"><?= htmlspecialchars($preview['phonetic']) ?></span>
		<?php endif; ?>
		<?php if (!empty($preview['partOfSpeech'])): ?>
			<span class="preview-pos">(<?= htmlspecialchars($preview['partOfSpeech']) ?>)</span>
		<?php endif; ?>
	</div>
	<?php if (!empty($preview['definition_en'])): ?>
		<div class="preview-def"><?= htmlspecialchars($preview['definition_en']) ?></div>
	<?php endif; ?>

	<?php if (!empty($preview['definition_fr']) || !empty($preview['definition_es'])): ?>
	<div class="preview-grid">
		<?php if (!empty($preview['definition_fr'])): ?>
		<div class="lang-card">
			<div class="preview-lang">Français <?= $showword_fr ? '' : '<span style="color:#888;font-weight:400">(disabled)</span>' ?></div>
			<div class="preview-def"><?= htmlspecialchars($preview['definition_fr']) ?></div>
		</div>
		<?php endif; ?>
		<?php if (!empty($preview['definition_es'])): ?>
		<div class="lang-card">
			<div class="preview-lang">Español <?= $showword_es ? '' : '<span style="color:#888;font-weight:400">(disabled)</span>' ?></div>
			<div class="preview-def"><?= htmlspecialchars($preview['definition_es']) ?></div>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
<?php else: ?>
<div class="card">
	<h2>Today's Word</h2>
	<span class="today-badge">Today · <?= htmlspecialchars(date('M j, Y')) ?></span>
	<div class="preview-word"><?= htmlspecialchars($todayWord) ?></div>
	<div class="preview-def" style="color:#888">Definition will load on next dashboard refresh.</div>
</div>
<?php endif; ?>

<div class="card">
	<h2>Word List</h2>
	<div class="word-count"><?= count($words) ?> words &mdash; today's word is #<?= $todayIndex + 1 ?> (<strong><?= htmlspecialchars($todayWord) ?></strong>)</div>
	<form method="POST">
		<label>One word per line. Saving will immediately reload definitions for today's word.</label>
		<textarea name="wordlist"><?= htmlspecialchars(implode("\n", $words)) ?></textarea>
		<button type="submit" class="btn-save">Save &amp; Reload Definitions</button>
	</form>
</div>

<div class="card">
	<h2>Force Refresh</h2>
	<div style="display:flex;gap:12px;flex-wrap:wrap">
		<button class="btn-save" onclick="forceRefresh('fetchWord.php','word-status')">↺ New Word</button>
		<button class="btn-save" onclick="forceRefresh('fetchQuote.php','quote-status')">↺ New Quote</button>
	</div>
	<div id="word-status"  style="margin-top:8px;font-size:13px"></div>
	<div id="quote-status" style="margin-top:8px;font-size:13px"></div>
</div>

<script>
function forceRefresh(endpoint, statusId) {
	var el = document.getElementById(statusId);
	el.textContent = 'Fetching…';
	fetch(endpoint, { method: 'POST', body: new URLSearchParams({ force: 1 }) })
		.then(function(r) { return r.json(); })
		.then(function(data) {
			if (!data) { el.textContent = '⚠ No data returned.'; return; }
			if (data.q)    { el.textContent = '✓ "' + data.q + '" — ' + data.a; return; }
			if (data.word) { el.textContent = '✓ ' + data.word + (data.definition_en ? ' — ' + data.definition_en : ''); return; }
			el.textContent = '⚠ No data returned.';
		})
		.catch(function() { el.textContent = '⚠ Request failed.'; });
}
</script>

</body>
</html>
