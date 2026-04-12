<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('adminEnvHelper.php');

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$mealieUrl      = trim($_POST['mealieUrl']      ?? '');
	$mealieUsername = trim($_POST['mealieUsername'] ?? '');
	$mealiePassword = trim($_POST['mealiePassword'] ?? '');

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
	<title>Admin — Recipe</title>
	<?php include('adminSharedStyles.php'); ?>
</head>
<body>
<?php include('adminNav.php'); ?>

<h1>Recipe</h1>

<?php if ($saved): ?>
	<div class="notice success">✓ Settings saved successfully.</div>
<?php elseif ($error): ?>
	<div class="notice error">⚠ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$showmealie): ?>
	<div class="notice error">The recipe page is currently <strong>disabled</strong>. Enable it in <a href="admin.php">Dashboard</a>.</div>
<?php endif; ?>

<form method="POST">
	<div class="card">
		<h2>Mealie Connection</h2>
		<p class="card-hint">Mealie is a self-hosted meal planner. Enter your server details below to display today's meal plan on the recipe page.</p>
		<label>Server URL <small style="font-weight:400;color:#888">(host:port, no http://)</small></label>
		<input type="text"     name="mealieUrl"      value="<?= htmlspecialchars($mealieUrl) ?>"      placeholder="192.168.1.100:9925">
		<label>Username</label>
		<input type="text"     name="mealieUsername" value="<?= htmlspecialchars($mealieUsername) ?>" autocomplete="off">
		<label>Password</label>
		<input type="password" name="mealiePassword" value="<?= htmlspecialchars($mealiePassword) ?>" autocomplete="off">
	</div>

	<button type="submit" class="btn-save">Save Settings</button>
</form>

</body>
</html>
