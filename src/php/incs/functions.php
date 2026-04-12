<?php
ini_set('error_reporting', false);
use Mexitek\PHPColors\Color;

// ── Shared today/holiday CSS variables from a 6-char hex ─────────────────────

function ae_todayVars($hex, $alpha) {
	$rgb = Color::hexToRgb($hex);
	$cc  = getContrastColor($rgb);
	$border = ($cc === '#000000') ? '#000' : '#fff';
	return '--fc-today-bg-color:rgba('.$rgb['R'].','.$rgb['G'].','.$rgb['B'].','.$alpha.');'
	     . '--fc-today-border:3px solid '.$border.';'
	     . '--fc-event-border-color:rgba('.$rgb['R'].','.$rgb['G'].','.$rgb['B'].','.$alpha.');'
	     . '--ae-holiday-color:rgba('.$rgb['R'].','.$rgb['G'].','.$rgb['B'].','.($alpha*0.5).');';
}

// ── Mode 1: Image Theme — Slight Variability ─────────────────────────────────
// Same algorithm as Mode 4 (Shades of Selected Colour) but the hue is taken
// from the image's dominant colour rather than a user choice.
// Generates 10 evenly-spaced lightness steps (25 %→70 %) in the image's hue.

function calColorSet($color, $alpha)
{
	$baseHex = ltrim($color, '#');
	$hsl     = Color::hexToHsl($baseHex);
	$h       = $hsl['H'];
	$s       = max(0.45, $hsl['S']); // boost very desaturated image colours
	$string  = '';

	for ($i = 0; $i < 10; $i++) {
		$l   = 0.25 + $i * 0.05;   // 0.25 → 0.70
		$hex = Color::hslToHex(['H' => $h, 'S' => $s, 'L' => $l]);
		$rgb = Color::hexToRgb($hex);
		$cc  = getContrastColor($rgb);
		$string .= "--ae-cal$i-color:#$hex;--ae-com$i-color:$cc;";
		if ($i === 3) $string .= ae_todayVars($hex, $alpha);
	}
	return $string;
}

// ── Mode 2: Image Theme — High Variability ────────────────────────────────────
// Same shading idea as Mode 1/4 but spread across 5 harmonically related hues
// derived from the image colour (pentagonal 72° intervals).
// Calendars 0–4 get dark shades (~38 % L) of each hue; 5–9 get light shades
// (~60 % L) of the same 5 hues, so consecutive calendars stay distinct.

function calColorSetHigh($baseHex, $alpha)
{
	$hsl     = Color::hexToHsl($baseHex);      // H:0-360, S:0-1, L:0-1
	$h       = $hsl['H'];
	$s       = max(0.55, min(0.85, $hsl['S']));
	$offsets = [0, 72, 144, 216, 288];        // pentagonal hue spread
	$passes  = [0.38, 0.60];                  // dark pass then light pass
	$string  = '';
	$i       = 0;

	foreach ($passes as $l) {
		foreach ($offsets as $offset) {
			$newH = fmod($h + $offset, 360);
			$hex  = Color::hslToHex(['H' => $newH, 'S' => $s, 'L' => $l]);
			$rgb  = Color::hexToRgb($hex);
			$cc   = getContrastColor($rgb);
			$string .= "--ae-cal{$i}-color:#$hex;--ae-com{$i}-color:$cc;";
			if ($i === 3) $string .= ae_todayVars($hex, $alpha);
			$i++;
		}
	}
	return $string;
}

// ── Mode 3: Bright & Distinguishable ─────────────────────────────────────────
// A fixed palette of 10 well-spaced, vivid Material Design colours covering
// the full hue wheel so every calendar is immediately recognisable.

function calColorSetBright($alpha)
{
	$palette = [
		'f44336', // red       (~  0°)
		'FF9800', // orange    (~ 38°) — distinct warm orange, not red-orange
		'ffc107', // amber     (~ 43°)
		'4caf50', // green     (~122°)
		'00bcd4', // cyan      (~187°)
		'2196f3', // blue      (~207°)
		'9c27b0', // purple    (~292°)
		'e91e63', // pink      (~338°)
		'795548', // brown     (~ 16°)
		'009688', // teal      (~174°)
	];
	$string = '';
	foreach ($palette as $i => $hex) {
		$rgb = Color::hexToRgb($hex);
		$cc  = getContrastColor($rgb);
		$string .= "--ae-cal$i-color:#$hex;--ae-com$i-color:$cc;";
		if ($i === 3) $string .= ae_todayVars($hex, $alpha);
	}
	return $string;
}

// ── Mode 4: Shades of a Selected Colour ──────────────────────────────────────
// Takes the user-chosen hue and generates 10 shades from dark (25% L) to
// light (70% L), each 5 percentage-points apart.

function calColorSetMono($baseHex, $alpha)
{
	$baseHex = ltrim($baseHex, '#');
	$hsl = Color::hexToHsl($baseHex);
	$h   = $hsl['H'];
	$s   = max(0.50, $hsl['S']);   // ensure visible saturation
	$string = '';

	for ($i = 0; $i < 10; $i++) {
		$l   = 0.25 + $i * 0.05;  // 0.25 → 0.70
		$hex = Color::hslToHex(['H' => $h, 'S' => $s, 'L' => $l]);
		$rgb = Color::hexToRgb($hex);
		$cc  = getContrastColor($rgb);
		$string .= "--ae-cal$i-color:#$hex;--ae-com$i-color:$cc;";
		if ($i === 3) $string .= ae_todayVars($hex, $alpha);
	}
	return $string;
}

// ── Mode 5: Image Theme — Distinctive ────────────────────────────────────────
// Quantizes the image down to ~30 representative colours, then uses greedy
// farthest-point sampling (weighted RGB distance) to pick the 10 that are
// as visually distinct from each other as possible.

function calColorSetDistinctive($imagePath, $alpha)
{
	if (!$imagePath || !file_exists($imagePath)) {
		return calColorSetBright($alpha); // no image — fall back to fixed palette
	}

	$imagick = new \Imagick(realpath($imagePath));
	// Resize to a small thumbnail so quantization is fast
	$imagick->resizeImage(150, 150, \Imagick::FILTER_LANCZOS, 1, true);
	// Quantize to extract representative palette colours
	$imagick->quantizeImage(30, \Imagick::COLORSPACE_RGB, 0, false, false);
	$histogram = $imagick->getImageHistogram();
	$imagick->clear();
	$imagick->destroy();

	// Build candidate list; getColor(true) always returns 0-1 floats
	$candidates = [];
	foreach ($histogram as $pixel) {
		$c = $pixel->getColor(true);
		$candidates[] = [
			'r'     => (int)round($c['r'] * 255),
			'g'     => (int)round($c['g'] * 255),
			'b'     => (int)round($c['b'] * 255),
			'count' => $pixel->getColorCount(),
		];
	}

	if (count($candidates) === 0) {
		return calColorSetBright($alpha);
	}

	// Seed with the most common colour, then greedily pick whichever remaining
	// candidate is farthest (in weighted RGB space) from all already-selected ones.
	usort($candidates, fn($a, $b) => $b['count'] - $a['count']);
	$selected  = [$candidates[0]];
	$remaining = array_slice($candidates, 1);
	$needed    = min(10, count($candidates));

	while (count($selected) < $needed && count($remaining) > 0) {
		$bestDist = -1;
		$bestIdx  = 0;
		foreach ($remaining as $idx => $c) {
			$minDist = PHP_FLOAT_MAX;
			foreach ($selected as $s) {
				$d = aeColorDist($c, $s);
				if ($d < $minDist) $minDist = $d;
			}
			if ($minDist > $bestDist) {
				$bestDist = $minDist;
				$bestIdx  = $idx;
			}
		}
		$selected[] = $remaining[$bestIdx];
		array_splice($remaining, $bestIdx, 1);
	}

	$string = '';
	foreach ($selected as $i => $c) {
		$hex = sprintf('%02x%02x%02x', $c['r'], $c['g'], $c['b']);
		$cc  = getContrastColor(['R' => $c['r'], 'G' => $c['g'], 'B' => $c['b']]);
		$string .= "--ae-cal{$i}-color:#$hex;--ae-com{$i}-color:$cc;";
		if ($i === 3) $string .= ae_todayVars($hex, $alpha);
	}
	return $string;
}

// Weighted Euclidean ("redmean") colour distance — better perceptual approximation
// than plain RGB distance while far cheaper than Delta-E in Lab space.
function aeColorDist($c1, $c2)
{
	$dr    = $c1['r'] - $c2['r'];
	$dg    = $c1['g'] - $c2['g'];
	$db    = $c1['b'] - $c2['b'];
	$rmean = ($c1['r'] + $c2['r']) / 2;
	return sqrt(
		(2 + $rmean / 256)         * $dr * $dr
		+ 4                        * $dg * $dg
		+ (2 + (255 - $rmean) / 256) * $db * $db
	);
}

// ── Background image selection ─────────────────────────────────────────────────

function makeCalBackground($dir, $support_dir, $imagecnt)
{
	$allFiles = array_values(array_filter(scandir($dir), fn($f) => $f[0] !== '.'));
	$files = array_values(array_filter($allFiles, function($f) use ($support_dir) {
		$stem = explode('.', $f)[0];
		return file_exists($support_dir . $stem . '_1.jpg');
	}));
	if (count($files) === 0) $files = $allFiles; // fall back if none preprocessed yet
	$filename  = $files[rand(0, count($files) - 1)];
	$fileparts = explode('.', $filename);

	$retArr['image']      = $dir . $filename;
	$retArr['background'] = 'url("' . $support_dir . $fileparts[0] . '_1.jpg");';

	if (file_exists($support_dir . $fileparts[0] . '_small.jpg')) {
		$imagick = new \Imagick(realpath($support_dir . $fileparts[0] . '_small.jpg'));
		$pixel   = $imagick->getImagePixelColor(1, 1);
		$colors  = $pixel->getColor();
		$retArr['color']    = $colors;
		$retArr['colorStr'] = $pixel->getColorAsString();
	} else {
		$retArr['color'] = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 1];
	}
	return $retArr;
}

// ── Colour scheme dispatcher ──────────────────────────────────────────────────
// $mode: 'image_low' | 'image_high' | 'image_distinct' | 'bright' | 'mono'
// $base_color: hex string (used by 'mono' mode only)

function setColours($images, $alpha, $mode = 'image_low', $base_color = null)
{
	$c = $images['color'];
	// Imagick returns lowercase r/g/b; fallback for uppercase (internal use)
	$r = $c['r'] ?? $c['R'] ?? 0;
	$g = $c['g'] ?? $c['G'] ?? 0;
	$b = $c['b'] ?? $c['B'] ?? 0;
	$imageHex = sprintf('%02x%02x%02x', $r, $g, $b);

	switch ($mode) {
		case 'image_distinct':
			$colorset = calColorSetDistinctive($images['image'] ?? '', $alpha);
			break;
		case 'image_high':
			$colorset = calColorSetHigh($imageHex, $alpha);
			break;
		case 'bright':
			$colorset = calColorSetBright($alpha);
			break;
		case 'mono':
			$colorset = calColorSetMono($base_color ?? '#4a90d9', $alpha);
			break;
		default: // 'image_low'
			$colorset = calColorSet('#' . $imageHex, $alpha);
			break;
	}

	// Global light/dark mode is always driven by the image's dominant colour
	$cc = getContrastColor(['R' => $r, 'G' => $g, 'B' => $b]);
	if ($cc === '#FFFFFF') {
		return $colorset
		     . '--ae-generic-background:rgba(0,0,0,1);'
		     . '--ae-generic-color:rgba(255,255,255,1);'
		     . '--ae-generic-border:rgba(255,255,255,1);'
		     . '--ae-blur-overlay:rgba(0,0,0,.3);';
	} else {
		return $colorset
		     . '--ae-generic-background:rgba(255,255,255,1);'
		     . '--ae-generic-color:rgba(0,0,0,1);'
		     . '--ae-generic-border:rgba(0,0,0,1);'
		     . '--ae-blur-overlay:rgba(255,255,255,.3);';
	}
}

// ── Text contrast helper ──────────────────────────────────────────────────────
// Returns '#000000' when the background is light enough for black text,
// '#FFFFFF' otherwise. Uses WCAG relative luminance formula.

function getContrastColor($color)
{
	if (isset($color['r'])) { $R1 = $color['r']; $G1 = $color['g']; $B1 = $color['b']; }
	if (isset($color['R'])) { $R1 = $color['R']; $G1 = $color['G']; $B1 = $color['B']; }

	$L1 = 0.2126 * pow($R1 / 255, 2.2)
	    + 0.7152 * pow($G1 / 255, 2.2)
	    + 0.0722 * pow($B1 / 255, 2.2);

	$contrastRatio = (int)(($L1 + 0.05) / 0.05); // vs pure black (L=0)
	return $contrastRatio > 5 ? '#000000' : '#FFFFFF';
}
