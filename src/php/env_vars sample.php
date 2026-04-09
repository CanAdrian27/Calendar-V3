<?php
$calendars = [
	[
		"cal"=> 'CALENDAR_URL'', //URL of Calendar
		'postprocess'=>false, //Post Process used to trigger manipulation of the calendar for Runna Calendar
		'name' => 'CALENDAR_NAME' //Name of Calendar
	]
];


$showski    = false; //Whether or not to show the Ski Hill information — requires scrape.py running
$showquote  = false; //Whether or not to show the daily inspirational quote
$showword   = false; //Whether or not to show the word of the day
$showweekly = false; //Include weekly calendar view in the toggle rotation
$showmealie = false; //Include Mealie recipe page in the toggle rotation
$shownotes  = false; //Include notes page in the toggle rotation
