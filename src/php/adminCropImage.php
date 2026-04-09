<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
	exit;
}

$file = basename($_POST['file'] ?? '');
if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
	http_response_code(400);
	echo json_encode(['error' => 'Invalid filename — received: ' . json_encode($file)]);
	exit;
}

$path = 'images/' . $file;
if (!file_exists($path)) {
	http_response_code(404);
	echo json_encode(['error' => 'File not found: ' . $file]);
	exit;
}

$stem    = pathinfo($file, PATHINFO_FILENAME);
$suppDir = 'images_supports/';

try {

$img = new Imagick(realpath($path));
$img->autoOrient(); // ensure pixel orientation matches what the browser displays
$geo = $img->getImageGeometry();
$origW = $geo['width'];
$origH = $geo['height'];

// ── Interactive canvas composite (from crop modal) ────────────────────────────
if (isset($_POST['canvas_w'])) {
	$canvasW = (int)$_POST['canvas_w'];
	$canvasH = (int)$_POST['canvas_h'];
	$imgX    = (int)$_POST['img_x'];   // image left in canvas (can be negative)
	$imgY    = (int)$_POST['img_y'];   // image top in canvas  (can be negative)
	$imgW    = (int)$_POST['img_w'];   // image width in canvas (scaled by zoom)
	$imgH    = (int)$_POST['img_h'];   // image height in canvas

	if ($canvasW <= 0 || $canvasH <= 0 || $imgW <= 0 || $imgH <= 0) {
		echo json_encode(['error' => "Invalid dimensions — canvas:{$canvasW}×{$canvasH} img:{$imgW}×{$imgH}"]);
		exit;
	}

	// Resize the original image to the requested canvas size
	if ($imgW !== $origW || $imgH !== $origH) {
		$img->resizeImage($imgW, $imgH, Imagick::FILTER_LANCZOS, 1);
	}

	// Create transparent canvas
	$canvas = new Imagick();
	$canvas->newImage($canvasW, $canvasH, new ImagickPixel('transparent'));
	$canvas->setImageFormat('png');

	// Composite image onto canvas (Imagick handles out-of-bounds coords correctly)
	$canvas->compositeImage($img, Imagick::COMPOSITE_OVER, $imgX, $imgY);
	$img->destroy();

	// Save as PNG — replace old file with new PNG path
	$newFile = $stem . '.png';
	$newPath = 'images/' . $newFile;
	$canvas->writeImage($newPath);
	$canvas->destroy();

	// Remove old file if extension changed
	if ($newPath !== $path) {
		@unlink($path);
	}

	// Regenerate support files from the new PNG
	regenSupport($newPath, $stem, $suppDir);
	echo json_encode(['ok' => true, 'file' => $newFile]);
	exit;
}

// ── Auto centre-crop to 2:1 (fallback / legacy) ───────────────────────────────
$targetRatio  = 2.0;
$currentRatio = $origW / $origH;

if (abs($currentRatio - $targetRatio) < 0.01) {
	$img->destroy();
	echo json_encode(['ok' => true, 'message' => 'Already 2:1']);
	exit;
}

if ($currentRatio > $targetRatio) {
	$newW = (int) round($origH * $targetRatio);
	$newH = $origH;
	$x    = (int) (($origW - $newW) / 2);
	$y    = 0;
} else {
	$newW = $origW;
	$newH = (int) round($origW / $targetRatio);
	$x    = 0;
	$y    = (int) (($origH - $newH) / 2);
}

$img->cropImage($newW, $newH, $x, $y);
$img->setImagePage(0, 0, 0, 0);
$img->writeImage(realpath($path));
$img->destroy();

regenSupport($path, $stem, $suppDir);
echo json_encode(['ok' => true, 'newWidth' => $newW, 'newHeight' => $newH]);

} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['error' => get_class($e) . ': ' . $e->getMessage()]);
}

// ── Support file helper ───────────────────────────────────────────────────────
function regenSupport($imagePath, $stem, $suppDir) {
	$img = new Imagick(realpath($imagePath));
	$geo = $img->getImageGeometry();
	$stripH = 100;
	$startY = max(0, $geo['height'] - $stripH);
	$img->cropImage($geo['width'], $stripH, 0, $startY);
	$img->setImageFormat('jpeg');
	$img->writeImage($suppDir . $stem . '_1.jpg');
	$img->destroy();

	$img2 = new Imagick(realpath($suppDir . $stem . '_1.jpg'));
	$img2->gaussianBlurImage(10, 100);
	$img2->writeImage($suppDir . $stem . '_1.jpg');
	$img2->destroy();

	$img3 = new Imagick(realpath($suppDir . $stem . '_1.jpg'));
	$img3->resizeImage(1, 1, Imagick::FILTER_LANCZOS, 1);
	$img3->writeImage($suppDir . $stem . '_small.jpg');
	$img3->destroy();
}
?>
