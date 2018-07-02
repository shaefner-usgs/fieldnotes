<?php

/**
 * Create circle from lat, lon, and radius to restrict query to geographical area
 *   (based on http://www.movable-type.co.uk/scripts/latlong-db.html)
 *
 * @param $params {Array}
 *
 * @return $filter {String}
 */
function createGeoFilter($params) {
  $R = 6371; // Earth's mean radius (km)

  // First-cut bounding box (in degrees) to speed up query
  $maxLat = $params['lat'] + rad2deg($params['radius']/$R);
  $minLat = $params['lat'] - rad2deg($params['radius']/$R);
  $maxLon = $params['lon'] + rad2deg(asin($params['radius']/$R) / cos(deg2rad($params['lat'])));
  $minLon = $params['lon'] - rad2deg(asin($params['radius']/$R) / cos(deg2rad($params['lat'])));

  $filter = sprintf('AND ((location.lat BETWEEN %s AND %s
    AND location.lon BETWEEN %s AND %s)
    OR (spoton.latitude BETWEEN %s AND %s AND spoton.longitude BETWEEN %s AND %s))
    AND ((acos(sin(radians(%s))*sin(radians(location.lat)) +
      cos(radians(%s))*cos(radians(location.lat))*cos(radians(location.lon) -
      radians(%s))) * %d < %d)
    OR (acos(sin(radians(%s))*sin(radians(spoton.latitude)) +
      cos(radians(%s))*cos(radians(spoton.latitude))*cos(radians(spoton.longitude) -
      radians(%s))) * %d < %d))',
    $minLat,
    $maxLat,
    $minLon,
    $maxLon,
    $minLat,
    $maxLat,
    $minLon,
    $maxLon,
    $params['lat'],
    $params['lat'],
    $params['lon'],
    $R,
    $params['radius'],
    $params['lat'],
    $params['lat'],
    $params['lon'],
    $R,
    $params['radius']
  );

  return $filter;
}

/**
 * Create MySQL query to extract appropriate data from database
 *
 * @param $params {Array}
 *     Query paramaters
 * @param $table {String}
 *     MySQL table to query
 *
 * @return $query {String}
 */
function createQuery($params, $table) {
  // Restrict query to geographical area if params set
  $geoFilter = '';
  if ($params['lat'] && $params['lon'] && $params['radius']) {
    $geoFilter = createGeoFilter($params);
  }

  $query = sprintf("SELECT * FROM %s
    LEFT JOIN location ON location_id = location.id
    LEFT JOIN spoton ON spoton_id = spoton.id
    WHERE (recorded BETWEEN '%s' AND '%s' OR synced BETWEEN '%s' AND '%s')
      AND ((location.lat != '' AND location.lon != '')
        OR (spoton.latitude != '' AND spoton.longitude != ''))
      %s
      AND operator LIKE '%s'
    ORDER BY recorded ASC;",
    $table,
    $params['after'],
    $params['before'],
    $params['after'],
    $params['before'],
    $geoFilter,
    $params['operator']
  );

  return $query;
}

/**
 * Get input parameters that control feed display: API allows several (optional)
 *   parameters to filter result set:
 *
 * 1. period=[hour|day|week|month|quarter]
 * 2. after=unix time stamp (e.g. 1377541566)
 * 3. before=unix time stamp (e.g. 1377541566)
 * 4. between=after,before (where after and before are unix time stamps)
 * 5. lat=decimal degrees; lon=decimal degrees; radius=km - restrict to geographic area (circle)
 * 6. operator=email address
 * 7. content=[all|checkins|observations]
 *
 * @param $periods {Array}
 *
 * @return $params {Array}
 */
function getParams($periods) {
  $params = Array();

  $params['after'] = safeParam('after', strtotime('2011-01-01'));
  $params['before'] = safeParam('before', time());
  $params['between'] = safeParam('between');
  $params['callback'] = safeParam('callback');
  $params['content'] = safeParam('content', 'observations');
  $params['lat'] = safeParam('lat');
  $params['lon'] = safeParam('lon');
  $params['operator'] = safeParam('operator', '%'); // default: mysql wildcard
  $params['period'] = safeParam('period');
  $params['radius'] = safeParam('radius');

  // Manually set 'after' (and 'before') values if 'between' or 'period' param is set
  if ($params['between']) {
    list($params['after'], $params['before']) = preg_split('/\s?,\s?/', $params['between']);
  }
  if ($params['period']) {
    $period = $params['period'];
    $params['after'] = strtotime($periods[$period]);
  }

  // Convert time stamps to MySQL datetime
  $params['after'] = date('Y-m-d H:i:s', $params['after']);
  $params['before'] = date('Y-m-d H:i:s', $params['before']);

  return $params;
}

/**
 * Convert MySQL null value to empty string
 *
 * @param $value {Mixed}
 *
 * @return $value {String}
 */
function null2string($value) {
  if ($value === null) {
    $value = '';
  }

  return $value;
}

/**
 * Get a request parameter from $_GET or $_POST
 *
 * @param $name {String}
 *     The parameter name
 * @param $default {?} default is NULL
 *     Optional default value if the parameter was not provided.
 * @param $filter {PHP Sanitize filter} default is FILTER_SANITIZE_STRING
 *     Optional sanitizing filter to apply
 *
 * @return $value {String}
 */
function safeParam ($name, $default=NULL, $filter=FILTER_SANITIZE_STRING) {
  $value = NULL;
  if (isset($_POST[$name]) && $_POST[$name] !== '') {
    $value = filter_input(INPUT_POST, $name, $filter);
  } else if (isset($_GET[$name]) && $_GET[$name] !== '') {
    $value = filter_input(INPUT_GET, $name, $filter);
  } else {
    $value = $default;
  }

  return $value;
}

/**
 * Set appropriate response headers shared by json/kml file types
 *
 * @param $type {String}
 */
function setHeaders($type) {
  header('Content-Type: ' . $type);
  header('Cache-Control: no-cache');
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: *');
  header('Access-Control-Allow-Headers: accept,origin,authorization,content-type');
}

/**
 * Convert an array to a json feed and output to user (display or download)
 *
 * @param $array {Array}
 *     Feed data in array form
 * @param $callback {String} default is NULL
 *     Optional callback for jsonp requests
 */
function showJson ($array, $callback=NULL) {
  setHeaders('application/json');

  $json = str_replace('\/','/', json_encode($array));
  if ($callback) {
    print "$callback($json)";
  } else {
    header('Content-Disposition: attachment; filename="fieldnotes.json"');
    print $json;
  }
}
