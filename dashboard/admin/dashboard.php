<?php
// BARIS PALING ATAS - PERBAIKAN
session_start();
require_once __DIR__ . '/auth_check.php';
requireAdmin();

$activePage = 'dashboard';

// Generate CSRF token
$csrf_token = generateCsrfToken();
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

  <!-- Main CSS -->
  <link rel="stylesheet" href="./assets/css/style.css" />
</head>

<body>
  <div class="app">
    <?php
    $activePage = 'dashboard';
    include "sidebar.php";
    ?>

    <div class="header">
      <div>
        <h1>Dashboard</h1>
        <p>
          Selamat datang, <strong><?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Admin') ?></strong>
          <span class="role-badge" style="background: <?= (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] == 'superadmin') ? '#7c3aed' : '#059669' ?>; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; margin-left: 8px; display: inline-block;">
            <i class="lucide-shield"></i> <?= htmlspecialchars(ucfirst($_SESSION['admin_role'] ?? 'Operator')) ?>
          </span>
          <a href="logout.php" class="btn-logout-header" style="margin-left: 10px; padding: 4px 12px; background: #dc2626; color: white; border-radius: 20px; font-size: 12px; text-decoration: none;">
            <i class="lucide-log-out"></i> Logout
          </a>
        </p>
        <p style="margin-top: 5px; font-size: 14px; color: #6b7280;">
          <i class="lucide-mail"></i> <?= htmlspecialchars($_SESSION['admin_email'] ?? '') ?>
        </p>
      </div>

    <?php
    // Load basic metrics from Supabase when available
    $nasabahCount = 0;
    $topNasabah = [];
    $totalSaldo = 0.0;
    $totalSampahToday = 0.0; // aggregate jumlah for today
    $pendapatanThisMonth = 0.0; // sum of total for this month

    if (file_exists(__DIR__ . '/../../server/supabase.php')) {
      include_once __DIR__ . '/../../server/supabase.php';

      // fetch nasabah list (limit 1000) and take counts and top by saldo
      $r = supabase_request('GET', '/rest/v1/nasabah?select=id_nasabah,nama_nasabah,saldo&order=saldo.desc&limit=1000', null, true);
      $list = [];
      if ($r && isset($r['status']) && $r['status'] >= 200 && $r['status'] < 300) {
        $list = is_array($r['body']) ? $r['body'] : [];
        $nasabahCount = count($list);
        $topNasabah = array_slice($list, 0, 10);
        foreach ($list as $u) {
          $totalSaldo += isset($u['saldo']) ? floatval($u['saldo']) : 0;
        }
      }

      // fetch transaksi_setor and detail_setor to compute sampah and pendapatan metrics
      // transaksi_setor contains overall totals per setor, detail_setor contains per-jenis breakdown
      $txSetorResp = supabase_request('GET', '/rest/v1/transaksi_setor?select=id_transaksi,id_nasabah,total_berat,total_nilai,status,tanggal_setor&order=tanggal_setor.desc&limit=1000', null, true);
      $transaksi_setor = [];
      if ($txSetorResp && isset($txSetorResp['status']) && $txSetorResp['status'] >= 200 && $txSetorResp['status'] < 300) {
        $transaksi_setor = is_array($txSetorResp['body']) ? $txSetorResp['body'] : [];
      }

      $detailResp = supabase_request('GET', '/rest/v1/detail_setor?select=id_detail,id_transaksi,id_jenis,berat_kg&order=id_detail.desc&limit=3000', null, true);
      $detail_setor = [];
      if ($detailResp && isset($detailResp['status']) && $detailResp['status'] >= 200 && $detailResp['status'] < 300) {
        $detail_setor = is_array($detailResp['body']) ? $detailResp['body'] : [];
      }

      // Build index of transaksi_setor by id_transaksi for lookups
      $txSetorIndex = [];
      foreach ($transaksi_setor as $s) {
        $txSetorIndex[$s['id_transaksi']] = $s;
      }

      $today = date('Y-m-d');
      $month = date('Y-m');

      // pendapatanThisMonth comes from transaksi_setor.total_nilai in current month
      foreach ($transaksi_setor as $s) {
        $date = isset($s['tanggal_setor']) ? substr($s['tanggal_setor'], 0, 10) : null;
        if ($date === $today) {
          $totalSampahToday += isset($s['total_berat']) ? floatval($s['total_berat']) : 0;
        }
        if ($date && strpos($date, $month) === 0) {
          $pendapatanThisMonth += isset($s['total_nilai']) ? floatval($s['total_nilai']) : 0;
        }
      }

      // prepare data needed for charts and top nasabah (activity by count in last 30 days)
      $transactions_for_charts = $transaksi_setor; // alias
      $cutoff30 = strtotime('-30 days');
      $activityCount = []; // id_nasabah => count
      foreach ($transaksi_setor as $s) {
        $dateTs = isset($s['tanggal_setor']) ? strtotime($s['tanggal_setor']) : 0;
        if ($dateTs >= $cutoff30) {
          $nid = $s['id_nasabah'] ?? null;
          if ($nid) {
            if (!isset($activityCount[$nid])) $activityCount[$nid] = 0;
            $activityCount[$nid]++;
          }
        }
      }

      // Resolve nasabah names for active list
      if (!empty($activityCount)) {
        $ids = implode(',', array_map('intval', array_keys($activityCount)));
        $nasResp = supabase_request('GET', '/rest/v1/nasabah?select=id_nasabah,nama_nasabah&limit=1000&id_nasabah=in.(' . $ids . ')', null, true);
        $nasMap = [];
        if ($nasResp && isset($nasResp['status']) && $nasResp['status'] >= 200 && $nasResp['status'] < 300) {
          foreach ($nasResp['body'] as $nn) {
            $nasMap[$nn['id_nasabah']] = $nn['nama_nasabah'];
          }
        }
        // build topNasabah by activity count
        arsort($activityCount);
        $topNasabah = [];
        $i = 0;
        foreach ($activityCount as $nid => $cnt) {
          if ($i++ >= 10) break;
          $topNasabah[] = ['id_nasabah' => $nid, 'nama_nasabah' => $nasMap[$nid] ?? ('ID:' . $nid), 'activity' => $cnt, 'saldo' => 0];
        }
        // fetch saldo for these top nasabah
        $topIds = implode(',', array_map('intval', array_column($topNasabah, 'id_nasabah')));
        if (!empty($topIds)) {
          $sresp = supabase_request('GET', '/rest/v1/nasabah?select=id_nasabah,saldo&id_nasabah=in.(' . $topIds . ')', null, true);
          if ($sresp && isset($sresp['status']) && $sresp['status'] >= 200 && $sresp['status'] < 300) {
            $saldoMap = [];
            foreach ($sresp['body'] as $ss) $saldoMap[$ss['id_nasabah']] = $ss['saldo'];
            foreach ($topNasabah as &$tn) {
              $tn['saldo'] = $saldoMap[$tn['id_nasabah']] ?? 0;
            }
            unset($tn);
          }
        }
      }
    }

    
    ?>

    <!-- ======= GRID CONTENT ======= -->
    <section class="grid">
      <!-- CARD 1 -->
      <div class="card col-4">
        <h3>Nasabah Terdaftar</h3>
        <div class="metric">
          <span class="value"><?= htmlspecialchars(number_format($nasabahCount, 0, ',', '.')) ?></span>
          <span class="delta">&nbsp;</span>
        </div>
        <div class="subtle">Total nasabah terdaftar</div>
      </div>

      <!-- CARD 2 -->
      <div class="card col-4">
        <h3>Total Sampah Hari Ini</h3>
        <div class="metric">
          <span class="value"><?= htmlspecialchars(number_format($totalSampahToday, 2, ',', '.')) ?> kg</span>
        </div>
        <div class="subtle">Diukur dari transaksi setor hari ini</div>
      </div>

      <!-- CARD 3 -->
      <div class="card col-4">
        <h3>Pendapatan Bulan Ini</h3>
        <div class="metric">
          <span class="value">Rp <?= htmlspecialchars(number_format($pendapatanThisMonth, 0, ',', '.')) ?></span>
        </div>
        <div class="subtle">Total nilai setor bulan ini</div>
      </div>

      <!-- TOTAL SALDO -->
      <div class="card col-4">
        <h3>Total Saldo Nasabah</h3>
        <div class="metric">
          <span class="value">Rp <?= htmlspecialchars(number_format($totalSaldo, 0, ',', '.')) ?></span>
        </div>
        <div class="subtle">Akumulasi saldo semua nasabah</div>
      </div>

      <!-- LINE CHART -->
      <div class="card col-8">
        <h3>Grafik Sampah (7 Hari Terakhir)</h3>
        <canvas id="lineChart" height="120"></canvas>
      </div>

      <!-- PIE CHART -->
      <div class="card col-4">
        <h3>Komposisi Sampah (30 Hari)</h3>
        <canvas id="pieChart" height="120"></canvas>
      </div>

      <!-- TABLE -->
      <div class="card col-12">
        <h3>Nasabah Paling Aktif</h3>
        <table class="table" id="customerTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Nama</th>
              <th>No. Rekening</th>
              <th>Saldo</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($topNasabah)): ?>
              <tr>
                <td colspan="4">Data tidak tersedia</td>
              </tr>
              <?php else: $i = 1;
              foreach ($topNasabah as $u): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><a href="edit-nasabah.php?id=<?= urlencode($u['id_nasabah'] ?? '') ?>"><?= htmlspecialchars($u['nama_nasabah'] ?? '') ?></a></td>
                  <td><?= htmlspecialchars($u['id_nasabah'] ?? '') ?></td>
                  <td>Rp <?= number_format((float)($u['saldo'] ?? 0), 0, ',', '.') ?></td>
                </tr>
            <?php endforeach;
            endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <?php
    // Prepare chart data (line: last 7 days total kg from transaksi_setor, pie: jenis totals last 30 days from detail_setor)
    $lineLabels = [];
    $lineData = [];
    $pieMap = [];

    // Line chart: total_berat per day for last 7 days
    for ($d = 6; $d >= 0; $d--) {
      $day = date('Y-m-d', strtotime("-{$d} days"));
      $label = date('d M', strtotime($day));
      $lineLabels[] = $label;
      $sum = 0.0;
      foreach ($transactions_for_charts as $s) {
        $date = isset($s['tanggal_setor']) ? substr($s['tanggal_setor'], 0, 10) : null;
        if ($date === $day) {
          $sum += isset($s['total_berat']) ? floatval($s['total_berat']) : 0;
        }
      }
      $lineData[] = $sum;
    }

    // Pie chart: sum berat per jenis in last 30 days
    $cutoffTs = strtotime('-30 days');
    // Build map of id_transaksi -> tanggal for quick lookup
    $txDateMap = [];
    foreach ($transactions_for_charts as $s) {
      $txDateMap[$s['id_transaksi']] = isset($s['tanggal_setor']) ? strtotime($s['tanggal_setor']) : 0;
    }

    foreach ($detail_setor as $d) {
      $txId = $d['id_transaksi'] ?? null;
      if (!$txId) continue;
      $txTs = $txDateMap[$txId] ?? 0;
      if ($txTs < $cutoffTs) continue;
      $jenisId = $d['id_jenis'] ?? '0';
      $amt = isset($d['berat_kg']) ? floatval($d['berat_kg']) : 0;
      if (!isset($pieMap[$jenisId])) $pieMap[$jenisId] = 0.0;
      $pieMap[$jenisId] += $amt;
    }

    // Resolve jenis names
    $pieLabels = [];
    $pieData = [];
    if (!empty($pieMap)) {
      $jenisIds = implode(',', array_map('intval', array_keys($pieMap)));
      $jresp = supabase_request('GET', '/rest/v1/jenis_sampah?select=id_jenis,nama_jenis&id_jenis=in.(' . $jenisIds . ')', null, true);
      $namaMap = [];
      if ($jresp && isset($jresp['status']) && $jresp['status'] >= 200 && $jresp['status'] < 300) {
        foreach ($jresp['body'] as $jj) $namaMap[$jj['id_jenis']] = $jj['nama_jenis'];
      }
      foreach ($pieMap as $jid => $val) {
        $pieLabels[] = $namaMap[$jid] ?? ('Jenis ' . $jid);
        $pieData[] = $val;
      }
    }
    ?>
    <script>
      // Pass PHP chart data to JS
      const chartLineLabels = <?= json_encode($lineLabels) ?>;
      const chartLineData = <?= json_encode($lineData) ?>;
      const chartPieLabels = <?= json_encode($pieLabels) ?>;
      const chartPieData = <?= json_encode($pieData) ?>;
    </script>
    </main>
  </div>


  <!-- Main Script -->
  <script src="./assets/js/apps.js"></script>
  <script>
    // Initialize charts when Chart.js is loaded
    try {
      if (typeof Chart !== 'undefined') {
        // Line chart
        const ctxLine = document.getElementById('lineChart').getContext('2d');
        new Chart(ctxLine, {
          type: 'line',
          data: {
            labels: chartLineLabels,
            datasets: [{
              label: 'Kg Sampah',
              data: chartLineData,
              borderColor: '#0ea5e9',
              backgroundColor: 'rgba(14,165,233,0.12)',
              tension: 0.3,
              fill: true
            }]
          },
          options: {
            responsive: true,
            plugins: {
              legend: {
                display: false
              }
            }
          }
        });

        // Pie chart
        const ctxPie = document.getElementById('pieChart').getContext('2d');
        const bgColors = ['#34d399', '#60a5fa', '#f97316', '#f43f5e', '#a78bfa', '#f59e0b'];
        new Chart(ctxPie, {
          type: 'pie',
          data: {
            labels: chartPieLabels,
            datasets: [{
              data: chartPieData,
              backgroundColor: bgColors.slice(0, chartPieLabels.length)
            }]
          },
          options: {
            responsive: true
          }
        });
      }
    } catch (e) {
      console.error('Chart init error', e);
    }
  </script>

</html>