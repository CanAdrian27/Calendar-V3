<?php
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
	
	require_once('incs/phpcolors/src/Mexitek/PHPColors/Color.php');
	require_once('incs/functions_2.php');
	
	use Mexitek\PHPColors\Color;
	
	$dir    = 'images/';
	$support_dir    = 'images_supports/';
	$imagecnt = 3;
	$images = makeCalBackground($dir,$support_dir,$imagecnt);
	echo '<style>html,body { background-image:'.$images['background'].'; background-size: contain; }';

	$alpha = .25;
	$bumpPerc = 25;
	
	setColours($images,$bumpPerc,$alpha);
?>
<!DOCTYPE html>
<html>
  <head>
	<script src="/js/loadWeather.js"></script> 
	<script src="/js/resize.js"></script>  
	<script>procImage()</script>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="css/weather-icons-wind.css"> 
	<link rel="stylesheet" href="css/weather-icons-wind.min.css"> 
	<link rel="stylesheet" href="css/weather-icons.css"> 
	<link rel="stylesheet" href="css/weather-icons.min.css"> 
<style>
	.colorbox
	{
		width: 50px;
		height: 50px;
	}
</style>
  </head>
  <body>
	  <div id="screenContain">
		<div id="blurLayer">
			<div id='photoContain' >
				<div id='weather'></div>
				<img id="photo" class="gradient-blur" src="<?php echo $images['image'];?>">
			</div>  
			<div id='calendar' >
				<div id="col1" class="colorbox"></div>
				<div id="col2" class="colorbox"></div>
				<div id="col3" class="colorbox"></div>
				<div id="col4" class="colorbox"></div>
				<div id="col5" class="colorbox"></div>
				<div id="col6" class="colorbox"></div>
				<div id="col7" class="colorbox"></div>
				<div id="col8" class="colorbox"></div>
				<div id="col9" class="colorbox"></div>
				<div id="col10" class="colorbox"></div>
				
			</div>
			<div id="BottomBox">
				<div id="hourlyWeather"></div>
				<div id="lastupdated"></div>
			</div>
			
		</div>
		<div id="blankBottomBox"></div>
		
	  </div>
  </body>
  <link rel="stylesheet" href="css/layout.css">
</html>