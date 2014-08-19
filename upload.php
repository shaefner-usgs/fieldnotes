<?php

//exit;
//print_r ($_FILES);

include_once 'conf/conf.inc.php';

// attempt to set php's max upload size
ini_set('upload_max_filesize', $maxsize);

// allowed file types
$allowed_exts = array('gif', 'jpeg', 'jpg', 'png');

// set path and filename (base filename passed in via name param)
$allowed = '/^\d+$/';
if (isSet($_POST['name']) && (preg_match($allowed, $_POST['name']))) {
	$name = $_POST['name'];
}
$parts = explode('.', $_FILES['photo']['name']);
$ext = strtolower(end($parts));
$upload_dir = dirname(__FILE__) . '/uploads';
$upload_file = "$upload_dir/$name.$ext";

if ($_FILES['photo']['error']) {
  $errors = array(
    1 => 'too large', // exceeds upload_max_filesize directive in php.ini
    2 => 'too large', // exceeds MAX_FILE_SIZE directive specified in HTML (not always honored by browser)
    3 => 'upload incomplete',
    4 => 'upload failed',
    6 => 'temp folder missing',
    7 => 'failed to write',
  );
  printf ('Could not upload photo - %s (code: %d)', $errors[$_FILES['photo']['error']], $_FILES['photo']['error']);
 	exit;
}

if (!in_array($ext, $allowed_exts)) {
  print "File type not allowed ($ext)";
  exit;
}

// enforce max size independent of server settings
if ($_FILES['photo']['size'] > $maxsize) {
	print 'Photo is too large';
	exit;
}

// move uploaded file and create thumbnail
if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_file)) {
  // success; create thumbnail
  exec("$convert $upload_file -auto-orient -thumbnail 300x300 -unsharp 0x.5 -bordercolor white -border 10 -bordercolor grey60 -border 1 -background black \( +clone -shadow 40x4+3+3 \) +swap -background none -flatten $upload_dir/$name-tn.png");
} else {
	print 'Could not copy photo';
}

?>