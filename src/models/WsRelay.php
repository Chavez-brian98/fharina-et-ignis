<?php

/**
 * Cliente del relay WebSocket (contenedor "ws").
 *
 * La app PHP le hace POST a http://ws:8090 cada vez que hay algo que
 * transmitir (posición del domiciliero, cambio de estado) y el relay se
 * encarga de difundirlo a los navegadores suscritos (pantallas de tracking).
 *
 * Falla en silencio: si el relay está caído, la app sigue funcionando; el
 * tracking solo pierde la emisión en vivo (el historial vive en la BD).
 */

class WsRelay
{
    /** La URL se puede sobreescribir con WS_RELAY_URL; por defecto el nombre
     * del servicio en la red de docker-compose. */
    private static function baseUrl()
    {
        return rtrim($_ENV['WS_RELAY_URL'] ?? 'http://ws:8090', '/');
    }

    private static function secret()
    {
        return $_ENV['WS_SECRET'] ?? '';
    }

    /** Difunde la posición del domiciliero a los suscriptores del envío. */
    public static function location($deliveryId, $lat, $lng, $driverId = null)
    {
        self::post('/location', [
            'delivery_id' => (int) $deliveryId,
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'driver_id' => $driverId !== null ? (int) $driverId : null,
            'reported_at' => date('c'),
        ]);
    }

    /** Difunde un evento de estado (tomado/preparando/en_camino/finalizado). */
    public static function event($deliveryId, $state, $label, $by = null)
    {
        self::post('/event', [
            'delivery_id' => (int) $deliveryId,
            'state' => (string) $state,
            'label' => (string) $label,
            'by' => $by,
            'at' => date('c'),
        ]);
    }

    private static function post($path, array $payload)
    {
        $body = json_encode($payload);
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
        ];
        if (self::secret() !== '') {
            $payload['secret'] = self::secret();
            $body = json_encode($payload);
            $headers[1] = 'Content-Length: ' . strlen($body);
        }

        $ch = curl_init(self::baseUrl() . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_exec($ch);
        curl_close($ch);
    }
}