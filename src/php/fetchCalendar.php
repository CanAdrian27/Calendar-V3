<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include_once('env_vars.php');

foreach($calendars as $cal)
{
	getCalendar($cal['cal'],$cal['postprocess']);
}

function getCalendar( $url, $postProcessCal)
{
	$curl = curl_init();
	
	curl_setopt_array($curl, array(
  	CURLOPT_URL => $url,
  	CURLOPT_RETURNTRANSFER => true,
  	CURLOPT_ENCODING => '',
  	CURLOPT_MAXREDIRS => 10,
  	CURLOPT_TIMEOUT => 0,
  	CURLOPT_FOLLOWLOCATION => true,
  	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  	CURLOPT_CUSTOMREQUEST => 'GET',
 	
  	CURLOPT_HTTPHEADER => array(
		'Content-Type: text/plain'
  	),
	));
	
	$response = curl_exec($curl);
	curl_close($curl);
	//ADD RUNNA Exception
	if($postProcessCal)
	{
		$pattern = '/(?<=DTSTART)(.*?)(?=\d\d\d\d\d\d\d\d\n)/mi';
		$replacement = ';VALUE=DATE:';
		$response =  preg_replace($pattern, $replacement, $response);
		$pattern = '/(?<=DTEND)(.*?)(?=\d\d\d\d\d\d\d\d\n)/mi';
		$replacement = ';VALUE=DATE:';
		$response =  preg_replace($pattern, $replacement, $response);
	}
	
	$filename = getCalName($response);
	$file = fopen("calendars/$filename.ics", 'w');
	fwrite($file, $response);
}

function getCalName($text)
{
	echo $keyword 	= 'X-WR-CALNAME:';
	echo '<br>start:';
    echo $start 		= strpos($text, $keyword);
	echo '<br> after kw: ';
	echo $start 		= $start+strlen($keyword);
	echo '<br>end: ';
	
	echo $end  		= strpos($text, "\r\n",$start);
	echo '<br>';
	if(!is_numeric($end))
	{
		$end 	= strpos($text, "\n",$start);
	}
	
	$unsafeName = substr($text, $start, $end-$start);
	$safeName 	= str_ireplace(' ', '_', $unsafeName);
	$safeName 	= str_ireplace('\'', '', $safeName );
	$safeName 	= str_ireplace('#', '', $safeName );
	echo '<br>';
	echo $end;
	echo ' - *';
	echo $safeName;
	echo '*<br>';
	return $safeName;
}
?>