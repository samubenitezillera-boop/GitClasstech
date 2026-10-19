<?php
session_start();
require __DIR__ . '/config.php'; // da $pdo

if (!isset($_SESSION['user_id'])) {
    header('Location: InicioSesion.html?error=' . urlencode('Debes iniciar sesión primero.'));
    exit;
}

$usuario = null;
$errorConexion = '';
try {
    $stmt = $pdo->prepare('SELECT nombre_completo, correo, rol, totp_habilitado FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    $errorConexion = 'No se pudo consultar la base de datos: ' . $e->getMessage();
}

$totpActivo = $usuario && !empty($usuario['totp_habilitado']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Mi Perfil — INEM José Félix</title>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    :root {
      --pine: #1a4d3a; --pine-dark: #0f3327; --leaf: #3f9468; --leaf-soft: #cfe6d9;
      --amber: #d9922f; --amber-soft: #f4dfb8; --paper: #f6f3ec; --panel: #fffdf8;
      --ink: #23261f; --ink-soft: #5c6157; --line: #ddd6c4; --red: #b3402c; --red-soft: #f1cdc4;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: var(--paper); color: var(--ink); min-height: 100vh; }
    h2, h3 { font-family: 'Fraunces', serif; }

    .header {
      background: var(--pine-dark); color: white; padding: 16px 24px;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 3px solid var(--amber); flex-wrap: wrap; gap: 8px 16px;
    }
    .header h1 { font-family: 'Fraunces', serif; font-size: 19px; font-weight: 600; }
    .header a { color: white; text-decoration: none; font-size: 13px; }
    .header a:hover { color: var(--amber); }

    .wrap { max-width: 620px; margin: 0 auto; padding: 40px 20px; }

    .error-msg { background: var(--red-soft); color: #8f3222; border: 1px solid var(--red);
      border-radius: 8px; padding: 10px 14px; font-size: 13.5px; margin-bottom: 16px; text-align: center; }

    .profile-card {
      background: var(--panel); border: 1px solid var(--line); border-radius: 12px;
      padding: 22px 24px; margin-bottom: 20px;
    }
    .profile-card h2 { font-size: 20px; color: var(--pine-dark); margin-bottom: 4px; }
    .profile-card .correo { color: var(--ink-soft); font-size: 13.5px; margin-bottom: 10px; }
    .badge-rol {
      display: inline-block; border-radius: 5px; padding: 3px 10px; font-weight: 600; font-size: 12px;
    }
    .badge-rol.administrador { background: var(--amber-soft); color: #8a5a10; }
    .badge-rol.docente { background: var(--leaf-soft); color: var(--pine-dark); }
    .badge-rol.estudiante { background: #dbe7f5; color: #1e4e7a; }

    .action-card {
      background: var(--panel); border: 1px solid var(--line); border-left: 4px solid var(--leaf);
      border-radius: 10px; padding: 20px 24px; margin-bottom: 16px;
    }
    .action-card h3 { font-size: 16.5px; color: var(--pine-dark); margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
    .action-card p { font-size: 13.5px; color: var(--ink-soft); margin-bottom: 14px; line-height: 1.5; }

    .estado-2fa { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: 13px; border-radius: 20px; padding: 4px 12px; margin-bottom: 14px; }
    .estado-2fa.on { background: var(--leaf-soft); color: var(--pine-dark); }
    .estado-2fa.off { background: #eee; color: var(--ink-soft); }

    .btn {
      display: inline-block; border: none; border-radius: 7px; padding: 10px 20px;
      font-size: 14px; font-weight: 600; font-family: 'Inter', sans-serif;
      cursor: pointer; text-align: center; text-decoration: none;
    }
    .btn-primary { background: var(--pine); color: white; }
    .btn-primary:hover { background: var(--pine-dark); }
    .btn-danger { background: var(--red-soft); color: #8f3222; border: 1px solid var(--red); }
    .btn-danger:hover { background: var(--red); color: white; }
    .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); }

    .campo { margin-top: 14px; }
    .campo label { display: block; font-size: 12.5px; font-weight: 700; color: var(--pine-dark); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.3px; }
    .campo input {
      width: 100%; border: 1.5px solid var(--line); background: var(--paper); border-radius: 8px;
      font-size: 15px; padding: 10px 12px; color: var(--ink); font-family: 'Inter', sans-serif;
      letter-spacing: 2px; text-align: center;
    }
    .campo input:focus { border-color: var(--leaf); outline: none; background: white; }
    .campo.pass input { letter-spacing: normal; text-align: left; padding-right: 40px; }
    .campo .input-wrap-perfil { position: relative; }
    .toggle-pass {
      position: absolute;
      right: 4px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      padding: 6px;
      cursor: pointer;
      color: #9a9184;
      display: flex;
      align-items: center;
      border-radius: 6px;
      transition: color 0.15s ease, background-color 0.15s ease;
    }
    .toggle-pass .material-icons { font-size: 19px; }
    .toggle-pass:hover { color: var(--pine); background: rgba(63,148,104,0.1); }

    #qrBox { display: none; margin-top: 16px; text-align: center; }
    #qrCanvas { display: inline-block; padding: 12px; background: white; border-radius: 10px; border: 1px solid var(--line); }
    .secreto-manual {
      margin-top: 10px; font-size: 12px; color: var(--ink-soft); word-break: break-all;
      background: var(--paper); border: 1px dashed var(--line); border-radius: 6px; padding: 8px 10px;
    }
    .feedback { font-size: 13px; margin-top: 10px; font-weight: 600; }
    .feedback.error { color: var(--red); }
    .feedback.exito { color: var(--pine); }

    .fila-botones { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
  </style>
</head>
<body>

<div class="header">
  <h1>Mi Perfil — INEM José Félix</h1>
  <div style="display:flex; align-items:center; gap:18px;">
    <a href="index.html">← Volver al Mapa</a>
    <a href="logout.php">Cerrar sesión</a>
  </div>
</div>

<div class="wrap">
  <?php if ($errorConexion): ?>
    <div class="error-msg"><?= htmlspecialchars($errorConexion) ?></div>
  <?php else: ?>

    <div class="profile-card">
      <h2><?= htmlspecialchars($usuario['nombre_completo']) ?></h2>
      <div class="correo"><?= htmlspecialchars($usuario['correo']) ?></div>
      <span class="badge-rol <?= htmlspecialchars($usuario['rol']) ?>"><?= htmlspecialchars($usuario['rol']) ?></span>
    </div>

    <div class="action-card">
      <h3>🔐 Verificación en dos pasos (2FA)</h3>
      <p>Agrega una capa extra de seguridad: además de tu contraseña, necesitarás un código de 6 dígitos generado por una app autenticadora en tu celular (Google Authenticator, Authy, Microsoft Authenticator, etc.).</p>

      <div id="estadoActual" class="estado-2fa <?= $totpActivo ? 'on' : 'off' ?>">
        <span id="estadoTexto"><?= $totpActivo ? '✓ Activada' : '○ Desactivada' ?></span>
      </div>
      <br>

      <!-- ── Vista: 2FA desactivada ── -->
      <div id="vistaActivar" style="<?= $totpActivo ? 'display:none;' : '' ?>">
        <button type="button" class="btn btn-primary" onclick="iniciarActivacion()">Activar verificación en dos pasos</button>

        <div id="qrBox">
          <p style="font-size:13.5px; color:var(--ink-soft); margin-bottom:10px;">
            Escanea este código con tu app autenticadora:
          </p>
          <div id="qrCanvas"></div>
          <div class="secreto-manual">
            ¿No podés escanear? Ingresa esta clave manualmente en tu app:<br>
            <strong id="secretoTexto">—</strong>
          </div>

          <div class="campo">
            <label for="inputCodigoActivar">Código de 6 dígitos</label>
            <input type="text" id="inputCodigoActivar" inputmode="numeric" maxlength="6" placeholder="000000">
          </div>
          <div class="fila-botones">
            <button type="button" class="btn btn-primary" onclick="confirmarActivacion()">Confirmar y activar</button>
            <button type="button" class="btn btn-secondary" onclick="cancelarActivacion()">Cancelar</button>
          </div>
          <div id="feedbackActivar" class="feedback"></div>
        </div>
      </div>

      <!-- ── Vista: 2FA activada ── -->
      <div id="vistaDesactivar" style="<?= $totpActivo ? '' : 'display:none;' ?>">
        <button type="button" class="btn btn-danger" onclick="mostrarFormDesactivar()">Desactivar verificación en dos pasos</button>

        <div id="formDesactivar" style="display:none;">
          <div class="campo pass">
            <label for="inputPasswordDesactivar">Confirma tu contraseña</label>
            <div class="input-wrap-perfil">
              <input type="password" id="inputPasswordDesactivar" placeholder="Tu contraseña actual" autocomplete="current-password">
              <button type="button" class="toggle-pass" tabindex="-1" aria-label="Mostrar contraseña" onclick="togglePassword('inputPasswordDesactivar', this)">
                <span class="material-icons">visibility</span>
              </button>
            </div>
          </div>
          <div class="fila-botones">
            <button type="button" class="btn btn-danger" onclick="confirmarDesactivacion()">Sí, desactivar</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('formDesactivar').style.display='none';">Cancelar</button>
          </div>
          <div id="feedbackDesactivar" class="feedback"></div>
        </div>
      </div>
    </div>

  <?php endif; ?>
</div>

<script>
  function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('.material-icons');
    const mostrar = input.type === 'password';
    input.type = mostrar ? 'text' : 'password';
    icon.textContent = mostrar ? 'visibility_off' : 'visibility';
    btn.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
  }

  let qrInstance = null;

  async function iniciarActivacion() {
    const fb = document.getElementById('feedbackActivar');
    fb.textContent = '';
    try {
      const resp = await fetch('totp_iniciar_activacion.php', { method: 'POST' });
      const data = await resp.json();
      if (!resp.ok || !data.exito) {
        fb.textContent = data.error || 'No se pudo iniciar la activación.';
        fb.className = 'feedback error';
        return;
      }

      document.getElementById('qrBox').style.display = 'block';
      document.getElementById('secretoTexto').textContent = data.secreto;

      const qrCanvas = document.getElementById('qrCanvas');
      qrCanvas.innerHTML = '';
      qrInstance = new QRCode(qrCanvas, {
        text: data.uri,
        width: 200,
        height: 200,
      });
    } catch (err) {
      fb.textContent = 'No se pudo conectar con el servidor.';
      fb.className = 'feedback error';
    }
  }

  function cancelarActivacion() {
    document.getElementById('qrBox').style.display = 'none';
    document.getElementById('inputCodigoActivar').value = '';
    document.getElementById('feedbackActivar').textContent = '';
  }

  async function confirmarActivacion() {
    const codigo = document.getElementById('inputCodigoActivar').value.trim();
    const fb = document.getElementById('feedbackActivar');

    if (!/^\d{6}$/.test(codigo)) {
      fb.textContent = 'Ingresa los 6 dígitos del código.';
      fb.className = 'feedback error';
      return;
    }

    try {
      const resp = await fetch('totp_confirmar_activacion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ codigo })
      });
      const data = await resp.json();

      if (resp.ok && data.exito) {
        fb.textContent = '✓ Activado correctamente.';
        fb.className = 'feedback exito';
        setTimeout(() => window.location.reload(), 800);
      } else {
        fb.textContent = data.error || 'Código incorrecto.';
        fb.className = 'feedback error';
      }
    } catch (err) {
      fb.textContent = 'No se pudo conectar con el servidor.';
      fb.className = 'feedback error';
    }
  }

  function mostrarFormDesactivar() {
    document.getElementById('formDesactivar').style.display = 'block';
  }

  async function confirmarDesactivacion() {
    const password = document.getElementById('inputPasswordDesactivar').value;
    const fb = document.getElementById('feedbackDesactivar');

    if (!password) {
      fb.textContent = 'Ingresa tu contraseña.';
      fb.className = 'feedback error';
      return;
    }

    try {
      const resp = await fetch('totp_desactivar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password })
      });
      const data = await resp.json();

      if (resp.ok && data.exito) {
        fb.textContent = '✓ Desactivado.';
        fb.className = 'feedback exito';
        setTimeout(() => window.location.reload(), 800);
      } else {
        fb.textContent = data.error || 'No se pudo desactivar.';
        fb.className = 'feedback error';
      }
    } catch (err) {
      fb.textContent = 'No se pudo conectar con el servidor.';
      fb.className = 'feedback error';
    }
  }
</script>
</body>
</html>
