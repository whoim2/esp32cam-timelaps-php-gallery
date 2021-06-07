# esp32cam-timelaps-php-gallery
esp32cam arduino and php-based scripts for cloud 3d-printing timelapses, may create animated gif or video-file from images, recieved at esp32cam on trigger (layer change) or timeout.

Based on arduino code from https://github.com/robotzero1/esp32cam-timelapse and animated gif php library from https://github.com/lunakid/AnimGif
im use esp32cam from Aliexpress: https://aliexpress.ru/item/1005002035573150.html

example of work: https://yadi.sk/i/rwDl-g6lLueByA


Example, pin mapping and z-layer-change gcode for RRF on SKR1.3:


config.g:
M950 P0 C"e1heat" //esp32 and light power mosfet
M42 P0 S0
M950 P1 C"xstop" //trigger pin x- to 12 pin esp
M42 P1 S0

slicer-z-layer-gcode:
M42 P1 S1
G4 P100
M42 P1 S0

for Cura slicer, script adding z-change layer code: https://github.com/whoim2/esp32cam-timelaps-php-gallery/blob/main/gcode.cmd
