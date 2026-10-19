<?php
require __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'error' => 'Método no permitido.']);
    exit;
}

// ── Leer el cuerpo como JSON (el formulario lo envía así, no como form-urlencoded) ──
$raw    = file_get_contents('php://input');
$datos  = json_decode($raw, true) ?? [];

$correo   = trim($datos['correo'] ?? '');
$password = $datos['password'] ?? '';
$codigo   = trim($datos['codigo'] ?? '');

$errorGenerico = 'Correo, contraseña o código incorrectos.';

if ($correo === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => $errorGenerico]);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE correo = :correo LIMIT 1');
$stmt->execute([':correo' => $correo]);
$usuario = $stmt->fetch();

// ── Validar credenciales ─────────────────────────────────
// Nota: el código de acceso solo se valida si el usuario lo envió;
// si tu flujo de registro no siempre lo pide, podés quitar este bloque.
$codigoValido = true;
if ($codigo !== '') {
    $codigoValido = $usuario && hash_equals((string)$usuario['codigo_acceso'], $codigo);
}

if (
    !$usuario
    || !password_verify($password, $usuario['password_hash'])
    || !$codigoValido
) {
    http_response_code(401);
    echo json_encode(['exito' => false, 'error' => $errorGenerico]);
    exit;
}

// ── Si el usuario activó la verificación en dos pasos, NO abrimos
//    sesión todavía. Dejamos solo una marca "pendiente de 2FA" y le
//    pedimos el código de 6 dígitos de su app autenticadora antes de
//    darle acceso real (eso lo resuelve login_verificar_totp.php). ──
if (!empty($usuario['totp_habilitado'])) {
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['pending_2fa_user_id']  = $usuario['id'];
    $_SESSION['pending_2fa_intentos'] = 0;

    echo json_encode([
        'exito'        => true,
        'requiere_2fa' => true,
    ]);
    exit;
}

// ── Credenciales correctas y sin 2FA: iniciar sesión directamente ──
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
        // Solo tienen sentido para rol = 'estudiante'; para docentes vienen vacíos.
        'grado'   => $usuario['grado'] ?? '',
        'seccion' => $usuario['seccion'] ?? '',
    ],
]);
exit;
