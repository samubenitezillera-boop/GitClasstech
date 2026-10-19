<?php
require __DIR__ . '/config.php'; // da $pdo (misma conexión que el resto del sitio)
require __DIR__ . '/enviar_correo.php'; // da la función enviarCorreo()
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'error' => 'Método no permitido.']);
    exit;
}

// El formulario envía JSON en el body (igual que login.php)
$raw   = file_get_contents('php://input');
$datos = json_decode($raw, true) ?? [];

$nombre   = trim($datos['nombre_completo'] ?? '');
$correo   = trim($datos['correo'] ?? '');
$password = (string) ($datos['password'] ?? '');
$rolInput = trim($datos['rol'] ?? 'docente');
$grado    = trim($datos['grado'] ?? '');
$seccion  = trim($datos['seccion'] ?? '');

// ── Solo se permiten estos dos roles desde el formulario público.
//    'administrador' NUNCA se asigna acá; se promueve a mano en la
//    base de datos (ver alter_usuarios_rol.sql). ──────────────────
$rol = in_array($rolInput, ['docente', 'estudiante'], true) ? $rolInput : 'docente';

// ── Validaciones básicas ──────────────────────────────────
if ($nombre === '' || $correo === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'Por favor completa todos los campos obligatorios.']);
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'El correo ingresado no es válido.']);
    exit;
}

// ── Solo se permite crear cuentas con el correo institucional ──────
if (!preg_match('/@inemjose\.edu\.co$/i', $correo)) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'Debes registrarte con tu correo institucional (@inemjose.edu.co).']);
    exit;
}

// ── La contraseña debe tener al menos 8 caracteres, una mayúscula,
//    una minúscula, un dígito y un carácter especial. ──────────────
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

// ── Grado y sección solo aplican (y son obligatorios) para estudiantes ──
if ($rol === 'estudiante') {
    if ($grado === '' || $seccion === '') {
        http_response_code(400);
        echo json_encode(['exito' => false, 'error' => 'Selecciona tu grado y grupo.']);
        exit;
    }
} else {
    // Un docente no guarda grado/seccion, aunque el cliente los mande.
    $grado   = '';
    $seccion = '';
}

$codigo_acceso = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
$password_hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nombre_completo, correo, rol, grado, seccion, password_hash, codigo_acceso)
         VALUES (:nombre, :correo, :rol, :grado, :seccion, :hash, :codigo)"
    );
    $stmt->execute([
        'nombre'  => $nombre,
        'correo'  => $correo,
        'rol'     => $rol,
        'grado'   => $grado,
        'seccion' => $seccion,
        'hash'    => $password_hash,
        'codigo'  => $codigo_acceso,
    ]);

    // ── Enviar el código de acceso por correo ──────────────────
    // Docente  -> el código llega al correo del administrador.
    // Estudiante -> el código llega al correo del propio estudiante.
    global $CORREO_ADMIN;
    $asunto = 'Tu código de acceso - Horarios INEM José';
    $html   = "<p>Hola <strong>{$nombre}</strong>,</p>"
            . "<p>Tu cuenta ({$rol}) fue creada correctamente.</p>"
            . "<p>Tu código de acceso es: <strong style=\"font-size:20px\">{$codigo_acceso}</strong></p>"
            . "<p>Correo registrado: {$correo}</p>";

    if ($rol === 'docente') {
        // Aviso al administrador con el código del nuevo docente.
        enviarCorreo($CORREO_ADMIN, 'Administrador', 'Nuevo docente registrado: ' . $nombre, $html);
    } else {
        // Código directo al correo del estudiante.
        enviarCorreo($correo, $nombre, $asunto, $html);
    }

    echo json_encode([
        'exito'         => true,
        'mensaje'       => 'Registro exitoso. Ya puedes iniciar sesión.',
        'codigo_acceso' => $codigo_acceso,
    ]);
} catch (PDOException $e) {
    // Código 23000 = violación de UNIQUE (correo ya registrado)
    if ((int) $e->getCode() === 23000) {
        http_response_code(409);
        echo json_encode(['exito' => false, 'error' => 'El correo electrónico ya se encuentra registrado.']);
    } else {
        http_response_code(500);
        echo json_encode(['exito' => false, 'error' => 'No se pudo completar el registro. Intenta de nuevo.']);
    }
}
