<?php

require_once __DIR__ . '/../src/UzumApi.php';

$api = new UzumApi();

// фильтр дат
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-3 days'));
$to = $_GET['to'] ?? date('Y-m-d');

$orders = [];
$error = null;
$syncMessage = null;

// 🔄 Обновление данных
if (isset($_POST['sync'])) {
    $output = [];
    exec('php ' . __DIR__ . '/../scripts/sync.php 2>&1', $output);
    $syncMessage = implode("\n", $output);
}

try {
    $response = $api->getOrders($from, $to);
    $orders = $response['data'] ?? $response ?? [];
} catch (Exception $e) {
    $error = $e->getMessage();
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Uzum Dashboard</title>

    <style>
        body {
            font-family: Arial;
            background: #f5f6fa;
            padding: 20px;
        }

        h1 {
            margin-bottom: 20px;
        }

        .filter {
            margin-bottom: 20px;
        }

        input, button {
            padding: 8px;
            margin-right: 10px;
        }

        button {
            cursor: pointer;
            background: #2f3640;
            color: white;
            border: none;
        }

        button:hover {
            background: #353b48;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #2f3640;
            color: white;
        }

        tr:hover {
            background: #f1f2f6;
        }

        .log {
            background: black;
            color: #00ff00;
            padding: 10px;
            margin-bottom: 20px;
            white-space: pre-wrap;
        }

        .error {
            color: red;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<h1>📊 Uzum Orders Dashboard</h1>

<!-- 🔍 Фильтр -->
<div class="filter">
    <form method="get">
        <label>From:</label>
        <input type="date" name="from" value="<?= $from ?>">
        
        <label>To:</label>
        <input type="date" name="to" value="<?= $to ?>">
        
        <button type="submit">Фильтр</button>
    </form>
</div>

<!-- 🔄 Кнопка обновления -->
<form method="post">
    <button type="submit" name="sync">🔄 Обновить данные</button>
</form>

<!-- 📜 Лог -->
<?php if ($syncMessage): ?>
    <div class="log"><?= htmlspecialchars($syncMessage) ?></div>
<?php endif; ?>

<!-- ❌ Ошибка -->
<?php if ($error): ?>
    <div class="error">Ошибка: <?= $error ?></div>
<?php endif; ?>

<!-- 📊 Таблица -->
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Дата</th>
            <th>Сумма</th>
            <th>Статус</th>
        </tr>
    </thead>

    <tbody>
    <?php if (empty($orders)): ?>
        <tr>
            <td colspan="4">Нет данных</td>
        </tr>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= $order['id'] ?? '-' ?></td>
                <td><?= $order['createdAt'] ?? '-' ?></td>
                <td><?= $order['totalPrice'] ?? '-' ?></td>
                <td><?= $order['status'] ?? '-' ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

</body>
</html>