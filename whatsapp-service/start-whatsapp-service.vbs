Set objShell = CreateObject("WScript.Shell")
objShell.CurrentDirectory = "C:\xampp\htdocs\mensajeroprov2\whatsapp-service"
objShell.Run "cmd /c set NODE_ENV=development && node src\index.js 3003 3 > logs\empresa-3.log 2>&1", 0, False
