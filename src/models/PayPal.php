<?php

/**
 * Cliente REST de PayPal (Checkout v2) vía cURL, sin SDK externo.
 *
 * Modo desde src/.env: PAYPAL_MODE=sandbox|live, PAYPAL_CLIENT_ID,
 * PAYPAL_API_SECRET. Se usa para pagar pedidos a domicilio colocados desde el
 * portal web: createOrder() arma la orden y el navegador la aprueba con el
 * botón/regreso del approve_link; después captureOrder() cobra la captura.
 */

class PayPal
{
    const MODE_SANDBOX_URL = 'https://api-m.sandbox.paypal.com';
    const MODE_LIVE_URL    = 'https://api-m.paypal.com';

    private $clientId;
    private $secret;
    private $mode;
    private $baseUrl;
    private $token = null;

    public function __construct()
    {
        $this->clientId = $_ENV['PAYPAL_CLIENT_ID'] ?? '';
        $this->secret   = $_ENV['PAYPAL_API_SECRET'] ?? '';
        $this->mode     = strtolower($_ENV['PAYPAL_MODE'] ?? 'sandbox');
        $this->baseUrl  = $this->mode === 'live' ? self::MODE_LIVE_URL : self::MODE_SANDBOX_URL;
    }

    public function configured(): bool
    {
        return $this->clientId !== '' && $this->secret !== '';
    }

    private function accessToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }
        $resp = $this->request('POST', '/v1/oauth2/token', [
            'headers' => [
                'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->secret),
                'Content-Type: application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
        ]);
        $this->token = $resp['access_token'] ?? '';
        return $this->token;
    }

    /**
     * Crea una orden de pago de captura inmediata.
     * Devuelve ['id', 'status', 'approve_link']; en el navegador se manda al
     * approve_link y, al aprobar, el comercio captura con captureOrder().
     *
     * $returnUrl/$cancelUrl son las URL absolutas a las que PayPal redirige al
     * comprador tras aprobar o cancelar (sin ellas la vuelta al sitio no ocurre).
     */
    public function createOrder($total, $reference, $returnUrl = null, $cancelUrl = null): array
    {
        $context = [
            'brand_name' => setting('business_name', 'Fharina et Ignis'),
            'shipping_preference' => 'NO_SHIPPING',
            'user_action' => 'PAY_NOW',
        ];
        if ($returnUrl !== null && $returnUrl !== '') {
            $context['return_url'] = $returnUrl;
        }
        if ($cancelUrl !== null && $cancelUrl !== '') {
            $context['cancel_url'] = $cancelUrl;
        }

        $body = json_encode([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $reference,
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => number_format((float) $total, 2, '.', ''),
                ],
            ]],
            'application_context' => $context,
        ]);

        $resp = $this->request('POST', '/v2/checkout/orders', [
            'headers' => [
                'Authorization: Bearer ' . $this->accessToken(),
                'Content-Type: application/json',
            ],
            'body' => $body,
        ]);

        $approve = null;
        foreach ($resp['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approve = $link['href'];
            }
        }

        return [
            'id' => $resp['id'] ?? null,
            'status' => $resp['status'] ?? null,
            'approve_link' => $approve,
        ];
    }

    /**
     * Captura una orden ya aprobada por el comprador.
     * Devuelve ['paypal_order_id', 'status', 'capture_id', 'capture_status'].
     */
    public function captureOrder($paypalOrderId): array
    {
        $resp = $this->request('POST', '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture', [
            'headers' => [
                'Authorization: Bearer ' . $this->accessToken(),
                'Content-Type: application/json',
            ],
            'body' => '{}',
        ]);

        $capture = null;
        foreach ($resp['purchase_units'][0]['payments']['captures'] ?? [] as $c) {
            $capture = $c;
            break;
        }

        return [
            'paypal_order_id' => $resp['id'] ?? $paypalOrderId,
            'status' => $resp['status'] ?? null,
            'capture_id' => $capture['id'] ?? null,
            'capture_status' => $capture['status'] ?? null,
            'amount' => isset($capture['amount']['value']) ? (float) $capture['amount']['value'] : null,
        ];
    }

    private function request($method, $path, $opts = []): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Accept: application/json'], $opts['headers'] ?? []));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        if (!empty($opts['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            throw new Exception('PayPal (red): ' . $error);
        }
        $data = json_decode((string) $raw, true);
        if ($status >= 400) {
            throw new Exception('PayPal ' . $status . ': ' . ($data['message'] ?? substr((string) $raw, 0, 300)));
        }
        return is_array($data) ? $data : [];
    }
}