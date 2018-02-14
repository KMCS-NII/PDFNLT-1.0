<?php
require_once(dirname(__FILE__) . "/lib.php");

// Read "config.txt"
$config = read_config();

header("Content-Type: application/json");
if (array_key_exists('directory', $_GET)) {
  $files = get_xhtml_options($config);
  echo json_encode($files);
}
