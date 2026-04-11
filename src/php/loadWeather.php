<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once('env_vars.php');

$json = @file_get_contents('weather/report.json');
$weather = $json ? json_decode($json) : null;

if (!$weather) {
  echo 'null';
  exit;
}

// Normalize new API format (current/weather_code) to old format (current_weather/weathercode)
if (isset($weather->current) && !isset($weather->current_weather)) {
  $weather->current_weather = (object)[
    'time'                 => $weather->current->time,
    'temperature'          => $weather->current->temperature_2m,
    'apparent_temperature' => $weather->current->apparent_temperature ?? null,
    'windspeed'            => $weather->current->wind_speed_10m,
    'winddirection'        => $weather->current->wind_direction_10m ?? null,
    'weathercode'          => $weather->current->weather_code,
  ];
}
if (isset($weather->hourly->weather_code)) {
  $weather->hourly->weathercode = $weather->hourly->weather_code;
}
if (isset($weather->daily->weather_code)) {
  $weather->daily->weathercode = $weather->daily->weather_code;
}
if (isset($weather->daily_units->wind_speed_10m_max)) {
  $weather->daily_units->windspeed_10m_max = $weather->daily_units->wind_speed_10m_max;
}

if (!isset($weather->current_weather) || !isset($weather->daily)) {
  echo 'null';
  exit;
}

$weather = convertWeatherCodesToIcons($weather);
$weather->showclock            = !empty($showclock);
$weather->showcurrentweather  = isset($showcurrentweather)  ? (bool)$showcurrentweather  : true;
$weather->showwindspeed        = isset($showwindspeed)       ? (bool)$showwindspeed       : true;
$weather->showweathericon      = isset($showweathericon)     ? (bool)$showweathericon     : true;
$weather->showtemperature      = isset($showtemperature)     ? (bool)$showtemperature     : true;
$weather->showfeelslike_box    = isset($showfeelslike_box)   ? (bool)$showfeelslike_box   : false;
$weather->showfeelslike_combo  = isset($showfeelslike_combo) ? (bool)$showfeelslike_combo : false;
$weather->showhourlyweather    = isset($showhourlyweather)   ? (bool)$showhourlyweather   : true;
$weather->showsunrisesunset    = isset($showsunrisesunset)   ? (bool)$showsunrisesunset   : true;
$weather->showmoonphase        = isset($showmoonphase)        ? (bool)$showmoonphase        : true;
$weather->showprecipqty        = isset($showprecipqty)       ? (bool)$showprecipqty       : true;
$weather->showprecipprob       = isset($showprecipprob)      ? (bool)$showprecipprob      : false;
$weather->showpreciphours      = isset($showpreciphours)     ? (bool)$showpreciphours     : false;
$weather->showuvindex          = isset($showuvindex)         ? (bool)$showuvindex         : false;
echo json_encode($weather);


function convertWeatherCodesToIcons($weather)
{
	$time = $weather->{'current_weather'}->{'time'};
	// $date = date_create_from_format('Y-m-d\TH:i',$time);
	// $day  = date_format($date, 'Y-m-d');
	$sunrise = $weather->{'daily'}->{'sunrise'}[0];
	$sunset = $weather->{'daily'}->{'sunset'}[0];
	
	//Current Weather
	$day  = getDayNight($time,$sunrise, $sunset);
	$weather->{'current_weather'}->{'icon'} = convertWeatherCodeToIcon($weather->{'current_weather'}->{'weathercode'},$day);
	
	//Hourly

	for($i=0;$i<count($weather->{'hourly'}->{'weathercode'});$i++)
	{
		$time = $weather->{'hourly'}->{'time'}[$i];
		$day  = getDayNight($time,$sunrise, $sunset);
		$weather->{'hourly'}->{'icon'}[$i] = convertWeatherCodeToIcon($weather->{'hourly'}->{'weathercode'}[$i],$day);
	}
	
	for($i=0;$i<7;$i++)
	{
		$weather->{'daily'}->{'icon'}[$i] = convertWeatherCodeToIcon($weather->{'daily'}->{'weathercode'}[$i],true);
	}
	return $weather;
}

function convertWeatherCodeToIcon($weatherCode,$day)
{
	switch ($weatherCode) {
		case 0:
			//Clear Sky
			if($day)
			{
				return '<i date-code="'.$weatherCode.'" class="wi wi-day-sunny"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-clear"></i>';
			}
			break;
		case 1:
			//Mainly Clear
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-sunny-overcast"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-partly-cloudy"></i>';
			}
			break;
		case 2:
			//Partly Cloudy
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-cloudy"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-alt-cloudy"></i>';
			}
			break;
		case 3:
			//Overcast
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-cloudy"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-cloudy"></i>';
			}
			break;
		case 45:
			//Fog
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-fog"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-fog"></i>';
			}
			break;
		case 48:
			//depositing rime fog
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-fog"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-fog"></i>';
			}
			break;
		case 51:
			//Light Drizzle
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-sprinkle"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-sprinkle"></i>';
			}
			break;
		case 53:
			//Moderate Drizzle
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-sprinkle"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-sprinkle"></i>';
			}
			break;
		case 55:
			//Dense Drizzle
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-sprinkle"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-sprinkle"></i>';
			}
			break;
		case 56:
			//Light Freezing Drizzle
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-snowflake-cold"></i><i class="wi wi-day-sprinkle"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-snowflake-cold"></i><i class="wi wi-night-sprinkle"></i>';
			}
			break;
		case 57:
			//Dense Freezing Drizzle
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-snowflake-cold"></i><i class="wi wi-day-sprinkle"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-snowflake-cold"></i><i class="wi wi-night-sprinkle"></i>';
			}
			break;
		
		case 61:
			//Slight Rain
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-rain"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-rain"></i>';
			}
			break;
		case 63:
			//Moderate Rain
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-rain"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-rain"></i>';
			}
			break;
		case 65:
			//Heavy Rain
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-rain"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-rain"></i>';
			}
			break;
		case 66:
			//Light Freezing Rain
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-snowflake-cold"></i><i class="wi wi-day-rain"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-snowflake-cold"></i><i class="wi wi-night-rain"></i>';
			}
			break;
		case 67:
			//Heavy Freezing Rain
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-snowflake-cold"></i><i class="wi wi-day-rain"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-snowflake-cold"></i><i class="wi wi-night-rain"></i>';
			}
			break;
		
		case 71:
			//Slight Snow
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-snow"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-snow"></i>';
			}
			break;
		case 73:
			//Moderate Snow
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-snow"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-snow"></i>';
			}
			break;
		case 75:
			//Heavy Snow
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-snow"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-snow"></i>';
			}
			break;
		case 77:
			//Snow Grains
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-hail"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-hail"></i>';
			}
			break;
			
		case 80:
			//Slight Rain Showers
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-sprinkle"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-sprinkle"></i>';
			}
			break;
		case 81:
			//Moderate Rain Showers
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-sprinkle"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-sprinkle"></i>';
			}
			break;
		case 82:
			//Violent Rain Showers
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-sprinkle"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-sprinkle"></i>';
			}
			break;
		case 85:
			//Slight Snow Showers
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-snow"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-snow"></i>';
			}
			break;
		case 86:
			//Heavy Snow Showers
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-snow"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-snow"></i>';
			}
			break;
		
		case 95:
			//Thunderstorm: Slight
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-thunderstorm"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-alt-thunderstorm"></i>';
			}
			break;
		case 96:
			//Thunderstorm: Slight Hail
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-snow-thunderstorm"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-alt-snow-thunderstorm"></i>';
			}
			break;
		case 99:
			//Thunderstorm: Heavy Hail
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-day-sleet-storm"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-night-alt-sleet-storm"></i>';
			}
			break;
		
		default:
			//Not Found
			if($day)
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-na"></i><i class="wi wi-day-sunny"></i>';
			}else
			{
				return '<i   date-code="'.$weatherCode.'" class="wi wi-na"></i><i class="wi wi-night-clear"></i>';
			}
			break;
	}
}

function testWeatherCodes()
{
	echo '
	<link rel="stylesheet" href="css/weather-icons-wind.css"> 
	<link rel="stylesheet" href="css/weather-icons-wind.min.css"> 
	<link rel="stylesheet" href="css/weather-icons.css"> 
	<link rel="stylesheet" href="css/weather-icons.min.css"> 
	<style>.wi {font-size:40px;} </style>';
	$codes = [0,1,2,3,45,48,51,53,55,56,57,61,63,65,66,67,71,73,75,77,80,81,82,85,86,95,96,99];
	foreach($codes as $code)
	{
		echo convertWeatherCodeToIcon($code,true);
		echo ' - ';
		echo convertWeatherCodeToIcon($code,false);
		echo '<br>';
	}
}

function getDayNight($time,$sunrise, $sunset)
{
	$dateobj = date_create_from_format('Y-m-d\TH:i',$time);
	$sunriseobj = date_create_from_format('Y-m-d\TH:i',$sunrise);
	$sunseteobj = date_create_from_format('Y-m-d\TH:i',$sunset);

	if($dateobj>$sunriseobj&&$dateobj<$sunseteobj)
	{
		return true;
	}else
	{
		return false;
	}
}