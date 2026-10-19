<?php
// ════════════════════════════════════════════════════════════
//  TOTP (RFC 6238) — implementación en PHP puro, sin librerías
//  externas (InfinityFree no permite instalar paquetes vía
//  Composer). Compatible con Google Authenticator, Authy,
//  Microsoft Authenticator y cualquier app que soporte el
//  estándar (SHA1, 6 dígitos, períodos de 30 segundos).
// ════════════════════════════════════════════════════════════

class TOTP
{
    private const ALFABETO_BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIODO_SEGUNDOS = 30;
    private const DIGITOS = 6;

    /** Genera un secreto aleatorio en Base32 (para guardar y mostrar en el QR). */
    public static function generarSecreto(int $longitud = 32): string
    {
        $secreto = '';
        for ($i = 0; $i < $longitud; $i++) {
            $secreto .= self::ALFABETO_BASE32[random_int(0, 31)];
        }
        return $secreto;
    }

    private static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $base32));
        $buffer = 0;
        $bitsEnBuffer = 0;
        $salida = '';

        for ($i = 0, $len = strlen($base32); $i < $len; $i++) {
            $valor = strpos(self::ALFABETO_BASE32, $base32[$i]);
            if ($valor === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $valor;
            $bitsEnBuffer += 5;
            if ($bitsEnBuffer >= 8) {
                $bitsEnBuffer -= 8;
                $salida .= chr(($buffer >> $bitsEnBuffer) & 0xFF);
            }
        }
        return $salida;
    }

    /** Genera el código de 6 dígitos válido para un instante de tiempo dado. */
    public static function generarCodigo(string $secretoBase32, ?int $tiempo = null): string
    {
        $tiempo = $tiempo ?? time();
        $claveBinaria = self::base32Decode($secretoBase32);
        $contador = intdiv($tiempo, self::PERIODO_SEGUNDOS);

        // Contador de 8 bytes, big-endian (formato exigido por el RFC).
        $contadorBinario = pack('N2', 0, $contador);

        $hash = hash_hmac('sha1', $contadorBinario, $claveBinaria, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $trozo = substr($hash, $offset, 4);
        $codigoNumerico = (unpack('N', $trozo)[1] & 0x7FFFFFFF) % (10 ** self::DIGITOS);

        return str_pad((string) $codigoNumerico, self::DIGITOS, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica un código ingresado por el usuario, tolerando un pequeño
     * desfase de reloj entre el celular y el servidor (± 1 período = 30s).
     */
    public static function verificarCodigo(string $secretoBase32, string $codigoIngresado, int $ventana = 1): bool
    {
        $codigoIngresado = trim($codigoIngresado);
        if (!preg_match('/^\d{6}$/', $codigoIngresado)) {
            return false;
        }

        $tiempoActual = time();
        for ($i = -$ventana; $i <= $ventana; $i++) {
            $codigoEsperado = self::generarCodigo($secretoBase32, $tiempoActual + ($i * self::PERIODO_SEGUNDOS));
            if (hash_equals($codigoEsperado, $codigoIngresado)) {
                return true;
            }
        }
        return false;
    }

    /** URI estándar "otpauth://" que las apps autenticadoras leen desde el QR. */
    public static function generarURI(string $secretoBase32, string $correoUsuario, string $emisor = 'INEM Jose Felix'): string
    {
        $etiqueta = rawurlencode($emisor . ':' . $correoUsuario);

        // Ojo: NO se usa http_build_query() acá a propósito. Muchos
        // hostings compartidos (InfinityFree incluido) configuran
        // arg_separator.output = "&amp;" en vez de "&" en su php.ini,
        // así que http_build_query() devolvería una URI del estilo
        // "secret=XXX&amp;issuer=..." que las apps autenticadoras no
        // pueden leer bien (y muestran "valor de la clave no válido").
        // Armando la query a mano evitamos depender de esa configuración.
        $parametros = [
            'secret'    => $secretoBase32,
            'issuer'    => $emisor,
            'algorithm' => 'SHA1',
            'digits'    => (string) self::DIGITOS,
            'period'    => (string) self::PERIODO_SEGUNDOS,
        ];

        $partes = [];
        foreach ($parametros as $clave => $valor) {
            $partes[] = $clave . '=' . rawurlencode($valor);
        }

        return "otpauth://totp/{$etiqueta}?" . implode('&', $partes);
    }
}
