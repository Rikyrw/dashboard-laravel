<?php
// BARIS PALING ATAS
session_start();
require_once __DIR__ . '/auth_check.php';
requireAdmin();
// Supabase helper
require_once __DIR__ . '/../../server/supabase.php';

$activePage = 'laporan';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Laporan | GreenPoint</title>

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">

  <!-- Main Styles -->
  <link rel="stylesheet" href="./assets/css/style.css" />
  <style>
    /* Tambahan style untuk tombol export */
    .export-buttons {
      display: flex;
      gap: 10px;
      margin-top: 15px;
      flex-wrap: wrap;
    }
    .btn-excel {
      background-color: #28a745;
      color: white;
      padding: 10px 20px;
      border-radius: 6px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-weight: 500;
      transition: all 0.3s;
      border: none;
      cursor: pointer;
    }
    .btn-excel:hover {
      background-color: #218838;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .btn-pdf {
      background-color: #dc3545;
      color: white;
      padding: 10px 20px;
      border-radius: 6px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-weight: 500;
      transition: all 0.3s;
      border: none;
      cursor: pointer;
    }
    .btn-pdf:hover {
      background-color: #c82333;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .btn-icon {
      font-size: 16px;
    }
  </style>
</head>
<body>
  <div class="app">
    <!-- SIDEBAR -->
    <?php 
    $activePage = 'laporan';
    include 'sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="main">
      <div class="page-header">
        <h2>Laporan</h2>
        <p>Generate otomatis</p>
      </div>

      <!-- FILTER PERIODE -->
      <div class="filter">
        <label for="periode" class="subtle">Periode</label>
        <div class="select-wrapper">
          <select id="periode" name="periode">
            <option value="today">Hari Ini</option>
            <option value="week">Minggu Ini</option>
            <option value="month" selected>Bulan Ini</option>
            <option value="year">Tahun Ini</option>
          </select>
        </div>
      </div>

      <?php
      // Determine date range based on selected period (default month)
      $period = $_GET['periode'] ?? 'month';
      $now = new DateTime();
      switch ($period) {
        case 'today':
          $start = (new DateTime('today'))->format('Y-m-d 00:00:00');
          $end = $now->format('Y-m-d H:i:s');
          break;
        case 'week':
          $start = (new DateTime('monday this week'))->format('Y-m-d 00:00:00');
          $end = $now->format('Y-m-d H:i:s');
          break;
        case 'year':
          $start = (new DateTime($now->format('Y') . '-01-01'))->format('Y-m-d 00:00:00');
          $end = $now->format('Y-m-d H:i:s');
          break;
        case 'month':
        default:
          $start = (new DateTime($now->format('Y-m-01')))->format('Y-m-d 00:00:00');
          $end = $now->format('Y-m-d H:i:s');
      }

      // Fetch aggregates
      $total_setoran = 0.0;
      $total_setoran_count = 0;
      $total_penarikan = 0.0;
      $saldo_akhir = 0.0;
      $composition = []; // jenis_id => total_kg
      $top_nasabah = [];
      try {
        // Fetch transaksi_setor in range
        $ts = supabase_request('GET', '/rest/v1/transaksi_setor?select=id_transaksi,total_berat,total_nilai,id_nasabah,tanggal_setor&tanggal_setor=gte.' . urlencode($start) . '&tanggal_setor=lte.' . urlencode($end), null, true);
        $ts_rows = ($ts && isset($ts['status']) && $ts['status'] >=200 && $ts['status']<300) ? ($ts['body'] ?? []) : [];
        foreach ($ts_rows as $r) {
          $total_setoran += floatval($r['total_nilai'] ?? 0);
          $total_setoran_count++;
        }

        // Fetch penarikan in range
        $p = supabase_request('GET', '/rest/v1/penarikan?select=nominal&tanggal_pengajuan=gte.' . urlencode($start) . '&tanggal_pengajuan=lte.' . urlencode($end), null, true);
        $p_rows = ($p && isset($p['status']) && $p['status'] >=200 && $p['status']<300) ? ($p['body'] ?? []) : [];
        foreach ($p_rows as $r) {
          $total_penarikan += floatval($r['nominal'] ?? 0);
        }

        // System saldo: sum nasabah.saldo
        $ns = supabase_request('GET', '/rest/v1/nasabah?select=saldo', null, true);
        $ns_rows = ($ns && isset($ns['status']) && $ns['status'] >=200 && $ns['status']<300) ? ($ns['body'] ?? []) : [];
        foreach ($ns_rows as $n) $saldo_akhir += floatval($n['saldo'] ?? 0);

        // Composition last 30 days
        $d30start = (new DateTime('-30 days'))->format('Y-m-d 00:00:00');
        $dresp = supabase_request('GET', '/rest/v1/detail_setor?select=id_detail,id_transaksi,id_jenis,berat_kg,subtotal,transaksi_setor(tanggal_setor)&transaksi_setor.tanggal_setor=gte.' . urlencode($d30start) . '&transaksi_setor.tanggal_setor=lte.' . urlencode($end), null, true);
        $drows = ($dresp && isset($dresp['status']) && $dresp['status']>=200 && $dresp['status']<300) ? ($dresp['body'] ?? []) : [];
        $jenis_ids = [];
        foreach ($drows as $dr) {
          $jid = $dr['id_jenis'] ?? null;
          if ($jid === null) continue;
          $composition[$jid] = ($composition[$jid] ?? 0) + floatval($dr['berat_kg'] ?? 0);
          $jenis_ids[$jid] = $jid;
        }
        // Resolve jenis names
        if (!empty($jenis_ids)) {
          $in = implode(',', array_map('intval', array_values($jenis_ids)));
          $jresp = supabase_request('GET', '/rest/v1/jenis_sampah?select=id_jenis,nama_jenis&id_jenis=in.(' . $in . ')', null, true);
          $jrows = ($jresp && isset($jresp['status']) && $jresp['status']>=200 && $jresp['status']<300) ? ($jresp['body'] ?? []) : [];
          $names = [];
          foreach ($jrows as $jr) $names[$jr['id_jenis']] = $jr['nama_jenis'];
          // map composition to names
          $comp_named = [];
          foreach ($composition as $jid => $w) {
            $comp_named[($names[$jid] ?? 'ID:' . $jid)] = $w;
          }
          arsort($comp_named);
          $composition = $comp_named;
        }

        // Top nasabah by total_berat in range
        $nsresp = supabase_request('GET', '/rest/v1/transaksi_setor?select=id_nasabah,sum=total_berat&tanggal_setor=gte.' . urlencode($start) . '&tanggal_setor=lte.' . urlencode($end) . '&group=id_nasabah&order=sum.desc&limit=5', null, true);
        $top_rows = ($nsresp && isset($nsresp['status']) && $nsresp['status']>=200 && $nsresp['status']<300) ? ($nsresp['body'] ?? []) : [];
        $top_nasabah = [];
        if (!empty($top_rows)) {
          $ids = array_map(function($r){ return $r['id_nasabah']; }, $top_rows);
          $ids_list = implode(',', array_map('intval', $ids));
          $uresp = supabase_request('GET', '/rest/v1/nasabah?select=id_nasabah,nama_nasabah&filter=id_nasabah=in.(' . $ids_list . ')', null, true);
          $unames = ($uresp && isset($uresp['status']) && $uresp['status']>=200 && $uresp['status']<300) ? ($uresp['body'] ?? []) : [];
          $name_map = [];
          foreach ($unames as $u) $name_map[$u['id_nasabah']] = $u['nama_nasabah'];
          foreach ($top_rows as $tr) {
            $nid = $tr['id_nasabah'];
            $top_nasabah[] = ['id' => $nid, 'nama' => ($name_map[$nid] ?? 'ID:' . $nid), 'berat' => floatval($tr['sum'] ?? 0)];
          }
        }
      } catch (Exception $e) {
        error_log('Laporan fetch error: ' . $e->getMessage());
      }
      ?>

      <!-- GRID LAPORAN -->
      <div class="grid">
        <!-- Laporan Keuangan -->
        <div class="col-3">
          <div class="card laporan-card">
            <div class="laporan-top">
              <div>
                <h3>Laporan Keuangan</h3>
                <span class="subtle">Ringkasan pemasukan & pengeluaran</span>
              </div>
              <i class="lucide-wallet"></i>
            </div>
            <div class="settings-section-title">Ringkasan</div>
            <div style="display:flex;gap:8px;align-items:center;">
              <div>
                <div class="text-sm subtle">Total setoran</div>
                <div class="font-bold">Rp <?= number_format($total_setoran, 0, ',', '.') ?></div>
              </div>
              <div>
                <div class="text-sm subtle">Total penarikan</div>
                <div class="font-bold">Rp <?= number_format($total_penarikan, 0, ',', '.') ?></div>
              </div>
              <div>
                <div class="text-sm subtle">Saldo akhir</div>
                <div class="font-bold">Rp <?= number_format($saldo_akhir, 0, ',', '.') ?></div>
              </div>
            </div>
            <div class="settings-section-title">Ekspor</div>
            <div class="export-buttons">
              <a href="download/laporan_keuangan.php?periode=<?= htmlspecialchars($period) ?>&export=excel" class="btn-excel">
                <i class="lucide-file-spreadsheet btn-icon"></i> Excel
              </a>
              <a href="download/laporan_keuangan.php?periode=<?= htmlspecialchars($period) ?>&export=pdf" class="btn-pdf">
                <i class="lucide-file-text btn-icon"></i> PDF
              </a>
            </div>
          </div>
        </div>

        <!-- Laporan Sampah Masuk -->
        <div class="col-3">
          <div class="card laporan-card">
            <div class="laporan-top">
              <div>
                <h3>Laporan Sampah Masuk</h3>
                <span class="subtle">Detail jenis & berat sampah</span>
              </div>
              <i class="lucide-recycle"></i>
            </div>
            <div class="settings-section-title">Ekspor</div>
            <div class="export-buttons">
              <a href="download/laporan_sampah_masuk.php?periode=<?= htmlspecialchars($period) ?>&export=excel" class="btn-excel">
                <i class="lucide-file-spreadsheet btn-icon"></i> Excel
              </a>
              <a href="download/laporan_sampah_masuk.php?periode=<?= htmlspecialchars($period) ?>&export=pdf" class="btn-pdf">
                <i class="lucide-file-text btn-icon"></i> PDF
              </a>
            </div>
          </div>
        </div>

        <!-- Laporan Per Bulan -->
        <!-- <div class="col-3">
          <div class="card laporan-card">
            <div class="laporan-top">
              <div>
                <h3>Laporan Per Bulan</h3>
                <span class="subtle">Agregasi bulanan</span>
              </div>
              <i class="lucide-calendar"></i>
            </div>
            <div class="settings-section-title">Ekspor</div>
            <div class="export-buttons">
              <a href="download/laporan_per_bulan.php?export=excel" class="btn-excel">
                <i class="lucide-file-spreadsheet btn-icon"></i> Excel
              </a>
              <a href="download/laporan_per_bulan.php?export=pdf" class="btn-pdf">
                <i class="lucide-file-text btn-icon"></i> PDF
              </a>
            </div>
          </div>
        </div> -->

        <!-- Laporan Data Nasabah -->
        <div class="col-3">
          <div class="card laporan-card">
            <div class="laporan-top">
              <div>
                <h3>Laporan Data Nasabah</h3>
                <span class="subtle">Daftar lengkap nasabah</span>
              </div>
              <i class="lucide-users"></i>
            </div>
            <div class="settings-section-title">Ekspor</div>
            <div class="export-buttons">
              <a href="download/laporan_data_nasabah.php?export=excel" class="btn-excel">
                <i class="lucide-file-spreadsheet btn-icon"></i> Excel
              </a>
              <a href="download/laporan_data_nasabah.php?export=pdf" class="btn-pdf">
                <i class="lucide-file-text btn-icon"></i> PDF
              </a>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Scripts -->
  <script src="./assets/js/apps.js"></script>
  <script>
    // Handle filter periode change
    document.getElementById('periode').addEventListener('change', function() {
      const periode = this.value;
      window.location.href = 'laporan.php?periode=' + periode;
    });
  </script>
</body>
</html>