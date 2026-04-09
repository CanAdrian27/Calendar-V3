<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ── Defaults ─────────────────────────────────────────────────────────────────
$calendars      = [];
$showski        = false;
$showclock      = false;
$showquote      = false;
$showword       = false;
$showword_fr    = false;
$showword_es    = false;
$showweekly     = false;
$showmealie     = false;
$shownotes      = false;
$show_notes_qr  = true;
$show_wifi_qr   = false;
$wifi_ssid      = '';
$wifi_password  = '';
$pi_base_url    = '';
$mealieUsername = '';
$mealiePassword = '';
$mealieUrl      = '';

if (file_exists('env_vars.php')) include('env_vars.php');

// ── Save env_vars ─────────────────────────────────────────────────────────────
$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$newCalendars = [];
	$urls  = $_POST['cal_url']  ?? [];
	$names = $_POST['cal_name'] ?? [];
	$pps   = $_POST['cal_pp']   ?? [];

	foreach ($urls as $i => $url) {
		$url = trim($url);
		if ($url === '') continue;
		$newCalendars[] = [
			'cal'         => $url,
			'name'        => trim($names[$i] ?? ''),
			'postprocess' => isset($pps[$i]),
		];
	}

	$newShowski        = isset($_POST['showski']);
	$newShowclock      = isset($_POST['showclock']);
	$newShowquote      = isset($_POST['showquote']);
	$newShowword       = isset($_POST['showword']);
	$newShowword_fr    = isset($_POST['showword_fr']);
	$newShowword_es    = isset($_POST['showword_es']);
	$newShowweekly     = isset($_POST['showweekly']);
	$newShowmealie     = isset($_POST['showmealie']);
	$newShownotes      = isset($_POST['shownotes']);
	$newShowNotesQr    = isset($_POST['show_notes_qr']);
	$newShowWifiQr     = isset($_POST['show_wifi_qr']);
	$newWifiSsid       = trim($_POST['wifi_ssid']      ?? '');
	$newWifiPassword   = trim($_POST['wifi_password']  ?? '');
	$newPiBaseUrl      = rtrim(trim($_POST['pi_base_url'] ?? ''), '/');
	$newMealieUsername = trim($_POST['mealieUsername'] ?? '');
	$newMealiePassword = trim($_POST['mealiePassword'] ?? '');
	$newMealieUrl      = trim($_POST['mealieUrl']      ?? '');

	$php  = "<?php\n";
	$php .= '$calendars = '    . var_export($newCalendars, true)        . ";\n\n";
	$php .= '$showski = '      . ($newShowski    ? 'true' : 'false')    . ";\n";
	$php .= '$showclock = '    . ($newShowclock  ? 'true' : 'false')    . ";\n";
	$php .= '$showquote = '    . ($newShowquote  ? 'true' : 'false')    . ";\n";
	$php .= '$showword = '     . ($newShowword    ? 'true' : 'false')    . ";\n";
	$php .= '$showword_fr = ' . ($newShowword_fr ? 'true' : 'false')    . ";\n";
	$php .= '$showword_es = ' . ($newShowword_es ? 'true' : 'false')    . ";\n";
	$php .= '$showweekly = '   . ($newShowweekly ? 'true' : 'false')    . ";\n";
	$php .= '$showmealie = '   . ($newShowmealie ? 'true' : 'false')    . ";\n";
	$php .= '$shownotes = '     . ($newShownotes   ? 'true' : 'false')   . ";\n";
	$php .= '$show_notes_qr = ' . ($newShowNotesQr ? 'true' : 'false')  . ";\n";
	$php .= '$show_wifi_qr = '  . ($newShowWifiQr  ? 'true' : 'false')  . ";\n";
	$php .= '$wifi_ssid = '     . var_export($newWifiSsid,     true)     . ";\n";
	$php .= '$wifi_password = ' . var_export($newWifiPassword, true)     . ";\n";
	$php .= '$pi_base_url = '   . var_export($newPiBaseUrl,   true)     . ";\n\n";
	$php .= '$mealieUsername = ' . var_export($newMealieUsername, true)  . ";\n";
	$php .= '$mealiePassword = ' . var_export($newMealiePassword, true) . ";\n";
	$php .= '$mealieUrl = '      . var_export($newMealieUrl,      true) . ";\n";

	if (file_put_contents('env_vars.php', $php) !== false) {
		$calendars      = $newCalendars;
		$showski        = $newShowski;
		$showclock      = $newShowclock;
		$showquote      = $newShowquote;
		$showword       = $newShowword;
		$showword_fr    = $newShowword_fr;
		$showword_es    = $newShowword_es;
		$showweekly     = $newShowweekly;
		$showmealie     = $newShowmealie;
		$shownotes      = $newShownotes;
		$show_notes_qr  = $newShowNotesQr;
		$show_wifi_qr   = $newShowWifiQr;
		$wifi_ssid      = $newWifiSsid;
		$wifi_password  = $newWifiPassword;
		$pi_base_url    = $newPiBaseUrl;
		$mealieUsername = $newMealieUsername;
		$mealiePassword = $newMealiePassword;
		$mealieUrl      = $newMealieUrl;
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
	<title>Admin — Options</title>
	<?php include('adminSharedStyles.php'); ?>
</head>
<body>
<?php include('adminNav.php'); ?>

<h1>Options</h1>

<?php if ($saved): ?>
	<div class="notice success">✓ Settings saved successfully.</div>
<?php elseif ($error): ?>
	<div class="notice error">⚠ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

	<div class="card">
		<h2>Calendars</h2>
		<div id="cal-list">
			<?php foreach ($calendars as $i => $cal): ?>
			<div class="cal-row">
				<input type="url"  name="cal_url[]"  placeholder="Calendar URL"  value="<?= htmlspecialchars($cal['cal']  ?? '') ?>">
				<input type="text" name="cal_name[]" placeholder="Display name"  value="<?= htmlspecialchars($cal['name'] ?? '') ?>">
				<div class="pp-cell">
					<input type="checkbox" name="cal_pp[<?= $i ?>]" <?= !empty($cal['postprocess']) ? 'checked' : '' ?>>
					<span>Runna</span>
				</div>
				<button type="button" class="btn-remove" onclick="removeRow(this)">×</button>
			</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="btn-add" onclick="addRow()">+ Add Calendar</button>
	</div>

	<div class="card">
		<h2>Mealie</h2>
		<label>Server URL <small style="font-weight:400;color:#888">(host:port, no http://)</small></label>
		<input type="text"     name="mealieUrl"      value="<?= htmlspecialchars($mealieUrl) ?>"      placeholder="192.168.1.100:9925">
		<label>Username</label>
		<input type="text"     name="mealieUsername" value="<?= htmlspecialchars($mealieUsername) ?>" autocomplete="off">
		<label>Password</label>
		<input type="password" name="mealiePassword" value="<?= htmlspecialchars($mealiePassword) ?>" autocomplete="off">
	</div>

	<div class="card">
		<h2>Display Options</h2>
		<div class="toggle-row">
			<input type="checkbox" id="showski"    name="showski"    <?= $showski    ? 'checked' : '' ?>>
			<label for="showski">Show ski conditions (Only Visible Nov. -> Mar.) </label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showclock"  name="showclock"  <?= $showclock  ? 'checked' : '' ?>>
			<label for="showclock">Show clock in weather area</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showquote"  name="showquote"  <?= $showquote  ? 'checked' : '' ?>>
			<label for="showquote">Show daily quote</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showword"    name="showword"    <?= $showword    ? 'checked' : '' ?>>
			<label for="showword">Show word of the day</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showword_fr" name="showword_fr" <?= $showword_fr ? 'checked' : '' ?>>
			<label for="showword_fr">Include French translation</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="showword_es" name="showword_es" <?= $showword_es ? 'checked' : '' ?>>
			<label for="showword_es">Include Spanish translation</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showweekly" name="showweekly" <?= $showweekly ? 'checked' : '' ?>>
			<label for="showweekly">Include weekly calendar view</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="showmealie" name="showmealie" <?= $showmealie ? 'checked' : '' ?>>
			<label for="showmealie">Include Mealie recipe page</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="shownotes"      name="shownotes"      <?= $shownotes      ? 'checked' : '' ?>>
			<label for="shownotes">Include notes page</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="show_notes_qr"  name="show_notes_qr"  <?= $show_notes_qr  ? 'checked' : '' ?>>
			<label for="show_notes_qr">Show "Edit Notes" QR code on notes page</label>
		</div>
		<div class="toggle-row" style="margin-left:26px">
			<input type="checkbox" id="show_wifi_qr"   name="show_wifi_qr"   <?= $show_wifi_qr   ? 'checked' : '' ?>>
			<label for="show_wifi_qr">Show WiFi QR code on notes page</label>
		</div>
	</div>

	<div class="card">
		<h2>WiFi QR Code</h2>
		<label>Network name (SSID)</label>
		<input type="text"     name="wifi_ssid"     value="<?= htmlspecialchars($wifi_ssid) ?>"     autocomplete="off" placeholder="MyNetwork">
		<label>Password</label>
		<input type="password" name="wifi_password" value="<?= htmlspecialchars($wifi_password) ?>" autocomplete="off" placeholder="••••••••">
	</div>

	<div class="card">
		<h2>Pi Address</h2>
		<label>Base URL <small style="font-weight:400;color:#888">Used for the "Edit Notes" QR code — enter the Pi's IP as seen from your phone</small></label>
		<input type="text" name="pi_base_url" value="<?= htmlspecialchars($pi_base_url) ?>" placeholder="http://192.168.1.42" autocomplete="off">
	</div>

	<button type="submit" class="btn-save">Save Settings</button>
</form>

<script>
var calIndex = <?= count($calendars) ?>;
function addRow() {
	var list = document.getElementById('cal-list');
	var row  = document.createElement('div');
	row.className = 'cal-row';
	row.innerHTML =
		'<input type="url"  name="cal_url[]"  placeholder="Calendar URL">' +
		'<input type="text" name="cal_name[]" placeholder="Display name">' +
		'<div class="pp-cell">' +
			'<input type="checkbox" name="cal_pp[' + calIndex + ']">' +
			'<span>Runna</span>' +
		'</div>' +
		'<button type="button" class="btn-remove" onclick="removeRow(this)">×</button>';
	list.appendChild(row);
	calIndex++;
}
function removeRow(btn) { btn.closest('.cal-row').remove(); }
</script>

</body>
</html>
