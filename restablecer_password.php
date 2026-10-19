<?php
require __DIR__ . '/config.php'; // da $pdo
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'error' => 'Método no permitido.']);
    exit;
}

$raw   = file_get_contents('php://input');
$datos = json_decode($raw, true) ?? [];

$token    = trim($datos['token'] ?? '');
$password = (string) ($datos['password'] ?? '');

if ($token === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'Faltan datos.']);
    exit;
}

// ── Misma política de contraseñas que en el registro ──────────────
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres.']);
    exit;
}
if (!preg_match('/[A-Z]/', $password)) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'La contraseña debe incluir al menos una letra mayúscula.']);
    exit;
}
if (!preg_match('/[a-z]/', $password)) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'La contraseña debe incluir al menos una letra minúscula.']);
    exit;
}
if (!preg_match('/[0-9]/', $password)) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'La contraseña debe incluir al menos un dígito.']);
    exit;
}
if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'La contraseña debe incluir al menos un carácter especial (ej: ! @ # $ %).']);
    exit;
}

$tokenHash = hash('sha256', $token);

try {
    $stmt = $pdo->prepare(
        'SELECT id FROM usuarios WHERE reset_token_hash = :hash AND reset_token_expira > NOW() LIMIT 1'
    );
    $stmt->execute(['hash' => $tokenHash]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        http_response_code(400);
        echo json_encode([
            'exito' => false,
            'error' => 'Este enlace no es válido o ya venció. Pide uno nuevo desde "Olvidé mi contraseña".',
        ]);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Guarda la nueva contraseña y anula el token (un solo uso).
    $upd = $pdo->prepare(
        'UPDATE usuarios
         SET password_hash = :hash, reset_token_hash = NULL, reset_token_expira = NULL
         WHERE id = :id'
    );
    $upd->execute(['hash' => $hash, 'id' => $usuario['id']]);

    echo json_encode(['exito' => true, 'mensaje' => 'Contraseña actualizada. Ya puedes iniciar sesión.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'error' => 'No se pudo actualizar la contraseña. Intenta de nuevo.']);
}
