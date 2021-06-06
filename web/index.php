<?php
/*******************************************
php image timelaps gallery for 3d-printing
based on esp32cam
https://github.com/whoim2/esp32cam-timelaps-php-gallery
********************************************/
//settings
date_default_timezone_set('Europe/Moscow');
$frames_delay = 10;
$gif_loop = 1;
$data_folder = 'data/'; //with slashes at last char

//part for load image from esp32cam
$folder = getcwd().'/'.$data_folder;
if(isset($_GET['new']) || scandir($folder, SCANDIR_SORT_DESCENDING)[0] == '..') { //if new espcam run or empty data folder
  if( !@mkdir($folder.time()) ) {
    $error = error_get_last();
    print $error['message'];
  }
}
$f = $folder.scandir($folder, SCANDIR_SORT_DESCENDING)[0]."/";
$received = file_get_contents('php://input'); //try to get input stream
$size = strlen($received);
if($size > 0 && $_SERVER["CONTENT_TYPE"] == 'image/jpg') { //if stream not empty and this jpeg image
  file_put_contents($f.time().'.jpg', $received); //save file
  exit(0);
}

//part for download animated gif
if(isset($_GET['download'])) {
  include('inc/AnimGif.php');
  ini_set('memory_limit', '512M');
  $dir = $_GET['download'];
  $files = scandir($folder.'/'.$dir, SCANDIR_SORT_ASCENDING);
  $frames = array();
  $durations = array();
  foreach($files as $file)
    if($file <> '.' && $file <> '..') {
        $frames[] = $folder.'/'.$dir.'/'.$file;
        $durations[] = $frames_delay;
    }
  $anim = new GifCreator\AnimGif();
  $anim->create($frames, $durations, $gif_loop);
  $gif = $anim->get();
  header("Content-type: image/gif");
  header("Content-disposition: attachment; filename=\"".$dir.".gif\""); 
  print $gif;
  exit(0);
}
//part for browser
$folders = scandir($folder, SCANDIR_SORT_DESCENDING);
foreach($folders as $dir)
  if($dir <> '.' && $dir <> '..' && $dir <> 'README') {
      $files = scandir($folder.'/'.$dir, SCANDIR_SORT_ASCENDING);
      $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
      print '<a href="'.$link.'?download='.$dir.'"><img src="'.$link.$data_folder.$dir.'/'.$files[2].'" title="'.date("d.m.Y H:i:s", $dir).'"  width="100" /></a>&nbsp;';
}
?>