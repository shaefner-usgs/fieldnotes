<?php

include 'cors.inc.php';

// script creates geoJSON file of recorded features

header('cache-control: no-cache');
header('Content-Type: application/json');

include_once 'conf/conf.inc.php';
include_once 'conf/db.inc.php';

$params = parseGetVals();

// $tables array is set to all tables with observations (everything but 'checkin')
if ($params['content'] === 'all') {
  array_push($tables, 'checkin');
} else if ($params['content'] === 'checkins') {
  $tables = ['checkin'];
}

$json_array = createJsonFeed($db, $tables, $params);

// Create json object from array and display
$json = str_replace('\/','/',json_encode($json_array));
if ($params['callback']) {
	printf ('%s(%s);', $params['callback'], $json);
} else { // no callback param, assume download option
	header('Content-Disposition: attachment; filename="fieldnotes.json"');
	print $json;
}



/**
 * Parse input parameters that control feed display
 * api allows several parameters to be set:
 *
 * 1. period=hour, period=day, etc.
 * 2. after=1377541566 (unix time stamp)
 * 3. before=1377541566 (unix time stamp)
 * 4. between=after,before (where after and before are unix time stamps)
 * 5. operator=email@domain.com
 */

function parseGetVals() {
	$params = array();

  // Set deafult values
  $after = strtotime('2011-01-01');
	$before = time();
  $callback = null;
  $content = 'features';
  $operator = '%'; // mysql wildcard

  $allowed = '/^[\w,\@\.]+$/'; // Sanitize input parameter (alphanumerics + a few others only)
  $periods = array(
    'hour' => '1 hour ago',
    'day' => '1 day ago',
    'week' => '7 days ago',
    'month' => '1 month ago',
    'quarter' => '3 months ago'
  );

  if (isSet($_GET['after']) && (preg_match($allowed, $_GET['after']))) {
    $after = $_GET['after'];
  }
	if (isSet($_GET['before']) && (preg_match($allowed, $_GET['before']))) {
		$before = $_GET['before'];
	}
	if (isSet($_GET['between']) && (preg_match($allowed, $_GET['between']))) {
		list($after, $before) = preg_split('/\s?,\s?/', $_GET['between']);
	}
	if (isSet($_GET['callback']) && (preg_match($allowed, $_GET['callback']))) {
		$callback = $_GET['callback'];
	}
  if (isSet($_GET['content']) && (preg_match('/^(all|checkins)$/', $_GET['content']))) {
    $content = $_GET['content'];
  }
	if (isSet($_GET['operator']) && (preg_match($allowed, $_GET['operator']))) {
		$operator = $_GET['operator'];
	}
  if (isSet($_GET['period']) && (preg_match($allowed, $_GET['period']))) {
    $period = $_GET['period'];
    $after = strtotime($periods[$period]);
  }

	$params['after'] = date('Y-m-d H:i:s', $after);
  $params['before'] = date('Y-m-d H:i:s', $before);
  $params['callback'] = $callback;
  $params['content'] = $content;
	$params['operator'] = $operator;

	return $params;
}

// Create json file
function createJsonFeed($db, $tables, $params) {
	// Create array to store features
	$features = array();

	// Get features from db
	foreach ($tables as $table => $name) {

		$query_rsFeatures = sprintf("SELECT * FROM %s
			LEFT JOIN location ON location_id = location.id
			LEFT JOIN spoton ON spoton_id = spoton.id
			WHERE (recorded BETWEEN '%s' AND '%s' OR synced BETWEEN '%s' AND '%s')
				AND ((location.lat != '' AND location.lon != '') OR (spoton.latitude != '' AND spoton.longitude != ''))
				AND operator LIKE '%s'
			ORDER BY recorded ASC;",
			$table, $params['after'], $params['before'], $params['after'], $params['before'], $params['operator']);

		$rsFeatures = mysql_query($query_rsFeatures, $db) or die(mysql_error());

		while ($row_rsFeatures = mysql_fetch_assoc($rsFeatures)) {

			$id = $row_rsFeatures['location_id'];

			// get timezone where user submitted form
			date_default_timezone_set('America/Los_Angeles'); // set to UTC above; need to change it to determine tz accurately
			$timezone = '';
			$timestamp = '';
			if ($row_rsFeatures['gmt_offset'] && $row_rsFeatures['timestamp'] !== '0000-00-00 00:00:00') {
				$dst = date('I', strtotime($row_rsFeatures['timestamp'])); // boolean: if timestamp is in daylight savings time or not
				$tz_name = timezone_name_from_abbr('', round($row_rsFeatures['gmt_offset']) * 3600, $dst); // timezone name (e.g. America / Los Angeles)
				if ($tz_name) {
					$dateTime = new DateTime($row_rsCheckins['timestamp']);
					$dateTime->setTimeZone(new DateTimeZone($tz_name));
					$timezone = $dateTime->format('T'); // timezone abbreviation (e.g. PDT)
				}
			}
			if ($row_rsFeatures['timestamp'] && $row_rsFeatures['timestamp'] !== '0000-00-00 00:00:00') {
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
					$key = 'attachment'; // Jim Morentz asked for the 'photo' field to be set to 'attachment' in the json feed
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

			$feature = array(
				"type" => "Feature",
				"id" => $id,
				"geometry" => array(
					"type" => "Point",
					"coordinates" => $coords
				),
				"properties" => $properties
			);

			array_push($features, $feature);
		};

	}

	$geojson = array(
		"type" => "FeatureCollection",
    "geometryType" => "esriGeometryPoint",
    "spatialReference" => array(
      "wkid" => 4326
    ),
		"features" => $features
	);
	return $geojson;
}

function sanitize($param) {
	if (!$param) {
		$param = '';
	}
	//$r = preg_replace('@\\"|\\\\|\\/|\\b|\\f|\\n|\\r|\\t|\\u@', "", $param);
	$r = str_replace('\\', '/', $param);
	$r = str_replace('"', "'", $param);
	return $r;
}

// convert null values from MySQL to empty strings
function null2string($value) {
	if ($value === null) {
		$value = '';
	}
	return $value;
}

?>
