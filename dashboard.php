<?php
require_once __DIR__ . '/db.php';
require_login();
$userKey = current_user_key();
$labels = $config['labels'] ?? ['user1' => 'Kişi 1', 'user2' => 'Kişi 2'];
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($config['site']['title'] ?? 'Sigara Sayaç'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link href="assets/styles.css" rel="stylesheet">
<meta name="csrf-token" content="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES); ?>">
<meta name="user-key" content="<?php echo htmlspecialchars($userKey, ENT_QUOTES); ?>">
<script>
  window.labels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
</script>
</head>
<body class="app-bg">
<nav class="navbar navbar-expand-lg bg-body-tertiary">
  <div class="container">
    <a class="navbar-brand" href="#"><?php echo htmlspecialchars($config['site']['title'] ?? 'Sigara Sayaç'); ?></a>
    <div class="d-flex ms-auto">
        <a class="btn btn-outline-secondary btn-sm" href="api/logout.php">Çıkış</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <div class="row g-4">
    <?php
      foreach (['user1','user2'] as $uk):
        $label = $labels[$uk] ?? strtoupper($uk);
        $isMe = ($uk === $userKey);
    ?>
    <div class="col-md-6">
      <div class="card app-card h-100 shadow-lg">
        <div class="card-body">
          <div id="card-header-<?php echo $uk; ?>" class="d-flex justify-content-between align-items-center mb-2 card-header-toggle" role="button">
            <h5 class="card-title mb-0"><i class="bi bi-person-circle me-2"></i><?php echo htmlspecialchars($label); ?></h5>
            <div class="d-flex gap-2">
              <button id="toggle-logs-<?php echo $uk; ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-list"></i> Kayıtlar</button>
              <button id="btn-<?php echo $uk; ?>" class="btn btn-<?php echo $isMe ? 'primary' : 'secondary'; ?> btn-lg rounded-pill px-4 app-plus" <?php echo $isMe ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-lg me-1"></i> +1
              </button>
            </div>
          </div>
          <div id="card-section-<?php echo $uk; ?>" style="display:none">
          <div id="msg-<?php echo $uk; ?>" class="small text-muted mb-3" style="min-height:1.25rem;"></div>
          <?php if (!$isMe): ?>
            <div class="text-muted small mb-2">Sadece kendi butonun aktiftir.</div>
          <?php endif; ?>
          <div class="row text-center">
            <div class="col-6 col-sm-3 stat-block today">
              <div class="stat-label"><i class="bi bi-sun me-1 text-warning"></i>Bugün</div>
              <div id="<?php echo $uk; ?>-today" class="stat-value">0</div>
            </div>
            <div class="col-6 col-sm-3 stat-block yesterday">
              <div class="stat-label"><i class="bi bi-moon-stars me-1 text-secondary"></i>Dün</div>
              <div id="<?php echo $uk; ?>-yesterday" class="stat-value">0</div>
            </div>
            <div class="col-6 col-sm-3 mt-3 mt-sm-0 stat-block last-week">
              <div class="stat-label"><i class="bi bi-calendar-week me-1 text-info"></i>Geçen Hafta</div>
              <div id="<?php echo $uk; ?>-last_week" class="stat-value">0</div>
            </div>
            <div class="col-6 col-sm-3 mt-3 mt-sm-0 stat-block this-month">
              <div class="stat-label"><i class="bi bi-calendar3 me-1 text-primary"></i>Bu Ay</div>
              <div id="<?php echo $uk; ?>-this_month" class="stat-value">0</div>
            </div>
            <div class="col-6 col-sm-3 mt-3 stat-block total">
              <div class="stat-label"><i class="bi bi-graph-up-arrow me-1 text-success"></i>Toplam</div>
              <div id="<?php echo $uk; ?>-total" class="stat-value">0</div>
            </div>
          </div>
          <hr>
          <div class="row">
            <div class="col-12">
              <div class="mb-2 small text-muted">Ortalama: <span id="<?php echo $uk; ?>-avg-msg">Veri yok</span></div>

              <div id="logs-panel-<?php echo $uk; ?>" class="mb-3" style="display:none">
                <div class="row g-2 align-items-end mb-2">
                  <div class="col-12 col-sm-6">
                    <label class="form-label small">Gün</label>
                    <input type="date" class="form-control form-control-sm" id="<?php echo $uk; ?>-date">
                  </div>
                  <div class="col-12 col-sm-2 d-grid">
                    <button class="btn btn-primary btn-sm" id="<?php echo $uk; ?>-logs-fetch">Getir</button>
                  </div>
                </div>
                <div id="<?php echo $uk; ?>-logs-list" class="list-group list-group-flush small" style="max-height:220px; overflow:auto;"></div>
              </div>

              <div class="d-flex justify-content-end mb-2">
                <select class="form-select form-select-sm w-auto" id="chart-mode-<?php echo $uk; ?>">
                  <option value="weekly" selected>Haftalık</option>
                  <option value="monthly">Aylık</option>
                </select>
              </div>
              <div class="row g-3">
                <div class="col-12">
                  <canvas id="chart-weekly-<?php echo $uk; ?>" height="140"></canvas>
                  <canvas id="chart-monthly-<?php echo $uk; ?>" height="140" style="display:none"></canvas>
                </div>
              </div>
            </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="container pb-4">
  <div class="row g-4">
    <div class="col-12">
      <div class="card app-card shadow-lg">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title mb-0"><i class="bi bi-people-fill me-2"></i>Karşılaştırma ve Liderlik</h5>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div id="compare-today" class="small text-muted">Bugün: -</div>
            <div>
              <select class="form-select form-select-sm w-auto" id="chart-mode-compare">
                <option value="weekly" selected>Haftalık</option>
                <option value="monthly">Aylık</option>
              </select>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-12">
              <canvas id="chart-weekly-compare" height="180"></canvas>
              <canvas id="chart-monthly-compare" height="180" style="display:none"></canvas>
            </div>
          </div>
          <hr>
          <div class="row g-3">
            <div class="col-12">
              <div class="fw-semibold">Yarışma</div>
              <div id="challenge-weekly" class="border rounded p-3 mb-2">
                <div class="small text-muted mb-2">Haftalık yarışma</div>
                <div class="d-flex gap-2 flex-wrap align-items-center mb-2" id="challenge-weekly-controls">
                  <div class="input-group input-group-sm" style="max-width: 360px;">
                    <input type="text" class="form-control" id="challenge-weekly-penalty" placeholder="Ceza/ödül (isteğe bağlı)">
                    <button class="btn btn-primary" id="challenge-weekly-propose">Yarışma teklif et</button>
                  </div>
                </div>
                <div class="small mt-2" id="challenge-weekly-status">Durum: -</div>
              </div>
              <div id="challenge-monthly" class="border rounded p-3">
                <div class="small text-muted mb-2">Aylık yarışma</div>
                <div class="d-flex gap-2 flex-wrap align-items-center mb-2" id="challenge-monthly-controls">
                  <div class="input-group input-group-sm" style="max-width: 360px;">
                    <input type="text" class="form-control" id="challenge-monthly-penalty" placeholder="Ceza/ödül (isteğe bağlı)">
                    <button class="btn btn-primary" id="challenge-monthly-propose">Yarışma teklif et</button>
                  </div>
                </div>
                <div class="small mt-2" id="challenge-monthly-status">Durum: -</div>
              </div>
            </div>
          </div>
          <hr>
          <div class="row">
            <div class="col-12">
              <div class="fw-semibold mb-2">Liderlik Tablosu</div>
              <ul class="list-group list-group-flush small" id="leaderboard-list"></ul>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12">
      <div class="card app-card shadow-lg">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title mb-0"><i class="bi bi-table me-2"></i>Geçmiş (Gün Gün)</h5>
            <select class="form-select form-select-sm w-auto" id="history-range">
              <option value="3">Son 3 gün</option>
              <option value="7" selected>Son 7 gün</option>
              <option value="14">Son 14 gün</option>
              <option value="30">Son 30 gün</option>
              <option value="180">Son 6 ay</option>
            </select>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle text-white-50" id="history-table">
              <thead>
                <tr>
                  <th>Tarih</th>
                  <th><?php echo htmlspecialchars($labels['user1'] ?? 'Kişi 1'); ?></th>
                  <th><?php echo htmlspecialchars($labels['user2'] ?? 'Kişi 2'); ?></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="assets/charts.js"></script>
<script src="assets/script.js"></script>
</body>
</html>

