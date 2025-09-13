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
$displayFmt = 'd.m.Y H:i';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$limit = max(1, min(200, $limit));

function fetch_logs_for(PDO $pdo, string $uk, int $limit, DateTimeZone $tz, string $displayFmt): array {
    $date = isset($_GET['date']) ? $_GET['date'] : null; // YYYY-MM-DD
    $tStart = isset($_GET['time_start']) ? $_GET['time_start'] : null; // HH:MM
    $tEnd = isset($_GET['time_end']) ? $_GET['time_end'] : null; // HH:MM

    if ($date) {
        // Build time window
        $startStr = $date . ' 00:00:00';
        $endStr = $date . ' 23:59:59';
        if ($tStart && preg_match('/^\d{2}:\d{2}$/', $tStart)) { $startStr = $date . ' ' . $tStart . ':00'; }
        if ($tEnd && preg_match('/^\d{2}:\d{2}$/', $tEnd)) { $endStr = $date . ' ' . $tEnd . ':59'; }
        $stmt = $pdo->prepare("SELECT id, created_at FROM events WHERE user_key = :uk AND created_at BETWEEN :s AND :e ORDER BY created_at DESC");
        $stmt->execute([':uk' => $uk, ':s' => $startStr, ':e' => $endStr]);
    } else {
        $limitSql = (int)$limit;
        $stmt = $pdo->prepare("SELECT id, created_at FROM events WHERE user_key = :uk ORDER BY id DESC LIMIT {$limitSql}");
        $stmt->execute([':uk' => $uk]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $logs = [];
    foreach ($rows as $r) {
        $dt = new DateTime($r['created_at'], $tz);
        $logs[] = [
            'id' => (int)$r['id'],
            'created_at' => $dt->format(DateTime::ATOM),
            'display' => $dt->format($displayFmt),
        ];
    }
    return $logs;
}

function average_minutes_per_cig(PDO $pdo, string $uk, DateTimeZone $tz): ?float {
    // Tüm içişlere göre ortalama süre: ilk ve son içiş arasındaki toplam süre / (toplam içiş - 1)
    $stmt = $pdo->prepare("SELECT MIN(created_at) AS min_t, MAX(created_at) AS max_t, COUNT(*) AS total FROM events WHERE user_key = :uk");
    $stmt->execute([':uk' => $uk]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $total = (int)($row['total'] ?? 0);
    if ($total <= 1) return null;
    $minT = new DateTime($row['min_t'], $tz);
    $maxT = new DateTime($row['max_t'], $tz);
    $diffSeconds = max(0, $maxT->getTimestamp() - $minT->getTimestamp());
    $avgMinutes = ($diffSeconds / ($total - 1)) / 60.0;
    return round($avgMinutes, 1);
}

try {
    $data = [];
    foreach (['user1', 'user2'] as $uk) {
        $data[$uk] = [
            'logs' => fetch_logs_for($pdo, $uk, $limit, $tz, $displayFmt),
            'avg_minutes_per_cig' => average_minutes_per_cig($pdo, $uk, $tz),
        ];
    }
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
}

