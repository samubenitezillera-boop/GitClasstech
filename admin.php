<?php
session_start();
require __DIR__ . '/config.php'; // da $pdo

// ── Protección: hay que estar logueado Y ser administrador ──
$logueado = isset($_SESSION['user_id']);
$esAdmin  = $logueado && (($_SESSION['rol'] ?? '') === 'administrador');

if (!$logueado) {
    header('Location: InicioSesion.html?error=' . urlencode('Debes iniciar sesión primero.'));
    exit;
}
if (!$esAdmin) {
    http_response_code(403);
    die('Acceso denegado: esta sección es solo para administradores.');
}

// ── Token CSRF: protege los links de "Eliminar" contra ataques
//    donde alguien te hace clic en un link malicioso estando logueado. ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ── Traer estadísticas y ocupación por grado ──────────────
$stats = [];
$porGrado = [];
$usuariosEncontrados = [];
$busqueda = trim($_GET['buscar'] ?? '');
$errorConexion = '';
$errorAccion = $_GET['error'] ?? '';
try {
    $stats['usuarios'] = (int) $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $stats['reservas'] = (int) $pdo->query("SELECT COUNT(*) FROM reservas WHERE fecha >= CURDATE()")->fetchColumn();

    // ── Búsqueda de usuarios (por nombre o correo) ──
    // Nota: se usan dos parámetros distintos (:q1 y :q2) porque con
    // PDO::ATTR_EMULATE_PREPARES => false, MySQL no permite reutilizar
    // el mismo parámetro con nombre dos veces en la misma consulta
    // (causaba el error "SQLSTATE[HY093]: Invalid parameter number").
    if ($busqueda !== '') {
        $stmtU = $pdo->prepare(
            "SELECT id, nombre_completo, correo, rol, grado, seccion, creado_en
             FROM usuarios
             WHERE nombre_completo LIKE :q1 OR correo LIKE :q2
             ORDER BY nombre_completo"
        );
        $like = '%' . $busqueda . '%';
        $stmtU->execute(['q1' => $like, 'q2' => $like]);
    } else {
        $stmtU = $pdo->query(
            "SELECT id, nombre_completo, correo, rol, grado, seccion, creado_en
             FROM usuarios
             ORDER BY nombre_completo"
        );
    }
    $usuariosEncontrados = $stmtU->fetchAll();

    $stmt = $pdo->query(
        "SELECT id, grado, seccion, salon, bloque, fecha, periodo, hora, materia, responsable
         FROM reservas
         WHERE fecha >= CURDATE()
         ORDER BY (grado = '' OR grado IS NULL), grado, fecha, periodo"
    );
    foreach ($stmt as $fila) {
        $g = $fila['grado'] !== '' && $fila['grado'] !== null ? $fila['grado'] : 'Sin grado especificado';
        $porGrado[$g][] = $fila;
    }
} catch (PDOException $e) {
    $errorConexion = 'No se pudo consultar la base de datos: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Panel Administrativo — INEM José Félix</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --pine: #1a4d3a; --pine-dark: #0f3327; --leaf: #3f9468; --leaf-soft: #cfe6d9;
      --amber: #d9922f; --amber-soft: #f4dfb8; --paper: #f6f3ec; --panel: #fffdf8;
      --ink: #23261f; --ink-soft: #5c6157; --line: #ddd6c4; --red: #b3402c;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: var(--paper); color: var(--ink); min-height: 100vh; }
    h2, h3 { font-family: 'Fraunces', serif; }

    .header {
      background: var(--pine-dark); color: white; padding: 16px 24px;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 3px solid var(--amber);
      flex-wrap: wrap; gap: 8px 16px;
    }
    .header h1 { font-family: 'Fraunces', serif; font-size: 19px; font-weight: 600; }
    .header .user-info { font-size: 12.5px; color: var(--leaf-soft); }
    .header a { color: white; text-decoration: none; font-size: 13px; border-bottom: 1px solid transparent; }
    .header a:hover { border-color: var(--amber); }
    @media (max-width: 520px) {
      .header { padding: 14px 16px; }
      .header h1 { font-size: 16px; }
    }

    .wrap { max-width: 760px; margin: 0 auto; padding: 40px 20px; }

    .error-msg { background: #f1cdc4; color: #8f3222; border: 1px solid var(--red);
      border-radius: 8px; padding: 10px 14px; font-size: 13.5px; margin-bottom: 16px; text-align: center; }

    .cards { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 24px 0; }
    .stat-card { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 20px; text-align: center; }
    .stat-card .num { font-family: 'Fraunces', serif; font-size: 32px; color: var(--pine-dark); font-weight: 600; }
    .stat-card .label { font-size: 13px; color: var(--ink-soft); margin-top: 4px; }

    .action-card {
      background: var(--panel); border: 1px solid var(--line); border-left: 4px solid var(--leaf);
      border-radius: 10px; padding: 20px 24px; margin-bottom: 16px;
    }
    .action-card h3 { font-size: 17px; color: var(--pine-dark); margin-bottom: 6px; }
    .action-card p { font-size: 13.5px; color: var(--ink-soft); margin-bottom: 14px; }
    .btn {
      display: inline-block; background: var(--pine); color: white; border: none;
      border-radius: 7px; padding: 10px 22px; font-size: 14px; font-weight: 600;
      font-family: 'Inter', sans-serif; cursor: pointer; text-align: center; text-decoration: none;
    }
    .btn:hover { background: var(--pine-dark); }

    .grado-block { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; margin-bottom: 18px; overflow: hidden; }
    .grado-header { background: var(--leaf-soft); color: var(--pine-dark); font-weight: 700; padding: 10px 18px; font-size: 15px; font-family: 'Fraunces', serif; }
    /* ── Las tablas no se achican ni se cortan en mobile: se deslizan
         horizontalmente dentro de su propio contenedor. ── */
    .tabla-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table.ocupacion { width: 100%; min-width: 560px; border-collapse: collapse; font-size: 13.5px; }
    table.ocupacion th, table.ocupacion td { text-align: left; padding: 9px 14px; border-bottom: 1px solid var(--line); white-space: nowrap; }
    table.ocupacion th { color: var(--ink-soft); font-weight: 600; font-size: 12px; text-transform: uppercase; }
    table.ocupacion tr:last-child td { border-bottom: none; }
    .badge-salon { display: inline-block; background: var(--amber-soft); color: var(--pine-dark); border-radius: 5px; padding: 2px 8px; font-weight: 600; font-size: 12.5px; }
    .sin-reservas { padding: 16px; color: var(--ink-soft); font-size: 13.5px; text-align: center; }
    .btn-borrar {
      display: inline-block; background: #f1cdc4; color: #8f3222; border: 1px solid var(--red);
      border-radius: 6px; padding: 5px 12px; font-size: 12.5px; font-weight: 600;
      text-decoration: none; cursor: pointer;
    }
    .btn-borrar:hover { background: var(--red); color: white; }

    .search-box {
      display: flex; gap: 10px; margin-bottom: 16px;
    }
    .search-box input {
      flex: 1; border: 1px solid var(--line); background: var(--paper); border-radius: 7px;
      font-size: 14px; padding: 10px 12px; color: var(--ink); font-family: 'Inter', sans-serif;
    }
    .search-box input:focus { border-color: var(--leaf); outline: none; background: white; }
    .search-box button {
      background: var(--pine); color: white; border: none; border-radius: 7px;
      padding: 0 20px; font-size: 14px; font-weight: 600; cursor: pointer;
    }
    .search-box button:hover { background: var(--pine-dark); }
    .badge-rol {
      display: inline-block; border-radius: 5px; padding: 2px 10px; font-weight: 600; font-size: 12px;
    }
    .badge-rol.administrador { background: #f4dfb8; color: #8a5a10; }
    .badge-rol.docente { background: var(--leaf-soft); color: var(--pine-dark); }
    .badge-rol.estudiante { background: #dbe7f5; color: #1e4e7a; }
    .btn-borrar.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }

    /* ── Mobile: apilar tarjetas de estadísticas y buscador ── */
    @media (max-width: 520px) {
      .wrap { padding: 24px 14px; }
      .cards { grid-template-columns: 1fr; }
      .search-box { flex-direction: column; }
      .search-box button { padding: 10px 20px; }
    }
  </style>
</head>
<body>

<div class="header">
  <div>
    <h1>Panel Administrativo — INEM José Félix</h1>
    <div class="user-info"><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></div>
  </div>
  <div style="display:flex; align-items:center; gap:18px;">
    <a href="index.html">← Volver al Mapa</a>
    <a href="logout.php">Cerrar sesión</a>
  </div>
</div>

<div class="wrap">
  <?php if ($errorAccion): ?>
    <div class="error-msg"><?= htmlspecialchars($errorAccion) ?></div>
  <?php endif; ?>

  <?php if ($errorConexion): ?>
    <div class="error-msg"><?= htmlspecialchars($errorConexion) ?></div>
  <?php else: ?>
    <div class="cards">
      <div class="stat-card">
        <div class="num"><?= $stats['usuarios'] ?></div>
        <div class="label">Docentes registrados</div>
      </div>
      <div class="stat-card">
        <div class="num"><?= $stats['reservas'] ?></div>
        <div class="label">Reservas activas</div>
      </div>
    </div>
  <?php endif; ?>

  <div class="action-card">
    <h3>📦 Copia de seguridad</h3>
    <p>Descarga un archivo .sql con toda la estructura y los datos actuales de la base de datos (usuarios y reservas).</p>
    <a href="backup.php" class="btn">Descargar copia de seguridad</a>
  </div>

  <h3 style="color: var(--pine-dark); margin: 28px 0 14px;">👥 Usuarios</h3>

  <form method="GET" class="search-box">
    <input type="text" name="buscar" placeholder="Buscar por nombre o correo..."
           value="<?= htmlspecialchars($busqueda) ?>">
    <button type="submit">Buscar</button>
  </form>

  <div class="grado-block">
    <?php if (empty($usuariosEncontrados)): ?>
      <div class="sin-reservas">No se encontraron usuarios<?= $busqueda !== '' ? ' para "' . htmlspecialchars($busqueda) . '"' : '' ?>.</div>
    <?php else: ?>
      <div class="tabla-scroll">
      <table class="ocupacion">
        <thead>
          <tr>
            <th>Nombre</th><th>Correo</th><th>Rol</th><th>Grado/Grupo</th><th>Registrado</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuariosEncontrados as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['nombre_completo']) ?></td>
              <td><?= htmlspecialchars($u['correo']) ?></td>
              <td><span class="badge-rol <?= htmlspecialchars($u['rol']) ?>"><?= htmlspecialchars($u['rol']) ?></span></td>
              <td><?= ($u['rol'] === 'estudiante') ? htmlspecialchars(($u['grado'] ?: '—') . ' - ' . ($u['seccion'] ?: '—')) : '—' ?></td>
              <td><?= htmlspecialchars($u['creado_en']) ?></td>
              <td>
                <?php $esUnoMismo = (int)$u['id'] === (int)$_SESSION['user_id']; ?>
                <a href="admin_borrar_usuario.php?id=<?= urlencode($u['id']) ?>&csrf=<?= urlencode($csrf) ?>"
                   class="btn-borrar <?= $esUnoMismo ? 'disabled' : '' ?>"
                   onclick="return confirm('¿Eliminar a <?= htmlspecialchars(addslashes($u['nombre_completo'])) ?>? Esta acción no se puede deshacer.');"
                   title="<?= $esUnoMismo ? 'No podés eliminar tu propia cuenta' : '' ?>">
                  🗑️ Eliminar
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <h3 style="color: var(--pine-dark); margin: 28px 0 14px;">🏫 Salones ocupados por grado (desde hoy)</h3>

  <?php if (empty($porGrado)): ?>
    <div class="grado-block">
      <div class="sin-reservas">No hay reservas activas desde hoy en adelante.</div>
    </div>
  <?php else: ?>
    <?php foreach ($porGrado as $grado => $reservasGrado): ?>
      <div class="grado-block">
        <div class="grado-header"><?= htmlspecialchars($grado) ?></div>
        <div class="tabla-scroll">
        <table class="ocupacion">
          <thead>
            <tr>
              <th>Salón</th><th>Sección</th><th>Fecha</th><th>Período</th>
              <th>Hora</th><th>Materia</th><th>Docente</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reservasGrado as $r): ?>
              <tr>
                <td><span class="badge-salon"><?= htmlspecialchars($r['salon']) ?></span></td>
                <td><?= htmlspecialchars($r['seccion'] ?: '—') ?></td>
                <td><?= htmlspecialchars($r['fecha']) ?></td>
                <td><?= htmlspecialchars($r['periodo']) ?></td>
                <td><?= htmlspecialchars($r['hora'] ?: '—') ?></td>
                <td><?= htmlspecialchars($r['materia']) ?></td>
                <td><?= htmlspecialchars($r['responsable']) ?></td>
                <td>
                  <a href="admin_borrar_reserva.php?id=<?= urlencode($r['id']) ?>&csrf=<?= urlencode($csrf) ?>"
                     class="btn-borrar"
                     onclick="return confirm('¿Eliminar esta reserva? Esta acción no se puede deshacer.');">
                    🗑️ Eliminar
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>


</body>
</html>
