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
	
		// Use the DateTime class (when possible) to correctly identify the start date/time:
		if (function_exists('date_create_from_format')) {
			$start = date_create_from_format('d/m/Y H:i', $fixture['date'] . ' ' . $fixture['time']);			
			$fixture['start'] 	= date_timestamp_get($start);
			// Matches end 105 minutes after they start; 45 minutes each way plus half time (obviously).
			$end = date_modify($start,'+105 minutes');
			$fixture['end'] 	= date_timestamp_get($end);
		}
		else {
			// For PHP < 5.3, we'll use the previous approach, but this is less reliable:
			$time = explode(':', $fixture['time']);
			$date = explode('/', $fixture['date']);
			if (count($date) == 3 && count($time) == 2) {
				list($hour,$minute) 	= $time;								
				list($day,$month,$year) = $date;		
				if ($start = @mktime((int)$hour,(int)$minute,0,(int)$month,(int)$day,(int)$year)) {
					$fixture['start'] 	= $start;					
					$fixture['end'] 	= $start + (60 * 105); // 105 minutes					
				}				
			}
		}
		if (isset($fixture['start']) && isset($fixture['end'])) {
			// Only add this fixture if we've been able to properly identify the kick-off time and date
			$fixtures[] = $fixture;
		}		
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