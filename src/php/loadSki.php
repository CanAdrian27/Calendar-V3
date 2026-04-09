<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$month = (int)date('n'); // 1–12, no zero-padding
include_once('env_vars.php');

if($showski)
{
	if(file_exists('ski/ski_hills.json') && ($month > 10 || $month < 4))
	{
		$json = file_get_contents('ski/ski_hills.json');
		$skiHills = json_decode($json); 
		?>
		<div id="ski_hill_info">
			<div class="ski_hill_row" 				id="ski_hill_row-title" > 
			Ski Report
			</div>
			<div class="ski_hill_row" 				id="ski_hill_row-header" > 
				<span class="ski_hill_header ski_hill_element ski_hill_name" 		>&nbsp;</span>
				<span class="ski_hill_header ski_hill_element ski_hill_day_trails" 	>Day Trails</span>
				<span class="ski_hill_header ski_hill_element ski_hill_day_lift" 	>Day Lifts</span>
				<span class="ski_hill_header ski_hill_element ski_hill_sf_24h" 		>Last Day</span>
				<span class="ski_hill_header ski_hill_element ski_hill_sf_7d" 		>Last Week</span>
			</div>
			<?php
			$cnt = 0;
			if( is_array($skiHills))
			{
				foreach($skiHills as $hill)
				{
					?>
					
					<div class="ski_hill_row" 				id="ski_hill_row-<?php echo $cnt;?>" > 
						<span class="ski_hill_element ski_hill_name" 		id="ski_hill_name-<?php echo $cnt;?>" > 		<?php echo  $hill->{'name'};?></span>
						<span class="ski_hill_element ski_hill_day_trails" 	id="ski_hill_day_trails-<?php echo $cnt;?>" > 	<?php echo  $hill->{'day_trails_open'};?></span>
						<span class="ski_hill_element ski_hill_day_lift" 	id="ski_hill_day_lift-<?php echo $cnt;?>" > 	<?php echo  $hill->{'day_lifts_open'};?></span>
						<span class="ski_hill_element ski_hill_sf_24h" 		id="ski_hill_sf_24h-<?php echo $cnt;?>" > 		<?php echo  $hill->{'snowfall_24h'};?></span>
						<span class="ski_hill_element ski_hill_sf_7d" 		id="ski_hill_sf_7d-<?php echo $cnt;?>" > 		<?php echo  $hill->{'snowfall_week'};?></span>
					</div>
					<?php
					$cnt++;
				}
			}else
			{
				echo '...';
			}
			?>
			</div>
		<?php
	}
}
