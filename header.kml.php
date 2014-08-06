<?php

date_default_timezone_set('UTC');
$domain = $_SERVER['SERVER_NAME'];
$path = pathinfo($_SERVER['REQUEST_URI'], PATHINFO_DIRNAME);

$header = sprintf('<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://earth.google.com/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom" xml:lang="en-US">
	<Document id="fieldnotes">
		<name>Earthquake Clearinghouse Fieldnotes</name>
		<Snippet maxLines="1">%s</Snippet>
		<description>
			<style></style>
			<p>Field observations recorded by scientists</p>
		</description>
		<open>1</open>
		<Style id="feature-inactive">
			<IconStyle><Icon><href>http://%s%s/img/pin-m-feature+00c.png</href></Icon></IconStyle>
			<LabelStyle><scale>0</scale></LabelStyle>
			<BalloonStyle><text><![CDATA[
				<style>
					table { font-family: Helvetica, Arial, sans-serif; margin-top: 1em; } 
					th { text-align: right; padding-right: .5em; }
				</style>
				<img src="http://%s%s/img/banner-2x.png" alt="Fieldnotes" width="400" height="40" />
				$[description]
			]]></text></BalloonStyle>
		</Style>
		<Style id="feature-active">
			<IconStyle><Icon><href>http://%s%s/img/pin-m-feature+00c.png</href></Icon></IconStyle>
			<LabelStyle><scale>1</scale></LabelStyle>
			<BalloonStyle><text><![CDATA[
				<style>
					h2 { font-family: Verdana; } 
					table { font-family: Helvetica, Arial, sans-serif; margin-top: 1em; } 
					th { text-align: right; padding-right: .5em; }
				</style>
				<img src="http://%s%s/img/banner-2x.png" alt="Fieldnotes" width="400" height="40" />
				$[description]
			]]></text></BalloonStyle>
		</Style>
		<StyleMap id="feature">
			<Pair><key>normal</key><styleUrl>#feature-inactive</styleUrl></Pair>
			<Pair><key>highlight</key><styleUrl>#feature-active</styleUrl></Pair>
		</StyleMap>
		<LookAt>
			<longitude>-99</longitude>
			<latitude>46</latitude>
			<range>8000000</range>
			<tilt>0</tilt>
			<heading>0</heading>
		</LookAt>
', 
	date('Y-m-d H:i:s') . ' UTC',
	$domain, $path,
	$domain, $path,
	$domain, $path,
	$domain, $path);

?>