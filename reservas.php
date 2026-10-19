<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Configuración de la base de datos MySQL — misma fuente que config.php,
// así solo hay que editar las credenciales en un único lugar.
require __DIR__ . '/db_credentials.php';
$dbHost = $DB_HOST;
$dbName = $DB_NAME;
$dbUser = $DB_USER;
$dbPass = $DB_PASS;

$pdo = null;
try {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Si MySQL no está disponible, se utiliza reservas.json como respaldo
    $pdo = null;
}

$archivoJSON = __DIR__ . '/reservas.json';

function obtenerReservasJSON($archivo) {
    if (!file_exists($archivo)) {
        file_put_contents($archivo, json_encode([]));
        return [];
    }
    $contenido = file_get_contents($archivo);
    $datos = json_decode($contenido, true);
    return is_array($datos) ? $datos : [];
}

function guardarReservasJSON($archivo, $reservas) {
    file_put_contents($archivo, json_encode($reservas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    // -------------------------------------------------------------
    // GET: Obtener reservas filtradas
    // -------------------------------------------------------------
    case 'GET':
        $bloque = $_GET['bloque'] ?? null;
        $salon  = $_GET['salon'] ?? null;
        $fecha  = $_GET['fecha'] ?? null;

        if ($pdo) {
            try {
                $sql = "SELECT * FROM reservas WHERE 1=1";
                $params = [];

                if ($bloque !== null) { $sql .= " AND bloque = :bloque"; $params['bloque'] = $bloque; }
                if ($salon  !== null) { $sql .= " AND salon = :salon";   $params['salon']  = $salon; }
                if ($fecha  !== null) { $sql .= " AND fecha = :fecha";   $params['fecha']  = $fecha; }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $reservas = $stmt->fetchAll();

                echo json_encode($reservas);
                exit;
            } catch (PDOException $e) {
                // Fallback a JSON si la consulta falla
            }
        }

        // Respaldo JSON
        $todas = obtenerReservasJSON($archivoJSON);
        $filtradas = array_values(array_filter($todas, function($r) use ($bloque, $salon, $fecha) {
            $coincideBloque = $bloque === null || (string)$r['bloque'] === (string)$bloque;
            $coincideSalon  = $salon === null  || (string)$r['salon']  === (string)$salon;
            $coincideFecha  = $fecha === null  || (string)$r['fecha']  === (string)$fecha;
            return $coincideBloque && $coincideSalon && $coincideFecha;
        }));

        echo json_encode($filtradas);
        break;

    // -------------------------------------------------------------
    // POST: Guardar nueva reserva
    // -------------------------------------------------------------
    case 'POST':
        // ── Solo docentes y administradores pueden crear reservas.
        //    Esto se valida acá (no solo en el frontend) porque cualquiera
        //    podría llamar a este endpoint directamente saltándose la UI. ──
        $rolSesion = $_SESSION['rol'] ?? null;
        if (!isset($_SESSION['user_id']) || $rolSesion === 'estudiante') {
            http_response_code(403);
            echo json_encode([
                'exito' => false,
                'error' => 'No tienes permiso para reservar salones.'
            ]);
            exit;
        }

        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!$data || empty($data['fecha']) || empty($data['periodo']) || empty($data['materia'])) {
            http_response_code(400);
            echo json_encode([
                'exito' => false,
                'error' => 'Faltan campos obligatorios para completar la reserva (Materia, Fecha o Período).'
            ]);
            exit;
        }

        $bloque      = (int)($data['bloque'] ?? 1);
        $salon       = (string)($data['salon'] ?? '101');
        $fecha       = (string)$data['fecha'];
        $periodo     = (string)$data['periodo'];
        $hora        = (string)($data['hora'] ?? '');
        $materia     = (string)$data['materia'];
        $grado       = (string)($data['grado'] ?? '');
        $seccion     = (string)($data['seccion'] ?? '');
        $motivoRaw   = (string)($data['motivo'] ?? '');
        $responsable = (string)($data['responsable'] ?? 'Docente INEM');

        $motivoCompleto = $motivoRaw;

        if ($pdo) {
            try {
                // Verificar si ya existe reserva para ese bloque, salon, fecha y periodo
                $stmtCheck = $pdo->prepare("SELECT id FROM reservas WHERE bloque = :bloque AND salon = :salon AND fecha = :fecha AND periodo = :periodo");
                $stmtCheck->execute([
                    'bloque'  => $bloque,
                    'salon'   => $salon,
                    'fecha'   => $fecha,
                    'periodo' => $periodo
                ]);

                if ($stmtCheck->fetch()) {
                    http_response_code(409);
                    echo json_encode([
                        'exito' => false,
                        'error' => 'Este horario ya se encuentra reservado en la base de datos.'
                    ]);
                    exit;
                }

                // Insertar en MySQL
                $sqlInsert = "INSERT INTO reservas (bloque, salon, fecha, periodo, hora, materia, grado, seccion, motivo, responsable, creado_en) 
                              VALUES (:bloque, :salon, :fecha, :periodo, :hora, :materia, :grado, :seccion, :motivo, :responsable, NOW())";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    'bloque'      => $bloque,
                    'salon'       => $salon,
                    'fecha'       => $fecha,
                    'periodo'     => $periodo,
                    'hora'        => $hora,
                    'materia'     => $materia,
                    'grado'       => $grado,
                    'seccion'     => $seccion,
                    'motivo'      => $motivoCompleto,
                    'responsable' => $responsable
                ]);

                $idGenerado = $pdo->lastInsertId();

                http_response_code(201);
                echo json_encode([
                    'exito' => true,
                    'mensaje' => 'Reserva guardada con éxito en la base de datos MySQL (inem_db)',
                    'id' => $idGenerado
                ]);
                exit;
            } catch (PDOException $e) {
                // Fallback a JSON en caso de fallo
            }
        }

        // Respaldo en JSON
        $todas = obtenerReservasJSON($archivoJSON);
        foreach ($todas as $r) {
            if ((string)$r['bloque'] === (string)$bloque &&
                (string)$r['salon'] === (string)$salon &&
                (string)$r['fecha'] === (string)$fecha &&
                (string)$r['periodo'] === (string)$periodo) {
                http_response_code(409);
                echo json_encode(['exito' => false, 'error' => 'Este horario ya se encuentra reservado.']);
                exit;
            }
        }

        $nuevaReserva = [
            'id'          => uniqid('res_'),
            'bloque'      => $bloque,
            'salon'       => $salon,
            'fecha'       => $fecha,
            'periodo'     => $periodo,
            'hora'        => $hora,
            'materia'     => $materia,
            'grado'       => $grado,
            'seccion'     => $seccion,
            'motivo'      => $motivoRaw,
            'responsable' => $responsable
        ];
        $todas[] = $nuevaReserva;
        guardarReservasJSON($archivoJSON, $todas);

        http_response_code(201);
        echo json_encode(['exito' => true, 'mensaje' => 'Reserva guardada correctamente', 'reserva' => $nuevaReserva]);
        break;

    // -------------------------------------------------------------
    // DELETE: Cancelar reserva
    // -------------------------------------------------------------
    case 'DELETE':
        // ── Misma protección que en POST: estudiantes y no logueados no pueden cancelar reservas ──
        $rolSesionDel = $_SESSION['rol'] ?? null;
        if (!isset($_SESSION['user_id']) || $rolSesionDel === 'estudiante') {
            http_response_code(403);
            echo json_encode([
                'exito' => false,
                'error' => 'No tienes permiso para cancelar reservas.'
            ]);
            exit;
        }

        $id = $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['exito' => false, 'error' => 'Se requiere el ID de la reserva.']);
            exit;
        }

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM reservas WHERE id = :id");
                $stmt->execute(['id' => $id]);

                echo json_encode(['exito' => true, 'mensaje' => 'Reserva eliminada de MySQL']);
                exit;
            } catch (PDOException $e) {
                // Fallback a JSON
            }
        }

        $todas = obtenerReservasJSON($archivoJSON);
        $nuevas = array_values(array_filter($todas, function($r) use ($id) {
            return (string)$r['id'] !== (string)$id;
        }));

        guardarReservasJSON($archivoJSON, $nuevas);
        echo json_encode(['exito' => true, 'mensaje' => 'Reserva cancelada con éxito']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        break;
}
?>