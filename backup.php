<?php
session_start();

// ── Solo accesible si estás logueado como administrador ──
$logueado = isset($_SESSION['user_id']);
$esAdmin  = $logueado && (($_SESSION['rol'] ?? '') === 'administrador');

if (!$logueado || !$esAdmin) {
    http_response_code(403);
    die('Acceso denegado.');
}

require __DIR__ . '/config.php'; // da $pdo

// ── Genera el .sql "a mano" con PHP, porque InfinityFree
//    (y la mayoría de hostings gratis) no permite ejecutar
//    el comando mysqldump desde PHP. ──────────────────────
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="backup_inem_' . date('Y-m-d_His') . '.sql"');

try {
    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    echo "-- Copia de seguridad — INEM José Félix\n";
    echo "-- Generada el " . date('Y-m-d H:i:s') . "\n";
    echo "-- Para restaurar: importa este archivo en phpMyAdmin.\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tablas as $tabla) {
        // ── Estructura de la tabla ──
        $create = $pdo->query("SHOW CREATE TABLE `$tabla`")->fetch(PDO::FETCH_ASSOC);
        echo "-- ────────────────────────────────────\n";
        echo "-- Estructura de la tabla `$tabla`\n";
        echo "-- ────────────────────────────────────\n";
        echo "DROP TABLE IF EXISTS `$tabla`;\n";
        echo $create['Create Table'] . ";\n\n";

        // ── Datos de la tabla ──
        $filas = $pdo->query("SELECT * FROM `$tabla`");
        $columnas = null;
        $huboFilas = false;

        foreach ($filas as $fila) {
            $huboFilas = true;
            if ($columnas === null) {
                $columnas = array_keys($fila);
            }
            $valores = array_map(function ($valor) use ($pdo) {
                return $valor === null ? 'NULL' : $pdo->quote($valor);
            }, array_values($fila));

            echo "INSERT INTO `$tabla` (`" . implode('`, `', $columnas) . "`) VALUES ("
                . implode(', ', $valores) . ");\n";
        }

        if ($huboFilas) {
            echo "\n";
        } else {
            echo "-- (sin datos)\n\n";
        }
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
} catch (PDOException $e) {
    // Si algo falla a mitad de la descarga, al menos dejar constancia en el archivo
    echo "\n-- ERROR generando el backup: " . $e->getMessage() . "\n";
}
