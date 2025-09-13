<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uk = current_user_key();

if ($method === 'GET') {
    try {
        if (!empty($_GET['all'])) {
            $stmt = $pdo->query("SELECT user_key, daily_limit, notify_email, penalty_text FROM user_settings");
            $data = ['user1' => ['daily_limit'=>null,'notify_email'=>null,'penalty_text'=>null], 'user2' => ['daily_limit'=>null,'notify_email'=>null,'penalty_text'=>null]];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data[$r['user_key']] = [
                    'daily_limit' => is_null($r['daily_limit']) ? null : (int)$r['daily_limit'],
                    'notify_email' => $r['notify_email'],
                    'penalty_text' => $r['penalty_text'],
                ];
            }
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            $stmt = $pdo->prepare("SELECT daily_limit, notify_email, penalty_text FROM user_settings WHERE user_key = :uk");
            $stmt->execute([':uk' => $uk]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['daily_limit' => null, 'notify_email' => null, 'penalty_text' => null];
            echo json_encode(['success' => true, 'data' => $row]);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB error']);
    }
    exit;
}

if ($method === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $daily_limit = isset($input['daily_limit']) ? (int)$input['daily_limit'] : null;
    $notify_email = isset($input['notify_email']) ? trim((string)$input['notify_email']) : null;
    $penalty_text = isset($input['penalty_text']) ? trim((string)$input['penalty_text']) : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO user_settings (user_key, daily_limit, notify_email, penalty_text) VALUES
            (:uk, :dl, :em, :pt)
            ON DUPLICATE KEY UPDATE daily_limit = VALUES(daily_limit), notify_email = VALUES(notify_email), penalty_text = VALUES(penalty_text)");
        $stmt->execute([':uk' => $uk, ':dl' => $daily_limit, ':em' => $notify_email, ':pt' => $penalty_text]);
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);

