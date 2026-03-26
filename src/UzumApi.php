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

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $this->token,
        'Content-Type: application/json',
        'Accept: application/json'
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
    return $this->request('/v1/orders', [
        'dateFrom' => $from . 'T00:00:00',
        'dateTo' => $to . 'T23:59:59'
    ]);
}
var_dump($this->token);
exit;
}