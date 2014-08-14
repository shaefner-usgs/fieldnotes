<?php

// ImageMagick's convert cmd
$convert = '/usr/bin/convert';

// Maximum upload filesize
$maxsize = 2000000;

// set time zone for recorded / synced db fields to UTC (timestamp from device's geolocation is stored in localtime)
date_default_timezone_set('UTC');

// Feature forms - names and associated db tables
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