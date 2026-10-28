@echo off
rem Force-stop leftovers if the start window was closed abnormally
taskkill /F /IM mariadbd.exe >nul 2>&1
taskkill /F /IM php.exe >nul 2>&1
echo Stopped.
pause
