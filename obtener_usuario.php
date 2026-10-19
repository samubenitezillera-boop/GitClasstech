<?php
// ════════════════════════════════════════════════════════
//  DEVUELVE LOS DATOS DEL USUARIO ACTUALMENTE LOGUEADO
//  (según la sesión PHP iniciada por login.php).
//
//  Es consumido por index.html, horario.html y TABLA.html:
//      const API_SESION = "obtener_usuario.php";
//      const resp = await fetch(API_SESION);
//      const data = await resp.json();
//      if (data.autenticado) { ... data.usuario.nombre / .rol / .seccion ... }
//
//  La forma de la respuesta es intencionalmente igual a la que
//  ya devuelve login.php, para no romper el código JS existente.
// ════════════════════════════════════════════════════════
session_start();

header('Content-Type: application/json; charset=utf-8');

// ── No hay sesión activa: no está logueado ──────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['autenticado' => false]);
    exit;
}

// ── Hay sesión activa: intentamos traer los datos frescos desde
//    la base de datos (por si su rol/grado/sección cambiaron desde
//    que inició sesión, por ejemplo por el panel admin). Si la
//    base de datos no responde, usamos lo que ya hay en la sesión
//    como respaldo, para no dejar al usuario "deslogueado" por un
//    problema de conexión. ─────────────────────────────────────
$usuarioRespuesta = [
    'nombre'  => $_SESSION['nombre'] ?? '',
    'correo'  => $_SESSION['correo'] ?? '',
    'rol'     => $_SESSION['rol'] ?? 'docente',
    'grado'   => $_SESSION['grado'] ?? '',
    'seccion' => $_SESSION['seccion'] ?? '',
];

try {
    require __DIR__ . '/config.php'; // da $pdo

    $stmt = $pdo->prepare('SELECT nombre_completo, correo, rol, grado, seccion FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        $usuarioRespuesta = [
            'nombre'  => $usuario['nombre_completo'],
            'correo'  => $usuario['correo'],
            'rol'     => $usuario['rol'] ?? 'docente',
            'grado'   => $usuario['grado'] ?? '',
            'seccion' => $usuario['seccion'] ?? '',
        ];

        // Mantenemos la sesión sincronizada con la base de datos.
        $_SESSION['nombre'] = $usuarioRespuesta['nombre'];
        $_SESSION['correo'] = $usuarioRespuesta['correo'];
        $_SESSION['rol']    = $usuarioRespuesta['rol'];
    } else {
        // El usuario de la sesión ya no existe en la base de datos
        // (por ejemplo, fue eliminado desde el panel admin): cerramos
        // la sesión para que el frontend lo trate como deslogueado.
        $_SESSION = [];
        session_destroy();
        echo json_encode(['autenticado' => false]);
        exit;
    }
} catch (Throwable $e) {
    // Si la base de datos no está disponible, seguimos respondiendo
    // con lo que ya sabíamos por la sesión, en vez de romper el login.
}

echo json_encode([
    'autenticado' => true,
    'usuario'     => $usuarioRespuesta,
]);
exit;
