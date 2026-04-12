<?php
$debug = isset($_GET['debug']);
ini_set('display_errors', $debug ? 1 : 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$month = (int)date('n');
include_once('env_vars.php');

if ($debug) {
	echo debugPageHeader('loadSki');
	echo '<div class="dbg-row"><span class="dbg-label">$showski</span><span class="dbg-val">' . ($showski ? 'true' : 'false') . '</span></div>';
	echo '<div class="dbg-row"><span class="dbg-label">Month</span><span class="dbg-val">' . $month . ' (' . date('F') . ')</span></div>';
	$inSeason = ($month > 10 || $month < 4);
	echo '<div class="dbg-row"><span class="dbg-label">In ski season</span><span class="dbg-val">' . ($inSeason ? 'Yes (Nov–Mar)' : 'No') . '</span></div>';
	$jsonFile = 'ski/ski_hills.json';
	echo '<div class="dbg-row"><span class="dbg-label">ski_hills.json</span><span class="dbg-val">' . (file_exists($jsonFile) ? 'Found' : 'NOT FOUND') . '</span></div>';
	if (file_exists($jsonFile)) {
		$raw = file_get_contents($jsonFile);
		echo '<h2>ski_hills.json</h2><pre>' . htmlspecialchars(json_encode(json_decode($raw), JSON_PRETTY_PRINT)) . '</pre>';
	}
	if ($showski && $inSeason && file_exists($jsonFile)) {
		echo '<h2>Rendered HTML</h2><pre>';
		ob_start();
	}
}

if ($showski) {
	if (file_exists('ski/ski_hills.json') && ($month > 10 || $month < 4)) {
		$json     = file_get_contents('ski/ski_hills.json');
		$skiHills = json_decode($json);
		?>
		<div id="ski_hill_info">
			<div class="ski_hill_row" id="ski_hill_row-title">
				Ski Report
			</div>
			<div class="ski_hill_row" id="ski_hill_row-header">
				<span class="ski_hill_header ski_hill_element ski_hill_name">&nbsp;</span>
				<span class="ski_hill_header ski_hill_element ski_hill_day_trails">Day Trails</span>
				<span class="ski_hill_header ski_hill_element ski_hill_day_lift">Day Lifts</span>
				<span class="ski_hill_header ski_hill_element ski_hill_sf_24h">Last Day</span>
				<span class="ski_hill_header ski_hill_element ski_hill_sf_7d">Last Week</span>
			</div>
			<?php
			$cnt = 0;
			if (is_array($skiHills)) {
				foreach ($skiHills as $hill) {
					?>
					<div class="ski_hill_row" id="ski_hill_row-<?php echo $cnt; ?>">
						<span class="ski_hill_element ski_hill_name"        id="ski_hill_name-<?php echo $cnt; ?>"><?php echo $hill->{'name'}; ?></span>
						<span class="ski_hill_element ski_hill_day_trails"  id="ski_hill_day_trails-<?php echo $cnt; ?>"><?php echo $hill->{'day_trails_open'}; ?></span>
						<span class="ski_hill_element ski_hill_day_lift"    id="ski_hill_day_lift-<?php echo $cnt; ?>"><?php echo $hill->{'day_lifts_open'}; ?></span>
						<span class="ski_hill_element ski_hill_sf_24h"      id="ski_hill_sf_24h-<?php echo $cnt; ?>"><?php echo $hill->{'snowfall_24h'}; ?></span>
						<span class="ski_hill_element ski_hill_sf_7d"       id="ski_hill_sf_7d-<?php echo $cnt; ?>"><?php echo $hill->{'snowfall_week'}; ?></span>
					</div>
					<?php
					$cnt++;
				}
			} else {
				echo '...';
			}
			?>
		</div>
		<?php
	}
}

if ($debug) {
	$inSeason = ($month > 10 || $month < 4);
	if ($showski && $inSeason && file_exists('ski/ski_hills.json')) {
		$html = ob_get_clean();
		echo htmlspecialchars($html) . '</pre>';
	}
	echo debugPageFooter();
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
