@echo off
title Actualizar mapa desde el CSV
cd /d "%~dp0"
node convertir-csv.js
echo.
echo Listo. Recargue la pagina del mapa (F5) para ver los cambios.
pause
