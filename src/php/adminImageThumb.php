<?php
$file = basename($_GET['f'] ?? '');
if (!preg_match('/^[a-f0-9]+\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
	http_response_code(400);
	exit;
}
$path = 'images/' . $file;
if (!file_exists($path)) {
	http_response_code(404);
	exit;
}
$img = new Imagick(realpath($path));
$img->cropThumbnailImage(240, 160);
header('Content-Type: image/jpeg');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
echo $img->getImageBlob();
$img->destroy();
?>
