<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$tz = new DateTimeZone($config['timezone'] ?? 'UTC');
$now = new DateTime('now', $tz);

$startToday = (clone $now); $startToday->setTime(0, 0, 0);
$startYesterday = (clone $startToday)->modify('-1 day');

$startThisWeek = (clone $startToday)->modify('monday this week');
$startLastWeek = (clone $startThisWeek)->modify('-7 days');

$startThisMonth = (clone $now); $startThisMonth->setDate((int)$now->format('Y'), (int)$now->format('m'), 1); $startThisMonth->setTime(0,0,0);

function count_range(PDO $pdo, string $start, string $end): array {
    $sql = "SELECT user_key, COUNT(*) AS cnt FROM events WHERE created_at >= :start AND created_at < :end GROUP BY user_key";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start' => $start, ':end' => $end]);
    $res = ['user1' => 0, 'user2' => 0];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $uk = $row['user_key'];
        $res[$uk] = (int)$row['cnt'];
    }
    return $res;
}

function count_total(PDO $pdo): array {
    $sql = "SELECT user_key, COUNT(*) AS cnt FROM events GROUP BY user_key";
    $stmt = $pdo->query($sql);
    $res = ['user1' => 0, 'user2' => 0];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $uk = $row['user_key'];
        $res[$uk] = (int)$row['cnt'];
    }
    return $res;
}

$out = [
'user1' => ['today' => 0, 'yesterday' => 0, 'last_week' => 0, 'this_week' => 0, 'this_month' => 0, 'total' => 0],
    'user2' => ['today' => 0, 'yesterday' => 0, 'last_week' => 0, 'this_week' => 0, 'this_month' => 0, 'total' => 0],
];

$fmt = 'Y-m-d H:i:s';

$todayCounts = count_range($pdo, $startToday->format($fmt), $now->format($fmt));
$yesterdayCounts = count_range($pdo, $startYesterday->format($fmt), $startToday->format($fmt));
$lastWeekCounts = count_range($pdo, $startLastWeek->format($fmt), $startThisWeek->format($fmt));
$thisWeekCounts = count_range($pdo, $startThisWeek->format($fmt), $now->format($fmt));
$thisMonthCounts = count_range($pdo, $startThisMonth->format($fmt), $now->format($fmt));
$totalCounts = count_total($pdo);

foreach (['user1','user2'] as $uk) {
    $out[$uk]['today'] = $todayCounts[$uk] ?? 0;
    $out[$uk]['yesterday'] = $yesterdayCounts[$uk] ?? 0;
    $out[$uk]['last_week'] = $lastWeekCounts[$uk] ?? 0;
    $out[$uk]['this_week'] = $thisWeekCounts[$uk] ?? 0;
    $out[$uk]['this_month'] = $thisMonthCounts[$uk] ?? 0;
    $out[$uk]['total'] = $totalCounts[$uk] ?? 0;
}

$cooldownSeconds = 60;
$cooldownRemaining = 0;
try {
    $me = current_user_key();
    $stmt = $pdo->prepare("SELECT created_at FROM events WHERE user_key = :uk ORDER BY id DESC LIMIT 1");
    $stmt->execute([':uk' => $me]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $last = new DateTime($row['created_at'], $tz);
        $diff = $now->getTimestamp() - $last->getTimestamp();
        $cooldownRemaining = max(0, $cooldownSeconds - $diff);
    }
} catch (Throwable $e) {
    $cooldownRemaining = 0;
}

echo json_encode([
    'success' => true,
    'data' => $out,
    'meta' => [
        'now' => $now->format(DateTime::ATOM),
        'cooldown_seconds' => $cooldownSeconds,
        'cooldown_remaining' => (int)$cooldownRemaining,
    ],
]);

