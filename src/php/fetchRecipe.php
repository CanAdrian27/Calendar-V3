<?php
error_reporting(E_ALL);
include_once('env_vars.php');
require_once('incs/stats.php');

$debug = isset($_GET['debug']);
if ($debug) {
	echo debugPageHeader('fetchRecipe');
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

stats_record('mealie', ['requests' => 1], ['last_request' => stats_now()]);

dbg('Mealie URL', $mealieUrl ?? '(not set)');
dbg('Username', $mealieUsername ?? '(not set)');

$accessToken = loginToMealie($mealieUsername, $mealiePassword, $mealieUrl);
stats_record('mealie', ['login_calls' => 1, 'errors' => $accessToken === null ? 1 : 0]);

if ($accessToken === null) {
	dbg('Login', 'FAILED — could not obtain access token');
	if (!$debug) echo 'null';
	if ($debug) echo debugPageFooter();
	exit;
}

dbg('Login', 'OK — token obtained');

$todaysMeal = getRecipeToday($accessToken, $mealieUrl);

if ($debug) {
	if ($todaysMeal) {
		$decoded = json_decode($todaysMeal);
		dbg('Recipe name', $decoded->name ?? '(no name)');
		dbg('Recipe slug', $decoded->slug ?? '(no slug)');
		echo '<h2>Full Response</h2><pre>' . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT)) . '</pre>';
	} else {
		dbg('Result', 'No meal plan for today — returned null');
	}
	echo debugPageFooter();
} else {
	echo $todaysMeal ?? 'null';
}


function getRecipeToday($accessToken, $mealieUrl)
{
	$url = 'http://' . $mealieUrl . '/api/households/mealplans/today';

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPGET, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Accept: application/json',
		'Authorization: Bearer ' . $accessToken,
	]);

	$response = curl_exec($ch);
	$err      = curl_errno($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	dbg('Mealplan today HTTP', $httpCode . ($err ? ' — curl error' : ''));
	stats_record('mealie', ['mealplan_calls' => 1, 'errors' => $err ? 1 : 0]);
	if ($err) return null;

	$responsObj = json_decode($response);
	if (empty($responsObj)) {
		dbg('Mealplan response', 'Empty — no meal planned today');
		return null;
	}

	$slug = $responsObj[0]->{'recipe'}->{'slug'} ?? null;
	if (!$slug) {
		dbg('Mealplan response', 'No slug found in response');
		return null;
	}

	dbg('Recipe slug', $slug);
	$recipeJSON = getRecipeDetails($slug, $accessToken, $mealieUrl);
	if (!$recipeJSON) return null;

	return $recipeJSON;
}

function getRecipeDetails($slug, $accessToken, $mealieUrl)
{
	$url = 'http://' . $mealieUrl . '/api/recipes/' . $slug;

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPGET, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Accept: application/json',
		'Authorization: Bearer ' . $accessToken,
	]);

	$response = curl_exec($ch);
	$err      = curl_errno($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	dbg('Recipe detail HTTP', $httpCode . ($err ? ' — curl error' : ''));
	stats_record('mealie', ['recipe_calls' => 1, 'errors' => $err ? 1 : 0], ['last_api_call' => stats_now()]);
	if ($err) return null;
	return $response;
}

function loginToMealie($username, $password, $mealieUrl)
{
	$url  = 'http://' . $mealieUrl . '/api/auth/token';
	$data = [
		'username'    => $username,
		'password'    => $password,
		'remember_me' => 'false',
	];

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Accept: application/json',
		'Content-Type: application/x-www-form-urlencoded',
	]);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

	$response = curl_exec($ch);
	$err      = curl_errno($ch);
	curl_close($ch);

	if ($err) return null;
	return json_decode($response)->access_token ?? null;
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
