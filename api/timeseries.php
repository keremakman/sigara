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
$fmt = 'Y-m-d';

function series_daily(PDO $pdo, DateTime $start, DateTime $end): array {
    $sql = "SELECT DATE(created_at) as d, user_key, COUNT(*) as cnt FROM events WHERE created_at >= :s AND created_at < :e GROUP BY d, user_key ORDER BY d";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':s' => $start->format('Y-m-d 00:00:00'), ':e' => $end->format('Y-m-d 00:00:00')]);
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = $row['d']; $uk = $row['user_key']; $cnt = (int)$row['cnt'];
        if (!isset($map[$d])) $map[$d] = ['user1'=>0,'user2'=>0];
        $map[$d][$uk] = $cnt;
    }
    return $map;
}

$now = new DateTime('now', $tz);
$startWeek = (clone $now)->modify('monday this week'); $startWeek->setTime(0,0,0);
$endWeek = (clone $startWeek)->modify('+7 days');
$startMonth = (clone $now); $startMonth->setDate((int)$now->format('Y'), (int)$now->format('m'), 1); $startMonth->setTime(0,0,0);
$endMonth = (clone $startMonth)->modify('+1 month');

try {
    $week = series_daily($pdo, $startWeek, $endWeek);
    $month = series_daily($pdo, $startMonth, $endMonth);

    // Normalize to fill missing days with 0
    $normalize = function(DateTime $start, DateTime $end, array $data) use ($fmt) {
        $labels = [];
        $u1 = []; $u2 = [];
        $ptr = clone $start;
        while ($ptr < $end) {
            $d = $ptr->format($fmt);
            $labels[] = $d;
            $u1[] = $data[$d]['user1'] ?? 0;
            $u2[] = $data[$d]['user2'] ?? 0;
            $ptr->modify('+1 day');
        }
        return ['labels' => $labels, 'user1' => $u1, 'user2' => $u2];
    };

    $out = [
        'weekly' => $normalize($startWeek, $endWeek, $week),
        'monthly' => $normalize($startMonth, $endMonth, $month),
    ];

    echo json_encode(['success' => true, 'data' => $out]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
}

