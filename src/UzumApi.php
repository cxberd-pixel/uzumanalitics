<?php

class UzumApi
{
    private $token;
    private $baseUrl;

    public function __construct()
    {
        $config = require __DIR__ . '/../config/uzum.php';
        $this->token = $config['token'];
        $this->baseUrl = $config['base_url'];
    }

    private function request($endpoint, $params = [])
    {
        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json'
            ],
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true);
    }

    public function getOrders($from, $to)
    {
        return $this->request('/orders', [
            'dateFrom' => $from,
            'dateTo' => $to
        ]);
    }
}