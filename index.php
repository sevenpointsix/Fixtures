<?php

/**
 * A simple script that generates an ICS file for football fixtures
 * based on the lists on the BBC Sport website.
 * The resulting URL can then be added to either iCal or Google Calendar
 * and is automatically synchronised with the BBC site
 * For example: http://[yourdomain.com]/fixtures.php
 * @author Chris Gibson <chris@sevenpointsix.com>
 */

// Load the iCal and DOM libraries; both are copyright their respective developers:
require_once('iCalcreator/iCalcreator.class.php');
require_once('simple_html_dom/simple_html_dom.php');

error_reporting(0);
if (phpversion() >= 5.3) {	
	date_default_timezone_set('Europe/London'); 
}

// Modify this URL to load fixtures from your chosen team: 
$url = 'http://www.bbc.co.uk/sport/football/teams/manchester-united/fixtures';    

// Load the data into an array of fixtures:
$fixtures = array();
$error = '';

if ($html = @file_get_html($url)) {

	foreach ($html->find('tr.preview') as $row) {

		$id = $row->id;	// Not currently used

		$cells = array();
		$cells['summary']		= 'match-details';
		$cells['description']	= 'match-competition';
		$cells['date']			= 'match-date';
		$cells['time']			= 'kickoff';
		$cells['status']		= 'status'; // Not sure what this is used for; cancellations, perhaps? TBC.

		$fixture = array();

		foreach ($cells as $key=>$class) {
			$value = $row->find("td.$class",0);
			$fixture[$key] = preg_replace('/\s\s+/', ' ', trim($value->plaintext));
		} 
		
		/*
			Establishing the year of the fixture is quite tricky, without some advanced parsing of the table headers and so on.
			However, we know that we're lookin at fixtures that are in the future. So:
			If the fixture is this year, and it's in the future, assume it takes place this year
			Otherwise, if the fixture would already have passed if it was this year, assume it's next year		
		*/
		$year = date("Y");
		$start = $fixture['date'] . ' '.$year.' '.$fixture['time'];
		if (strtotime($start) < time()) {		
			$start = $fixture['date'] . ' '.($year + 1).' '.$fixture['time'];
		}
		$fixture['start'] 	= strtotime($start);
		$fixture['end'] 	= strtotime('+105 minutes',$fixture['start']);	
				
		$fixtures[] = $fixture;
	}

	if (count($fixtures) == 0) {
		$error = 'Cannot parse data; check for updates';
	}
}   
else {
	$error = 'Cannot load data; check for updates';
}
if ($error) {
	$fixtures[] = array('summary'=>'Fixtures cannot be loaded','description'=>$error,'start'=>time(),'end'=>time());
}

$config = array('unique_id' => 'sevenpointsix');

$v = new vcalendar( $config );

$v->setProperty('method', 'PUBLISH');
$v->setProperty("x-wr-calname", "Fixtures");
$v->setProperty("X-WR-CALDESC", "Football fixtures");
$v->setProperty("X-WR-TIMEZONE", "Europe/London");

foreach ($fixtures as $fixture) {
	$vevent =& $v->newComponent('vevent'); 
	// Parse the start and end date/times
	foreach (array('start','end') as $period) {
		$getdate = getdate($fixture[$period]);	
		$components = array(
			'year'=>$getdate['year'],
			'month'=>$getdate['mon'],
			'day'=>$getdate['mday'],
			'hour'=>$getdate['hours'],
			'min'=>$getdate['minutes'],
			'sec'=>$getdate['seconds']
		);
		$vevent->setProperty('dt'.$period, $components);
	}
	$vevent->setProperty('summary', $fixture['summary']);
	$vevent->setProperty('description', $fixture['description']);  
}

$v->returnCalendar();

?>