<?php
// ════════════════════════════════════════════════════════
//  CONFIGURACIÓN DE CONEXIÓN A LA BASE DE DATOS
//  Las credenciales viven en db_credentials.php (edítalas ahí,
//  es el único lugar que hay que tocar).
// ════════════════════════════════════════════════════════
require __DIR__ . '/db_credentials.php';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die("No se pudo conectar a la base de datos. Verifica config.php.");
}
