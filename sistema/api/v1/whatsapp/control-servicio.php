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
// LOG CRÍTICO
error_log("=== CONTROL SERVICIO ===");
error_log("Acción: " . $accion);
error_log("Empresa: " . $empresa_id);

$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$servicePath = dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'whatsapp-service';

try {
    if ($accion == 'iniciar') {
        // Obtener puerto asignado a esta empresa
        $puerto = obtenerPuertoEmpresa($pdo, $empresa_id);

        if ($isWindows) {
            // Buscar procesos que usen este puerto
            $output = shell_exec("netstat -ano | findstr :$puerto");
            if ($output) {
                preg_match_all('/\s+(\d+)\s*$/m', $output, $matches);
                if (!empty($matches[1])) {
                    $pids = array_unique($matches[1]);
                    foreach ($pids as $pid) {
                        @exec("taskkill /PID $pid /F 2>&1");
                    }
                    sleep(1);
                }
            }
        } else {
            @exec("lsof -t -i:$puerto | xargs kill -9 2>&1");
            sleep(1);
        }

        // Verificar si ya está corriendo
        if (verificarPuertoActivo($puerto)) {
            $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET estado = 'verificando' WHERE empresa_id = ?");
            $stmt->execute([$empresa_id]);
            echo json_encode(['success' => true, 'message' => 'El servicio ya está en ejecución']);
            exit;
        }

        // Limpiar sesión anterior
        // Limpiar sesión anterior usando script con privilegios
        error_log("Limpiando tokens de empresa " . $empresa_id);
        $output = [];
        exec("sudo /var/www/mensajeropro/whatsapp-service/clean-tokens.sh $empresa_id 2>&1", $output);
        error_log("Clean tokens output: " . print_r($output, true));

        // Actualizar estado
        $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET estado = 'iniciando', qr_code = NULL, numero_conectado = NULL WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);

        // Crear carpeta logs
        $logsDir = $servicePath . DIRECTORY_SEPARATOR . 'logs';
        if (!file_exists($logsDir)) {
            mkdir($logsDir, 0777, true);
        }

        if ($isWindows) {
            // Windows con Task Scheduler
            $vbsPath = $servicePath . '\\start-whatsapp-service.vbs';
            $vbsContent = 'Set objShell = CreateObject("WScript.Shell")' . "\r\n";
            $vbsContent .= 'objShell.CurrentDirectory = "' . $servicePath . '"' . "\r\n";
            $nodeEnv = IS_LOCALHOST ? 'development' : 'production';
            $vbsContent .= 'objShell.Run "cmd /c set NODE_ENV=' . $nodeEnv . ' && node src\index.js ' . $puerto . ' ' . $empresa_id . ' > logs\empresa-' . $empresa_id . '.log 2>&1", 0, False' . "\r\n";
            file_put_contents($vbsPath, $vbsContent);

            $taskName = "MensajeroPro_WhatsApp_Empresa_" . $empresa_id;
            @exec('schtasks /delete /tn "' . $taskName . '" /f 2>&1');
            $createTask = 'schtasks /create /tn "' . $taskName . '" /tr "wscript.exe \"' . $vbsPath . '\"" /sc once /st 00:00 /f /rl highest';
            @exec($createTask, $output, $returnCode);
            if ($returnCode !== 0) {
                $createTask = 'schtasks /create /tn "' . $taskName . '" /tr "wscript.exe \"' . $vbsPath . '\"" /sc once /st 00:00 /f';
                @exec($createTask);
            }
            @exec('schtasks /run /tn "' . $taskName . '"');
        } else {
            // Linux/Mac con PM2
            $pm2Check = shell_exec('which pm2');

            if ($pm2Check) {
                chdir($servicePath);
                $processName = "mensajeropro-whatsapp-empresa-" . $empresa_id;
                $nodeEnv = IS_LOCALHOST ? 'development' : 'production';
                $cmd = "sudo /var/www/mensajeropro/whatsapp-service/start-pm2.sh $nodeEnv $processName $puerto $empresa_id 2>&1";
                exec($cmd, $output, $returnCode);
            } else {
                // Nohup como fallback
                $nodeEnv = IS_LOCALHOST ? 'development' : 'production';
                $cmd = "cd $servicePath && NODE_ENV=$nodeEnv nohup node src/index.js $puerto $empresa_id > logs/empresa-$empresa_id.log 2>&1 &";
                @exec($cmd);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Servicio iniciándose...']);
    } elseif ($accion == 'detener') {
        error_log("=== INICIANDO DETENCIÓN ===");
        // Obtener puerto
        $stmt = $pdo->prepare("SELECT puerto FROM whatsapp_sesiones_empresa WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        $result = $stmt->fetch();
        $puerto = $result['puerto'] ?? 3001;

        error_log("Puerto obtenido: " . $puerto);

        // Actualizar estado
        $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET estado = 'deteniendo' WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);

        error_log("Estado actualizado a deteniendo");

        if ($isWindows) {
            $taskName = "MensajeroPro_WhatsApp_Empresa_" . $empresa_id;
            @exec('schtasks /end /tn "' . $taskName . '" 2>&1');
            @exec('schtasks /delete /tn "' . $taskName . '" /f 2>&1');
            @exec('taskkill /F /IM node.exe 2>&1');

            $vbsPath = $servicePath . '\\start-whatsapp-service.vbs';
            if (file_exists($vbsPath)) {
                @unlink($vbsPath);
            }
        } else {
            error_log("Sistema Linux detectado");
            $pm2Check = shell_exec('which pm2');
            error_log("PM2 check: " . ($pm2Check ? "encontrado" : "no encontrado"));

            if ($pm2Check) {
                $processName = "mensajeropro-whatsapp-empresa-" . $empresa_id;
                error_log("Deteniendo proceso: " . $processName);

                $output = [];
                exec("sudo /var/www/mensajeropro/whatsapp-service/stop-pm2.sh $processName 2>&1", $output);
                error_log("Stop script output: " . print_r($output, true));
            }
        }

        error_log("Esperando 2 segundos...");

        sleep(2);
        error_log("Limpiando sesión...");

        // Limpiar sesión
        $sessionEmpresa = $servicePath . DIRECTORY_SEPARATOR . 'tokens' . DIRECTORY_SEPARATOR . 'empresa-' . $empresa_id;
        if (file_exists($sessionEmpresa)) {
            if ($isWindows) {
                @exec("rmdir /s /q \"$sessionEmpresa\" 2>&1");
            } else {
                @exec("rm -rf \"$sessionEmpresa\" 2>&1");
            }
        }

        // ACTUALIZAR A DESCONECTADO
        $stmt = $pdo->prepare("UPDATE whatsapp_sesiones_empresa SET estado = 'desconectado', qr_code = NULL, numero_conectado = NULL WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);

        error_log("Estado actualizado a desconectado");

        echo json_encode(['success' => true, 'message' => 'Servicio detenido']);
    } elseif ($accion == 'verificar') {
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
