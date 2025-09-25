Set objShell = CreateObject("WScript.Shell")
objShell.CurrentDirectory = "C:\xampp\htdocs\mensajeroprov2\whatsapp-service"
objShell.Run "cmd /c node src\index.js 3001 1 > logs\empresa-1.log 2>&1", 0, False
