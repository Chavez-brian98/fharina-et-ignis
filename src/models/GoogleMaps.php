<?php

/**
 * Cliente Google Maps Platform (cURL, sin SDK).
 *
 * Uso actual: geocodificar la dirección de entrega en el checkout a domicilio
 * (Geocoding API) y servir el mapa en las pantallas de seguimiento (Maps
 * JavaScript API, que carga el navegador con la misma key via ?key=...). El
 * ETA se calcula en el navegador con Distance Matrix Service (JS), no aquí.
 *
 * La key sale de GOOGLE_MAPS_API_KEY en src/.env. Un fallo de geocoding NO
 * rompe el pedido: la entrega queda con coordenadas NULL (el mapa muestra el
 * texto de la dirección y aun así el movimiento del domiciliero).
 */

class GoogleMaps
{
    public static function key(): string
    {
        return trim($_ENV['GOOGLE_MAPS_API_KEY'] ?? '');
    }

    public static function configured(): bool
    {
        return self::key() !== '';
    }

    /**
     * Convierte una dirección a coordenadas.
     * Devuelve ['lat' => .., 'lng' => .., 'formatted' => ..] o null si no
     * resolvió (sin key, ZERO_RESULTS, o error de red — nunca truena).
     */
    public static function geocode($address): ?array
    {
        if (!self::configured() || trim((string) $address) === '') {
            return null;
        }

        $url = 'https://maps.googleapis.com/maps/api/geocode/json'
            . '?address=' . rawurlencode((string) $address)
            . '&key=' . rawurlencode(self::key());

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $raw = curl_exec($ch);
        curl_close($ch);

        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'OK' || empty($data['results'][0])) {
            return null;
        }

        $first = $data['results'][0];
        return [
            'lat' => (float) $first['geometry']['location']['lat'],
            'lng' => (float) $first['geometry']['location']['lng'],
            'formatted' => $first['formatted_address'] ?? null,
        ];
    }
}