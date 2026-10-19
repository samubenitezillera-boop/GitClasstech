<?php
require __DIR__ . '/config.php';
require __DIR__ . '/totp_helper.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['exito' => false, 'error' => 'Debes iniciar sesión.']);
    exit;
}

if (empty($_SESSION['totp_secreto_temporal'])) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'No hay una activación en curso. Genera el código QR de nuevo.']);
    exit;
}

$raw    = file_get_contents('php://input');
$datos  = json_decode($raw, true) ?? [];
$codigo = trim((string)($datos['codigo'] ?? ''));

if ($codigo === '') {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'Ingresa el código de tu app autenticadora.']);
    exit;
}

$secreto = $_SESSION['totp_secreto_temporal'];

if (!TOTP::verificarCodigo($secreto, $codigo)) {
    http_response_code(401);
    echo json_encode(['exito' => false, 'error' => 'Código incorrecto. Verifica que escaneaste bien el QR y que la hora de tu celular está correcta.']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE usuarios SET totp_secret = :secreto, totp_habilitado = 1 WHERE id = :id');
    $stmt->execute(['secreto' => $secreto, 'id' => $_SESSION['user_id']]);

    unset($_SESSION['totp_secreto_temporal']);

    echo json_encode(['exito' => true, 'mensaje' => 'Verificación en dos pasos activada correctamente.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'error' => 'No se pudo activar. Intenta de nuevo.']);
}
