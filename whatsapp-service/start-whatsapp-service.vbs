Set objShell = CreateObject("WScript.Shell")
objShell.CurrentDirectory = "C:\xampp\htdocs\mensajeroprov2\whatsapp-service"
objShell.Run "cmd /c node src\index.js > logs\service-2025-09-21.log 2>&1", 0, False
