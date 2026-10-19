<?php
// ════════════════════════════════════════════════════════
//  ENVÍO DE CORREOS VÍA LA API DE BREVO (HTTP/HTTPS)
//  InfinityFree bloquea mail() y los puertos SMTP salientes,
//  pero sí permite conexiones salientes HTTPS (puerto 443),
//  así que usamos la API REST de Brevo en vez de SMTP.
// ════════════════════════════════════════════════════════
require __DIR__ . '/email_credentials.php';

/**
 * Envía un correo usando la API de Brevo.
 *
 * @param string $destinatarioCorreo
 * @param string $destinatarioNombre
 * @param string $asunto
 * @param string $htmlContenido
 * @return array ['exito' => bool, 'error' => string|null]
 */
function enviarCorreo(string $destinatarioCorreo, string $destinatarioNombre, string $asunto, string $htmlContenido): array
{
    global $BREVO_API_KEY, $CORREO_REMITENTE, $NOMBRE_REMITENTE;

    $payload = [
        'sender'      => ['name' => $NOMBRE_REMITENTE, 'email' => $CORREO_REMITENTE],
        'to'          => [['email' => $destinatarioCorreo, 'name' => $destinatarioNombre]],
        'subject'     => $asunto,
        'htmlContent' => $htmlContenido,
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $BREVO_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);

    $respuesta  = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl  = curl_error($ch);
    curl_close($ch);

    if ($errorCurl) {
        error_log('Error cURL enviando correo: ' . $errorCurl);
        return ['exito' => false, 'error' => $errorCurl];
    }

    // Brevo responde 201 cuando el correo fue aceptado para envío.
    if ($codigoHttp >= 200 && $codigoHttp < 300) {
        return ['exito' => true, 'error' => null];
    }

    error_log('Brevo respondió con error (' . $codigoHttp . '): ' . $respuesta);
    return ['exito' => false, 'error' => $respuesta];
}
