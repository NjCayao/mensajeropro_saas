<?php
// Desactivar reporte de errores para evitar corrupción del JSON
error_reporting(0);
ini_set('display_errors', 0);

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
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$accion = $_POST['accion'] ?? '';
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$servicePath = dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'whatsapp-service';

try {
    if ($accion == 'iniciar') {
        // Obtener puerto asignado a esta empresa
        $puerto = obtenerPuertoEmpresa($pdo, $empresa_id);

        // Verificar si ya está corriendo en ese puerto
        if (verificarPuertoActivo($puerto)) {
            $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET estado = 'verificando' WHERE empresa_id = ?");
            $stmt->execute([$empresa_id]);
            echo json_encode(['success' => true, 'message' => 'El servicio ya está en ejecución']);
            exit;
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
        $stmt->execute([$empresa_id]);

        // Crear carpeta logs si no existe
        $logsDir = $servicePath . DIRECTORY_SEPARATOR . 'logs';
        if (!file_exists($logsDir)) {
            mkdir($logsDir, 0777, true);
        }

        // Limpiar logs antiguos (más de 7 días)
        $logFiles = glob($logsDir . DIRECTORY_SEPARATOR . '*.log');
        if (is_array($logFiles)) {
            foreach ($logFiles as $logFile) {
                if (file_exists($logFile) && filemtime($logFile) < strtotime('-7 days')) {
                    @unlink($logFile);
                }
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
            $vbsContent .= 'objShell.Run "cmd /c node src\index.js ' . $puerto . ' ' . $empresa_id . ' > logs\empresa-' . $empresa_id . '.log 2>&1", 0, False' . "\r\n";

            // Escribir el archivo VBS
            file_put_contents($vbsPath, $vbsContent);

            // Usar Task Scheduler
            $taskName = "MensajeroPro_WhatsApp_Empresa_" . $empresa_id;

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
                $processName = "mensajeropro-whatsapp-empresa-" . $empresa_id;
                @exec("pm2 start src/index.js --name $processName -- $puerto $empresa_id 2>&1");
            } else {
                // Usar nohup
                $cmd = "cd $servicePath && nohup node src/index.js $puerto $empresa_id > logs/empresa-$empresa_id.log 2>&1 &";
                @exec($cmd);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Servicio iniciándose...']);
        
    } elseif ($accion == 'detener') {
        // Obtener puerto de la empresa
        $stmt = $pdo->prepare("SELECT puerto FROM whatsapp_sesiones_empresa WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        $result = $stmt->fetch();
        $puerto = $result['puerto'] ?? 3001;

        // Actualizar estado
        $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET estado = 'deteniendo' WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);

        if ($isWindows) {
            // Detener tarea programada
            $taskName = "MensajeroPro_WhatsApp_Empresa_" . $empresa_id;
            @exec('schtasks /end /tn "' . $taskName . '" 2>&1');
            @exec('schtasks /delete /tn "' . $taskName . '" /f 2>&1');

            // Matar procesos node que estén usando ese puerto
            // En Windows es más complicado, por ahora matamos todos los node
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
                $processName = "mensajeropro-whatsapp-empresa-" . $empresa_id;
                @exec("pm2 stop $processName 2>&1");
                @exec("pm2 delete $processName 2>&1");
            } else {
                // Buscar proceso por puerto y matarlo
                @exec("lsof -t -i:$puerto | xargs kill -9 2>&1");
            }
        }

        sleep(2);

        // Limpiar sesión específica de la empresa
        $sessionPath = $servicePath . DIRECTORY_SEPARATOR . '.wwebjs_auth' . DIRECTORY_SEPARATOR . 'session-empresa-' . $empresa_id;
        if (file_exists($sessionPath)) {
            if ($isWindows) {
                @exec("rmdir /s /q \"$sessionPath\" 2>&1");
            } else {
                @exec("rm -rf \"$sessionPath\" 2>&1");
            }
        }

        $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET estado = 'desconectado', qr_code = NULL, numero_conectado = NULL WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        
        echo json_encode(['success' => true, 'message' => 'Servicio detenido']);
        
    } elseif ($accion == 'verificar') {
        // Obtener puerto de la empresa
        $stmt = $pdo->prepare("SELECT puerto FROM whatsapp_sesiones_empresa WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        $result = $stmt->fetch();
        $puerto = $result['puerto'] ?? 3001;

        $connection = @fsockopen("localhost", $puerto);
        if (is_resource($connection)) {
            fclose($connection);
            echo json_encode(['success' => true, 'message' => 'Servicio activo', 'running' => true]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Servicio inactivo', 'running' => false]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
exit;