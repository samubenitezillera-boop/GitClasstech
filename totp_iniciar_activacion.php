<?php
session_start();
require __DIR__ . '/totp_helper.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['exito' => false, 'error' => 'Debes iniciar sesión.']);
    exit;
}

// Genera un secreto nuevo y lo guarda TEMPORALMENTE en la sesión.
// Todavía no se toca la base de datos: eso pasa recién cuando el
// usuario confirma con un código válido (totp_confirmar_activacion.php).
// Así, si cierra la pestaña a mitad de camino, no queda con el 2FA
// "a medias" ni se pisa un secreto anterior sin querer.
$secreto = TOTP::generarSecreto();
$_SESSION['totp_secreto_temporal'] = $secreto;

$correo = $_SESSION['correo'] ?? 'usuario';
$uri = TOTP::generarURI($secreto, $correo);

echo json_encode([
    'exito'   => true,
    'secreto' => $secreto,
    'uri'     => $uri,
]);
