<?php
session_start();
require __DIR__ . '/config.php'; // da $pdo

// ── Solo accesible si estás logueado como administrador ──
$logueado = isset($_SESSION['user_id']);
$esAdmin  = $logueado && (($_SESSION['rol'] ?? '') === 'administrador');

if (!$logueado || !$esAdmin) {
    http_response_code(403);
    die('Acceso denegado.');
}

// ── Verificación del token CSRF (evita que un link externo borre datos
//    aprovechando que el admin tiene sesión activa) ──
$csrf = $_GET['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    die('Token de seguridad inválido o vencido. Vuelve al panel administrativo e inténtalo de nuevo.');
}

$id = $_GET['id'] ?? null;

// ── Protección: nunca te podés borrar a vos mismo desde acá ──
if ($id && (int)$id === (int)$_SESSION['user_id']) {
    header('Location: admin.php?error=' . urlencode('No podés eliminar tu propia cuenta desde el panel.'));
    exit;
}

if ($id) {
    try {
        $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        // Si falla, simplemente no se borra
    }
}

header('Location: admin.php');
exit;
