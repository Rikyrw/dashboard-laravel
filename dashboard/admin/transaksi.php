<!-- // Reject modal for setor: use distinct id to avoid collision with penarikan modals -->
<?php
// BARIS PALING ATAS - SEBELUM APA PUN
session_start();

// Debug session
if (isset($_GET['debug'])) {
    echo "<pre>Session Debug:\n";
    print_r($_SESSION);
    echo "\n</pre>";
}

// Include supabase.php helper so `supabase_request()` is available
$supabase_helper = __DIR__ . '/../../server/supabase.php';
if (file_exists($supabase_helper)) {
  require_once $supabase_helper;
} else {
  // If helper missing, we continue so template renders with helpful error later
  // but many features will be unavailable. Log to PHP error log.
  error_log("supabase helper not found: " . $supabase_helper);
}

// Include auth_check for CSRF helpers and admin session utilities
$auth_check = __DIR__ . '/auth_check.php';
if (file_exists($auth_check)) {
  require_once $auth_check;
} else {
  // try fallback one level up
  $auth_check2 = __DIR__ . '/../admin/auth_check.php';
  if (file_exists($auth_check2)) require_once $auth_check2;
}

// Ensure $csrf_token is available for forms
if (function_exists('generateCsrfToken')) {
  $csrf_token = generateCsrfToken();
} else {
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  $csrf_token = $_SESSION['csrf_token'];
}

// Provide a safe fallback for pg_escape_string when PHP pg extension is not installed.
if (!function_exists('pg_escape_string')) {
  function pg_escape_string($s) {
    // Basic escape: double single-quotes for SQL contexts. We don't call PG, this is only
    // used to build a safe in-list for PostgREST queries. Cast to string first.
    return str_replace("'", "''", (string)$s);
  }
}

// Cek apakah user adalah admin yang valid
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Redirect ke login jika bukan admin
    header('Location: login.php');
    exit;
}

// Set $activePage untuk sidebar
$activePage = 'transaksi';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>GreenPoint • Transaksi</title>

  <!-- Font & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet" />

  <!-- Main CSS -->
  <link rel="stylesheet" href="./assets/css/style.css" />
  <style>
    :root {
      --green-primary: #10b981;
      --green-light: #d1fae5;
      --gray-light: #f9fafb;
      --gray-border: #e5e7eb;
      --text-dark: #111827;
      --text-gray: #6b7280;
    }
    
    .card {
      background: white;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      margin-bottom: 24px;
    }
    
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .card-title {
      font-size: 20px;
      font-weight: 600;
      color: var(--text-dark);
    }
    
    .card-subtitle {
      font-size: 12px;
      color: var(--text-gray);
    }
    
    .table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .table th {
      background-color: var(--gray-light);
      color: var(--text-gray);
      font-weight: 600;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid var(--gray-border);
    }
    
    .table td {
      padding: 16px;
      border-bottom: 1px solid var(--gray-border);
      font-size: 14px;
      color: var(--text-dark);
    }
    
    .table tr:hover {
      background-color: #f9fafb;
    }
    
    .status-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .status-menunggu {
      background-color: #fef3c7;
      color: #d97706;
    }
    
    .status-berhasil {
      background-color: #d1fae5;
      color: #065f46;
    }
    
    .status-tolak {
      background-color: #fee2e2;
      color: #dc2626;
    }
    
    .btn {
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      border: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }
    
    .btn-sm {
      padding: 6px 12px;
      font-size: 12px;
    }
    
    .btn-success {
      background-color: var(--green-primary);
      color: white;
    }
    
    .btn-success:hover {
      background-color: #059669;
    }
    
    .btn-danger {
      background-color: #ef4444;
      color: white;
    }
    
    .btn-danger:hover {
      background-color: #dc2626;
    }
    
    .btn-info {
      background-color: #3b82f6;
      color: white;
    }
    
    .btn-info:hover {
      background-color: #2563eb;
    }
    
    .btn-secondary {
      background-color: #9ca3af;
      color: white;
    }
    
    .btn-secondary:hover {
      background-color: #6b7280;
    }
    
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: var(--text-gray);
    }
    
    .action-group {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    /* Inline card-scoped modal: positioned inside the card container */
    .modal.in-card {
      position: absolute;
      inset: 0; /* cover the card area */
      background: transparent; /* no dark overlay inside the card */
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      padding: 12px; /* allow breathing room inside card */
    }

    /* ensure modal content is centered within the in-card overlay */
    .modal.in-card .modal-content {
      margin: auto;
      box-shadow: 0 6px 20px rgba(0,0,0,0.08); /* subtle shadow only on the dialog */
    }
    
    .modal-content {
      background: white;
      padding: 24px;
      border-radius: 12px;
      width: 90%;
      max-width: 400px;
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .modal-title {
      font-size: 18px;
      font-weight: 600;
      color: var(--text-dark);
    }
    
    .modal-close {
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text-gray);
      font-size: 20px;
    }
    
    textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid var(--gray-border);
      border-radius: 8px;
      font-size: 14px;
      resize: vertical;
      min-height: 100px;
    }
    
    .alert {
      background: #d1fae5;
      color: #065f46;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .alert-error {
      background: #fee2e2;
      color: #991b1b;
    }
  </style>
</head>

<body>
  <div class="app">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="main">
      <h2>Transaksi</h2>

      <section class="grid" style="gap: 24px; margin-top: 10px;">

        <!-- Daftar Permintaan Setor Sampah -->
        <div class="card col-12" style="position: relative;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3>Permintaan Setor Sampah</h3>
            <small class="card-subtitle">Tinjau dan proses permintaan setor sampah</small>
          </div>

          <!-- Alert untuk notifikasi -->
          <?php if (isset($_GET['success']) && $_GET['success'] === 'approved'): ?>
            <div class="alert">
              <i data-lucide="check-circle"></i>
              <span>Setor sampah berhasil disetujui!</span>
            </div>
          <?php elseif (isset($_GET['success']) && $_GET['success'] === 'rejected'): ?>
            <div class="alert alert-error">
              <i data-lucide="x-circle"></i>
              <span>Setor sampah berhasil ditolak!</span>
            </div>
          <?php endif; ?>

          <div style="overflow-x: auto;">
            <table class="table" style="margin-top: 16px;">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nasabah</th>
                  <th>Jenis</th>
                  <th>Total Berat</th>
                  <th>Total Nilai</th>
                  <th>Tanggal</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Fetch data from transaksi_setor
                if (file_exists(__DIR__ . '/../../server/supabase.php') && function_exists('supabase_request')) {
                  $response = supabase_request('GET', '/rest/v1/transaksi_setor?select=*&status=eq.menunggu&order=tanggal_setor.desc', null, true);
                  
                  if ($response && isset($response['status']) && $response['status'] >= 200 && $response['status'] < 300) {
                    $rows = $response['body'] ?? [];
                    
                    if (empty($rows)) {
                      echo '<tr><td colspan="7" class="empty-state">Tidak ada permintaan setor sampah yang menunggu.</td></tr>';
                    } else {
                      // Pre-fetch details untuk semua transaksi
                      $details_map = [];
                      $tx_ids = array_column($rows, 'id_transaksi');
                      if (!empty($tx_ids)) {
                        // pg_escape_string may not be available (no pgsql ext). Use a safe fallback
                        $id_list = implode(',', array_map(function($id) {
                          $s = (string)$id;
                          // double single quotes for SQL literal safety
                          $s = str_replace("'", "''", $s);
                          return "'" . $s . "'";
                        }, $tx_ids));
                        $detail_resp = supabase_request('GET', '/rest/v1/detail_setor?select=id_transaksi,id_jenis,berat_kg&id_transaksi=in.(' . $id_list . ')', null, true);
                        if ($detail_resp && isset($detail_resp['status']) && $detail_resp['status'] >= 200 && $detail_resp['status'] < 300) {
                          $detail_rows = $detail_resp['body'] ?? [];
                          foreach ($detail_rows as $dr) {
                            $tid = $dr['id_transaksi'] ?? null;
                            if ($tid) {
                              if (!isset($details_map[$tid])) $details_map[$tid] = [];
                              $details_map[$tid][] = $dr;
                            }
                          }
                          // Ambil jenis sampah untuk semua detail
                          $jenis_ids = [];
                          foreach ($detail_rows as $dr) {
                            if (!empty($dr['id_jenis'])) $jenis_ids[] = $dr['id_jenis'];
                          }
                          $jenis_ids = array_unique($jenis_ids);
                          $jenis_names = [];
                          if (!empty($jenis_ids)) {
                            $inJenis = implode(',', array_map('intval', $jenis_ids));
                            $jresp = supabase_request('GET', '/rest/v1/jenis_sampah?select=id_jenis,nama_jenis&id_jenis=in.(' . $inJenis . ')', null, true);
                            if ($jresp && isset($jresp['status']) && $jresp['status'] >= 200 && $jresp['status'] < 300) {
                              foreach ($jresp['body'] as $jj) {
                                $jenis_names[$jj['id_jenis']] = $jj['nama_jenis'];
                              }
                            }
                            // Attach names to details_map
                            foreach ($details_map as &$details) {
                              foreach ($details as &$detail) {
                                $detail['nama_jenis'] = $jenis_names[$detail['id_jenis']] ?? ('ID:' . ($detail['id_jenis'] ?? '-'));
                              }
                            }
                          }
                        }
                      }

                      // Ambil semua data nasabah untuk mapping
                      $nasabah_ids = array_column($rows, 'id_nasabah');
                      $nasabah_ids = array_unique($nasabah_ids);
                      $nasabah_map = [];

                      if (!empty($nasabah_ids)) {
                        $id_list = implode(',', $nasabah_ids);
                        $nasabah_r = supabase_request('GET', "/rest/v1/nasabah?select=id_nasabah,nama_nasabah&id_nasabah=in.($id_list)", null, true);
                        if ($nasabah_r && isset($nasabah_r['status']) && $nasabah_r['status'] >= 200 && $nasabah_r['status'] < 300) {
                          foreach ($nasabah_r['body'] as $n) {
                            $nasabah_map[$n['id_nasabah']] = $n['nama_nasabah'];
                          }
                        }
                      }

                      foreach ($rows as $tx) {
                        $id = htmlspecialchars($tx['id_transaksi'] ?? '');
                        echo "<tr id='row-setor-{$id}'>";
                        $nasabah_id = $tx['id_nasabah'] ?? '';
                        $nama_nasabah = $nasabah_map[$nasabah_id] ?? "ID: $nasabah_id";
                        $berat = number_format(floatval($tx['total_berat'] ?? 0), 2, ',', '.');
                        $nilai = number_format(floatval($tx['total_nilai'] ?? 0), 0, ',', '.');
                        $tanggal = date('d M Y H:i', strtotime($tx['tanggal_setor'] ?? ''));
                        $status = htmlspecialchars($tx['status'] ?? 'menunggu');

                        // determine status color and action availability
                        $status_raw = strtolower(trim($tx['status'] ?? 'menunggu'));
                        $status_class = 'status-menunggu';
                        if (in_array($status_raw, ['approved', 'success', 'berhasil'])) {
                          $status_class = 'status-berhasil';
                        } elseif (in_array($status_raw, ['rejected','failed','gagal'])) {
                          $status_class = 'status-tolak';
                        }

                        echo "<td><strong>#{$id}</strong></td>";
                        // build jenis summary from pre-fetched details_map
                        $jenis_summary = '';
                        $tx_id_raw = $tx['id_transaksi'] ?? null;
                        if ($tx_id_raw !== null && isset($details_map[$tx_id_raw]) && is_array($details_map[$tx_id_raw])) {
                          $parts = [];
                          foreach ($details_map[$tx_id_raw] as $dd) {
                            $parts[] = $dd['nama_jenis'] ?? ('ID:' . ($dd['id_jenis'] ?? '-'));
                          }
                          $parts = array_values(array_unique(array_filter($parts)));
                          if (!empty($parts)) $jenis_summary = htmlspecialchars(implode(', ', $parts));
                        } else if ($tx_id_raw !== null) {
                          // Fallback: fetch detail_setor for this transaksi and resolve jenis names
                          $fallback = [];
                          $dresp = supabase_request('GET', '/rest/v1/detail_setor?id_transaksi=eq.' . urlencode($tx_id_raw) . '&select=id_jenis,berat_kg', null, true);
                          if ($dresp && isset($dresp['status']) && $dresp['status'] >= 200 && $dresp['status'] < 300) {
                            $drows = $dresp['body'] ?? [];
                            $jenis_ids_local = [];
                            foreach ($drows as $dr) {
                              if (!empty($dr['id_jenis'])) $jenis_ids_local[] = $dr['id_jenis'];
                            }
                            $jenis_ids_local = array_values(array_unique($jenis_ids_local));
                            $jenis_names_local = [];
                            if (!empty($jenis_ids_local)) {
                              $inJenisLocal = implode(',', array_map('intval', $jenis_ids_local));
                              $jresp_local = supabase_request('GET', '/rest/v1/jenis_sampah?select=id_jenis,nama_jenis&id_jenis=in.(' . $inJenisLocal . ')', null, true);
                              if ($jresp_local && isset($jresp_local['status']) && $jresp_local['status'] >= 200 && $jresp_local['status'] < 300) {
                                foreach ($jresp_local['body'] as $jjl) {
                                  $jenis_names_local[$jjl['id_jenis']] = $jjl['nama_jenis'];
                                }
                              }
                            }
                            foreach ($drows as $dr) {
                              $fallback[] = $jenis_names_local[$dr['id_jenis']] ?? ('ID:' . ($dr['id_jenis'] ?? '-'));
                            }
                            $fallback = array_values(array_unique(array_filter($fallback)));
                            if (!empty($fallback)) $jenis_summary = htmlspecialchars(implode(', ', $fallback));
                          }
                        }
                        echo "<td>{$nama_nasabah}</td>";
                        echo "<td>{$jenis_summary}</td>";
                        echo "<td>{$berat} kg</td>";
                        echo "<td>Rp {$nilai}</td>";
                        echo "<td>{$tanggal}</td>";
                        echo "<td><span class='status-badge {$status_class}'>" . ucfirst($status) . "</span></td>";

                        // Actions: only show approve/reject when pending
                        echo "<td>";
                        $is_pending = in_array($status_raw, ['menunggu','pending','wait','waiting']);
                        if ($is_pending) {
                          echo "<div class='action-group'>";
                          echo "<form method='POST' action='transaksi_action.php' style='margin: 0;'>";
                          echo "<input type='hidden' name='id_transaksi' value='{$id}'>";
                          echo "<input type='hidden' name='csrf_token' value='{$csrf_token}'>";
                          echo "<input type='hidden' name='action' value='approve_deposit'>";
                          echo "<button type='submit' class='btn btn-success btn-sm'>";
                          echo "<i data-lucide='check' style='width: 14px; height: 14px;'></i> Setujui";
                          echo "</button>";
                          echo "</form>";
                          echo "<button type='button' class='btn btn-danger btn-sm' onclick=\"showRejectModal('setor', {$id})\">";
                          echo "<i data-lucide='x' style='width: 14px; height: 14px;'></i> Tolak";
                          echo "</button>";
                          echo "</div>";
                        } else {
                          // Already processed: show badge only
                          if (in_array($status_raw, ['approved','success','berhasil'])) {
                            echo "<span class='status-badge status-berhasil'>Berhasil</span>";
                          } else {
                            echo "<span class='status-badge status-tolak'>Tidak Setuju</span>";
                          }
                        }

                        // Tombol untuk lihat detail
                        echo "<button type='button' class='btn btn-info btn-sm' onclick='showDetailModal({$id})' style='margin-top: 8px; width: 100%; display: flex; align-items: center; gap: 4px; justify-content: center;'>";
                        echo "<i data-lucide='eye' style='width: 14px; height: 14px;'></i> Lihat Detail";
                        echo "</button>";

                        echo "</td>";
                        echo "</tr>";
                      }
                    }
                  } else {
                    echo '<tr><td colspan="7" class="empty-state">Gagal memuat data transaksi.</td></tr>';
                  }
                } else {
                  echo '<tr><td colspan="7" class="empty-state">Sistem database tidak tersedia.</td></tr>';
                }
                ?>
              </tbody>
            </table>

            <!-- Modal untuk alasan penolakan setor (scoped inside this card) -->
            <div id="rejectModalSetor" class="modal in-card">
              <div class="modal-content">
                <div class="modal-header">
                  <h3 class="modal-title">Alasan Penolakan</h3>
                  <button class="modal-close" onclick="document.getElementById('rejectModalSetor').style.display='none'">&times;</button>
                </div>
                <form id="rejectFormSetor" method="POST" action="transaksi_action.php">
                  <input type="hidden" name="id_transaksi" id="rejectIdSetor">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                  <input type="hidden" name="action" value="reject_deposit">
                  <div style="margin-bottom: 16px;">
                    <textarea name="admin_note" placeholder="Masukkan alasan penolakan..." required></textarea>
                  </div>
                  <div style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('rejectModalSetor').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-danger">Konfirmasi Tolak</button>
                  </div>
                </form>
              </div>
            </div>

            <!-- Modal untuk detail transaksi (scoped inside this card) -->
            <div id="detailModal" class="modal in-card">
              <div class="modal-content" style="max-width: 500px; max-height: 80vh; overflow-y: auto;">
                <div class="modal-header">
                  <h3 class="modal-title">Detail Transaksi</h3>
                  <button class="modal-close" onclick="document.getElementById('detailModal').style.display='none'">&times;</button>
                </div>
                <div id="detailContent">
                  <!-- Detail akan diisi via JavaScript -->
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Riwayat Penarikan & Setor Sampah -->
        <div class="card col-12">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3>Riwayat Penarikan & Setor</h3>
            <small class="card-subtitle">Riwayat terakhir nasabah menarik atau setor</small>
          </div>

          <div style="overflow-x: auto;">
            <table class="table" style="margin-top: 16px;">
              <thead>
                <tr>
                  <th>Waktu</th>
                  <th>Nama Nasabah</th>
                  <th>Jumlah (Rp)</th>
                  <th>Sumber / Keterangan</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Combine recent penarikan and transaksi (setor) into a single history list
                if (file_exists(__DIR__ . '/../../server/supabase.php') && function_exists('supabase_request')) {
                  $hist = [];

                  // fetch penarikan (use canonical column names from schema)
                  $r1 = supabase_request('GET', '/rest/v1/penarikan?select=id_penukaran,id_nasabah,jenis_penukaran,nominal,deskripsi,tanggal_pengajuan&order=tanggal_pengajuan.desc&limit=100', null, true);
                  $penarikan = ($r1 && isset($r1['status']) && $r1['status'] >= 200 && $r1['status'] < 300) ? ($r1['body'] ?? []) : [];
                  foreach ($penarikan as $p) {
                    $hist[] = [
                      'type' => 'penarikan',
                      'id' => $p['id_penukaran'] ?? null,
                      'id_nasabah' => $p['id_nasabah'] ?? null,
                      'amount' => isset($p['nominal']) ? floatval($p['nominal']) : 0,
                      'source' => ($p['jenis_penukaran'] ?? '') . ' - ' . ($p['deskripsi'] ?? ''),
                      'created_at' => $p['tanggal_pengajuan'] ?? null,
                    ];
                  }

                  // fetch transaksi (setor) - canonical transaksi table
                  $r2 = supabase_request('GET', '/rest/v1/transaksi?select=id,id_nasabah,jenis,total,created_at&order=created_at.desc&limit=100', null, true);
                  $trans = ($r2 && isset($r2['status']) && $r2['status'] >= 200 && $r2['status'] < 300) ? ($r2['body'] ?? []) : [];
                  foreach ($trans as $t) {
                    $hist[] = [
                      'type' => 'setor',
                      'id' => $t['id'] ?? null,
                      'id_nasabah' => $t['id_nasabah'] ?? null,
                      'amount' => isset($t['total']) ? floatval($t['total']) : 0,
                      'source' => isset($t['jenis']) ? ('Setor: ' . $t['jenis']) : 'Setor Sampah',
                      'created_at' => $t['created_at'] ?? null,
                    ];
                  }

                  // fetch transaksi_setor (raw setor requests) and include them too
                  $r3 = supabase_request('GET', '/rest/v1/transaksi_setor?select=id_transaksi,id_nasabah,total_nilai,tanggal_setor,status&order=tanggal_setor.desc&limit=100', null, true);
                  $txset = ($r3 && isset($r3['status']) && $r3['status'] >= 200 && $r3['status'] < 300) ? ($r3['body'] ?? []) : [];
                  foreach ($txset as $ts) {
                    $hist[] = [
                      'type' => 'setor_raw',
                      'id' => $ts['id_transaksi'] ?? null,
                      'id_nasabah' => $ts['id_nasabah'] ?? null,
                      'amount' => isset($ts['total_nilai']) ? floatval($ts['total_nilai']) : 0,
                      'source' => 'Setor Sampah (permintaan)',
                      'created_at' => $ts['tanggal_setor'] ?? null,
                    ];
                  }

                  // sort combined by created_at desc
                  usort($hist, function ($a, $b) {
                    $ta = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
                    $tb = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
                    return $tb <=> $ta;
                  });

                  // Only show the latest 5 entries
                  $hist = array_slice($hist, 0, 5);

                  // get unique nasabah ids and fetch names in bulk
                  $ids = [];
                  foreach ($hist as $h) {
                    if (!empty($h['id_nasabah'])) $ids[] = $h['id_nasabah'];
                  }
                  $ids = array_values(array_unique($ids));
                  $nameMap = [];
                  if (!empty($ids)) {
                    // build in-list (assume numeric ids)
                    $inList = implode(',', array_map('intval', $ids));
                    $nr = supabase_request('GET', '/rest/v1/nasabah?select=id_nasabah,nama_nasabah&id_nasabah=in.(' . $inList . ')', null, true);
                    if ($nr && isset($nr['status']) && $nr['status'] >= 200 && $nr['status'] < 300) {
                      foreach ($nr['body'] as $nn) {
                        $nameMap[$nn['id_nasabah']] = $nn['nama_nasabah'];
                      }
                    }
                  }

                  if (empty($hist)) {
                    echo '<tr><td colspan="4" class="empty-state">Belum ada riwayat penarikan atau setor.</td></tr>';
                  } else {
                    foreach ($hist as $h) {
                      $time = $h['created_at'] ? date('d M Y H:i', strtotime($h['created_at'])) : '-';
                      $nid = $h['id_nasabah'] ?? '-';
                      $name = $nameMap[$nid] ?? $nid;
                      $amt = 'Rp ' . number_format((float)$h['amount'], 0, ',', '.');
                      $src = htmlspecialchars($h['source'] ?? '-');
                      echo "<tr>\n";
                      echo "<td>" . htmlspecialchars($time) . "</td>\n";
                      echo "<td>" . htmlspecialchars($name) . "</td>\n";
                      echo "<td>" . htmlspecialchars($amt) . "</td>\n";
                      echo "<td>" . $src . "</td>\n";
                      echo "</tr>\n";
                    }
                  }
                } else {
                  echo '<tr><td colspan="4" class="empty-state">Riwayat belum tersedia (Supabase helper hilang).</td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Daftar Permintaan Penarikan -->
        <div class="card col-12" style="position: relative;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3>Permintaan Penarikan</h3>
            <small class="card-subtitle">Tinjau dan proses permintaan penarikan nasabah</small>
          </div>

          <div style="overflow-x: auto;">
            <table class="table" style="margin-top: 16px;">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nasabah</th>
                  <th>Tujuan</th>
                  <th>Jumlah (Rp)</th>
                  <th>Deskripsi</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody id="penarikan-body">
                <?php
                if (file_exists(__DIR__ . '/../../server/supabase.php')) {
                  // reuse supabase helper
                  $rows = [];
                  // Query penarikan using schema columns
                  $r = supabase_request('GET', '/rest/v1/penarikan?select=id_penukaran,id_nasabah,jenis_penukaran,nominal,deskripsi,status,tanggal_pengajuan&order=tanggal_pengajuan.desc&limit=200', null, true);
                  if ($r && isset($r['status']) && $r['status'] >= 200 && $r['status'] < 300) {
                    $rows = is_array($r['body']) ? $r['body'] : [];
                  }
                  if (empty($rows)) {
                    echo '<tr><td colspan="6" class="empty-state">Tidak ada permintaan penarikan.</td></tr>';
                  } else {
                    foreach ($rows as $p) {
                      // canonical fields from schema
                      $raw_id = $p['id_penukaran'] ?? null;
                      $id = htmlspecialchars($raw_id ?? '');
                      echo "<tr id='row-penarikan-{$id}'>\n";
                      $nas = htmlspecialchars($p['id_nasabah'] ?? '');
                      $jenis = htmlspecialchars($p['jenis_penukaran'] ?? '');
                      $jumlah = htmlspecialchars($p['nominal'] ?? '');
                      // Normalize and dedupe description parts for display (collapse repeated Admin notes)
                      $raw_desc = $p['deskripsi'] ?? '';
                      if ($raw_desc !== '') {
                        $parts = array_map('trim', explode(' | ', $raw_desc));
                        $parts = array_values(array_unique($parts));
                        $display_desc = htmlspecialchars(implode(' | ', $parts));
                      } else {
                        $display_desc = '';
                        $parts = [];
                      }
                      echo "<td>{$id}</td>\n";
                      echo "<td>{$nas}</td>\n";
                      echo "<td>{$jenis}</td>\n";
                      echo "<td>Rp " . number_format((float)$jumlah, 0, ',', '.') . "</td>\n";
                      echo "<td>{$display_desc}</td>\n";

                      // Determine status and show buttons only for pending items
                      $status_raw = strtolower(trim($p['status'] ?? 'menunggu'));
                      $is_pending = in_array($status_raw, ['menunggu', 'pending', 'wait', 'waiting']);

                      echo "<td>";
                      if ($is_pending) {
                        echo "<div class='action-group'>";
                        echo "<button type='button' class='btn btn-success btn-sm' onclick=\"showApproveModal('" . $id . "')\">";
                        echo "<i data-lucide='check' style='width:14px;height:14px;'></i> Setuju";
                        echo "</button>";

                        // Reject button for penarikan - label changed to 'Tidak Setuju'
                        echo "<button type='button' class='btn btn-danger btn-sm' onclick=\"showRejectModal('penarikan', '{$id}')\">";
                        echo "<i data-lucide='x' style='width:14px;height:14px;'></i> Tidak Setuju";
                        echo "</button>";
                        echo "</div>";

                        // Approve modal markup (scoped inside card)
                        echo "<div id='approveModal{$id}' class='modal in-card'>";
                        echo "<div class='modal-content'>";
                        echo "<h4 style='margin-bottom: 12px;'>Konfirmasi Setuju</h4>";
                        echo "<form method='POST' action='transaksi_action.php'>";
                        echo "<input type='hidden' name='id_penukaran' value='" . $id . "'>";
                        echo "<input type='hidden' name='action' value='approve'>";
                        echo "<input type='hidden' name='csrf_token' value='{$csrf_token}'>";
                        echo "<div style='margin-bottom:12px;'>";
                        echo "<label for='admin_note_{$id}' style='display:block; margin-bottom:6px;'>Catatan (opsional)</label>";
                        echo "<textarea id='admin_note_{$id}' name='admin_note' placeholder='Masukkan catatan atau keterangan...' style='width:100%; padding:12px; border:1px solid #e5e7eb; border-radius:8px; min-height:80px;'></textarea>";
                        echo "</div>";
                        echo "<div style='display:flex; gap:8px; justify-content:flex-end;'>";
                        echo "<button type='button' class='btn btn-secondary btn-sm' onclick=\"document.getElementById('approveModal{$id}').style.display='none'\">Batal</button>";
                        echo "<button type='submit' class='btn btn-success btn-sm'>Konfirmasi Setuju</button>";
                        echo "</div>";
                        echo "</form>";
                        echo "</div>";
                        echo "</div>";

                        // Reject modal for penarikan (scoped inside card)
                        echo "<div id='rejectModalPenarikan{$id}' class='modal in-card'>";
                        echo "<div class='modal-content'>";
                        echo "<h4 style='margin-bottom: 12px;'>Konfirmasi Tidak Setuju</h4>";
                        echo "<form method='POST' action='transaksi_action.php'>";
                        echo "<input type='hidden' name='id_penukaran' value='" . $id . "'>";
                        echo "<input type='hidden' name='action' value='reject'>";
                        echo "<input type='hidden' name='csrf_token' value='{$csrf_token}'>";
                        echo "<div style='margin-bottom:12px;'>";
                        echo "<label for='admin_note_reject_{$id}' style='display:block; margin-bottom:6px;'>Alasan Penolakan</label>";
                        echo "<textarea id='admin_note_reject_{$id}' name='admin_note' placeholder='Masukkan alasan penolakan...' required style='width:100%; padding:12px; border:1px solid #e5e7eb; border-radius:8px; min-height:80px;'></textarea>";
                        echo "</div>";
                        echo "<div style='display:flex; gap:8px; justify-content:flex-end;'>";
                        echo "<button type='button' class='btn btn-secondary btn-sm' onclick=\"document.getElementById('rejectModalPenarikan{$id}').style.display='none'\">Batal</button>";
                        echo "<button type='submit' class='btn btn-danger btn-sm'>Konfirmasi Tidak Setuju</button>";
                        echo "</div>";
                        echo "</form>";
                        echo "</div>";
                        echo "</div>";
                      } else {
                        // Already processed: display a badge and optionally the admin note
                        if (in_array($status_raw, ['approved','success','berhasil'])) {
                          $badge = "<span class='status-badge status-berhasil'>Berhasil</span>";
                        } else {
                          $badge = "<span class='status-badge status-tolak'>Tidak Setuju</span>";
                        }
                        echo $badge;

                        // If there's an admin note, show it below the badge (extract parts starting with 'Admin:')
                        $admin_notes = [];
                        if (!empty($parts)) {
                          foreach ($parts as $pt) {
                            if (stripos($pt, 'Admin:') === 0) $admin_notes[] = trim(substr($pt, strlen('Admin:')));
                          }
                        }
                        if (!empty($admin_notes)) {
                          echo "<div style='margin-top:6px; font-size:12px; color:#374151;'>Catatan: " . htmlspecialchars(implode(' | ', $admin_notes)) . "</div>";
                        }
                      }
                      echo "</td>";
                      echo "</tr>\n";
                    }
                  }
                } else {
                  echo '<tr><td colspan="6" class="empty-state">Fitur penarikan belum tersedia (Supabase helper hilang).</td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

      </section>
    </main>
  </div>

  <script>
    function showRejectModal(type, id) {
      if (type === 'setor') {
        document.getElementById('rejectIdSetor').value = id;
        document.getElementById('rejectModalSetor').style.display = 'flex';
        // ensure the card is visible and modal is centered in viewport
        try { document.getElementById('rejectModalSetor').scrollIntoView({behavior: 'smooth', block: 'center'}); } catch(e){}
      } else if (type === 'penarikan') {
        const modal = document.getElementById('rejectModalPenarikan' + id);
        if (modal) modal.style.display = 'flex';
      }
    }

    function showApproveModal(id) {
      const modal = document.getElementById('approveModal' + id);
      if (modal) modal.style.display = 'flex';
    }

    function showDetailModal(id) {
      // Ambil data detail via AJAX
      fetch(`get_transaction_detail.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
          let detailContent = `
            <div style="margin-bottom: 16px;">
                <strong>ID Transaksi:</strong> #${data.id_transaksi}
            </div>
            <div style="margin-bottom: 16px;">
                <strong>Nasabah:</strong> ${data.nama_nasabah}
            </div>
            <div style="margin-bottom: 16px;">
                <strong>Total Berat:</strong> ${data.total_berat} kg
            </div>
            <div style="margin-bottom: 16px;">
                <strong>Total Nilai:</strong> Rp ${data.total_nilai.toLocaleString('id-ID')}
            </div>
            <div style="margin-bottom: 16px;">
                <strong>Tanggal:</strong> ${data.tanggal}
            </div>
            <div style="margin-bottom: 16px;">
                <strong>Status:</strong> <span class="status-badge status-menunggu">${data.status}</span>
            </div>
          `;

          if (data.items && data.items.length > 0) {
            detailContent += `
              <h5 style="margin-top: 20px; margin-bottom: 12px;">Detail Sampah:</h5>
              <table style="width: 100%; border-collapse: collapse;">
                  <thead>
                      <tr style="background: #f9fafb;">
                          <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Jenis</th>
                          <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Berat</th>
                          <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Harga/kg</th>
                          <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Subtotal</th>
                      </tr>
                  </thead>
                  <tbody>
            `;

            data.items.forEach(item => {
              detailContent += `
                  <tr>
                      <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">${item.nama_jenis}</td>
                      <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">${item.berat_kg} kg</td>
                      <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">Rp ${item.harga_per_kg.toLocaleString('id-ID')}</td>
                      <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">Rp ${item.subtotal.toLocaleString('id-ID')}</td>
                  </tr>
              `;
            });

            detailContent += `</tbody></table>`;
          }

                document.getElementById('detailContent').innerHTML = detailContent;
                document.getElementById('detailModal').style.display = 'flex';
                try { document.getElementById('detailModal').scrollIntoView({behavior: 'smooth', block: 'center'}); } catch(e){}
        })
        .catch(error => {
          console.error('Error:', error);
          document.getElementById('detailContent').innerHTML = 'Error loading details.';
          document.getElementById('detailModal').style.display = 'flex';
        });
    }

    // Close modal ketika klik di luar konten modal
    window.onclick = function(event) {
      const modals = document.getElementsByClassName('modal');
      for (let modal of modals) {
        if (event.target === modal) {
          modal.style.display = 'none';
        }
      }
    };

    // Intercept admin approve/reject form submissions and send via AJAX
    document.addEventListener('submit', function (e) {
      const form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      const actionUrl = (form.getAttribute('action') || '').split('/').pop();
      if (actionUrl !== 'transaksi_action.php') return; // only handle admin action forms

      e.preventDefault();
      // prevent duplicate submits
      if (form.dataset.submitted === '1') return;
      form.dataset.submitted = '1';
      const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
      submitButtons.forEach(b => b.disabled = true);

      const data = new FormData(form);
      // optimistic UI: hide the corresponding row immediately for snappier UX
      try {
        const maybePen = String(data.get('id_penukaran') || '').trim();
        const maybeTx = String(data.get('id_transaksi') || '').trim();
        const fadeRemove = (selectorPrefix, idVal) => {
          if (!idVal) return;
          let pid = idVal.replace(/^['\"]|['\"]$/g, '');
          let r = document.getElementById(selectorPrefix + pid);
          if (!r) {
            const candidates = document.querySelectorAll('[id^="' + selectorPrefix + '"]');
            for (const c of candidates) { if (c.id.endsWith('-' + pid) || c.id === (selectorPrefix + pid)) { r = c; break; } }
          }
          if (r) {
            r.style.transition = 'opacity 250ms ease, height 250ms ease, margin 250ms ease';
            r.style.opacity = '0'; r.style.height = '0'; r.style.margin = '0';
            setTimeout(() => { try { r.remove(); } catch(e){} }, 300);
          }
        };
        if (maybePen) fadeRemove('row-penarikan-', maybePen);
        else if (maybeTx) fadeRemove('row-setor-', maybeTx);
      } catch (e) { console.warn('optimistic remove failed', e); }

      fetch(form.getAttribute('action'), {
        method: 'POST',
        credentials: 'same-origin',
        body: data
      }).then(async r => {
        const text = await r.text();
        let json = null;
        try {
          json = text ? JSON.parse(text) : null;
        } catch (e) {
          // If server returned non-JSON but HTTP OK, treat as success
          if (r.ok) {
            json = { status: 'ok', message: 'OK (non-JSON response)' };
          } else {
            submitButtons.forEach(b => b.disabled = false);
            form.dataset.submitted = '0';
            alert('Aksi gagal: respon server tidak valid:\n' + text + '\nHalaman akan direfresh.');
            window.location.reload();
            return;
          }
        }

        if (!json || json.status !== 'ok') {
          // re-enable on failure so admin can retry
          submitButtons.forEach(b => b.disabled = false);
          form.dataset.submitted = '0';
          // show error then reload to restore UI consistency
          alert('Aksi gagal: ' + (json?.message || 'Tidak diketahui') + '\nHalaman akan direfresh untuk menyelaraskan data.');
          window.location.reload();
          return;
        }

        // Determine which id was submitted
        const id_penukaran = data.get('id_penukaran');
        const id_transaksi = data.get('id_transaksi');
        const action = data.get('action');

        // sanitize ids
        const sanitize = s => s ? String(s).trim().replace(/^['\"]|['\"]$/g, '') : null;
        const pid = sanitize(id_penukaran);
        const sid = sanitize(id_transaksi);

        if (pid) {
          // ensure any remaining row is removed
          let row = document.getElementById('row-penarikan-' + pid);
          if (!row) {
            const candidates = document.querySelectorAll('[id^="row-penarikan-"]');
            for (const c of candidates) { if (c.id.endsWith('-' + pid) || c.id === ('row-penarikan-' + pid)) { row = c; break; } }
          }
          if (row) {
            try { row.remove(); } catch(e) { /* ignore */ }
          }
          // also try to hide any open modal for this id
          const appModal = document.getElementById('approveModal' + pid);
          if (appModal) appModal.style.display = 'none';
          const rejModal = document.getElementById('rejectModalPenarikan' + pid) || document.getElementById('rejectModal' + pid) || document.getElementById('rejectModalSetor' + pid);
          if (rejModal) rejModal.style.display = 'none';
        } else if (sid) {
          // sanitize id for setor as well
          let sid = String(id_transaksi).trim().replace(/^['\"]|['\"]$/g, '');
          let srow = document.getElementById('row-setor-' + sid);
          if (!srow) {
            const sc = document.querySelectorAll('[id^="row-setor-"]');
            for (const c of sc) {
              if (c.id.endsWith('-' + sid) || c.id === ('row-setor-' + sid)) { srow = c; break; }
            }
          }
          if (srow) {
            srow.style.transition = 'opacity 250ms ease, height 250ms ease, margin 250ms ease';
            srow.style.opacity = '0';
            srow.style.height = '0';
            srow.style.margin = '0';
            setTimeout(() => { try { srow.remove(); } catch (e){} }, 300);
          }
          const rejModal = document.getElementById('rejectModalSetor' + sid) || document.getElementById('rejectModalPenarikan' + sid) || document.getElementById('rejectModal' + sid);
          if (rejModal) rejModal.style.display = 'none';
        }

      }).catch(err => {
        console.error(err);
        // re-enable on network error
        try { submitButtons.forEach(b => b.disabled = false); } catch(e){}
        form.dataset.submitted = '0';
        alert('Terjadi kesalahan saat memproses aksi');
      });
    });
  </script>
</body>
</html>