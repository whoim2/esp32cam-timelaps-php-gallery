# esp32cam-timelaps-php-gallery
esp32cam arduino and php-based scripts for cloud 3d-printing timelapses, may create animated gif or video-file from images, recieved at esp32cam on trigger (layer change) or timeout.

__UPDATES__
18.06.2021 - added to index.php: css view; download video/gif links, photos view subgallery; delete folders and files links;

![esp32cam](https://github.com/whoim2/esp32cam-timelaps-php-gallery/raw/main/Screenshot_2.png)
![esp32cam](https://github.com/whoim2/esp32cam-timelaps-php-gallery/raw/main/Screenshot_5.png)

Based on arduino code from https://github.com/robotzero1/esp32cam-timelapse and animated gif php library from https://github.com/lunakid/AnimGif

im use esp32cam from Aliexpress: https://aliexpress.ru/item/1005002035573150.html

and 5v dc-dc stepdown: https://a.aliexpress.com/_A5k3OF

example of work GIF: https://yadi.sk/i/rwDl-g6lLueByA

video mov: https://youtu.be/k5FLd7WKuOI mpeg: https://youtu.be/wQOmJVJ83b4

web screenshot: 

![esp32cam](https://github.com/whoim2/esp32cam-timelaps-php-gallery/raw/main/Screenshot_8.png)


__Example, pin mapping and z-layer-change gcode for RRF (https://github.com/gloomyandy/RepRapFirmware) on SKR1.3:__

__config.g:__
```
M950 P0 C"e1heat" //esp32 and light power mosfet
M42 P0 S0
M950 P1 C"xstop" //trigger pin x- to 12 pin esp
M42 P1 S0
```
__After need power on lights and esp32cam, in RRF file tpre0.g or slicer start code__
```
M42 P0 S1 ;start lights and cam rec
```
__slicer-z-layer-change-gcode:__
```
M42 P1 S1
G4 P100
M42 P1 S0
```
__and power off lights and esp, tfree0.g or slicer finised code:__
```
M42 P0 S0
```

__for Cura slicer__
script adding z-change layer code: https://github.com/whoim2/esp32cam-timelaps-php-gallery/blob/main/gcode.cmd

OR

use this postscript cura plugin (tested with 4.8 cura): https://github.com/whoim2/esp32cam-timelaps-php-gallery/raw/main/TimeLapse.py
