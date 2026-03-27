<?php

require_once __DIR__ . '/src/UzumApi.php';

function parseAmount($value)
{
    if (is_numeric($value)) {
        return (float) $value;
    }

    if (is_string($value)) {
        $normalized = preg_replace('/[^\d.\-]/', '', str_replace(',', '.', $value));
        if ($normalized !== '' && is_numeric($normalized)) {
            return (float) $normalized;
        }
    }

    return 0.0;
}

function formatAmount($value)
{
    return number_format((float) $value, 2, '.', ' ');
}

function collectOrderStats($orders)
{
    $stats = [
        'count' => 0,
        'revenue' => 0.0,
        'avg' => 0.0,
        'statuses' => [],
    ];

    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }

        $stats['count']++;
        $amount = parseAmount($order['totalPrice'] ?? $order['price'] ?? 0);
        $stats['revenue'] += $amount;

        $status = (string) ($order['status'] ?? 'unknown');
        if (!isset($stats['statuses'][$status])) {
            $stats['statuses'][$status] = 0;
        }
        $stats['statuses'][$status]++;
    }

    if ($stats['count'] > 0) {
        $stats['avg'] = $stats['revenue'] / $stats['count'];
    }

    arsort($stats['statuses']);

    return $stats;
}

function parseQuantity($value)
{
    if (is_numeric($value)) {
        return max(0, (int) round((float) $value));
    }

    return 1;
}

function analyzeSkuSales($orders, $from, $to)
{
    $days = max(1, (int) floor((strtotime($to . ' 23:59:59') - strtotime($from . ' 00:00:00')) / 86400) + 1);
    $items = [];

    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }

        $sku = (string) ($order['sku'] ?? $order['offerId'] ?? $order['productSku'] ?? '');
        if ($sku === '') {
            continue;
        }

        if (!isset($items[$sku])) {
            $items[$sku] = [
                'sku' => $sku,
                'sold_qty' => 0,
                'revenue' => 0.0,
                'current_stock' => 0,
            ];
        }

        $qty = parseQuantity($order['quantity'] ?? $order['count'] ?? 1);
        $amount = parseAmount($order['totalPrice'] ?? $order['price'] ?? 0);
        $currentStock = (int) ($order['fboStock'] ?? $order['stock'] ?? $order['currentStock'] ?? 0);

        $items[$sku]['sold_qty'] += $qty;
        $items[$sku]['revenue'] += $amount;
        $items[$sku]['current_stock'] = max($items[$sku]['current_stock'], $currentStock);
    }

    foreach ($items as &$row) {
        $row['avg_daily_sales'] = $row['sold_qty'] / $days;
        $row['recommended_stock_20d'] = (int) ceil($row['avg_daily_sales'] * 20);
        $row['replenish_qty'] = max(0, $row['recommended_stock_20d'] - $row['current_stock']);
    }
    unset($row);

    usort($items, function ($a, $b) {
        return $b['replenish_qty'] <=> $a['replenish_qty'];
    });

    return $items;
}

function analyzeFboStorage($orders)
{
    $result = [];

    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }

        $days = $order['storageDays'] ?? $order['fboStorageDays'] ?? $order['daysInStorage'] ?? null;
        if (!is_numeric($days)) {
            continue;
        }

        $days = (int) $days;
        if ($days <= 15) {
            continue;
        }

        $sku = (string) ($order['sku'] ?? $order['offerId'] ?? $order['productSku'] ?? 'unknown');
        if (!isset($result[$sku])) {
            $result[$sku] = [
                'sku' => $sku,
                'storage_days' => $days,
                'stock' => (int) ($order['fboStock'] ?? $order['stock'] ?? $order['currentStock'] ?? 0),
            ];
        } else {
            $result[$sku]['storage_days'] = max($result[$sku]['storage_days'], $days);
            $result[$sku]['stock'] = max($result[$sku]['stock'], (int) ($order['fboStock'] ?? $order['stock'] ?? $order['currentStock'] ?? 0));
        }
    }

    usort($result, function ($a, $b) {
        return $b['storage_days'] <=> $a['storage_days'];
    });

    return $result;
}

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$to = $_GET['to'] ?? date('Y-m-d');

$orders = [];
$stats = collectOrderStats([]);
$skuPlan = [];
$fboAging = [];
$notes = [];
$error = null;
$syncMessage = null;

if (isset($_POST['sync'])) {
    $output = [];
    exec('php ' . escapeshellarg(__DIR__ . '/scripts/sync.php') . ' 2>&1', $output);
    $syncMessage = implode("\n", $output);
}

try {
    $api = new UzumApi();
    $response = $api->getOrders($from, $to);
    $orders = $response['data'] ?? [];
    if (!is_array($orders)) {
        $orders = [];
    }
    $stats = collectOrderStats($orders);
    $skuPlan = analyzeSkuSales($orders, $from, $to);
    $fboAging = analyzeFboStorage($orders);

    if (empty($skuPlan)) {
        $notes[] = 'Нет SKU/quantity в данных заказов — расчёт пополнения запасов невозможен.';
    }

    if (empty($fboAging)) {
        $notes[] = 'Нет полей storageDays/fboStorageDays/daysInStorage — анализ хранения FBO > 15 дней недоступен.';
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Uzum Sales Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6fa; margin: 0; padding: 24px; color: #2f3640; }
        h1 { margin: 0 0 16px; }
        .row { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
        .card { background: #fff; border-radius: 8px; padding: 14px 16px; min-width: 220px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .metric { font-size: 24px; font-weight: bold; margin-top: 6px; }
        .filters, .actions { margin-bottom: 12px; }
        input, button { padding: 8px; border-radius: 6px; border: 1px solid #dcdde1; }
        button { cursor: pointer; background: #2f3640; color: #fff; border: none; }
        .error { color: #b00020; margin: 10px 0; font-weight: bold; }
        .log { background: #111; color: #00ff99; padding: 10px; border-radius: 8px; white-space: pre-wrap; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #ecf0f1; text-align: left; }
        th { background: #2f3640; color: #fff; }
        tr:hover { background: #f8f9fb; }
        .status-badges span { display: inline-block; background: #edf2f7; border-radius: 999px; padding: 4px 10px; margin: 3px 4px 0 0; font-size: 12px; }
        .note { background: #fff4ce; border: 1px solid #f7d488; padding: 10px 12px; border-radius: 8px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <h1>📊 Аналитика продаж Uzum</h1>

    <div class="filters">
        <form method="get">
            <label>От:</label>
            <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
            <label>До:</label>
            <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
            <button type="submit">Применить</button>
        </form>
    </div>

    <div class="actions">
        <form method="post">
            <button type="submit" name="sync">🔄 Проверить API</button>
        </form>
    </div>

    <?php if ($syncMessage): ?>
        <div class="log"><?= htmlspecialchars($syncMessage) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error">Ошибка API: <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php foreach ($notes as $note): ?>
        <div class="note"><?= htmlspecialchars($note) ?></div>
    <?php endforeach; ?>

    <div class="row">
        <div class="card">
            <div>Заказов</div>
            <div class="metric"><?= (int) $stats['count'] ?></div>
        </div>
        <div class="card">
            <div>Выручка</div>
            <div class="metric"><?= formatAmount($stats['revenue']) ?></div>
        </div>
        <div class="card">
            <div>Средний чек</div>
            <div class="metric"><?= formatAmount($stats['avg']) ?></div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <div><strong>Статусы заказов</strong></div>
        <div class="status-badges">
            <?php if (empty($stats['statuses'])): ?>
                <span>Нет данных</span>
            <?php else: ?>
                <?php foreach ($stats['statuses'] as $status => $count): ?>
                    <span><?= htmlspecialchars($status) ?>: <?= (int) $count ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <div><strong>Рекомендации пополнения (горизонт 20 дней)</strong></div>
        <table style="margin-top:10px;">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Продано (шт)</th>
                    <th>Средние продажи/день</th>
                    <th>Текущий остаток FBO</th>
                    <th>Нужно на 20 дней</th>
                    <th>Рекомендуемое пополнение</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($skuPlan)): ?>
                    <tr><td colspan="6">Недостаточно данных для расчёта</td></tr>
                <?php else: ?>
                    <?php foreach ($skuPlan as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['sku']) ?></td>
                            <td><?= (int) $row['sold_qty'] ?></td>
                            <td><?= htmlspecialchars(formatAmount($row['avg_daily_sales'])) ?></td>
                            <td><?= (int) $row['current_stock'] ?></td>
                            <td><?= (int) $row['recommended_stock_20d'] ?></td>
                            <td><strong><?= (int) $row['replenish_qty'] ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <div><strong>FBO хранение &gt; 15 дней → кандидаты на распродажу</strong></div>
        <table style="margin-top:10px;">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Дней хранения</th>
                    <th>Остаток</th>
                    <th>Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fboAging)): ?>
                    <tr><td colspan="4">Нет позиций с хранением больше 15 дней или нет поля хранения</td></tr>
                <?php else: ?>
                    <?php foreach ($fboAging as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['sku']) ?></td>
                            <td><?= (int) $row['storage_days'] ?></td>
                            <td><?= (int) $row['stock'] ?></td>
                            <td>Запустить распродажу</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

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
                <tr><td colspan="4">Нет данных за выбранный период</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($order['id'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) ($order['createdAt'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars(formatAmount(parseAmount($order['totalPrice'] ?? $order['price'] ?? 0))) ?></td>
                        <td><?= htmlspecialchars((string) ($order['status'] ?? '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
