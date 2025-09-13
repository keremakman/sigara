<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
if (!verify_csrf_token($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
    exit;
}

$tz = new DateTimeZone($config['timezone'] ?? 'UTC');
$now = new DateTime('now', $tz);
$uk = current_user_key();
$cooldownSeconds = 60;

// Daily goal info pre-check (count before insert)
$startToday = (clone $now); $startToday->setTime(0,0,0);
$fmt = 'Y-m-d H:i:s';
$todayCountBefore = 0;
$dailyLimit = null; $notifyEmail = null; $penaltyText = null;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM events WHERE user_key = :uk AND created_at >= :s");
    $stmt->execute([':uk' => $uk, ':s' => $startToday->format($fmt)]);
    $todayCountBefore = (int)($stmt->fetchColumn() ?: 0);
    $s2 = $pdo->prepare("SELECT daily_limit, notify_email, penalty_text FROM user_settings WHERE user_key = :uk");
    $s2->execute([':uk' => $uk]);
    if ($row = $s2->fetch(PDO::FETCH_ASSOC)) {
        $dailyLimit = is_null($row['daily_limit']) ? null : (int)$row['daily_limit'];
        $notifyEmail = $row['notify_email'] ?: null;
        $penaltyText = $row['penalty_text'] ?: null;
    }
} catch (Throwable $e) {
    // ignore
}

// Rate limit: last click must be >= 60 seconds ago
try {
    $stmt = $pdo->prepare("SELECT created_at FROM events WHERE user_key = :uk ORDER BY id DESC LIMIT 1");
    $stmt->execute([':uk' => $uk]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $last = new DateTime($row['created_at'], $tz);
        $diff = $now->getTimestamp() - $last->getTimestamp();
        $remaining = $cooldownSeconds - $diff;
        if ($remaining > 0) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Too Many Requests', 'meta' => [
                'cooldown_seconds' => $cooldownSeconds,
                'cooldown_remaining' => (int)$remaining,
                'now' => $now->format(DateTime::ATOM)
            ]]);
            exit;
        }
    }
} catch (Throwable $e) {
    // If rate-limit check fails, continue but still handle DB insert below
}

try {
    $stmt = $pdo->prepare("INSERT INTO events (user_key) VALUES (:uk)");
    $stmt->execute([':uk' => $uk]);

    // Build updated stats (same as stats.php)
    $startToday = (clone $now); $startToday->setTime(0, 0, 0);
    $startYesterday = (clone $startToday)->modify('-1 day');
    $startThisWeek = (clone $startToday)->modify('monday this week');
    $startLastWeek = (clone $startThisWeek)->modify('-7 days');
    $startThisMonth = (clone $now); $startThisMonth->setDate((int)$now->format('Y'), (int)$now->format('m'), 1); $startThisMonth->setTime(0,0,0);

    $fmt = 'Y-m-d H:i:s';
    $countRange = function(PDO $pdo, string $start, string $end): array {
        $sql = "SELECT user_key, COUNT(*) AS cnt FROM events WHERE created_at >= :start AND created_at < :end GROUP BY user_key";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);
        $res = ['user1' => 0, 'user2' => 0];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res[$row['user_key']] = (int)$row['cnt'];
        }
        return $res;
    };
    $countTotal = function(PDO $pdo): array {
        $sql = "SELECT user_key, COUNT(*) AS cnt FROM events GROUP BY user_key";
        $stmt = $pdo->query($sql);
        $res = ['user1' => 0, 'user2' => 0];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res[$row['user_key']] = (int)$row['cnt'];
        }
        return $res;
    };

    $out = [
        'user1' => ['today' => 0, 'yesterday' => 0, 'last_week' => 0, 'this_month' => 0, 'total' => 0],
        'user2' => ['today' => 0, 'yesterday' => 0, 'last_week' => 0, 'this_month' => 0, 'total' => 0],
    ];

    $todayCounts = $countRange($pdo, $startToday->format($fmt), $now->format($fmt));
    $yesterdayCounts = $countRange($pdo, $startYesterday->format($fmt), $startToday->format($fmt));
    $lastWeekCounts = $countRange($pdo, $startLastWeek->format($fmt), $startThisWeek->format($fmt));
    $thisWeekCounts = $countRange($pdo, $startThisWeek->format($fmt), $now->format($fmt));
    $thisMonthCounts = $countRange($pdo, $startThisMonth->format($fmt), $now->format($fmt));
    $totalCounts = $countTotal($pdo);

    foreach (['user1','user2'] as $k) {
        $out[$k]['today'] = $todayCounts[$k] ?? 0;
        $out[$k]['yesterday'] = $yesterdayCounts[$k] ?? 0;
        $out[$k]['last_week'] = $lastWeekCounts[$k] ?? 0;
        $out[$k]['this_week'] = $thisWeekCounts[$k] ?? 0;
        $out[$k]['this_month'] = $thisMonthCounts[$k] ?? 0;
        $out[$k]['total'] = $totalCounts[$k] ?? 0;
    }

    // Goal meta
    $todayAfter = $out[$uk]['today'];
    $goal = null;
    if (!is_null($dailyLimit) && $dailyLimit > 0) {
        $percent = (int)floor(($todayAfter / $dailyLimit) * 100);
        $approaching = $percent >= 80 && $percent < 100;
        $exceeded = $todayAfter >= $dailyLimit;
        $just_crossed = ($todayCountBefore < $dailyLimit) && $exceeded;
        if ($just_crossed && $notifyEmail) {
            $subject = 'Günlük hedef aşıldı';
            $body = "Bugünkü sigara sayın: {$todayAfter}/{$dailyLimit}. Hedefi aştın.";
            @send_email($notifyEmail, $subject, $body);
        }
        $goal = [
            'limit' => $dailyLimit,
            'today' => $todayAfter,
            'percent' => $percent,
            'approaching' => $approaching,
            'exceeded' => $exceeded,
            'just_crossed' => $just_crossed,
            'penalty_text' => $penaltyText,
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $out,
        'meta' => [
            'now' => $now->format(DateTime::ATOM),
            'cooldown_seconds' => $cooldownSeconds,
            'cooldown_remaining' => $cooldownSeconds,
            'goal' => $goal,
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
}

