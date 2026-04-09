<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── List ─────────────────────────────────────────────────────────────────────
if ($action === 'list') {
	$dir   = 'images/';
	$files = array_filter(scandir($dir), fn($f) => $f[0] !== '.');
	usort($files, fn($a, $b) => filemtime($dir . $b) - filemtime($dir . $a));
	$result = [];
	foreach ($files as $file) {
		$path = $dir . $file;
		$size = @getimagesize($path);
		$stem = pathinfo($file, PATHINFO_FILENAME);
		$result[] = [
			'file'       => $file,
			'width'      => $size ? $size[0] : 0,
			'height'     => $size ? $size[1] : 0,
			'hasSupport' => file_exists('images_supports/' . $stem . '_1.jpg'),
		];
	}
	echo json_encode($result);
	exit;
}

// ── Delete ───────────────────────────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$file = basename($_POST['file'] ?? '');
	if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
		http_response_code(400);
		echo json_encode(['error' => 'Invalid filename — received: ' . json_encode($file)]);
		exit;
	}
	$stem = pathinfo($file, PATHINFO_FILENAME);
	@unlink('images/' . $file);
	@unlink('images_supports/' . $stem . '_1.jpg');
	@unlink('images_supports/' . $stem . '_small.jpg');
	echo json_encode(['ok' => true]);
	exit;
}

// ── Upload ───────────────────────────────────────────────────────────────────
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$uploadErrorMessages = [
		UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize (' . ini_get('upload_max_filesize') . '). Edit /etc/php/*/apache2/php.ini and raise upload_max_filesize and post_max_size, then restart Apache.',
		UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE specified in the HTML form.',
		UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
		UPLOAD_ERR_NO_FILE    => 'No file was sent.',
		UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary upload folder.',
		UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk (check permissions on /tmp).',
		UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
	];
	$code = $_FILES['image']['error'] ?? -1;
	if (!isset($_FILES['image']) || $code !== UPLOAD_ERR_OK) {
		http_response_code(400);
		$msg = $uploadErrorMessages[$code] ?? 'Unknown upload error (code ' . $code . ')';
		echo json_encode([
			'error'            => $msg,
			'php_upload_limit' => ini_get('upload_max_filesize'),
			'php_post_limit'   => ini_get('post_max_size'),
		]);
		exit;
	}
	$tmp  = $_FILES['image']['tmp_name'];
	$type = mime_content_type($tmp);
	$ext  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
	$knownImageExts = ['jpg','jpeg','png','gif','webp','heic','heif','tiff','bmp'];

	if (!str_starts_with($type, 'image/') && !in_array($ext, $knownImageExts)) {
		http_response_code(400);
		echo json_encode(['error' => 'File is not an image (type: ' . $type . ')']);
		exit;
	}

	$stem = bin2hex(random_bytes(10));
	$dest = 'images/' . $stem . '.jpg';
	try {
		$img = new Imagick($tmp);
		$img->autoOrient(); // bake EXIF rotation into pixels so orientation is always correct
		$img->setImageFormat('jpeg');
		$img->writeImage($dest);
		$img->destroy();
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode(['error' => 'Image conversion failed: ' . $e->getMessage()]);
		exit;
	}
	createSupportFiles($dest, $stem);
	echo json_encode(['ok' => true, 'file' => $stem . '.jpg']);
	exit;
}

// ── Rotate ───────────────────────────────────────────────────────────────────
if ($action === 'rotate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$file = basename($_POST['file'] ?? '');
	$dir  = (int)($_POST['dir'] ?? 90); // 90 = clockwise, -90 = counter-clockwise
	if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
		http_response_code(400);
		echo json_encode(['error' => 'Invalid filename — received: ' . json_encode($file)]);
		exit;
	}
	if (!in_array($dir, [90, -90, 180])) {
		http_response_code(400);
		echo json_encode(['error' => 'Invalid rotation']);
		exit;
	}
	$path = 'images/' . $file;
	$stem = pathinfo($file, PATHINFO_FILENAME);
	try {
		$img = new Imagick(realpath($path));
		$img->autoOrient(); // apply any remaining EXIF orientation before rotating
		$img->rotateImage(new ImagickPixel('none'), $dir);
		$img->writeImage($path);
		$img->destroy();
		// Regenerate support files for the rotated image
		@unlink('images_supports/' . $stem . '_1.jpg');
		@unlink('images_supports/' . $stem . '_small.jpg');
		createSupportFiles($path, $stem);
		echo json_encode(['ok' => true]);
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode(['error' => 'Rotate failed: ' . $e->getMessage()]);
	}
	exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);

// ── Helpers ──────────────────────────────────────────────────────────────────
function createSupportFiles($imagePath, $stem) {
	$supportDir = 'images_supports/';
	$height = 100;

	$img = new Imagick(realpath($imagePath));
	$geo = $img->getImageGeometry();
	$startX = rand(0, max(0, floor($geo['height'] / $height)));
	$startY = max(0, $geo['height'] - $height);
	$img->cropImage($geo['width'], $height, $startX, $startY);
	$img->writeImage($supportDir . $stem . '_1.jpg');
	$img->destroy();

	$img2 = new Imagick(realpath($supportDir . $stem . '_1.jpg'));
	$img2->gaussianBlurImage(10, 100);
	$img2->writeImage($supportDir . $stem . '_1.jpg');
	$img2->destroy();

	$img3 = new Imagick(realpath($supportDir . $stem . '_1.jpg'));
	$img3->resizeImage(1, 1, Imagick::FILTER_LANCZOS, 1);
	$img3->writeImage($supportDir . $stem . '_small.jpg');
	$img3->destroy();
}
?>
