<?php
echo "=== PRUEBA DE RUTAS ===\n\n";
echo "Directorio actual: " . __DIR__ . "\n\n";

// Probar diferentes niveles
$rutas = [
    '3 niveles' => __DIR__ . '/../../../config/database.php',
    '4 niveles' => __DIR__ . '/../../../../config/database.php',
];

foreach ($rutas as $nombre => $ruta) {
    echo "$nombre: $ruta\n";
    echo "Existe: " . (file_exists($ruta) ? "SÍ ✓" : "NO ✗") . "\n";
    echo "Ruta real: " . (file_exists($ruta) ? realpath($ruta) : "N/A") . "\n\n";
}

// Mostrar estructura
echo "Estructura esperada:\n";
echo "sistema/api/v1/whatssistema/ <- Estás aquí\n";
echo "config/database.php <- Necesitas llegar aquí\n";