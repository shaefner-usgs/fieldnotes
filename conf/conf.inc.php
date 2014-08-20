<?php

/* 
 * Configuration params - feel free to change these
 */

// ImageMagick's convert cmd
$convert = '/usr/bin/convert';

// Maximum upload filesize
$maxsize = 2000000;

/* 
 * Params used by multiple files in app 
 */

// set time zone for recorded / synced db fields to UTC (timestamp from device's geolocation is stored in localtime)
date_default_timezone_set('UTC');

// Feature forms - names and associated db tables (must match form name values in index.html)
$tables = array(
	'landslide' => 'Landslide',
	'liquefaction' => 'Liquefaction',
	'rupture' => 'Fault Rupture',
	'tsunami' => 'Tsunami',
	'lifelines' => 'Lifelines',
	'building' => 'Building',
	'general' => 'General'
);

?>