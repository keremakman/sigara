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
$tz = new DateTimeZone($config['timezone'] ?? 'UTC');
$uk = current_user_key();

function get_period_bounds(string $period, DateTimeZone $tz): array {
    $now = new DateTime('now', $tz);
    if ($period === 'weekly') {
        $start = (clone $now)->modify('monday this week'); $start->setTime(0,0,0);
        $end = (clone $start)->modify('+7 days');
    } else {
        $start = new DateTime('first day of this month', $tz); $start->setTime(0,0,0);
        $end = (clone $start)->modify('+1 month');
    }
    return [$start, $end];
}

if ($method === 'GET') {
    try {
        if (!empty($_GET['current']) && !empty($_GET['period']) && in_array($_GET['period'], ['weekly','monthly'], true)) {
            $period = $_GET['period'];
            $bounds = get_period_bounds($period, $tz);
            $start = $bounds[0];
            $end = $bounds[1];
            $find = $pdo->prepare("SELECT * FROM challenges WHERE period=:p AND start_at=:s AND end_at=:e ORDER BY id DESC LIMIT 1");
            $find->execute([':p'=>$period, ':s'=>$start->format('Y-m-d H:i:s'), ':e'=>$end->format('Y-m-d H:i:s')]);
            $challenge = $find->fetch(PDO::FETCH_ASSOC) ?: null;
            $until = new DateTime('now', $tz);
            if ($until > $end) { $until = $end; }
            $cntStmt = $pdo->prepare("SELECT user_key, COUNT(*) AS cnt FROM events WHERE created_at >= :s AND created_at < :u GROUP BY user_key");
            $cntStmt->execute([':s'=>$start->format('Y-m-d H:i:s'), ':u'=>$until->format('Y-m-d H:i:s')]);
            $counts = ['user1'=>0,'user2'=>0];
            while ($r = $cntStmt->fetch(PDO::FETCH_ASSOC)) { $counts[$r['user_key']] = (int)$r['cnt']; }

            $can = [
                'propose' => ($challenge === null) || in_array((isset($challenge['status']) ? $challenge['status'] : ''), ['declined','completed','canceled'], true),
                'accept' => false,
                'decline' => false,
                'cancel' => false,
                'complete' => false,
            ];
            if ($challenge) {
                if ($challenge['status'] === 'pending') {
                    if ($challenge['created_by'] !== $uk) { $can['accept'] = $can['decline'] = true; }
                    if ($challenge['created_by'] === $uk) { $can['cancel'] = true; }
                } elseif ($challenge['status'] === 'active') {
                    if ((new DateTime('now', $tz)) >= new DateTime($challenge['end_at'], $tz)) { $can['complete'] = true; }
                }
            }

            echo json_encode(['success'=>true, 'data'=>[
                'period' => $period,
                'start_at' => $start->format(DateTime::ATOM),
                'end_at' => $end->format(DateTime::ATOM),
                'now' => (new DateTime('now', $tz))->format(DateTime::ATOM),
                'challenge' => $challenge,
                'counts' => $counts,
                'can' => $can,
            ]]);
        } else {
            $stmt = $pdo->query("SELECT * FROM challenges ORDER BY id DESC LIMIT 20");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB error']);
    }
    exit;
}

$token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (!verify_csrf_token($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if ($input === null) $input = array();
$action = isset($input['action']) ? $input['action'] : '';
$period = isset($input['period']) ? $input['period'] : 'weekly';

if (!in_array($period, ['weekly','monthly'], true)) { $period = 'weekly'; }

if ($action === 'propose') {
    $bounds = get_period_bounds($period, $tz);
    $start = $bounds[0];
    $end = $bounds[1];
    $penalty = isset($input['penalty_text']) ? trim((string)$input['penalty_text']) : null;
    try {
        // If there is already an active/pending for this period and window, skip new one
        $chk = $pdo->prepare("SELECT id FROM challenges WHERE period=:p AND start_at=:s AND end_at=:e AND status IN ('pending','active') LIMIT 1");
        $chk->execute([':p'=>$period, ':s'=>$start->format('Y-m-d H:i:s'), ':e'=>$end->format('Y-m-d H:i:s')]);
        if ($chk->fetch()) {
            echo json_encode(['success' => true, 'message' => 'Zaten bir yarışma var.']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO challenges (period, start_at, end_at, created_by, status, penalty_text) VALUES (:p,:s,:e,:cb,'pending',:pt)");
        $stmt->execute([':p'=>$period, ':s'=>$start->format('Y-m-d H:i:s'), ':e'=>$end->format('Y-m-d H:i:s'), ':cb'=>$uk, ':pt'=>$penalty]);
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB error']);
    }
    exit;
}

if (in_array($action, ['accept','decline','cancel','complete'], true)) {
    $id = (int)(isset($input['id']) ? $input['id'] : 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Bad id']); exit; }
    try {
        $stmt = $pdo->prepare("SELECT * FROM challenges WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Not found']); exit; }
        if ($action === 'accept') {
            if ($c['status'] !== 'pending' || $c['created_by'] === $uk) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Not allowed']); exit; }
            $u = $pdo->prepare("UPDATE challenges SET status='active', accepted_by=:ab WHERE id=:id");
            $u->execute([':ab'=>$uk, ':id'=>$id]);
        } elseif ($action === 'decline') {
            if ($c['status'] !== 'pending' || $c['created_by'] === $uk) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Not allowed']); exit; }
            $u = $pdo->prepare("UPDATE challenges SET status='declined', accepted_by=:ab WHERE id=:id");
            $u->execute([':ab'=>$uk, ':id'=>$id]);
        } elseif ($action === 'cancel') {
            if ($c['created_by'] !== $uk) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Not allowed']); exit; }
            $u = $pdo->prepare("UPDATE challenges SET status='canceled' WHERE id=:id");
            $u->execute([':id'=>$id]);
        } elseif ($action === 'complete') {
            // Only complete if time passed and was active
            $now = new DateTime('now', $tz);
            if ($c['status'] !== 'active') { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Not active']); exit; }
            if ($now < new DateTime($c['end_at'], $tz)) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Not ended']); exit; }
            $cntStmt = $pdo->prepare("SELECT user_key, COUNT(*) AS cnt FROM events WHERE created_at >= :s AND created_at < :e GROUP BY user_key");
            $cntStmt->execute([':s'=>$c['start_at'], ':e'=>$c['end_at']]);
            $counts = ['user1'=>0,'user2'=>0];
            while ($r = $cntStmt->fetch(PDO::FETCH_ASSOC)) { $counts[$r['user_key']] = (int)$r['cnt']; }
            $winner = null;
            if ($counts['user1'] !== $counts['user2']) {
                // En az içen kazansın
                $winner = ($counts['user1'] < $counts['user2']) ? 'user1' : 'user2';
            }
            $u = $pdo->prepare("UPDATE challenges SET status='completed', winner_user_key=:w WHERE id=:id");
            $u->execute([':w'=>$winner, ':id'=>$id]);
            echo json_encode(['success'=>true, 'data'=>['winner'=>$winner, 'counts'=>$counts]]);
            exit;
        }
        echo json_encode(['success'=>true]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'DB error']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success'=>false,'error'=>'Unknown action']);

