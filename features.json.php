<?php

// Script creates a GeoJSON file of recorded features

include_once 'conf/conf.inc.php'; // $periods and $tables arrays are set here
include_once 'conf/cors.inc.php';
include_once 'conf/db.inc.php';
include_once 'conf/functions.inc.php'; // shared methods

$params = getParams($periods);

// Default $tables array is all tables with observations (everything but 'checkin')
if ($params['content'] === 'all') {
  $tables['checkin'] = 'Checkin'; // add checkins
} else if ($params['content'] === 'checkins') {
  $tables = array('checkin' => 'Checkin'); // set to checkins only
}

// Create geojson and output to user
$jsonArray = createJsonFeed($db, $tables, $params);
showJson($jsonArray, $params['callback']);


/**
 * Create GeoJson feed (as an array which is easily converted to json)
 *
 * @param $db {Resource}
 *     MySQL database
 * @param $tables {Array}
 *     Database tables from which to query data for output
 * @param $params {Array}
 *     Query paramaters
 *
 * @return $feed {Array}
 */
function createJsonFeed($db, $tables, $params) {
  $count = 0;
  $features = array(); // Create array to store features

  // Get features from db
  foreach ($tables as $table => $name) {
    $query_rsFeatures = createQuery($params, $table);
    $rsFeatures = mysql_query($query_rsFeatures, $db) or die(mysql_error());

    while ($row_rsFeatures = mysql_fetch_assoc($rsFeatures)) {

      $id = $row_rsFeatures['location_id'];

      // Get timezone where user submitted form
      date_default_timezone_set('America/Los_Angeles'); // set to UTC in conf; must change to determine tz accurately
      $timestamp = '';
      $timezone = '';
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
      }

      $properties = array('form' => $name, 'timestamp' => $timestamp, 'timezone' => $timezone);

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
          $value .= ' UTC'; // add explicit UTC timezone to recorded/snyced fields
        }
        if ($key === 'photo') {
          // Jim Morentz asked for the 'photo' field to be set to 'attachment' in the json feed
          $key = 'attachment';
          if ($value) {
            $path = pathinfo($_SERVER['REQUEST_URI'], PATHINFO_DIRNAME);
            if (file_exists("uploads/$value")) {
              $value = sprintf ('https://%s%s/uploads/%s', $_SERVER['SERVER_NAME'], $path, $value);
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

      $count ++;
    };

  }

  $feed = array(
    "type" => "FeatureCollection",
    "metadata" => array(
      "count" => $count,
      "generated" => time(),
      "tables" => implode(', ', $tables),
      "url" => "https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"
    ),
    "geometryType" => "esriGeometryPoint",
    "spatialReference" => array(
      "wkid" => 4326
    ),
    "features" => $features
  );

  return $feed;
}
