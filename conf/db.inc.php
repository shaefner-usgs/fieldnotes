<?php

// db params
define('HOSTNAME', '');
define('DATABASE', '');
define('USERNAME', '');
define('PASSWORD', '');

// db connect
$db = mysql_connect(HOSTNAME, USERNAME, PASSWORD) or die(mysql_error());
mysql_select_db(DATABASE, $db);
