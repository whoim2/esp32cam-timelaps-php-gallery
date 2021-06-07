<?php
/*******************************************
php image timelaps gallery for 3d-printing
based on esp32cam
https://github.com/whoim2/esp32cam-timelaps-php-gallery
********************************************/
//settings
date_default_timezone_set('Europe/Moscow');
//for gif
$frames_delay = 10;
$gif_loop = 1;
$rotate = 270; //0, 90, 180, 270
//
$data_folder = 'data/'; //with slashes at last char
$dmode = 'video'; //'gif' or 'video'. Video need ffmpeg, for example cmd: ffmpeg -framerate 10 -pattern_type glob -i "*.jpg" output.avi
$video_format = "mov"; //avi, mpg, wmv, mov. Use ffmpeg --codesc for details

//part for load image from esp32cam
$folder = getcwd().'/'.$data_folder;
if(isset($_GET['new']) || scandir($folder, SCANDIR_SORT_DESCENDING)[0] == '..') { //if new espcam run or empty data folder
  if( !@mkdir($folder.time()) ) {
    $error = error_get_last();
    print $error['message'];
  }
}
$f = $folder.scandir($folder, SCANDIR_SORT_DESCENDING)[0]."/";
if(file_exists($f.'README')) unlink($f.'README');
$received = file_get_contents('php://input'); //try to get input stream
$size = strlen($received);
if($size > 0 && $_SERVER["CONTENT_TYPE"] == 'image/jpg') { //if stream not empty and this jpeg image
  $f .=  time().'.jpg';
  file_put_contents($f, $received); //save file
  if($rotate > 0) {//need rotate
    $i = false;
    while(!$i) {
      $i = imagerotate(imagecreatefromjpeg($f), $rotate, 0);
    }
    imagejpeg($i, $f);
  }
  exit(0);
}

//part for download animated gif
if(isset($_GET['download'])) {
 $dir = $_GET['download'];
 if($dmode == 'gif') {
    include('inc/AnimGif.php');
    ini_set('memory_limit', '1024M');
    set_time_limit(300);
    $files = preg_grep('~\.(jpeg|jpg|png)$~', scandir($folder.'/'.$dir, SCANDIR_SORT_ASCENDING));
    if(sizeof($files) < 4) die("few images for building animation");
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
}
else if($dmode = 'video') {
    shell_exec('ffmpeg -y -framerate 25 -pattern_type glob -i "'.$folder.'/'.$dir.'/*.jpg" '.$folder.'/'.$dir.'/'.$dir.'.'.$video_format);
    $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]".explode('?', $_SERVER['REQUEST_URI'], 2)[0];
    
    header('HTTP/1.1 301 Moved Permanently'); 
    header('Location: '.$link.$data_folder.$dir.'/'.$dir.'.'.$video_format);
    
    /*print '<VIDEO>
          <SOURCE SRC="'.$link.$data_folder.$dir.'/'.$dir.'.avi'.'" TYPE="video/avi">
          <P>The video can`t be played on this browser.</P>
          </VIDEO>';*/
}
  exit(0);
}
//part for browser
$folders = scandir($folder, SCANDIR_SORT_DESCENDING);
foreach($folders as $dir)
  if($dir <> '.' && $dir <> '..') {
      $files = preg_grep('~\.(jpeg|jpg|png)$~', scandir($folder.'/'.$dir, SCANDIR_SORT_ASCENDING));
      if(sizeof($files) > 2) {
        $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]".explode('?', $_SERVER['REQUEST_URI'], 2)[0];
        print '<a href="'.$link.'?download='.$dir.'&rand='.rand(10000, 99999).'"><img src="'.$link.$data_folder.$dir.'/'.$files[sizeof($files)-1].'" title="'.date("d.m.Y H:i:s", $dir).'"  width="200" /></a>&nbsp;';
      } else {
        if(time() - $dir > 86400) rmdir($folder.'/'.$dir); //delete folder where <2 pictures after 24 hour
      }
}
?>