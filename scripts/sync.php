<?php

require_once __DIR__ . '/../src/UzumApi.php';

echo "=== START SYNC ===\n";

try {
    $api = new UzumApi();

    $from = date('Y-m-d', strtotime('-3 days'));
    $to = date('Y-m-d');

    $response = $api->getOrders($from, $to);
    $orders = $response['data'] ?? [];

    $revenue = 0.0;
    foreach ($orders as $order) {
        $value = $order['totalPrice'] ?? $order['price'] ?? 0;
        if (is_numeric($value)) {
            $revenue += (float) $value;
        }
    }

    echo "=== SUMMARY ===\n";
    echo "orders_count=" . count($orders) . "\n";
    echo "revenue=" . $revenue . "\n";
    echo "=== ORDERS ===\n";
    echo json_encode($orders, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
