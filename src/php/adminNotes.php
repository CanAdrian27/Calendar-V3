<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('adminEnvHelper.php');

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
	$show_notes_qr = isset($_POST['show_notes_qr']);
	$show_wifi_qr  = isset($_POST['show_wifi_qr']);
	$wifi_ssid     = trim($_POST['wifi_ssid']    ?? '');
	$wifi_password = trim($_POST['wifi_password'] ?? '');
	$pi_base_url   = rtrim(trim($_POST['pi_base_url'] ?? ''), '/');

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
	<title>Admin — Notes</title>
	<?php include('adminSharedStyles.php'); ?>
	<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
</head>
<body>
<?php include('adminNav.php'); ?>

<h1>Notes</h1>

<?php if ($saved): ?>
	<div class="notice success">✓ Settings saved successfully.</div>
<?php elseif ($error): ?>
	<div class="notice error">⚠ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$shownotes): ?>
	<div class="notice error">The notes page is currently <strong>disabled</strong>. Enable it in <a href="admin.php">Dashboard</a>.</div>
<?php endif; ?>

<form method="POST">
	<input type="hidden" name="save_settings" value="1">
	<div class="card">
		<h2>QR Codes</h2>
		<p class="card-hint">QR codes appear in the corner of the notes page for easy access.</p>
		<div class="toggle-row">
			<input type="checkbox" id="show_notes_qr" name="show_notes_qr" <?= $show_notes_qr ? 'checked' : '' ?>>
			<label for="show_notes_qr">Show "Edit Notes" QR code</label>
		</div>
		<div class="toggle-row">
			<input type="checkbox" id="show_wifi_qr" name="show_wifi_qr" <?= $show_wifi_qr ? 'checked' : '' ?>>
			<label for="show_wifi_qr">Show WiFi QR code</label>
		</div>
	</div>

	<div class="card">
		<h2>WiFi Credentials</h2>
		<p class="card-hint">Used to generate the WiFi QR code above.</p>
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

	<button type="submit" class="btn-save" style="margin-bottom:24px">Save Settings</button>
</form>

<div class="card">
	<h2>Notes Content</h2>
	<div id="quill-editor"></div>
	<div style="display:flex;gap:12px;align-items:center;margin-top:12px">
		<button type="button" class="btn-save" style="width:auto;padding:10px 28px" onclick="saveNotes()">Save Notes</button>
		<span id="notes-status" style="font-size:13px;color:#555"></span>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
var quill = new Quill('#quill-editor', {
	theme: 'snow',
	modules: {
		toolbar: [
			[{ header: [1, 2, 3, false] }],
			['bold', 'italic', 'underline'],
			[{ list: 'ordered' }, { list: 'bullet' }],
			[{ size: ['small', false, 'large', 'huge'] }],
			['clean']
		]
	}
});

fetch('notes/note.html')
	.then(r => r.text())
	.then(html => {
		var match = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
		quill.clipboard.dangerouslyPasteHTML(match ? match[1] : html);
	})
	.catch(() => {});

function saveNotes() {
	var status = document.getElementById('notes-status');
	status.textContent = 'Saving…';
	var fd = new FormData();
	fd.append('html', quill.root.innerHTML);
	fetch('adminSaveNotes.php', { method: 'POST', body: fd })
		.then(r => r.json())
		.then(res => { status.textContent = res.ok ? '✓ Saved' : '⚠ ' + res.error; })
		.catch(() => { status.textContent = '⚠ Save failed'; });
}
</script>
</body>
</html>
