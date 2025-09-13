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
$days = isset($_GET['days']) ? max(1, min(90, (int)$_GET['days'])) : 30;

$end = new DateTime('today', $tz);
$end->setTime(0,0,0);
$end->modify('+1 day'); // exclusive end
$start = (clone $end)->modify('-'.$days.' days');

$fmt = 'Y-m-d';
try {
    $sql = "SELECT DATE(created_at) AS d, user_key, COUNT(*) AS cnt
            FROM events
            WHERE created_at >= :s AND created_at < :e
            GROUP BY d, user_key";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':s' => $start->format('Y-m-d 00:00:00'), ':e' => $end->format('Y-m-d 00:00:00')]);
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = $row['d']; $uk = $row['user_key']; $cnt = (int)$row['cnt'];
        if (!isset($map[$d])) $map[$d] = ['user1'=>0,'user2'=>0];
        $map[$d][$uk] = $cnt;
    }
    $rows = [];
    $ptr = clone $start;
    while ($ptr < $end) {
        $d = $ptr->format($fmt);
        $u1 = $map[$d]['user1'] ?? 0;
        $u2 = $map[$d]['user2'] ?? 0;
        $rows[] = ['date' => $d, 'user1' => $u1, 'user2' => $u2, 'total' => $u1 + $u2];
        $ptr->modify('+1 day');
    }
    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
}

