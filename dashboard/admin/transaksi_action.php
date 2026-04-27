<?php
// transaksi_action.php
// Handles approve/reject actions for deposits and withdrawals.
// Response: JSON { status: 'ok'|'error', message: '...' }

include __DIR__ . '/../../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
// include Supabase helper when available
$supabase_helper = __DIR__ . '/../../server/supabase.php';
if (file_exists($supabase_helper)) include_once $supabase_helper;

// Try to verify Firebase ID token early so we can skip CSRF for API clients
$isFirebaseAdmin = false;
$isAdminSession = false;
$fbHelper = __DIR__ . '/../../server/supabase.php';
if (file_exists($fbHelper)) {
  include_once $fbHelper;
  // supabase_is_admin_request will inspect headers / POST for a Bearer token
  if (function_exists('supabase_is_admin_request')) {
    $fv = supabase_is_admin_request();
    if (!empty($fv['ok'])) {
      $isFirebaseAdmin = true;
      $_SESSION['admin_uid'] = $fv['user']['id'] ?? null;
    }
  }
}

header('Content-Type: application/json; charset=utf-8');

// Helper
function res($status, $message) {
  echo json_encode(['status'=>$status, 'message'=>$message]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  res('error', 'Metode harus POST');
}

// CSRF check: only required for requests that are NOT authenticated via
// a valid Firebase admin ID token (API clients using Bearer tokens).
if (!$isFirebaseAdmin) {
  $csrf = $_POST['csrf_token'] ?? '';
  if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    res('error', 'Token CSRF tidak valid');
  }
}

// Basic admin check - allow either PHP session-admin OR valid Firebase admin ID token
$isAdminSession = !(empty($_SESSION['admin_logged_in']) && empty($_SESSION['is_admin']));
if (!$isAdminSession && !$isFirebaseAdmin) {
  res('error', 'Akses ditolak. Silakan login sebagai admin.');
}

$id = $_POST['id_transaksi'] ?? $_POST['id_penukaran'] ?? null;
$action = $_POST['action'] ?? '';

if (empty($id) || empty($action)) {
  res('error', 'Parameter kurang');
}

$id = intval($id);
  $allowed = ['approve_deposit','reject_deposit','approve','reject','approve_detail','reject_detail'];
if (!in_array($action, $allowed)) {
  res('error', 'Aksi tidak dikenal');
}

// Use Supabase REST if available
if (function_exists('supabase_request')) {
  // If this is a penarikan action (approve/reject), operate on penarikan table
  if (in_array($action, ['approve', 'reject'])) {
    // Fetch penarikan row by id_penukaran
    $getp = supabase_request('GET', '/rest/v1/penarikan?id_penukaran=eq.' . urlencode($id) . '&select=*', null, true);
    if (!$getp || !isset($getp['status'])) {
      $msg = 'Gagal menghubungi backend penarikan.';
      res('error', $msg);
    }
    if ($getp['status'] >= 400) {
      $raw = $getp['raw'] ?? '';
      $snippet = strlen($raw) > 400 ? substr($raw, 0, 400) . '...' : $raw;
      $msg = 'Layanan penarikan tidak tersedia. (' . $getp['status'] . ') ' . $snippet;
      res('error', $msg);
    }
    $rows = $getp['body'] ?? [];
    if (!is_array($rows) || count($rows) === 0) {
      res('error', 'Permintaan penarikan tidak ditemukan.');
    }
    $p = $rows[0];

    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    // If approving a penarikan, ensure nasabah currently has enough saldo first.
    $id_nasabah = $p['id_nasabah'] ?? null;
    $penarikan_nominal = isset($p['nominal']) ? (float)$p['nominal'] : 0;
    if ($action === 'approve') {
      if (empty($id_nasabah)) {
        res('error', 'Data nasabah tidak tersedia untuk penarikan ini.');
      }
      $rbal = supabase_request('GET', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id_nasabah) . '&select=saldo', null, true);
      if (!$rbal || !isset($rbal['status']) || $rbal['status'] >= 400) {
        res('error', 'Gagal membaca saldo nasabah sebelum approve.');
      }
      $bbody = $rbal['body'] ?? [];
      $curr_bal = (is_array($bbody) && count($bbody) > 0) ? (float)($bbody[0]['saldo'] ?? 0) : 0;
      if ($curr_bal < $penarikan_nominal) {
        res('error', 'Saldo nasabah tidak mencukupi untuk menyetujui penarikan ini.');
      }
    }

    $patch = [
      'status' => $new_status,
      'tanggal_proses' => date('Y-m-d H:i:s')
    ];
    $admin_note = trim($_POST['admin_note'] ?? '');
    if (!empty($_SESSION['admin_id'])) $patch['id_admin'] = $_SESSION['admin_id'];
    if (!empty($_SESSION['admin_uid'])) $patch['id_admin'] = $_SESSION['admin_uid'];
    if (!empty($admin_note)) {
      $existing_desc = !empty($p['deskripsi']) ? $p['deskripsi'] : '';
      $append = trim($admin_note);
      // Avoid appending the same admin note repeatedly. If the exact
      // "Admin: {note}" already exists in the description, skip appending.
      if ($existing_desc === '') {
        $new_desc = $append;
      } else {
        // Normalize check (case-insensitive search)
        $needle = 'Admin: ' . $append;
        if (stripos($existing_desc, $needle) === false) {
          $new_desc = $existing_desc . ' | Admin: ' . $append;
        } else {
          // already present, keep existing description unchanged
          $new_desc = $existing_desc;
        }
      }
      // Normalize and remove duplicate description parts (e.g., repeated "Admin: ...")
      $parts = array_map('trim', explode(' | ', $new_desc));
      $parts = array_values(array_unique($parts));
      $clean_desc = implode(' | ', $parts);
      $patch['deskripsi'] = $clean_desc;
    }

    $upd = supabase_request('PATCH', '/rest/v1/penarikan?id_penukaran=eq.' . urlencode($id), $patch, true);
    // If PostgREST complains about missing tanggal_proses column, retry without it
    if (!$upd || !isset($upd['status']) || $upd['status'] >= 400) {
      $raw = $upd['raw'] ?? '';
      if (is_string($raw) && stripos($raw, "Could not find the 'tanggal_proses'") !== false) {
        error_log('[transaksi_action] penarikan PATCH failed due to missing tanggal_proses, retrying without that field');
        $patch2 = $patch;
        unset($patch2['tanggal_proses']);
        $upd2 = supabase_request('PATCH', '/rest/v1/penarikan?id_penukaran=eq.' . urlencode($id), $patch2, true);
        if ($upd2 && isset($upd2['status']) && $upd2['status'] >= 200 && $upd2['status'] < 300) {
          $upd = $upd2; // treat as success
        } else {
          $raw2 = $upd2['raw'] ?? '';
          $snippet2 = strlen($raw2) > 300 ? substr($raw2, 0, 300) . '...' : $raw2;
          res('error', 'Gagal mengupdate status penarikan. ' . $snippet2);
        }
      } else {
        $snippet = is_string($raw) ? (strlen($raw) > 300 ? substr($raw, 0, 300) . '...' : $raw) : '';
        res('error', 'Gagal mengupdate status penarikan. ' . $snippet);
      }
    }

    // Verify persisted
    $verify = supabase_request('GET', '/rest/v1/penarikan?id_penukaran=eq.' . urlencode($id) . '&select=status', null, true);
    $current = '';
    if ($verify && isset($verify['status']) && $verify['status'] >= 200 && $verify['status'] < 300) {
      $vb = $verify['body'] ?? [];
      $current = is_array($vb) && count($vb) > 0 ? ($vb[0]['status'] ?? '') : '';
    }
    if (strtolower($current) !== strtolower($new_status)) {
      res('error', 'Status penarikan tidak berubah di backend (expected: ' . $new_status . ', actual: ' . $current . ').');
    }

    // If approved, deduct nasabah saldo, create transaksi and notification
    if ($action === 'approve') {
      // attempt to deduct saldo (use the previously-read balance if available)
      $id_nasabah = $p['id_nasabah'] ?? null;
      $nominal = isset($p['nominal']) ? (float)$p['nominal'] : 0;
      // Re-read current saldo to reduce race window
      $rbal2 = supabase_request('GET', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id_nasabah) . '&select=saldo', null, true);
      $curr2 = 0;
      if ($rbal2 && isset($rbal2['status']) && $rbal2['status'] >= 200 && $rbal2['status'] < 300) {
        $bb = $rbal2['body'] ?? [];
        $curr2 = (is_array($bb) && count($bb) > 0) ? (float)($bb[0]['saldo'] ?? 0) : 0;
      }
      if ($curr2 < $nominal) {
        // Try to revert penarikan status back to pending to be safe
        supabase_request('PATCH', '/rest/v1/penarikan?id_penukaran=eq.' . urlencode($id), ['status' => 'menunggu'], true);
        res('error', 'Saldo nasabah tidak mencukupi saat memproses persetujuan.');
      }

      $newSaldo = $curr2 - $nominal;
      $patchSaldo = supabase_request('PATCH', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id_nasabah), ['saldo' => $newSaldo], true);
      if (!$patchSaldo || !isset($patchSaldo['status']) || $patchSaldo['status'] >= 400) {
        // rollback penarikan status
        supabase_request('PATCH', '/rest/v1/penarikan?id_penukaran=eq.' . urlencode($id), ['status' => 'menunggu'], true);
        $raws = $patchSaldo['raw'] ?? '';
        res('error', 'Gagal mengurangi saldo nasabah: ' . ($raws ? substr($raws,0,300) : 'unknown'));
      }

      // create transaksi record for nasabah
      $jenisStr = !empty($p['jenis_penukaran']) ? $p['jenis_penukaran'] : 'PPOB';
      $jenis = 'PPOB - ' . $jenisStr;
      $tx_payload = [
        'id_nasabah' => $id_nasabah,
        'jenis' => $jenis,
        'total' => $nominal,
        'status' => 'success',
        'created_at' => date('Y-m-d H:i:s')
      ];
      $ins = supabase_request('POST', '/rest/v1/transaksi', $tx_payload, true);
      if (!$ins || !isset($ins['status']) || $ins['status'] >= 400) {
        error_log('Gagal membuat transaksi PPOB: ' . print_r($ins, true));
      }

      // Create a notification for the nasabah about approval
      try {
        $noteTitle = 'PPOB: Transaksi Disetujui';
        $noteBody = 'Permintaan ' . $jenisStr . ' sebesar Rp ' . number_format($nominal,0,',','.') . ' telah disetujui oleh admin.';
        if (!empty($admin_note)) $noteBody .= ' Catatan: ' . $admin_note;
        $notif = [
          'id_nasabah' => $id_nasabah,
          'judul' => $noteTitle,
          'isi' => $noteBody,
          'jenis_notif' => 'ppob'
        ];
        supabase_request('POST', '/rest/v1/notifikasi', $notif, true);
      } catch (Exception $e) {
        // ignore notification errors
      }
    }

    // If rejected, create a notification for the nasabah about rejection
    if ($action === 'reject') {
      $id_nasabah = $p['id_nasabah'] ?? null;
      try {
        $noteTitle = 'PPOB: Transaksi Ditolak';
        $noteBody = 'Permintaan ' . ($p['jenis_penukaran'] ?? 'PPOB') . ' sebesar Rp ' . number_format((float)($p['nominal'] ?? 0),0,',','.') . ' ditolak oleh admin.';
        if (!empty($admin_note)) $noteBody .= ' Alasan: ' . $admin_note;
        $notif = [
          'id_nasabah' => $id_nasabah,
          'judul' => $noteTitle,
          'isi' => $noteBody,
          'jenis_notif' => 'ppob'
        ];
        supabase_request('POST', '/rest/v1/notifikasi', $notif, true);
      } catch (Exception $e) {
        // ignore
      }
    }

    res('ok', 'Aksi penarikan diproses');
  }

  // If this is a deposit/setor approval (transaksi_setor), handle it on transaksi_setor table
  if (in_array($action, ['approve_deposit','reject_deposit'])) {
    $getd = supabase_request('GET', '/rest/v1/transaksi_setor?id_transaksi=eq.' . urlencode($id) . '&select=*', null, true);
    if (!$getd || !isset($getd['status'])) {
      res('error', 'Gagal menghubungi backend transaksi_setor.');
    }
    if ($getd['status'] >= 400) {
      res('error', 'Layanan transaksi_setor tidak tersedia.');
    }
    $drows = $getd['body'] ?? [];
    if (!is_array($drows) || count($drows) === 0) {
      res('error', 'Transaksi setor tidak ditemukan.');
    }
    $tx = $drows[0];

    $new_status = ($action === 'approve_deposit') ? 'approved' : 'rejected';
    $patch = ['status' => $new_status, 'tanggal_proses' => date('Y-m-d H:i:s')];
    if (!empty($_SESSION['admin_id'])) $patch['id_admin'] = $_SESSION['admin_id'];
    if (!empty($_SESSION['admin_uid'])) $patch['id_admin'] = $_SESSION['admin_uid'];

    $upd = supabase_request('PATCH', '/rest/v1/transaksi_setor?id_transaksi=eq.' . urlencode($id), $patch, true);
    if (!$upd || !isset($upd['status']) || $upd['status'] >= 400) {
      $raw = $upd['raw'] ?? null;
      // If error indicates missing tanggal_proses column, retry without it
      if (is_string($raw) && stripos($raw, "Could not find the 'tanggal_proses'") !== false) {
        error_log('[transaksi_action] transaksi_setor PATCH failed due to missing tanggal_proses, retrying without that field');
        $patch2 = $patch;
        unset($patch2['tanggal_proses']);
        $upd2 = supabase_request('PATCH', '/rest/v1/transaksi_setor?id_transaksi=eq.' . urlencode($id), $patch2, true);
        if ($upd2 && isset($upd2['status']) && $upd2['status'] >= 200 && $upd2['status'] < 300) {
          $upd = $upd2;
        } else {
          $raw2 = $upd2['raw'] ?? '';
          $statusCode = $upd2['status'] ?? 0;
          $snippet = is_string($raw2) ? (strlen($raw2) > 800 ? substr($raw2, 0, 800) . '...' : $raw2) : '';
          error_log('[transaksi_action] update transaksi_setor failed (retry): ' . print_r($upd2, true));
          res('error', 'Gagal mengupdate status transaksi_setor. HTTP ' . $statusCode . ': ' . $snippet);
        }
      } else {
        $statusCode = $upd['status'] ?? 0;
        $snippet = is_string($raw) ? (strlen($raw) > 800 ? substr($raw, 0, 800) . '...' : $raw) : '';
        error_log('[transaksi_action] update transaksi_setor failed: ' . print_r($upd, true));
        res('error', 'Gagal mengupdate status transaksi_setor. HTTP ' . $statusCode . ': ' . $snippet);
      }
    }

    // Verify persisted
    $verify = supabase_request('GET', '/rest/v1/transaksi_setor?id_transaksi=eq.' . urlencode($id) . '&select=status', null, true);
    $current = '';
    if ($verify && isset($verify['status']) && $verify['status'] >= 200 && $verify['status'] < 300) {
      $vb = $verify['body'] ?? [];
      $current = is_array($vb) && count($vb) > 0 ? ($vb[0]['status'] ?? '') : '';
    }
    if (strtolower($current) !== strtolower($new_status)) {
      res('error', 'Status transaksi_setor tidak berubah di backend (expected: ' . $new_status . ', actual: ' . $current . ').');
    }

    // If approved, credit nasabah saldo and update jenis_sampah stok
    if ($action === 'approve_deposit') {
      $id_nasabah = $tx['id_nasabah'] ?? null;
      $total_nilai = isset($tx['total_nilai']) ? (float)$tx['total_nilai'] : 0;
      if ($id_nasabah && $total_nilai > 0) {
        // Fetch nasabah saldo
        $r2 = supabase_request('GET', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id_nasabah) . '&select=saldo', null, true);
        if ($r2 && isset($r2['status']) && $r2['status'] >= 200 && $r2['status'] < 300) {
          $nrows = $r2['body'] ?? [];
          if (is_array($nrows) && count($nrows) > 0) {
            $curr = (float)($nrows[0]['saldo'] ?? 0);
            $newSaldo = $curr + $total_nilai;
            supabase_request('PATCH', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id_nasabah), ['saldo' => $newSaldo], true);
          }
        }
      }

      // Update jenis_sampah stok based on detail_setor rows
      try {
        $dresp = supabase_request('GET', '/rest/v1/detail_setor?select=id_jenis,berat_kg&id_transaksi=eq.' . urlencode($id), null, true);
        if ($dresp && isset($dresp['status']) && $dresp['status'] >= 200 && $dresp['status'] < 300) {
          foreach ($dresp['body'] as $dr) {
            $id_jenis = $dr['id_jenis'] ?? null;
            $berat = isset($dr['berat_kg']) ? (float)$dr['berat_kg'] : 0;
            if ($id_jenis && $berat > 0) {
              // fetch current stok_kg
              $rj = supabase_request('GET', '/rest/v1/jenis_sampah?id_jenis=eq.' . urlencode($id_jenis) . '&select=stok_kg', null, true);
              if ($rj && isset($rj['status']) && $rj['status'] >= 200 && $rj['status'] < 300) {
                $jr = $rj['body'] ?? [];
                $curr_stok = is_array($jr) && count($jr) ? (float)($jr[0]['stok_kg'] ?? 0) : 0;
                $new_stok = $curr_stok + $berat;
                supabase_request('PATCH', '/rest/v1/jenis_sampah?id_jenis=eq.' . urlencode($id_jenis), ['stok_kg' => $new_stok], true);
              }
            }
          }
        }
      } catch (Exception $e) {
        // ignore stok update errors
      }

      // create notification
      try {
        $noteTitle = 'Setor: Disetujui';
        $noteBody = 'Setor sampah Anda senilai Rp ' . number_format($total_nilai,0,',','.') . ' telah disetujui oleh admin.';
        supabase_request('POST', '/rest/v1/notifikasi', ['id_nasabah'=>$id_nasabah,'judul'=>$noteTitle,'isi'=>$noteBody,'jenis_notif'=>'setor'], true);
      } catch (Exception $e) {}
    } else {
      // rejected: notify
      try {
        $noteTitle = 'Setor: Ditolak';
        $noteBody = 'Setor sampah Anda ditolak oleh admin.';
        $id_nasabah = $tx['id_nasabah'] ?? null;
        supabase_request('POST', '/rest/v1/notifikasi', ['id_nasabah'=>$id_nasabah,'judul'=>$noteTitle,'isi'=>$noteBody,'jenis_notif'=>'setor'], true);
      } catch (Exception $e) {}
    }

    res('ok', 'Aksi transaksi_setor diproses');
  }

  // Handle per-detail approve/reject for detail_setor rows
  if (in_array($action, ['approve_detail','reject_detail'])) {
    // Expect posted identifying fields: id_transaksi, id_jenis, berat_kg, subtotal
    $id_trans = $_POST['id_transaksi'] ?? null;
    $id_jenis = $_POST['id_jenis'] ?? null;
    $berat = isset($_POST['berat_kg']) ? floatval($_POST['berat_kg']) : (isset($_POST['berat']) ? floatval($_POST['berat']) : null);
    $subtotal = isset($_POST['subtotal']) ? floatval($_POST['subtotal']) : null;

    if (empty($id_trans) || empty($id_jenis) || $berat === null) {
      res('error', 'Parameter detail kurang: butuh id_transaksi,id_jenis,berat_kg');
    }

    // Find matching detail_setor rows (prefer status menunggu)
    $filter = '/rest/v1/detail_setor?id_transaksi=eq.' . urlencode($id_trans) . '&id_jenis=eq.' . urlencode($id_jenis);
    if ($subtotal !== null) $filter .= '&subtotal=eq.' . urlencode($subtotal);
    $dresp = supabase_request('GET', $filter . '&select=*', null, true);
    if (!$dresp || !isset($dresp['status']) || $dresp['status'] >= 400) {
      res('error', 'Gagal mencari detail_setor.');
    }
    $drows = $dresp['body'] ?? [];
    // Prefer to pick the first with status menunggu if available
    $target = null;
    foreach ($drows as $dr) {
      $s = strtolower(trim($dr['status'] ?? 'menunggu'));
      if ($s === 'menunggu') { $target = $dr; break; }
    }
    if ($target === null && count($drows) > 0) $target = $drows[0];
    if ($target === null) res('error', 'Detail setor tidak ditemukan.');

    // Build patch to update this detail's status
    $new_status = ($action === 'approve_detail') ? 'approved' : 'rejected';
    // Try to identify a primary key in the returned row. Common names: id, id_detail, id_detail_setor
    $pk_name = null; $pk_value = null;
    foreach (['id_detail','id','id_detail_setor','id_det'] as $cand) {
      if (isset($target[$cand])) { $pk_name = $cand; $pk_value = $target[$cand]; break; }
    }

    // If we have a primary key, patch by id. Otherwise patch by matching fields (may affect duplicates)
    if ($pk_name && $pk_value) {
      $patch_url = '/rest/v1/detail_setor?' . $pk_name . '=eq.' . urlencode($pk_value);
    } else {
      // fallback patch by composite filters
      $patch_url = '/rest/v1/detail_setor?id_transaksi=eq.' . urlencode($id_trans) . '&id_jenis=eq.' . urlencode($id_jenis) . '&berat_kg=eq.' . urlencode($berat);
      if ($subtotal !== null) $patch_url .= '&subtotal=eq.' . urlencode($subtotal);
    }

    $patch = ['status' => $new_status];
    if (!empty($_SESSION['admin_id'])) $patch['id_admin'] = $_SESSION['admin_id'];
    if (!empty($_SESSION['admin_uid'])) $patch['id_admin'] = $_SESSION['admin_uid'];
    $upd = supabase_request('PATCH', $patch_url, $patch, true);
    if (!$upd || !isset($upd['status']) || $upd['status'] >= 400) {
      res('error', 'Gagal mengupdate status detail_setor.');
    }

    // If approved -> increase jenis_sampah.stok_kg and credit nasabah saldo by subtotal, create transaksi per-item
    if ($action === 'approve_detail') {
      $id_nasabah = $target['id_nasabah'] ?? null;
      $id_jenis_t = $target['id_jenis'] ?? $id_jenis;
      $berat_t = isset($target['berat_kg']) ? floatval($target['berat_kg']) : floatval($berat);
      $subtotal_t = isset($target['subtotal']) ? floatval($target['subtotal']) : floatval($subtotal ?? 0);

      // update jenis_sampah stok
      try {
        $rj = supabase_request('GET', '/rest/v1/jenis_sampah?id_jenis=eq.' . urlencode($id_jenis_t) . '&select=stok_kg', null, true);
        if ($rj && isset($rj['status']) && $rj['status'] >= 200 && $rj['status'] < 300) {
          $jr = $rj['body'] ?? [];
          $curr_stok = is_array($jr) && count($jr) ? (float)($jr[0]['stok_kg'] ?? 0) : 0;
          $new_stok = $curr_stok + $berat_t;
          supabase_request('PATCH', '/rest/v1/jenis_sampah?id_jenis=eq.' . urlencode($id_jenis_t), ['stok_kg' => $new_stok], true);
        }
      } catch (Exception $e) {}

      // credit nasabah saldo by subtotal (if available)
      if (!empty($id_nasabah) && $subtotal_t > 0) {
        $r2 = supabase_request('GET', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id_nasabah) . '&select=saldo', null, true);
        if ($r2 && isset($r2['status']) && $r2['status'] >= 200 && $r2['status'] < 300) {
          $nrows = $r2['body'] ?? [];
          if (is_array($nrows) && count($nrows) > 0) {
            $curr = (float)($nrows[0]['saldo'] ?? 0);
            $newSaldo = $curr + $subtotal_t;
            supabase_request('PATCH', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id_nasabah), ['saldo' => $newSaldo], true);
          }
        }

        // create transaksi record for this approved item
        try {
          $jenisName = 'Setor Sampah';
          $tx_payload = [
            'id_nasabah' => $id_nasabah,
            'jenis' => $jenisName,
            'total' => $subtotal_t,
            'status' => 'success',
            'created_at' => date('Y-m-d H:i:s')
          ];
          supabase_request('POST', '/rest/v1/transaksi', $tx_payload, true);
        } catch (Exception $e) {}
      }
    }

    // Notifications
    try {
      $id_nasabah_n = $target['id_nasabah'] ?? null;
      if ($action === 'approve_detail') {
        $noteTitle = 'Setor: Item Disetujui';
        $noteBody = 'Satu item setoran (' . ($target['id_jenis'] ?? '') . ') senilai Rp ' . number_format($target['subtotal'] ?? 0,0,',','.') . ' disetujui.';
      } else {
        $noteTitle = 'Setor: Item Ditolak';
        $noteBody = 'Satu item setoran Anda ditolak oleh admin.';
      }
      if (!empty($id_nasabah_n)) supabase_request('POST', '/rest/v1/notifikasi', ['id_nasabah'=>$id_nasabah_n,'judul'=>$noteTitle,'isi'=>$noteBody,'jenis_notif'=>'setor'], true);
    } catch (Exception $e) {}

    res('ok', 'Aksi per-item diproses');
  }

  // Otherwise handle transaksi table actions (deposit approvals)
  $get = supabase_request('GET', '/rest/v1/transaksi?id=eq.' . urlencode($id) . '&select=id,id_nasabah,total,status', null, true);
  if (!$get || !isset($get['status'])) {
    res('error', 'Gagal menghubungi backend transaksi.');
  }
  if ($get['status'] >= 400) {
    // treat as no transaksi table or permission error
    res('ok', 'Tindakan dicatat (service transaksi tidak tersedia).');
  }
  $rows = $get['body'] ?? [];
  if (!is_array($rows) || count($rows) === 0) {
    res('error', 'Transaksi tidak ditemukan.');
  }
  $tx = $rows[0];

  $new_status = in_array($action, ['approve_deposit','approve']) ? 'approved' : 'rejected';

  // Update transaksi status
  $patch = ['status' => $new_status];
  $upd = supabase_request('PATCH', '/rest/v1/transaksi?id=eq.' . urlencode($id), $patch, true);
  if (!$upd || !isset($upd['status']) || $upd['status'] >= 400) {
    res('error', 'Gagal mengupdate status transaksi.');
  }

  // If approving a deposit, credit nasabah saldo
  if ($action === 'approve_deposit') {
    $id_nasabah = $tx['id_nasabah'] ?? null;
    $total = isset($tx['total']) ? (float)$tx['total'] : 0;
    if ($id_nasabah && $total > 0) {
      // Fetch nasabah current saldo
      $r2 = supabase_request('GET', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id_nasabah) . '&select=saldo', null, true);
      if ($r2 && isset($r2['status']) && $r2['status'] >= 200 && $r2['status'] < 300) {
        $nrows = $r2['body'] ?? [];
        if (is_array($nrows) && count($nrows) > 0) {
          $curr = (float)($nrows[0]['saldo'] ?? 0);
          $newSaldo = $curr + $total;
          $r3 = supabase_request('PATCH', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($id_nasabah), ['saldo' => $newSaldo], true);
          // ignore patch errors but continue
        }
      }
    }
  }

  res('ok', 'Aksi diproses');
} else {
  // Fallback: old mysqli behavior is not available because Supabase helper missing
  res('error', 'Supabase helper tidak tersedia di server.');
}
