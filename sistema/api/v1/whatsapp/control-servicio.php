<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/multi_tenant.php';
require_once __DIR__ . '/../../../../includes/whatsapp_ports.php';

$empresa_id = getEmpresaActual();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'No autorizado');
}

$accion = $_POST['accion'] ?? '';
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$servicePath = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'whatsapp-service';

try {
    if ($accion == 'iniciar') {
        // Obtener puerto asignado a esta empresa
        $puerto = obtenerPuertoEmpresa($pdo, $empresa_id);

        // Verificar si ya está corriendo en ese puerto
        if (verificarPuertoActivo($puerto)) {
            $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET estado = 'verificando' WHERE empresa_id = ?");
            $stmt->execute([$empresa_id]);
            jsonResponse(true, 'El servicio ya está en ejecución');
        }

        // Limpiar sesiones anteriores
        $sessionPaths = [
            $servicePath . DIRECTORY_SEPARATOR . '.wwebjs_auth',
            $servicePath . DIRECTORY_SEPARATOR . 'tokens'
        ];

        foreach ($sessionPaths as $sessionPath) {
            if (file_exists($sessionPath)) {
                if ($isWindows) {
                    @exec("rmdir /s /q \"$sessionPath\" 2>&1");
                } else {
                    @exec("rm -rf \"$sessionPath\" 2>&1");
                }
            }
        }

        // Actualizar estado
        $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET estado = 'iniciando', qr_code = NULL, numero_conectado = NULL WHERE empresa_id = ?");
        $stmt->execute([getEmpresaActual()]);

        // Crear carpeta logs si no existe
        $logsDir = $servicePath . DIRECTORY_SEPARATOR . 'logs';
        if (!file_exists($logsDir)) {
            mkdir($logsDir, 0777, true);
        }

        // Limpiar logs antiguos (más de 7 días)
        $logFiles = glob($logsDir . DIRECTORY_SEPARATOR . '*.log');
        foreach ($logFiles as $logFile) {
            if (filemtime($logFile) < strtotime('-7 days')) {
                @unlink($logFile);
            }
        }

        // Limitar tamaño del log actual
        $currentLog = $logsDir . DIRECTORY_SEPARATOR . 'service.log';
        if (file_exists($currentLog) && filesize($currentLog) > 10 * 1024 * 1024) { // 10MB
            @unlink($currentLog);
        }

        if ($isWindows) {
            // CREAR ARCHIVO VBS DINÁMICAMENTE
            $vbsPath = $servicePath . '\\start-whatsapp-service.vbs';
            $vbsContent = 'Set objShell = CreateObject("WScript.Shell")' . "\r\n";            
            $vbsContent .= 'objShell.CurrentDirectory = "' . $servicePath . '"' . "\r\n";
            $vbsContent .= 'objShell.Run "cmd /c node src\index.js ' . $puerto . ' ' . getEmpresaActual() . ' > logs\empresa-' . getEmpresaActual() . '.log 2>&1", 0, False' . "\r\n";


            // Escribir el archivo VBS
            file_put_contents($vbsPath, $vbsContent);

            // Usar Task Scheduler
            $taskName = "MensajeroPro_WhatsApp_Empresa_" . getEmpresaActual();

            // Eliminar tarea si existe
            @exec('schtasks /delete /tn "' . $taskName . '" /f 2>&1');

            // Crear nueva tarea
            $createTask = 'schtasks /create /tn "' . $taskName . '" /tr "wscript.exe \"' . $vbsPath . '\"" /sc once /st 00:00 /f /rl highest';
            @exec($createTask, $output, $returnCode);

            if ($returnCode !== 0) {
                // Si falla, intentar sin privilegios elevados
                $createTask = 'schtasks /create /tn "' . $taskName . '" /tr "wscript.exe \"' . $vbsPath . '\"" /sc once /st 00:00 /f';
                @exec($createTask);
            }

            // Ejecutar la tarea
            @exec('schtasks /run /tn "' . $taskName . '"');
        } else {
            // Linux/Mac - usar PM2 si está instalado, sino nohup
            $pm2Check = shell_exec('which pm2');

            if ($pm2Check) {
                // Usar PM2
                chdir($servicePath);
                @exec('pm2 start src/index.js --name mensajeropro-whatsapp 2>&1');
            } else {
                // Usar nohup
                $cmd = "cd $servicePath && nohup node src/index.js > logs/service.log 2>&1 &";
                @exec($cmd);
            }
        }

        jsonResponse(true, 'Servicio iniciándose...');
    } elseif ($accion == 'detener') {
        // Actualizar estado
        $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET estado = 'deteniendo' WHERE empresa_id = ?");
        $stmt->execute([getEmpresaActual()]);

        if ($isWindows) {
            // Detener tarea programada
            $taskName = "MensajeroPro_WhatsApp";
            @exec('schtasks /end /tn "' . $taskName . '" 2>&1');
            @exec('schtasks /delete /tn "' . $taskName . '" /f 2>&1');

            // Matar procesos
            @exec('taskkill /F /IM node.exe 2>&1');

            // Eliminar archivo VBS
            $vbsPath = $servicePath . '\\start-whatsapp-service.vbs';
            if (file_exists($vbsPath)) {
                @unlink($vbsPath);
            }
        } else {
            // Linux/Mac
            $pm2Check = shell_exec('which pm2');

            if ($pm2Check) {
                @exec('pm2 stop mensajeropro-whatsapp 2>&1');
                @exec('pm2 delete mensajeropro-whatsapp 2>&1');
            } else {
                @exec("pkill -f 'node.*mensajeropro'");
            }
        }

        sleep(2);

        // Limpiar sesión
        $sessionPaths = [
            $servicePath . DIRECTORY_SEPARATOR . '.wwebjs_auth',
            $servicePath . DIRECTORY_SEPARATOR . 'tokens'
        ];

        foreach ($sessionPaths as $sessionPath) {
            if (file_exists($sessionPath)) {
                if ($isWindows) {
                    @exec("rmdir /s /q \"$sessionPath\" 2>&1");
                } else {
                    @exec("rm -rf \"$sessionPath\" 2>&1");
                }
            }
        }

        $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET estado = 'desconectado', qr_code = NULL, numero_conectado = NULL WHERE empresa_id = ?");
        $stmt->execute([getEmpresaActual()]);
        jsonResponse(true, 'Servicio detenido');
    } elseif ($accion == 'verificar') {
        $connection = @fsockopen("localhost", 3001);
        if (is_resource($connection)) {
            fclose($connection);
            jsonResponse(true, 'Servicio activo', ['running' => true]);
        } else {
            jsonResponse(true, 'Servicio inactivo', ['running' => false]);
        }
    }
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
