<?php
$folder = getcwd().'/data/';
if(isset($_GET['new']) || scandir($folder, SCANDIR_SORT_DESCENDING)[0] == '..') { //if new espcam run or empty data folder
  if( !@mkdir($folder.time()) ) {
    $error = error_get_last();
    print $error['message'];
  }
}
$folder .= scandir($folder, SCANDIR_SORT_DESCENDING)[0]."/";
$received = file_get_contents('php://input'); //try to get input stream
$size = strlen($received);
if($size > 0 && $_SERVER["CONTENT_TYPE"] == 'image/jpg') { //if stream not empty and this jpeg image
  file_put_contents($folder.time().'.jpg', $received); //save file
  exit(0);
}
?>