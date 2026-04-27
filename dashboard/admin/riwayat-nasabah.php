<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/config.php';

$admin_verified_via_firebase = false;
if (file_exists(__DIR__ . '/../../server/supabase.php')) {
  include_once __DIR__ . '/../../server/supabase.php';
  if (function_exists('supabase_is_admin_request')) {
    $fb = supabase_is_admin_request();
    if (is_array($fb) && !empty($fb['ok'])) $admin_verified_via_firebase = true;
  }
}

$isAdminSession = !empty($_SESSION['admin_logged_in']) || !empty($_SESSION['is_admin']);
if (!$isAdminSession && !$admin_verified_via_firebase) {
  header('Location: /dashboard/admin/login.php');
  exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
  die('ID nasabah tidak valid.');
}

$transactions = [];
if (function_exists('supabase_request')) {
  $q = '/rest/v1/transaksi?select=id,id_nasabah,total,status,created_at&order=created_at.desc&limit=500&id_nasabah=eq.' . urlencode($id);
  $r = supabase_request('GET', $q, null, true);
  if ($r && isset($r['status']) && $r['status'] >= 200 && $r['status'] < 300) {
    $transactions = is_array($r['body']) ? $r['body'] : [];
  }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Riwayat Nasabah</title>
  <link rel="stylesheet" href="./assets/css/style.css" />
</head>
<body>
  <div class="app">
    <?php $activePage = 'nasabah'; include __DIR__ . '/sidebar.php'; ?>
    <main class="main">
      <h1>Riwayat Transaksi Nasabah #<?= htmlspecialchars($id) ?></h1>
      <section class="card">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Jumlah (Rp)</th>
              <th>Status</th>
              <th>Waktu</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($transactions)): ?>
              <tr><td colspan="4">Tidak ada riwayat transaksi (atau tabel transaksi tidak ada)</td></tr>
            <?php else: ?>
              <?php foreach ($transactions as $t): ?>
                <tr>
                  <td><?= htmlspecialchars($t['id']) ?></td>
                  <td>Rp <?= number_format((float)$t['total'],0,',','.') ?></td>
                  <td><?= htmlspecialchars($t['status']) ?></td>
                  <td><?= htmlspecialchars($t['created_at'] ?? $t['processedAt'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        <div style="margin-top:12px;"><a class="btn btn-outline-secondary" href="/dashboard/admin/nasabah.php">Kembali</a></div>
      </section>
    </main>
  </div>
</body>
</html>
