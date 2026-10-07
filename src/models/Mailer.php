<?php

/**
 * Mailer SMTP sin dependencias externas.
 *
 * Lee la configuración de src/.env (mismas convenciones que Database.php):
 *   MAIL_HOST        — servidor SMTP. Vacío = envíos deshabilitados (no-op).
 *   MAIL_PORT        — puerto SMTP (default 587).
 *   MAIL_ENCRYPTION  — 'ssl' | 'tls' | 'none' (default: 'tls').
 *   MAIL_USERNAME    — usuario de autenticación (vacío = SMTP sin login).
 *   MAIL_PASSWORD    — contraseña de autenticación.
 *   MAIL_FROM        — dirección remitente (default: MAIL_USERNAME).
 *   MAIL_FROM_NAME   — nombre del remitente (default: MAIL_HOST).
 *   MAIL_TO          — buzón de la empresa para notificaciones (ej: contacto).
 *
 * Nunca lanza excepciones hacia la página: send() devuelve bool y el error no
 * rompe una operación legítima (mismo patrón que AuditLog::write()).
 */
class Mailer
{
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $from;
    private $fromName;
    private $businessMail;

    public function __construct()
    {
        $this->host = trim((string) ($_ENV['MAIL_HOST'] ?? ''));
        $this->port = (int) ($_ENV['MAIL_PORT'] ?? 587);
        $enc = strtolower(trim((string) ($_ENV['MAIL_ENCRYPTION'] ?? 'tls')));
        $this->encryption = in_array($enc, ['ssl', 'tls', 'none'], true) ? $enc : 'tls';
        $this->username = trim((string) ($_ENV['MAIL_USERNAME'] ?? ''));
        $this->password = (string) ($_ENV['MAIL_PASSWORD'] ?? '');
        $this->from = trim((string) ($_ENV['MAIL_FROM'] ?? '')) ?: ($this->username ?: 'no-reply@localhost');
        $this->fromName = trim((string) ($_ENV['MAIL_FROM_NAME'] ?? '')) ?: $this->host;
        $this->businessMail = trim((string) ($_ENV['MAIL_TO'] ?? ''));
    }

    /** True si hay un servidor SMTP configurado (MAIL_HOST con valor). */
    public function configured(): bool
    {
        return $this->host !== '';
    }

    /** Buzón de la empresa para notificaciones ('MAIL_TO'). */
    public function businessMail(): string
    {
        return $this->businessMail;
    }

    /**
     * Envía un correo HTML. Devuelve false (sin excepción) ante cualquier
     * error o si el SMTP no está configurado.
     */
    public function send(string $to, string $subject, string $htmlBody, string $replyTo = ''): bool
    {
        if ($to === '' || !$this->configured()) {
            return false;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $socket = $this->connect();
            if (!$socket) {
                return false;
            }

            // Comando con chequeo de la respuesta esperada.
            $cmd = function (string $line, array $expected) use ($socket, &$cmd) {
                fwrite($socket, $line);
                return $this->expect($socket, $expected);
            };

            if (!$cmd("EHLO " . $this->host . "\r\n", [250])) {
                fclose($socket);
                return false;
            }

            // STARTTLS: hay que repetir EHLO tras negociar TLS. También se
            // acepta un servidor sin soporte TLS (falla en 500) para no
            // bloquear el envío en redes internas tipo Mailpit.
            if ($this->encryption === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                $starttls = $this->expect($socket, [220, 500, 502, 454]);
                if ($starttls && (bool) stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
                    && !$cmd("EHLO " . $this->host . "\r\n", [250])) {
                    fclose($socket);
                    return false;
                }
            }

            if ($this->username !== '') {
                if (!$cmd("AUTH LOGIN\r\n", [334])
                    || !$cmd(base64_encode($this->username) . "\r\n", [334])
                    || !$cmd(base64_encode($this->password) . "\r\n", [235])) {
                    fclose($socket);
                    return false;
                }
            }

            $fromAddr = $this->from;
            if (!$cmd("MAIL FROM:<{$fromAddr}>\r\n", [250])
                || !$cmd("RCPT TO:<{$to}>\r\n", [250, 251])) {
                fclose($socket);
                return false;
            }

            if (!$cmd("DATA\r\n", [354])) {
                fclose($socket);
                return false;
            }

            $headers = [
                'From: ' . $this->encodeName($this->fromName) . ' <' . $fromAddr . '>',
                'To: <' . $to . '>',
                'Subject: ' . $this->encodeHeader($subject),
                'Date: ' . date('r'),
                'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->host . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
            ];
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $headers[] = 'Reply-To: <' . $replyTo . '>';
            }

            $data = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody;
            // Puntos DOT-STUFFING: una línea que empieza con "." se escapa a "..".
            $data = preg_replace('/^\./m', '..', $data);
            if (!fwrite($socket, $data . "\r\n.\r\n")) {
                fclose($socket);
                return false;
            }
            $ok = $this->expect($socket, [250]);
            fclose($socket);
            return $ok;
        } catch (Throwable $e) {
            if (isset($socket) && is_resource($socket)) {
                fclose($socket);
            }
            return false;
        }
    }

    /** Abre la conexión TCP (con TLS implícito si encryption === 'ssl'). */
    private function connect()
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);
        $socket = @stream_socket_client(
            $scheme . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if (!$socket) {
            return false;
        }
        stream_set_timeout($socket, 10);
        return $this->expect($socket, [220]) ? $socket : false;
    }

    /** Lee líneas hasta encontrar un código de respuesta esperado. */
    private function expect($socket, array $codes): bool
    {
        while (($line = fgets($socket)) !== false) {
            $line = rtrim($line, "\r\n");
            $code = (int) substr($line, 0, 3);
            if ($code > 0 && in_array($code, $codes, true)) {
                return true;
            }
            if (isset($line[3]) && $line[3] === ' ') {
                // Fin del bloque multilínea SMTP sin código esperado.
                return in_array($code, $codes, true);
            }
        }
        return false;
    }

    /** Codifica una cabecera larga (subject) como UTF-8 base64. */
    private function encodeHeader(string $value): string
    {
        // Control chars (\r\n) romperían cabeceras: se filtran.
        $value = preg_replace('/[\r\n]+/', ' ', $value);
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    /** Igual que encodeHeader pero para el nombre del remitente. */
    private function encodeName(string $value): string
    {
        return $this->encodeHeader($value);
    }
}