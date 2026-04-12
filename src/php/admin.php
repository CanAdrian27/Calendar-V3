<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('adminEnvHelper.php');

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$showweekly      = isset($_POST['showweekly']);
	$showmealie      = isset($_POST['showmealie']);
	$shownotes       = isset($_POST['shownotes']);
	$ui_font         = trim($_POST['ui_font']        ?? 'IBM Plex Sans');
	$event_font_size = max(8, min(20, (int)($_POST['event_font_size'] ?? 12)));

	if (writeEnvVars()) {
		$saved = true;
	} else {
		$error = 'Could not write env_vars.php — check file permissions.';
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin — Dashboard</title>
	<?php include('adminSharedStyles.php'); ?>
</head>
<body>
<?php include('adminNav.php'); ?>

<h1>Dashboard</h1>

<?php if ($saved): ?>
	<div class="notice success">✓ Settings saved successfully.</div>
<?php elseif ($error): ?>
	<div class="notice error">⚠ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

	<div class="card">
		<h2>Pages</h2>
		<p class="card-hint">Enable or disable pages in the display rotation (cycle with the T key).</p>
		<div class="toggle-row">
			<input type="checkbox" id="showweekly" name="showweekly" <?= $showweekly ? 'checked' : '' ?>>
			<label for="showweekly">Weekly calendar view</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showmealie" name="showmealie" <?= $showmealie ? 'checked' : '' ?>>
			<label for="showmealie">Mealie recipe page</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="shownotes" name="shownotes" <?= $shownotes ? 'checked' : '' ?>>
			<label for="shownotes">Notes page</label>
		</div>
	</div>

	<?php
	$font_options = [
		'— Sans-serif —'     => null,
		'IBM Plex Sans'      => null,
		'Inter'              => 'https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,600;0,700;1,400&display=swap',
		'Roboto'             => 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;0,700;1,400&display=swap',
		'Nunito'             => 'https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,400;0,600;0,700;1,400&display=swap',
		'Montserrat'         => 'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,700;1,400&display=swap',
		'— Serif —'          => null,
		'Playfair Display'   => 'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap',
		'Merriweather'       => 'https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;1,300;1,400&display=swap',
		'Lora'               => 'https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,600;0,700;1,400&display=swap',
		'Josefin Slab'       => 'https://fonts.googleapis.com/css2?family=Josefin+Slab:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400&display=swap',
		'— Script —'         => null,
		'Pacifico'           => 'https://fonts.googleapis.com/css2?family=Pacifico&display=swap',
		'Dancing Script'     => 'https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;600;700&display=swap',
		'Caveat'             => 'https://fonts.googleapis.com/css2?family=Caveat:wght@400;600;700&display=swap',
		'— Display & Special —' => null,
		'Bebas Neue'         => 'https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap',
		'Abril Fatface'      => 'https://fonts.googleapis.com/css2?family=Abril+Fatface&display=swap',
		'VT323'              => 'https://fonts.googleapis.com/css2?family=VT323&display=swap',
		'Special Elite'      => 'https://fonts.googleapis.com/css2?family=Special+Elite&display=swap',
	];
	$js_font_map = [];
	foreach ($font_options as $name => $url) {
		if ($url) $js_font_map[$name] = $url;
	}
	?>
	<div class="card">
		<h2>Appearance</h2>
		<label for="ui_font">Page font</label>
		<select id="ui_font" name="ui_font" onchange="previewFont(this.value)">
			<?php foreach ($font_options as $name => $url): ?>
				<?php if ($url === null && strpos($name, '—') !== false): ?>
					<option disabled><?= htmlspecialchars($name) ?></option>
				<?php else: ?>
					<option value="<?= htmlspecialchars($name) ?>" <?= ($ui_font === $name) ? 'selected' : '' ?>>
						<?= htmlspecialchars($name) ?>
					</option>
				<?php endif; ?>
			<?php endforeach; ?>
		</select>
		<div class="font-preview" id="font-preview">January · The quick brown fox jumps over the lazy dog</div>
		<label for="event_font_size" style="margin-top:4px">Calendar event font size (px)</label>
		<input type="number" id="event_font_size" name="event_font_size"
		       min="8" max="20" value="<?= (int)$event_font_size ?>">
	</div>
	<script>
	var _fontMap = <?= json_encode($js_font_map) ?>;
	var _loadedFonts = {};
	function previewFont(name) {
		var preview = document.getElementById('font-preview');
		var url = _fontMap[name];
		if (url && !_loadedFonts[name]) {
			var link = document.createElement('link');
			link.rel = 'stylesheet';
			link.href = url;
			document.head.appendChild(link);
			_loadedFonts[name] = true;
		}
		preview.style.fontFamily = "'" + name + "', serif";
	}
	previewFont(document.getElementById('ui_font').value);
	</script>

	<button type="submit" class="btn-save">Save Settings</button>
</form>

<div class="card" style="margin-top:24px">
	<h2>Debug Tools</h2>
	<p class="card-hint">Open an endpoint in debug mode to inspect its output and diagnose issues. Each link opens in a new tab.</p>
	<div style="margin-bottom:16px">
		<div style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#888;margin-bottom:8px;font-weight:600">Fetch — triggers external API calls</div>
		<div style="display:flex;flex-wrap:wrap;gap:8px">
			<a href="fetchWeather.php?debug=1"  target="_blank" class="btn-debug">fetchWeather</a>
			<a href="fetchCalendar.php?debug=1" target="_blank" class="btn-debug">fetchCalendar</a>
			<a href="fetchRecipe.php?debug=1"   target="_blank" class="btn-debug">fetchRecipe</a>
			<a href="fetchQuote.php?debug=1"    target="_blank" class="btn-debug">fetchQuote</a>
			<a href="fetchWord.php?debug=1"     target="_blank" class="btn-debug">fetchWord</a>
		</div>
	</div>
	<div>
		<div style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#888;margin-bottom:8px;font-weight:600">Load — reads local cache / data</div>
		<div style="display:flex;flex-wrap:wrap;gap:8px">
			<a href="loadWeather.php?debug=1"       target="_blank" class="btn-debug">loadWeather</a>
			<a href="loadCalsAndNotes.php?debug=1"  target="_blank" class="btn-debug">loadCalsAndNotes</a>
			<a href="loadSunData.php?debug=1"       target="_blank" class="btn-debug">loadSunData</a>
			<a href="loadSki.php?debug=1"           target="_blank" class="btn-debug">loadSki</a>
			<a href="SelectImages.php?debug=1"      target="_blank" class="btn-debug">SelectImages</a>
		</div>
	</div>
</div>

</body>
</html>
