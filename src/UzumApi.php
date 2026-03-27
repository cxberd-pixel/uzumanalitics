<?php

class UzumApi
{
    private $token;
    private $baseUrl;

    public function __construct()
    {
        $config = require __DIR__ . '/../config/uzum.php';
        $this->token = $config['token'] ?? null;
        $this->baseUrl = $config['base_url'] ?? null;

        if (empty($this->token)) {
            throw new Exception('UZUM_TOKEN is not set');
        }

        if (empty($this->baseUrl)) {
            throw new Exception('UZUM base URL is not set');
        }
    }

    private function request($endpoint, $params = [])
    {
        $query = http_build_query($params);
        $url = $this->baseUrl . $endpoint . ($query ? '?' . $query : '');

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->token,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);

        if ($response === false || $response === '') {
            throw new Exception('Empty response from Uzum API');
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON from Uzum API: ' . json_last_error_msg());
        }

        if ($httpCode >= 400) {
            $message = $decoded['message'] ?? $decoded['error'] ?? ('HTTP ' . $httpCode);
            throw new Exception('Uzum API error: ' . $message);
        }

        return $decoded;
    }

    public function getOrders($from, $to)
    {
        $response = $this->request('/v1/orders', [
            'dateFrom' => $from . 'T00:00:00',
            'dateTo' => $to . 'T23:59:59',
        ]);

        $orders = $response['data'] ?? $response['items'] ?? $response;
        if (!is_array($orders)) {
            $orders = [];
        }

        return [
            'data' => $orders,
            'raw' => $response,
        ];
    }
}
