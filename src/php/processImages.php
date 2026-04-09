<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$dir = 'images';
$files = scandir('images');
foreach($files as $file)
{
	if($file[0] !== '.')
	{
		echo $file;
		$image_path =$dir.'/'.$file;
		$height =100;
		$fn = getfilename($image_path);
		if(file_exists("images_supports/$fn"."_1.jpg"))
		{
			echo ' - File Exists';
		}else
		{
			$newfile = bin2hex(random_bytes(10));
			rename($dir.'/'.$file,$dir.'/'.$newfile.'.jpg');
			$image_path =$dir.'/'.$newfile.'.jpg';
			echo ' -> '.$newfile;
			$height =100;
			$fn = getfilename($image_path);
			getBlocks($image_path,1,$height, $fn);
			gaussianBlurImage("images_supports/$fn"."_1.jpg", 10, 100, 'All');
			getSmallImage("images_supports/$fn"."_1.jpg",$fn);
		}
		echo '<br>';
	}
}


function getfilename($image_path)
{
	$parts = explode('.', $image_path);
	$parts = explode('/', $parts[0]);
	$filename = $parts[count($parts)-1];
	return $filename;
}

function getRow($image_path,$i,$height,$filename)
{
	$imagick = new \Imagick(realpath($image_path));
	$geos = $imagick->getImageGeometry();
	$width = $geos['width'];
	$startX = 0;
	$startY =  $geos['height']- ($i*$height);
	$imagick->cropImage($width, $height, $startX, $startY);
	$blob =  $imagick->getImageBlob();
	$imagick->writeImage ("images_supports/$filename"."_$i.jpg"); 
}

function getBlocks($image_path,$i,$height,$filename)
{
	$imagick = new \Imagick(realpath($image_path));
	$geos = $imagick->getImageGeometry();
	$width = $height;
	$startX = rand(0,floor($geos['height']/$height));
	$startY =  $geos['height']- ($i*$height);
	$imagick->cropImage($width, $height, $startX, $startY);
	$imagick->writeImage ("images_supports/$filename"."_$i.jpg"); 
}

function gaussianBlurImage($image_path, $radius, $sigma, $channel)
{
	$imagick = new \Imagick(realpath($image_path));
	$imagick->gaussianBlurImage($radius, $sigma);
	$blob = $imagick->getImageBlob();
	$imagick->writeImage ($image_path);	
}

function getSmallImage($image_path,$filename)
{
	$imagick = new \Imagick(realpath($image_path));
	$imagick->resizeImage( 1, 1, Imagick::FILTER_LANCZOS, 1);
	$imagick->writeImage ("images_supports/$filename"."_small.jpg"); 
}
?>