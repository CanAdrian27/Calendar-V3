<?php
error_reporting(E_ALL);
include_once('env_vars.php');
require_once('incs/stats.php');

stats_record('mealie', ['requests' => 1], ['last_request' => stats_now()]);

$accessToken = loginToMealie($mealieUsername, $mealiePassword, $mealieUrl);
stats_record('mealie', ['login_calls' => 1, 'errors' => $accessToken === null ? 1 : 0]);
if ($accessToken === null) {
	echo 'null';
	exit;
}
$todaysMeal = getRecipeToday($accessToken, $mealieUrl);
echo $todaysMeal ?? 'null';


function getRecipeToday($accessToken, $mealieUrl)
{
	$url = 'http://'.$mealieUrl.'/api/households/mealplans/today';

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPGET, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Accept: application/json',
		'Authorization: Bearer ' . $accessToken
	]);

	$response = curl_exec($ch);
	$err = curl_errno($ch);
	curl_close($ch);

	stats_record('mealie', ['mealplan_calls' => 1, 'errors' => $err ? 1 : 0]);
	if ($err) return null;

	$responsObj = json_decode($response);
	if (empty($responsObj)) return null;

	$slug = $responsObj[0]->{'recipe'}->{'slug'} ?? null;
	if (!$slug) return null;

	$recipeJSON = getRecipeDetails($slug, $accessToken, $mealieUrl);
	if (!$recipeJSON) return null;

	return $recipeJSON;
}

function getRecipeDetails($slug, $accessToken, $mealieUrl)
{
	$url = 'http://'.$mealieUrl.'/api/recipes/'.$slug;

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPGET, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Accept: application/json',
		'Authorization: Bearer ' . $accessToken
	]);

	$response = curl_exec($ch);
	$err = curl_errno($ch);
	curl_close($ch);

	stats_record('mealie', ['recipe_calls' => 1, 'errors' => $err ? 1 : 0], ['last_api_call' => stats_now()]);
	if ($err) return null;
	return $response;
}

function loginToMealie($username, $password, $mealieUrl)
{
	$url = 'http://'.$mealieUrl.'/api/auth/token';

	$data = [
		'username' => $username,
		'password' => $password,
		'remember_me' => 'false',
	];

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Accept: application/json',
		'Content-Type: application/x-www-form-urlencoded'
	]);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

	$response = curl_exec($ch);
	$err = curl_errno($ch);
	curl_close($ch);

	if ($err) return null;
	return json_decode($response)->access_token ?? null;
}
