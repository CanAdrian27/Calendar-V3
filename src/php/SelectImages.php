<?php
	ini_set('display_errors', 0);
	ini_set('display_startup_errors', 0);
	error_reporting(E_ALL);

	require_once('incs/phpcolors/src/Mexitek/PHPColors/Color.php');
	require_once('incs/functions.php');
	include_once('env_vars.php');

	$debug = isset($_GET['debug']);

	$dir         = 'images/';
	$support_dir = 'images_supports/';
	$alpha       = 0.45;

	$ui_font         = isset($ui_font)           ? $ui_font               : 'IBM Plex Sans';
	$event_font_size = isset($event_font_size)   ? (int)$event_font_size  : 12;
	$color_scheme    = isset($color_scheme)      ? $color_scheme          : 'image_low';
	$color_scheme_base = isset($color_scheme_base) ? $color_scheme_base   : '#4a90d9';
	$image_height    = isset($image_height)      ? max(200, min(1800, (int)$image_height)) : 750;

	// Google Fonts URL for each supported font (IBM Plex Sans is already bundled in layout.css).
	$font_urls = [
		'IBM Plex Sans'    => null,
		'Inter'            => 'https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,600;0,700;1,400&display=swap',
		'Roboto'           => 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;0,700;1,400&display=swap',
		'Nunito'           => 'https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,400;0,600;0,700;1,400&display=swap',
		'Montserrat'       => 'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,700;1,400&display=swap',
		'Playfair Display' => 'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap',
		'Merriweather'     => 'https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;1,300;1,400&display=swap',
		'Lora'             => 'https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,600;0,700;1,400&display=swap',
		'Josefin Slab'     => 'https://fonts.googleapis.com/css2?family=Josefin+Slab:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400&display=swap',
		'Pacifico'         => 'https://fonts.googleapis.com/css2?family=Pacifico&display=swap',
		'Dancing Script'   => 'https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;600;700&display=swap',
		'Caveat'           => 'https://fonts.googleapis.com/css2?family=Caveat:wght@400;600;700&display=swap',
		'Bebas Neue'       => 'https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap',
		'Abril Fatface'    => 'https://fonts.googleapis.com/css2?family=Abril+Fatface&display=swap',
		'VT323'            => 'https://fonts.googleapis.com/css2?family=VT323&display=swap',
		'Special Elite'    => 'https://fonts.googleapis.com/css2?family=Special+Elite&display=swap',
	];
	$font_url = $font_urls[$ui_font] ?? null;
	$retArr['font_link'] = $font_url
		? '<link rel="stylesheet" href="' . htmlspecialchars($font_url) . '">'
		: '';

	$imageFiles = is_dir($dir) ? array_filter(scandir($dir), fn($f) => $f[0] !== '.') : [];

	if (count($imageFiles) === 0) {
		// No images uploaded yet — try to fetch a placeholder from Unsplash/picsum
		$fetched = fetchFallbackImage($dir, $support_dir);
		if ($fetched) {
			$imageFiles = [$fetched];
		}
	}

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

	// Save dominant colour for admin preview (modes that depend on the image)
	if (isset($images['color']['r'])) {
		@file_put_contents(
			$support_dir . 'last_color.json',
			json_encode(['r' => $images['color']['r'], 'g' => $images['color']['g'], 'b' => $images['color']['b']])
		);
	}

	$extra_css = "--ae-font-family:'" . addslashes($ui_font) . "';--ae-event-font-size:{$event_font_size}px;--ae-image-height:{$image_height}px;";

	$retArr['image']       = $images['image'];
	$retArr['blurry']      = $images['background'];
	$retArr['alpha_color'] = ':root{' . setColours($images, $alpha, $color_scheme, $color_scheme_base) . $extra_css . '}';

	if ($debug) {
		echo debugPageHeader('SelectImages');
		echo '<div class="dbg-row"><span class="dbg-label">Images directory</span><span class="dbg-val">' . htmlspecialchars($dir) . ' — ' . count($imageFiles) . ' image(s)' . (count($imageFiles) === 1 && isset($fetched) && $fetched ? ' (fallback downloaded)' : '') . '</span></div>';
		echo '<div class="dbg-row"><span class="dbg-label">Selected image</span><span class="dbg-val">' . htmlspecialchars($images['image'] ?: '(none)') . '</span></div>';
		echo '<div class="dbg-row"><span class="dbg-label">Dominant color</span><span class="dbg-val">r=' . ($images['color']['r'] ?? '?') . ' g=' . ($images['color']['g'] ?? '?') . ' b=' . ($images['color']['b'] ?? '?') . '</span></div>';
		echo '<div class="dbg-row"><span class="dbg-label">Colour scheme</span><span class="dbg-val">' . htmlspecialchars($color_scheme) . '</span></div>';
		echo '<div class="dbg-row"><span class="dbg-label">Scheme base colour</span><span class="dbg-val">' . htmlspecialchars($color_scheme_base) . '</span></div>';
		echo '<div class="dbg-row"><span class="dbg-label">Font</span><span class="dbg-val">' . htmlspecialchars($ui_font) . ' — event size: ' . $event_font_size . 'px</span></div>';
		echo '<div class="dbg-row"><span class="dbg-label">Font link</span><span class="dbg-val">' . htmlspecialchars($retArr['font_link'] ?: '(none — bundled)') . '</span></div>';
		echo '<h2>CSS Variables</h2><pre>' . htmlspecialchars($retArr['alpha_color']) . '</pre>';
		if ($images['image']) {
			echo '<h2>Preview</h2><img src="' . htmlspecialchars($images['image']) . '" style="max-width:400px;border-radius:8px;border:1px solid #d0d5dd">';
		}
		echo debugPageFooter();
	} else {
		echo json_encode($retArr);
	}

	// Downloads a random nature photo from Unsplash (with picsum fallback) when
	// the images/ folder is empty. Saves the image and generates the required
	// support files so makeCalBackground() works on the next request too.
	function fetchFallbackImage($dir, $support_dir) {
		$sources = [
			'https://source.unsplash.com/1080x1920/?nature,landscape',
			'https://picsum.photos/1080/1920',
		];

		$imageData = null;
		foreach ($sources as $url) {
			$curl = curl_init();
			curl_setopt_array($curl, [
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_TIMEOUT        => 15,
				CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; CalendarV3)',
			]);
			$data     = curl_exec($curl);
			$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			curl_close($curl);

			if ($data && $httpCode === 200) {
				$imageData = $data;
				break;
			}
		}

		if (!$imageData) return null;

		// Save the downloaded image with a random hex filename
		$stem     = bin2hex(random_bytes(10));
		$filename = $stem . '.jpg';
		if (!is_dir($dir))         mkdir($dir, 0775, true);
		if (!is_dir($support_dir)) mkdir($support_dir, 0775, true);

		if (file_put_contents($dir . $filename, $imageData) === false) return null;

		// Generate support files using the same pipeline as processImages.php:
		// 1. Crop a 100×100 block from the bottom of the image
		// 2. Gaussian-blur it (used as stretched CSS background)
		// 3. Resize to 1×1 for dominant colour extraction
		try {
			$imagick = new \Imagick();
			$imagick->readImageBlob($imageData);
			$geos    = $imagick->getImageGeometry();
			$size    = 100;
			$startX  = rand(0, max(0, (int)floor($geos['height'] / $size)));
			$startY  = max(0, $geos['height'] - $size);
			$imagick->cropImage($size, $size, $startX, $startY);
			$imagick->writeImage($support_dir . $stem . '_1.jpg');
			$imagick->destroy();

			$blur = new \Imagick(realpath($support_dir . $stem . '_1.jpg'));
			$blur->gaussianBlurImage(10, 100);
			$blur->writeImage($support_dir . $stem . '_1.jpg');
			$blur->destroy();

			$small = new \Imagick(realpath($support_dir . $stem . '_1.jpg'));
			$small->resizeImage(1, 1, \Imagick::FILTER_LANCZOS, 1);
			$small->writeImage($support_dir . $stem . '_small.jpg');
			$small->destroy();
		} catch (\Exception $e) {
			// ImageMagick failed — image is saved but unprocessed; makeCalBackground
			// will still serve it (it falls back to unprocessed files).
			return $filename;
		}

		return $filename;
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
?>
