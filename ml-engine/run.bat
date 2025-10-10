@echo off
echo Iniciando ML Engine...

REM Activar entorno virtual
call venv\Scripts\activate

REM Iniciar servidor
python src/api_server.py

pause