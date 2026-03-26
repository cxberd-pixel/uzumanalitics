<?php

require_once __DIR__ . '/../src/UzumApi.php';

echo "=== START SYNC ===\n";

try {
    $api = new UzumApi();

    $from = date('Y-m-d', strtotime('-3 days'));
    $to = date('Y-m-d');

    $orders = $api->getOrders($from, $to);

    echo "=== ORDERS ===\n";
    print_r($orders);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}