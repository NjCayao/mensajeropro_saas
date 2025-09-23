<?php
// Archivo de prueba para verificar el UPDATE
session_start();
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

try {
    // Primero verificar que existe el registro
    $check = $pdo->query("SELECT * FROM configuracion_bot WHERE id = 1")->fetch();
    
    if (!$check) {
        // Si no existe, crear uno
        $pdo->exec("INSERT INTO configuracion_bot (id) VALUES (1)");
    }
    
    // Intentar actualizar con valores de prueba
    $sql = "UPDATE configuracion_bot 
            SET system_prompt = :system_prompt,
                business_info = :business_info
            WHERE id = 1";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':system_prompt' => 'PRUEBA: Soy un asistente de prueba',
        ':business_info' => 'PRUEBA: Información de prueba del negocio'
    ]);
    
    // Verificar resultado
    $rowCount = $stmt->rowCount();
    
    // Leer lo que se guardó
    $verify = $pdo->query("SELECT system_prompt, business_info FROM configuracion_bot WHERE id = 1")->fetch();
    
    echo json_encode([
        'success' => true,
        'update_result' => $result,
        'rows_affected' => $rowCount,
        'data_saved' => $verify,
        'pdo_error' => $pdo->errorInfo(),
        'stmt_error' => $stmt->errorInfo()
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}