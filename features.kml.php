<?php

// Script creates a KML file of recorded features

include_once 'conf/conf.inc.php'; // $periods and $tables arrays are set here
include_once 'conf/db.inc.php';
include_once 'conf/functions.inc.php'; // shared methods

$params = getParams($periods);

// Default $tables array is all tables with observations (everything but 'checkin')
if ($params['content'] === 'all') {
  $tables['checkin'] = 'Checkin'; // add checkins
} else if ($params['content'] === 'checkins') {
  $tables = array('checkin' => 'Checkin'); // set to checkins only
}

$kmlFeed = createKmlFeed($db, $tables, $params);

// Set headers shared by json/kml file types
setHeaders('application/vnd.google-earth.kml+xml');
// Trigger download
header('Content-Disposition: attachment; filename="fieldnotes.kml"');

print $kmlFeed;


/**
 * Create KML feed
 *
 * @param $db {Resource}
 *     MySQL database
 * @param $tables {Array}
 *     Database tables from which to query data for output
 * @param $params {Array}
 *     Query paramaters
 *
 * @return $output {Kml}
 */
function createKmlFeed($db, $tables, $params) {

	// KML header
	include_once $_SERVER['DOCUMENT_ROOT'] . '/fieldnotes/header.kml.php';
	$output = $header;

	// Get features from db
	foreach ($tables as $table => $folder_name) {
		$output .= "		<Folder><name>$folder_name</name><open>0</open>";

		$query_rsFeatures = createQuery($params, $table);
    $rsFeatures = mysql_query($query_rsFeatures, $db) or die(mysql_error());

		while ($row_rsFeatures = mysql_fetch_assoc($rsFeatures)) {

			$id = $row_rsFeatures['location_id'];
			$table = '<table>';

			// Get timezone where user submitted form
			date_default_timezone_set('America/Los_Angeles'); // set to UTC in conf; must change to determine tz accurately
			$timezone = '';
			$timestamp = '';
			$name = 'No timestamp'; // default value
			if ($row_rsFeatures['gmt_offset'] && $row_rsFeatures['timestamp'] !== '0000-00-00 00:00:00') {
				// Boolean: if timestamp is in daylight savings time or not
				$dst = date('I', strtotime($row_rsFeatures['timestamp']));
				// Timezone name (e.g. America / Los Angeles)
				$tz_name = timezone_name_from_abbr('', round($row_rsFeatures['gmt_offset']) * 3600, $dst);
				if ($tz_name) {
					$dateTime = new DateTime($row_rsFeatures['timestamp']);
					$dateTime->setTimeZone(new DateTimeZone($tz_name));
					$timezone = $dateTime->format('T'); // timezone abbreviation (e.g. PDT)
				}
			}
			if ($row_rsFeatures['timestamp'] && $row_rsFeatures['timestamp'] !== '0000-00-00 00:00:00') {
				$timestamp = date('D, M j Y g:ia', strtotime($row_rsFeatures['timestamp']));
				$name = $timestamp;
			}

			$properties = array('form' => $folder_name, 'timestamp' => $timestamp, 'timezone' => $timezone);

			$device_lat = floatval(round($row_rsFeatures['lat'], 5));
			$device_lon = floatval(round($row_rsFeatures['lon'], 5));

			if ($row_rsFeatures['latitude'] && $row_rsFeatures['longitude']) { // use SpotOn location if set
				// Store device location
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

			// Get rid of values we don't want in properties array
			unset($row_rsFeatures['id']);
			unset($row_rsFeatures['spoton_id']);
			unset($row_rsFeatures['location_id']);
			unset($row_rsFeatures['lat']);
			unset($row_rsFeatures['lon']);
			unset($row_rsFeatures['z']);
			unset($row_rsFeatures['timestamp']);
			unset($row_rsFeatures['gmt_offset']);
			// (include spoton values)
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
					<description><![CDATA[%s]]></description>
					<Snippet maxLines="0"></Snippet>
					<LookAt><longitude>%s</longitude><latitude>%s</latitude><range>1000000</range></LookAt>
					<styleUrl>#feature</styleUrl>
					<Style><IconStyle><scale>1.6</scale></IconStyle></Style>
					<Point><coordinates>%s</coordinates></Point>
				</Placemark>',
				$id, $name, $table, $coords[0], $coords[1], implode(',', $coords)
			);

		}

		$output .= "</Folder>";

	}

	$footer .= '	</Document>
</kml>';
	$output .= $footer;

	return $output;
}
