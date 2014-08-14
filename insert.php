<?php

// script inserts recorded features into database

//print 'error';
//exit;

header('cache-control: no-cache');

include_once 'conf/conf.inc.php';
include_once 'conf/db.inc.php';

$params = parseGetVals();

// SpotOn table
if ($params['spoton_fields']) {
	$sql_spoton = sprintf('INSERT INTO spoton (%s) VALUES (%s);',
		implode(', ', $params['spoton_fields']),
		implode(', ', $params['spoton_values'])
	);
	dbInsert($db, $sql_spoton);

	// add spoton id to data table
	array_push($params['data_fields'], '`spoton_id`');
	array_push($params['data_values'], sqlString(mysql_insert_id(), 'int'));
}

// Location table
$sql_location = sprintf('INSERT INTO location (%s) VALUES (%s);',
	implode(', ', $params['location_fields']),
	implode(', ', $params['location_values'])
);
dbInsert($db, $sql_location);

// add location id to data table
array_push($params['data_fields'], '`location_id`');
array_push($params['data_values'], sqlString(mysql_insert_id(), 'int'));

// Data table
$sql_data = sprintf('INSERT INTO %s (%s) VALUES (%s);',
	$params['table'],
	implode(', ', $params['data_fields']),
	implode(', ', $params['data_values'])
);
dbInsert($db, $sql_data);




// Parse submitted values into arrays
function parseGetVals() {
	$params = array();
	$params['location_fields'] = array();
	$params['location_values'] = array();
	$params['spoton_fields'] = array();
	$params['spoton_values'] = array();
	$params['data_fields'] = array();
	$params['data_values'] = array();

	$valid_spoton_fields = array('igid', 'username', 'email', 'fname', 'lname', 'wpid', 'name', 'latitude', 'longitude');

	foreach ($_GET as $key => $value) {

		// don't store form name (but use it for MySQL table name)
		if ($key == 'form-name') {
			$params['table'] = $value;
			continue;
		}

		// don't store file upload attributes
		if ($key == 'MAX_FILE_SIZE' || $key == 'file') {
			continue;
		}

		// combine checkbox values into string
		if (is_array($value)) {
			$cb_values = implode(', ', $value);
			$value = $cb_values;
		}

		// convert recorded field from millisecs to date string
		if (preg_match('/^\d{13}$/', $value)) {
			$value = date("Y-m-d H:i:s", $value / 1000);
		}

		if (preg_match('/^location.*/', $key)) { // location fields
			array_push($params['location_fields'], str_replace('location-', '', "`$key`"));
			array_push($params['location_values'], sqlString($value));

		} else if (preg_match('/^spoton-.+/', $key)) { // spoton fields
			$field = str_replace('spoton-', '', $key);
			if (in_array($field, $valid_spoton_fields)) { // fields passed via querystring, so filter them
				array_push($params['spoton_fields'], "`$field`");
				array_push($params['spoton_values'], urldecode(sqlString($value)));
			}

		} else { // data fields (everything else)
			array_push($params['data_fields'], "`$key`");
			array_push($params['data_values'], sqlString($value));
		}
	}

	// if recorded is set, user is syncing a record from device's localStorage; store current datetime in 'synced' field instead
	if ($_GET['recorded']) {
		array_push($params['data_fields'], '`synced`'); // set 'synced' field to now
	} else {
		array_push($params['data_fields'], '`recorded`'); // set 'recorded' field to now
	}
	array_push($params['data_values'], sqlString(date("Y-m-d H:i:s")));

	return $params;
}

// Insert record in db
function dbInsert($db, $sql) {
	mysql_query($sql, $db) or logError(mysql_error(), $sql);
}

// Log errors to screen and a file
function logError($error, $sql) {
	print $error;
	$now = date('Y-m-d H:i:s');
	$file = fopen("error.log", "a");
	fwrite($file, "$now UTC\n	 $error\n	 $sql\n");
	fclose($file);
}

// sanitize data going into db
function sqlString($value, $type = "text", $definedValue = "", $notDefinedValue = "") {
  if (get_magic_quotes_gpc()) stripslashes($value);
  //add a space when '<' is used as an arithmetic expression so it won't be caught by strip_tags ('<1' becomes '< 1')
  $value = preg_replace(array('/<([0-9]+)/'), array('< $1'), $value);
  if ($type != 'html' && $type != 'xml') $value = strip_tags($value);
  $value = mysql_real_escape_string($value);

  switch ($type) {
    case "text":
      $value = ($value != "") ? "'" . $value . "'" : "NULL";
      break;
    case "long":
    case "int":
      $value = ($value != "") ? intval($value) : "NULL";
      break;
    case "double":
      $value = ($value != "") ? "'" . doubleval($value) . "'" : "NULL";
      break;
    case "date":
      $value = ($value != "") ? "'" . $value . "'" : "NULL";
      break;
    case "float":
      $value = ($value != "") ? floatval($value) : "NULL";
      break;
    case "defined":
      $value = ($value != "") ? $definedValue : $notDefinedValue;
      break;
    case "xml":
      $value = ($value != "") ? "'" . $value . "'" : "NULL";
      break;
    case "html":
      //1. strip disallowed HTML tags
      $value = ($value != "") ? "'" . strip_tags($value, "<a><strong><em><ul><ol><li>") . "'" : "NULL";

      //2. remove any style declarations, javascript declarations and comment tags
      $value = preg_replace("'<style[^>]*?>.*?</style>'si",'',$value);
      $value = preg_replace("'<script[^>]*?>.*?</script>'si",'',$value);
      $value = str_replace('<!--', '', $value);
      $value = str_replace('-->', '', $value);

      //3. remove all attributes except href
      $value = preg_replace('/(<a.*)href=(.*>)/', '${1}href..;,;..${2}', $value);
      //$value = preg_replace('/(<.*)id=(.*>)/', '${1}id..;,;..${2}', $value);
      //$value = preg_replace('/(<.*)class=(.*>)/', '${1}class..;,;..${2}', $value);
      while (preg_match('/(<.*) .*=(\'|"|\w)\w*(\'|"|\w)(.*>)/', $value)) $value = preg_replace('/(<.*) .*=(\'|"|\w)\w*(\'|"|\w)(.*>)/', '${1}${4}', $value);
      $value = str_replace('..;,;..', '=', $value);
      break;
  }
  return $value;
}

?>