<?php

// script creates geoJSON file of recorded features

header('cache-control: no-cache');
header('Content-Type: application/vnd.google-earth.kml+xml');

date_default_timezone_set('UTC'); // recorded / synced db fields are in UTC

include_once $_SERVER['DOCUMENT_ROOT'] . '/template/db/dbConnect.fieldnotes.inc.php';

$tables = array(
	'landslide' => 'Landslide', 
	'liquefaction' => 'Liquefaction', 
	'rupture' => 'Fault Rupture', 
	'tsunami' => 'Tsunami',
	'lifelines' => 'Lifelines',
	'building' => 'Building',
	'general' => 'General'
);

$params = parseGetVals();
$kml = createKmlFeed($db, $tables, $params);

header('Content-Disposition: attachment; filename="fieldnotes.kml"');
print $kml;



/**
 * Parse input parameters that control feed display
 * api allows several parameters to be set:
 *
 * 1. period=hour, period=day, etc.
 * 2. after=1377541566 (unix time stamp)
 * 3. before=1377541566 (unix time stamp)
 * 4. between=after,before (where after and before are unix time stamps)
 */

function parseGetVals() {
	$params = array();
	$periods = array(
		'hour' => '1 hour ago',
		'day' => '1 day ago',
		'week' => '7 days ago', 
		'month' => '1 month ago',
		'quarter' => '3 months ago'
	);
	$before = time();
	$after = strtotime('2011-01-01');
	$allowed = '/^[\w,]+$/'; // Sanitize input parameter (alphanumerics only)
	
	if (isSet($_GET['before']) && (preg_match($allowed, $_GET['before']))) {
		$before = $_GET['before'];
	}
	if (isSet($_GET['after']) && (preg_match($allowed, $_GET['after']))) {
		$after = $_GET['after'];
	}
	if (isSet($_GET['between']) && (preg_match($allowed, $_GET['between']))) {
		list($after, $before) = preg_split('/\s?,\s?/', $_GET['between']);
	}
	
	$params['before_sql'] = date('Y-m-d H:i:s', $before);
	$params['after_sql'] = date('Y-m-d H:i:s', $after);
	
	if (isSet($_GET['period']) && (preg_match($allowed, $_GET['period']))) {
		$period = $_GET['period'];
		$params['after_sql'] = date("Y-m-d H:i:s", strtotime($periods[$period]));
	}
	
	return $params;
}

// Create kml file
function createKmlFeed($db, $tables, $params) {
	
	// KML header
	include_once $_SERVER['DOCUMENT_ROOT'] . '/sandbox/git/fieldnotes/header.kml.php';
	$output = $header;

	// Get features from db
	foreach ($tables as $table => $name) {

		$output .= "		<Folder><name>$name</name><open>0</open>";

		$query_rsFeatures = sprintf("SELECT * FROM %s 
			LEFT JOIN location ON location_id = location.id 
			LEFT JOIN spoton ON spoton_id = spoton.id 
			WHERE (recorded BETWEEN '%s' AND '%s' OR synced BETWEEN '%s' AND '%s') 
				AND ((location.lat != '' AND location.lon != '') OR (spoton.latitude != '' AND spoton.longitude != ''))
			ORDER BY recorded DESC;", 
			$table, $params['after_sql'], $params['before_sql'], $params['after_sql'], $params['before_sql']);

		$rsFeatures = mysql_query($query_rsFeatures, $db) or die(mysql_error());
	
		while ($row_rsFeatures = mysql_fetch_assoc($rsFeatures)) {

			$id = $row_rsFeatures['location_id'];
			$table = '<table>';

			// get timezone where user submitted form
			date_default_timezone_set('America/Los_Angeles'); // set to UTC above; need to change it to determine tz accurately
			$timezone = '';
			$timestamp = '';
			if ($row_rsFeatures['gmt_offset']) {
				$dst = date('I', strtotime($row_rsFeatures['timestamp'])); // boolean: if timestamp is in daylight savings time or not
				$tz_name = timezone_name_from_abbr('', $row_rsFeatures['gmt_offset'] * 3600, $dst); // timezone name (e.g. America / Los Angeles)
				$dateTime = new DateTime($row_rsFeatures['timestamp']); 
				$dateTime->setTimeZone(new DateTimeZone($tz_name)); 
				$timezone = $dateTime->format('T'); // timezone abbreviation (e.g. PDT)
			}
			if ($row_rsFeatures['timestamp']) {
				$timestamp = date('D, M j Y g:ia', strtotime($row_rsFeatures['timestamp']));
			}

			$properties = array('form' => $name, 'timestamp' => $timestamp, 'timezone' => $timezone);

			$device_lat = floatval(round($row_rsFeatures['lat'], 5));
			$device_lon = floatval(round($row_rsFeatures['lon'], 5));

			if ($row_rsFeatures['latitude'] && $row_rsFeatures['longitude']) { // use SpotOn location if set
				// store device location
				$properties['device-lat'] = $device_lat;
				$properties['device-lon'] = $device_lon;
				$coords = array(
					floatval(round($row_rsFeatures['longitude'], 5)),
					floatval(round($row_rsFeatures['latitude'], 5))
				);
			} else {
				$coords = array(
					$device_lon, 
					$device_lat,
					floatval($row_rsFeatures['z'])		
				);
			}

			// get rid of values we don't want in properties array
			unset($row_rsFeatures['id']);
			unset($row_rsFeatures['spoton_id']);
			unset($row_rsFeatures['location_id']);
			unset($row_rsFeatures['lat']);
			unset($row_rsFeatures['lon']);
			unset($row_rsFeatures['z']);
			unset($row_rsFeatures['timestamp']);
			unset($row_rsFeatures['gmt_offset']);
			// spoton values
			unset($row_rsFeatures['name']);
			unset($row_rsFeatures['email']);
			unset($row_rsFeatures['fname']);
			unset($row_rsFeatures['lname']);
			unset($row_rsFeatures['username']);
			unset($row_rsFeatures['wpid']);
			unset($row_rsFeatures['longitude']);
			unset($row_rsFeatures['latitude']);

			foreach ($row_rsFeatures as $key => $value) {
				if (($key === 'recorded' || $key === 'synced') && $value) {
					$value .= ' UTC'; // add UTC timezone string to recorded / snyced fields
				}
				if ($key === 'photo') {
					if ($value) {
						$path = pathinfo($_SERVER['REQUEST_URI'], PATHINFO_DIRNAME);
						if (file_exists("uploads/$value")) {
							$value = sprintf ('http://%s%s/uploads/%s', $_SERVER['SERVER_NAME'], $path, $value);
						} else {
							$value = '';
						}
					}
				}
				$properties[$key] = null2string($value);
			}

			foreach ($properties as $key => $value) {
				$table .= "<tr><th>$key</th><td>" . htmlentities($value) . "</td></tr>";
			}
			$table .= '</table>';

			$output .= sprintf('
				<Placemark id="%d">
					<visibility>1</visibility>
					<name>%s</name>
					<description>%s</description>
					<Snippet maxLines="0"></Snippet>
					<LookAt><longitude>%s</longitude><latitude>%s</latitude><range>1000000</range></LookAt>
					<styleUrl>#circle</styleUrl>
					<Style><IconStyle><color>dd0099ff</color><scale>0.6</scale></IconStyle></Style>
					<Point><coordinates>%s</coordinates></Point>
				</Placemark>', 
				$id, $timestamp, $table, $coords[0], $coords[1], implode(',', $coords)
			);
	
		}

		$output .= "</Folder>";

	}

	$footer .= '	</Document>
</kml>';
	$output .= $footer;

	return $output;

}

// convert null values from MySQL to empty strings in JSON
function null2string($value) {
	if ($value === null) {
		$value = '';
	}
	return $value;
}