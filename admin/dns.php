<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdminLogin();

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Handle AJAX Ping Test
if ($action === 'test_ping') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM dns_servers WHERE id = ?");
    $stmt->execute([$id]);
    $dns = $stmt->fetch();

    if (!$dns) {
        jsonResponse(['success' => false, 'error' => 'DNS entry not found.']);
    }

    $url = $dns['server_url'];
    $start = microtime(true);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_NOBODY, true);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $timeTaken = round((microtime(true) - $start) * 1000);
    $isOnline = ($res !== false || $httpCode > 0);
    $status = $isOnline ? 'online' : 'offline';

    $pdo->prepare("UPDATE dns_servers SET status = ?, response_time_ms = ? WHERE id = ?")
        ->execute([$status, $timeTaken, $id]);

    jsonResponse([
        'success' => true,
        'status' => $status,
        'response_time_ms' => $timeTaken,
        'http_code' => $httpCode
    ]);
}

// Handle Add DNS
if ($action === 'add_dns' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = sanitizeInput($_POST['server_url'] ?? '');
    $protocol = strpos($url, 'https://') === 0 ? 'https' : 'http';
    $order = (int)($_POST['sort_order'] ?? 1);

    if (!empty($url)) {
        $stmt = $pdo->prepare("INSERT INTO dns_servers (server_url, protocol, status, response_time_ms, is_active, sort_order) VALUES (?, ?, 'online', 0, 1, ?)");
        $stmt->execute([$url, $protocol, $order]);
    }
    header('Location: dns.php');
    exit;
}

// Handle Delete DNS
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM dns_servers WHERE id = ?")->execute([$id]);
    }
    header('Location: dns.php');
    exit;
}

$pageTitle = 'DNS Manager & Server Failover';
require_once __DIR__ . '/header.php';

$servers = $pdo->query("SELECT * FROM dns_servers ORDER BY sort_order ASC")->fetchAll();
?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
    <!-- Add DNS Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">+ Add Failover DNS</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="dns.php">
                <input type="hidden" name="action" value="add_dns">
                <div class="form-group">
                    <label class="form-label">Server Domain / URL *</label>
                    <input type="text" name="server_url" class="form-control" placeholder="https://api2.yourdomain.com" required>
                    <small style="color: var(--text-secondary); font-size: 11px;">The app will attempt this domain if the primary server is unreachable.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority Order (1 = Primary)</label>
                    <input type="number" name="sort_order" class="form-control" value="2" min="1" max="10">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Save DNS Server</button>
            </form>
        </div>
    </div>

    <!-- DNS List Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📡 Active DNS Servers (<?= count($servers) ?>)</h2>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Server URL</th>
                            <th>Protocol</th>
                            <th>Status</th>
                            <th>Latency</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servers as $s): ?>
                            <tr>
                                <td>#<?= $s['sort_order'] ?></td>
                                <td><strong><?= htmlspecialchars($s['server_url']) ?></strong></td>
                                <td><span class="badge badge-primary"><?= strtoupper($s['protocol']) ?></span></td>
                                <td>
                                    <span id="dns-badge-<?= $s['id'] ?>" class="badge <?= $s['status'] === 'online' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= ucfirst($s['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span id="dns-ms-<?= $s['id'] ?>"><?= $s['response_time_ms'] ?> ms</span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="testDnsServer(<?= $s['id'] ?>, this)">⚡ Test Ping</button>
                                        <a href="dns.php?action=delete&id=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Remove this DNS?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
