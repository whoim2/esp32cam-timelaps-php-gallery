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

//overrides
if(isset($_GET['dmode']))
  if(!empty($_GET['dmode']))
    if($_GET['dmode'] == 'gif') $dmode = 'gif';
    else $dmode = 'video';

if(isset($_GET['video_format']))
  if(!empty($_GET['video_format']))
    $video_format = trim($_GET['video_format']);

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
 ini_set('memory_limit', '1024M');
 set_time_limit(300);
 $dir = $_GET['download'];
 if($dmode == 'gif') {
    include('inc/AnimGif.php');
    $files = array_values(preg_grep('~\.(jpeg|jpg|png)$~', scandir($folder.'/'.$dir, SCANDIR_SORT_ASCENDING)));
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

//del folder
if(isset($_GET['del'])) {
  _delete_dir($folder.trim($_GET['del']));
  header('Location: '.(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]".explode('?', $_SERVER['REQUEST_URI'], 2)[0]);
}

//del file
if(isset($_GET['delfile'])) {
  unlink($folder.trim($_GET['delfile']));
  header('Location: '.(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]".explode('?', $_SERVER['REQUEST_URI'], 2)[0].'?photos='.explode('/', trim($_GET['delfile']))[0]);
}

if(isset($_GET['resized'])) {
      $file = $folder.trim($_GET['resized']);
      $w = 200;
      $h = 200;
      $crop = true;
      list($width, $height) = getimagesize($file);
      $r = $width / $height;
      if ($crop) {
          if ($width > $height) {
              $width = ceil($width-($width*abs($r-$w/$h)));
          } else {
              $height = ceil($height-($height*abs($r-$w/$h)));
          }
          $newwidth = $w;
          $newheight = $h;
      } else {
          if ($w/$h > $r) {
              $newwidth = $h*$r;
              $newheight = $h;
          } else {
              $newheight = $w/$r;
              $newwidth = $w;
          }
      }
      $src = imagecreatefromjpeg($file);
      $dst = imagecreatetruecolor($newwidth, $newheight);
      imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
      header("Content-type: image/jpeg");
      imagejpeg($dst, null, 90);
      exit(0);
}

//functions
function _delete_dir($path) {
  if (!is_dir($path)) {
    throw new InvalidArgumentException("$path must be a directory");
  }
  if (substr($path, strlen($path) - 1, 1) != DIRECTORY_SEPARATOR) {
    $path .= DIRECTORY_SEPARATOR;
  }
  $files = glob($path . '*', GLOB_MARK);
  foreach ($files as $file) {
    if (is_dir($file)) {
      _delete_dir($file);
    } else {
      unlink($file);
    }
  }
  rmdir($path);
}

//part for browser
?>
<html>
<head>
<style>
body,td,th {
	font-family: Verdana, Arial, Helvetica, sans-serif;
	font-size: 12px;
	color: #333333;
}
body {
	background-color: #FFFFFF;
	margin-left: 3px;
	margin-top: 3px;
	margin-right: 3px;
	margin-bottom: 3px;
}
a {
	font-family: Verdana, Arial, Helvetica, sans-serif;
	font-size: 12px;
	color: #990000;
}
a:link {
	text-decoration: none;
}
a:visited {
	text-decoration: none;
	color: #990000;
}
a:hover {
	text-decoration: underline;
	color: #990000;
}
a:active {
	text-decoration: none;
	color: #990000;
}
div.gallery {
  margin: 5px;
  border: 1px solid #ccc;
  float: left;
  width: 180px;
}

div.gallery:hover {
  border: 1px solid #777;
}

div.gallery img {
  width: 100%;
  height: auto;
}

div.desc {
  padding: 15px;
  text-align: center;
}
</style>
</head>
<body>
<?php
if(isset($_GET['photos'])) { //view details photo
  $dir = trim($_GET['photos']);
  print '<a href="'.(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]".explode('?', $_SERVER['REQUEST_URI'], 2)[0].'"> &lt;&lt;&lt; '.date("d.m.Y H:i:s", $dir).'</a><hr>';
  $files = array_values(preg_grep('~\.(jpeg|jpg|png)$~', scandir($folder.'/'.$dir, SCANDIR_SORT_ASCENDING)));
     if(sizeof($files) > 0) {
        $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]".explode('?', $_SERVER['REQUEST_URI'], 2)[0];
        foreach($files as $file) {?>
          <div class="gallery">
          <a target="_blank" href="<?php print $link.'/'.$data_folder.$dir.'/'.$file;?>">
          <img src="?resized=<?php print $dir.'/'.$file;?>" alt="<?php print date("d.m.Y H:i:s", $file);?>" width="200" height="200">
          </a>
          <div class="desc"><?php print $file;?>&nbsp[<a href="<?php print $link.'?delfile='.$dir.'/'.$file.'&rand='.rand(10000, 99999);?>" onclick="return confirm('Уверены?')">DEL</a>]
          </div>
          </div>
        <?php
        }     
     }
} else {
  $folders = scandir($folder, SCANDIR_SORT_DESCENDING);
  foreach($folders as $dir)
    if($dir <> '.' && $dir <> '..') {
        $files = array_values(preg_grep('~\.(jpeg|jpg|png)$~', scandir($folder.'/'.$dir, SCANDIR_SORT_ASCENDING)));
        if(sizeof($files) > 0) {
          $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]".explode('?', $_SERVER['REQUEST_URI'], 2)[0];
          ?>
          <div class="gallery">
          <a href="<?php print $link.'?photos='.$dir.'&rand='.rand(10000, 99999);?>">
          <img src="?resized=<?php print $dir.'/'.$files[sizeof($files)-1];?>" alt="<?php print date("d.m.Y H:i:s", $dir);?>" width="200" height="200">
          </a>
          <div class="desc"><?php print date("d.m.Y H:i:s", $dir);?><hr>
          [<a href="<?php print $link.'?download='.$dir.'&dmode=video&rand='.rand(10000, 99999);?>"><?php print strtoupper($video_format);?></a>]&nbsp
          [<a href="<?php print $link.'?download='.$dir.'&dmode=gif&rand='.rand(10000, 99999);?>">GIF</a>]&nbsp
          [<a href="<?php print $link.'?photos='.$dir.'&rand='.rand(10000, 99999);?>">PHOTOS</a>]&nbsp
          [<a href="<?php print $link.'?del='.$dir.'&rand='.rand(10000, 99999);?>" onclick="return confirm('Уверены?')">DEL</a>]
          </div>
          </div>
          <?php
          //print '<a href="'.$link.'?download='.$dir.'&rand='.rand(10000, 99999).'"><img src="'.$link.$data_folder.$dir.'/'.$files[sizeof($files)-1].'" title="'.date("d.m.Y H:i:s", $dir).'"  width="200" /></a>&nbsp;';
        } else {
          if(time() - $dir > 86400) rmdir($folder.'/'.$dir); //delete folder where <2 pictures after 24 hour
        }
  }
}
?>
</body>
</html>