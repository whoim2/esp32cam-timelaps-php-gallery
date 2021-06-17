# Copyright (c) 2020 Ultimaker B.V.
# Cura is released under the terms of the LGPLv3 or higher.
# Created by Wayne Porter

from ..Script import Script


class TimeLapse(Script):
    def __init__(self):
        super().__init__()

    def getSettingDataString(self):
        return """{
            "name": "Time Lapse",
            "key": "TimeLapse",
            "metadata": {},
            "version": 2,
            "settings":
            {
                "trigger_command":
                {
                    "label": "Trigger camera command",
                    "description": "G-code command used to trigger camera.",
                    "type": "str",
                    "default_value": "M42 P1 S1 G4 P100 M42 P1 S0"
                },
                "pause_length":
                {
                    "label": "Pause length",
                    "description": "How long to wait (in ms) after camera was triggered.",
                    "type": "int",
                    "default_value": 0,
                    "minimum_value": 0,
                    "unit": "ms"
                },
                "park_print_head":
                {
                    "label": "Park Print Head",
                    "description": "Park the print head out of the way. Assumes absolute positioning.",
                    "type": "bool",
                    "default_value": true
                },
                "set_absolute":
                {
                    "label": "absolute mode",
                    "description": "Set absolute positioning mode and return to relative",
                    "type": "bool",
                    "default_value": false
                },
                "head_park_x":
                {
                    "label": "Park Print Head X",
                    "description": "What X location does the head move to for photo.",
                    "unit": "mm",
                    "type": "float",
                    "default_value": 0,
                    "enabled": "park_print_head"
                },
                "head_park_y":
                {
                    "label": "Park Print Head Y",
                    "description": "What Y location does the head move to for photo.",
                    "unit": "mm",
                    "type": "float",
                    "default_value": 0,
                    "enabled": "park_print_head"
                },
                "park_feed_rate":
                {
                    "label": "Park Feed Rate",
                    "description": "How fast does the head move to the park coordinates.",
                    "unit": "mm/s",
                    "type": "float",
                    "default_value": 6000,
                    "enabled": "park_print_head"
                }
            }
        }"""

    def execute(self, data):
        feed_rate = self.getSettingValueByKey("park_feed_rate")
        park_print_head = self.getSettingValueByKey("park_print_head")
        set_absolute = self.getSettingValueByKey("set_absolute")
        x_park = self.getSettingValueByKey("head_park_x")
        y_park = self.getSettingValueByKey("head_park_y")
        trigger_command = self.getSettingValueByKey("trigger_command")
        pause_length = self.getSettingValueByKey("pause_length")
        gcode_to_append = ";TimeLapse Begin\n"
        last_x = 0
        last_y = 0

        if park_print_head:
            if set_absolute:
                gcode_to_append += "G90\n" + self.putValue(G=1, F=feed_rate, X=x_park, Y=y_park) + "\nG91 ;Park print head\n"
            else:
                gcode_to_append += self.putValue(G=0, F=feed_rate, X=x_park, Y=y_park) + " ;Park print head\n"
        gcode_to_append += self.putValue(M=400) + " ;Wait for moves to finish\n"
        gcode_to_append += trigger_command + " ;Snap Photo\n"
        gcode_to_append += self.putValue(G=4, P=pause_length) + " ;Wait for camera\n"

        for idx, layer in enumerate(data):
            for line in layer.split("\n"):
                if self.getValue(line, "G") in {0, 1}:  # Track X,Y location.
                    last_x = self.getValue(line, "X", last_x)
                    last_y = self.getValue(line, "Y", last_y)
            # Check that a layer is being printed
            lines = layer.split("\n")
            for line in lines:
                if ";LAYER:" in line:
                    layer += gcode_to_append

                    layer += "G0 X%s Y%s\n" % (last_x, last_y)

                    data[idx] = layer
                    break
        return data
