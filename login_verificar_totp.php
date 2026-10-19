<?php
// ════════════════════════════════════════════════════════
//  SEGUNDO PASO DEL LOGIN (cuando el usuario tiene 2FA activado).
//  login.php ya validó correo+contraseña y dejó una marca
//  "pendiente" en la sesión ($_SESSION['pending_2fa_user_id']).
//  Acá se verifica el código de 6 dígitos de la app autenticadora
//  y, si es correcto, se abre la sesión real.
// ════════════════════════════════════════════════════════
require __DIR__ . '/config.php';
require __DIR__ . '/totp_helper.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'error' => 'Método no permitido.']);
    exit;
}

if (empty($_SESSION['pending_2fa_user_id'])) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'No hay una verificación en curso. Inicia sesión de nuevo.']);
    exit;
}

// ── Límite de intentos: evita que alguien pruebe códigos al azar
//    indefinidamente contra una sesión pendiente. ───────────────
$_SESSION['pending_2fa_intentos'] = ($_SESSION['pending_2fa_intentos'] ?? 0) + 1;
if ($_SESSION['pending_2fa_intentos'] > 5) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_intentos']);
    http_response_code(429);
    echo json_encode(['exito' => false, 'error' => 'Demasiados intentos fallidos. Inicia sesión de nuevo.']);
    exit;
}

$raw    = file_get_contents('php://input');
$datos  = json_decode($raw, true) ?? [];
$codigo = trim((string)($datos['codigo'] ?? ''));

if ($codigo === '') {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'Ingresa el código de 6 dígitos.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $_SESSION['pending_2fa_user_id']]);
$usuario = $stmt->fetch();

if (!$usuario || empty($usuario['totp_habilitado']) || empty($usuario['totp_secret'])) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_intentos']);
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'No se pudo verificar. Inicia sesión de nuevo.']);
    exit;
}

if (!TOTP::verificarCodigo($usuario['totp_secret'], $codigo)) {
    http_response_code(401);
    echo json_encode(['exito' => false, 'error' => 'Código incorrecto. Verifica la hora de tu celular e inténtalo de nuevo.']);
    exit;
}

// ── Código correcto: recién ahora se abre la sesión completa ───────
unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_intentos']);
session_regenerate_id(true);
$_SESSION['user_id'] = $usuario['id'];
$_SESSION['nombre']  = $usuario['nombre_completo'];
$_SESSION['correo']  = $usuario['correo'];
$_SESSION['rol']     = $usuario['rol'] ?? 'docente';

echo json_encode([
    'exito'   => true,
    'usuario' => [
        'nombre'  => $usuario['nombre_completo'],
        'correo'  => $usuario['correo'],
        'rol'     => $usuario['rol'] ?? 'docente',
        'grado'   => $usuario['grado'] ?? '',
        'seccion' => $usuario['seccion'] ?? '',
    ],
]);
exit;
