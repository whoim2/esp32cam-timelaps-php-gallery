powershell -Command "(gc %1) -replace \";LAYER:\", \"M42 P1 S1`nG4 P100`nM42 P1 S0`n;LAYER:\" | Out-File -encoding ASCII %1"

