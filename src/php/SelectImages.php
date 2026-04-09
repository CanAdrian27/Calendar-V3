<?php
	ini_set('display_errors', 0);
	ini_set('display_startup_errors', 0);
	error_reporting(E_ALL);

	require_once('incs/phpcolors/src/Mexitek/PHPColors/Color.php');
	require_once('incs/functions.php');

	$dir         = 'images/';
	$support_dir = 'images_supports/';
	$alpha       = 0.45;

	$imageFiles = is_dir($dir) ? array_filter(scandir($dir), fn($f) => $f[0] !== '.') : [];

	if (count($imageFiles) === 0) {
		$images = [
			'image'      => '',
			'background' => '',
			'color'      => ['r' => 26, 'g' => 42, 'b' => 74],
		];
	} else {
		$imagecnt = 3;
		$images = makeCalBackground($dir, $support_dir, $imagecnt);
	}

	$retArr['image']       = $images['image'];
	$retArr['blurry']      = $images['background'];
	$retArr['alpha_color'] = ':root{' . setColours($images, $alpha) . '}';
	echo json_encode($retArr);
?>