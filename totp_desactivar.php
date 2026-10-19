<?php
require __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['exito' => false, 'error' => 'Debes iniciar sesión.']);
    exit;
}

$raw      = file_get_contents('php://input');
$datos    = json_decode($raw, true) ?? [];
$password = (string)($datos['password'] ?? '');

if ($password === '') {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'Ingresa tu contraseña para confirmar.']);
    exit;
}

$stmt = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $_SESSION['user_id']]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
    http_response_code(401);
    echo json_encode(['exito' => false, 'error' => 'Contraseña incorrecta.']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE usuarios SET totp_secret = NULL, totp_habilitado = 0 WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);

    echo json_encode(['exito' => true, 'mensaje' => 'Verificación en dos pasos desactivada.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'error' => 'No se pudo desactivar. Intenta de nuevo.']);
}
