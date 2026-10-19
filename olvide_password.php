<?php
require __DIR__ . '/config.php';        // da $pdo
require __DIR__ . '/enviar_correo.php'; // da la función enviarCorreo()
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'error' => 'Método no permitido.']);
    exit;
}

$raw   = file_get_contents('php://input');
$datos = json_decode($raw, true) ?? [];
$correo = trim($datos['correo'] ?? '');

if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'error' => 'Ingresa un correo válido.']);
    exit;
}

// ── Mensaje genérico: no revelamos si el correo existe o no en la
//    base de datos (evita que alguien use este formulario para
//    averiguar qué correos están registrados). Se muestra siempre,
//    haya o no un usuario con ese correo. ─────────────────────────
$mensajeGenerico = 'Si ese correo está registrado, te enviamos un enlace para restablecer tu contraseña. Revisa tu bandeja de entrada (y la carpeta de spam).';

try {
    $stmt = $pdo->prepare('SELECT id, nombre_completo FROM usuarios WHERE correo = :correo LIMIT 1');
    $stmt->execute(['correo' => $correo]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        // ── Generar un token aleatorio de un solo uso. El token en
        //    texto plano solo viaja por correo; en la base de datos
        //    solo se guarda su hash SHA-256 (no reversible). ────────
        $token     = bin2hex(random_bytes(32));       // 64 caracteres hex
        $tokenHash = hash('sha256', $token);
        $expira    = date('Y-m-d H:i:s', time() + 1800); // vence en 30 min

        $upd = $pdo->prepare(
            'UPDATE usuarios SET reset_token_hash = :hash, reset_token_expira = :expira WHERE id = :id'
        );
        $upd->execute([
            'hash'   => $tokenHash,
            'expira' => $expira,
            'id'     => $usuario['id'],
        ]);

        // ── Armar el enlace. Ajusta el dominio si tu sitio vive en
        //    otra URL (ej: classtech.infinityfreeapp.com). ──────────
        $dominio = 'https://' . $_SERVER['HTTP_HOST'];
        $enlace  = $dominio . '/RestablecerPassword.html?token=' . urlencode($token);

        $asunto = 'Restablece tu contraseña - INEM José';
        $html   = "<p>Hola <strong>{$usuario['nombre_completo']}</strong>,</p>"
                . "<p>Recibimos una solicitud para restablecer tu contraseña.</p>"
                . "<p><a href=\"{$enlace}\" style=\"background:#1a4d3a;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;display:inline-block;\">Restablecer contraseña</a></p>"
                . "<p>O copia y pega este enlace en tu navegador:<br>{$enlace}</p>"
                . "<p>Este enlace vence en 30 minutos. Si tú no pediste esto, ignora este correo — tu contraseña actual sigue funcionando normalmente.</p>";

        enviarCorreo($correo, $usuario['nombre_completo'], $asunto, $html);
    }

    // Responde igual, exista o no el usuario.
    echo json_encode(['exito' => true, 'mensaje' => $mensajeGenerico]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'error' => 'No se pudo procesar la solicitud. Intenta de nuevo.']);
}
