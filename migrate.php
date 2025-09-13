<?php
require_once __DIR__ . '/bootstrap.php';

$dbCfg = $config['db'];
$host = $dbCfg['host'];
$port = (int)$dbCfg['port'];
$user = $dbCfg['user'];
$pass = $dbCfg['pass'];
$name = $dbCfg['name'];
$charset = $dbCfg['charset'] ?? 'utf8mb4';

$messages = [];
$ok = true;
try {
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    $pdo2 = new PDO($dsn, $user, $pass, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]);

    // Ensure events table exists
    $pdo2->exec("CREATE TABLE IF NOT EXISTS `events` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_key` ENUM('user1','user2') NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_user_time` (`user_key`, `created_at`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE {$charset}_unicode_ci;");
    $messages[] = 'events tablosu OK';

    // Ensure user_settings exists
    $pdo2->exec("CREATE TABLE IF NOT EXISTS `user_settings` (
        `user_key` ENUM('user1','user2') NOT NULL,
        `daily_limit` INT NULL,
        `notify_email` VARCHAR(255) NULL,
        `penalty_text` VARCHAR(255) NULL,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`user_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE {$charset}_unicode_ci;");
    $pdo2->exec("INSERT INTO `user_settings` (`user_key`, `daily_limit`, `updated_at`) VALUES
        ('user1', NULL, NOW()), ('user2', NULL, NOW())
        ON DUPLICATE KEY UPDATE `user_key` = VALUES(`user_key`)");
    $messages[] = 'user_settings tablosu OK';

    // Ensure challenges exists
    $pdo2->exec("CREATE TABLE IF NOT EXISTS `challenges` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `period` ENUM('weekly','monthly') NOT NULL,
        `start_at` DATETIME NOT NULL,
        `end_at` DATETIME NOT NULL,
        `created_by` ENUM('user1','user2') NOT NULL,
        `status` ENUM('pending','active','declined','completed','canceled') NOT NULL DEFAULT 'pending',
        `accepted_by` ENUM('user1','user2') NULL,
        `winner_user_key` ENUM('user1','user2') NULL,
        `penalty_text` VARCHAR(255) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_period_time` (`period`, `start_at`, `end_at`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE {$charset}_unicode_ci;");
    $messages[] = 'challenges tablosu OK';

} catch (Throwable $e) {
    $ok = false;
    $messages[] = 'Hata: ' . $e->getMessage();
}
?><!doctype html>
<html lang="tr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Migrasyon - <?php echo htmlspecialchars($config['site']['title'] ?? 'Sigara Sayaç'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light">
<div class="container py-5">
  <div class="col-md-8 mx-auto">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h4 mb-3">Veritabanı Migrasyonu</h1>
        <?php foreach ($messages as $m): ?>
          <div><?php echo htmlspecialchars($m); ?></div>
        <?php endforeach; ?>
        <div class="alert alert-<?php echo $ok ? 'success' : 'danger'; ?> mt-3">
          <?php echo $ok ? 'İşlem tamamlandı.' : 'Hata oluştu.'; ?>
        </div>
        <a class="btn btn-primary" href="index.php">Panele dön</a>
      </div>
    </div>
  </div>
</div>
</body></html>

