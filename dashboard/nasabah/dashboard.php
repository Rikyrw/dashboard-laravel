<?php
session_start();
require './login/config.php';

// Check authentication - lebih strict
if (!isset($_SESSION['id_nasabah'])) {
    header("Location: login/login.php");
    exit();
}

// Ambil data user terbaru dari REST API
try {
    $user_data = supabase('nasabah?select=*&id_nasabah=eq.' . $_SESSION['id_nasabah']);
    
    if ($user_data && count($user_data) > 0) {
        $user = $user_data[0];
        $_SESSION['nama_nasabah'] = $user['nama_nasabah'];
        $_SESSION['saldo'] = $user['saldo'] ?? 0;
    } else {
        // Jika user tidak ditemukan, logout
        session_destroy();
        header("Location: login/login.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Fetch recent user transactions for display on dashboard
$recent_setor = [];
$recent_ppob = [];
try {
  $user_id = $_SESSION['id_nasabah'];

  // Recent setor (last 5)
  $recent_setor = supabase('transaksi_setor?select=id_transaksi,id_nasabah,total_berat,total_nilai,status,tanggal_setor&id_nasabah=eq.' . $user_id . '&order=tanggal_setor.desc&limit=5') ?: [];

  // Fetch penarikan (PPOB) and approved transaksi (PPOB) then merge
  $p = supabase('penarikan?select=id_penukaran,jenis_penukaran,nominal,status,tanggal_pengajuan,deskripsi&id_nasabah=eq.' . $user_id . '&order=tanggal_pengajuan.desc&limit=5') ?: [];
  $t = supabase('transaksi?select=id,id_nasabah,jenis,total,status,created_at,deskripsi&id_nasabah=eq.' . $user_id . '&order=created_at.desc&limit=5') ?: [];

  $hist = [];
  foreach ($p as $r) {
    $hist[] = [
      'type' => 'penarikan',
      'id' => $r['id_penukaran'] ?? null,
      'service' => $r['jenis_penukaran'] ?? 'PPOB',
      'amount' => isset($r['nominal']) ? floatval($r['nominal']) : 0,
      'status' => $r['status'] ?? 'menunggu',
      'deskripsi' => $r['deskripsi'] ?? '',
      'created_at' => $r['tanggal_pengajuan'] ?? null,
    ];
  }
  foreach ($t as $r) {
    $hist[] = [
      'type' => 'transaksi',
      'id' => $r['id'] ?? null,
      'service' => $r['jenis'] ?? 'Transaksi',
      'amount' => isset($r['total']) ? floatval($r['total']) : 0,
      'status' => $r['status'] ?? 'success',
      'deskripsi' => $r['deskripsi'] ?? '',
      'created_at' => $r['created_at'] ?? null,
    ];
  }
  usort($hist, function($a, $b) {
    $ta = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    $tb = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    return $tb <=> $ta;
  });
  $recent_ppob = array_slice($hist, 0, 5);
} catch (Exception $e) {
  error_log('Dashboard recent transactions error: ' . $e->getMessage());
}

// Compute aggregates for the top cards (per-nasabah)
$setor_count = 0;
$ppob_total = 0;
try {
  $user_id = $_SESSION['id_nasabah'];
  $all_setor = supabase('transaksi_setor?select=id_transaksi&id_nasabah=eq.' . $user_id) ?: [];
  $setor_count = is_array($all_setor) ? count($all_setor) : 0;

  // Sum nominal from penarikan
  $all_penarikan = supabase('penarikan?select=nominal&id_nasabah=eq.' . $user_id) ?: [];
  $sum_penarikan = 0;
  if (is_array($all_penarikan)) {
    foreach ($all_penarikan as $pp) {
      $sum_penarikan += isset($pp['nominal']) ? floatval($pp['nominal']) : 0;
    }
  }

  // Sum transaksi totals (PPOB-related transactions created on approve)
  $all_transaksi = supabase('transaksi?select=total,id_nasabah,jenis&id_nasabah=eq.' . $user_id) ?: [];
  $sum_transaksi = 0;
  if (is_array($all_transaksi)) {
    foreach ($all_transaksi as $tt) {
      $sum_transaksi += isset($tt['total']) ? floatval($tt['total']) : 0;
    }
  }

  $ppob_total = $sum_penarikan + $sum_transaksi;
} catch (Exception $e) {
  error_log('Dashboard aggregates error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>GreenPoint • Dashboard</title>

  <!-- Font & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Main CSS -->
  <link rel="stylesheet" href="assets/css/style.css" />
</head>

<body class="bg-gray-50">
  <div class="app flex min-h-screen">
    <!-- SIDEBAR -->
    <?php 
    $activePage = 'dashboard';
    if (file_exists('sidebar.php')) {
        include "sidebar.php";
    } else {
        // Fallback sidebar
        echo '
        <div class="sidebar bg-green-800 text-white p-4 w-64">
          <h2 class="text-xl font-bold mb-6">GreenPoint</h2>
          <nav>
            <ul class="space-y-2">
              <li class="bg-green-700 p-2 rounded">
                <a href="dashboard.php" class="block">Dashboard</a>
              </li>
              <li class="p-2 rounded hover:bg-green-700">
                <a href="transaksi.php" class="block">Transaksi</a>
              </li>
              <li class="p-2 rounded hover:bg-green-700">
                <a href="profil.php" class="block">Profile</a>
              </li>
              <li class="p-2 rounded hover:bg-green-700">
                <a href="logout.php" class="block">Logout</a>
              </li>
            </ul>
          </nav>
        </div>';
    }
    ?>

    <main class="main flex-1 p-6">
      <div class="page-header mb-6">
        <div class="header-content">
          <h2 class="text-2xl font-bold text-gray-800">Dashboard</h2>
          <p class="text-gray-600">Selamat datang, <?= htmlspecialchars($_SESSION['nama_nasabah'] ?? 'User') ?>! di sistem manajemen bank sampah</p>
        </div>
      </div>

      <!-- GRID CONTENT -->
      <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- CARD 1 -->
        <div class="bg-white p-6 rounded-lg shadow-md">
          <h3 class="text-lg font-semibold mb-3">Transaksi Setor Sampah</h3>
          <div class="metric">
            <span class="value text-green-600 text-2xl font-bold"><?= number_format((int)($setor_count ?? 0), 0, ',', '.') ?></span>
            <span class="delta text-sm text-green-500">+12 dari bulan lalu</span>
          </div>
        </div>

        <!-- CARD 2 -->
        <div class="bg-white p-6 rounded-lg shadow-md">
          <h3 class="text-lg font-semibold mb-3">Transaksi PPOB</h3>
          <div class="metric">
            <span class="value text-green-600 text-2xl font-bold">Rp <?= number_format((float)($ppob_total ?? 0), 0, ',', '.') ?></span>
            <span class="delta text-sm text-green-500">+12% dari bulan lalu</span>
          </div>
        </div>

        <!-- CARD 3 - PPOB Section -->
        <div class="bg-white p-6 rounded-lg shadow-md md:col-span-2">
          <section class="ppob-section">
            <h3 class="text-lg font-semibold mb-4">PPOB</h3>
            <div class="ppob-cards-container">
              <div class="ppob-cards flex flex-wrap gap-4">
                <a href="emoney.php" class="ppob-card flex flex-col items-center justify-center p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                  <img src="assets/img/Card Wallet.png" alt="E money" class="w-12 h-12 mb-2">
                  <span class="text-sm font-medium">E money</span>
                </a>
                
                <a href="pulsa.php" class="ppob-card flex flex-col items-center justify-center p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                  <img src="assets/img/Phonelink Ring.png" alt="Pulsa" class="w-12 h-12 mb-2">
                  <span class="text-sm font-medium">Pulsa</span>
                </a>
                
                <a href="pln.php" class="ppob-card flex flex-col items-center justify-center p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                  <img src="assets/img/Flash On.png" alt="PLN" class="w-12 h-12 mb-2">
                  <span class="text-sm font-medium">PLN</span>
                </a>
              </div>
            </div>
          </section>
        </div>
      </section>

      <!-- RECENT TRANSACTIONS -->
      <section class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
          <h3 class="text-lg font-semibold mb-3">Transaksi Setor Terbaru</h3>
          <?php if (empty($recent_setor)): ?>
            <div class="text-gray-600">Belum ada transaksi setor.</div>
          <?php else: ?>
            <ul class="space-y-3">
              <?php foreach ($recent_setor as $rs): ?>
                <li class="flex justify-between items-center border p-3 rounded">
                  <div>
                    <div class="text-sm text-gray-600">ID: <?= htmlspecialchars($rs['id_transaksi'] ?? '-') ?></div>
                    <div class="font-medium">Total Berat: <?= htmlspecialchars(number_format((float)($rs['total_berat'] ?? 0), 2, ',', '.')) ?> kg</div>
                    <div class="text-sm text-gray-500">Nilai: Rp <?= number_format((float)($rs['total_nilai'] ?? 0), 0, ',', '.') ?></div>
                  </div>
                  <div class="text-right">
                    <div class="text-sm text-gray-500"><?= $rs['tanggal_setor'] ? date('d M Y', strtotime($rs['tanggal_setor'])) : '-' ?></div>
                    <div class="mt-1">
                      <span class="px-3 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800"><?= htmlspecialchars(ucfirst($rs['status'] ?? 'menunggu')) ?></span>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
          <h3 class="text-lg font-semibold mb-3">Transaksi PPOB Terbaru</h3>
          <?php if (empty($recent_ppob)): ?>
            <div class="text-gray-600">Belum ada transaksi PPOB.</div>
          <?php else: ?>
            <ul class="space-y-3">
              <?php foreach ($recent_ppob as $it): ?>
                <li class="flex justify-between items-center border p-3 rounded">
                  <div>
                    <div class="font-medium"><?= htmlspecialchars($it['service'] ?? '-') ?></div>
                    <div class="text-sm text-gray-500"><?= htmlspecialchars($it['deskripsi'] ?? '') ?></div>
                  </div>
                  <div class="text-right">
                    <div class="font-medium">Rp <?= number_format((float)($it['amount'] ?? 0), 0, ',', '.') ?></div>
                    <div class="text-sm text-gray-500"><?= $it['created_at'] ? date('d M Y', strtotime($it['created_at'])) : '-' ?></div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </section>
    </main>
  </div>

  <!-- Main Script -->
  <script src="assets/js/apps.js"></script>
</body>
</html>