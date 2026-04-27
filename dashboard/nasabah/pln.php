<?php
session_start();
require_once __DIR__ . '/../../server/supabase.php';

if (!isset($_SESSION['id_nasabah'])) {
    header('Location: ../login/login.php');
    exit;
}
$user_id = $_SESSION['id_nasabah'];

$pln_error = '';
$saldo_val = 0;
// fetch current saldo for display
$saldo_resp_display = supabase_request('GET', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($user_id) . '&select=saldo', null, true);
if ($saldo_resp_display && isset($saldo_resp_display['status']) && $saldo_resp_display['status'] >= 200 && $saldo_resp_display['status'] < 300) {
    $sb = $saldo_resp_display['body'] ?? [];
    if (is_array($sb) && count($sb) > 0) $saldo_val = floatval($sb[0]['saldo'] ?? 0);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pln'])) {
    $token = trim($_POST['token'] ?? '');
    $nominal = intval($_POST['nominal'] ?? 0);
    $allowed = [50000, 100000];
    if ($token === '' || !in_array($nominal, $allowed, true)) {
        $pln_error = 'Data tidak valid. Token dan nominal harus benar.';
    } else {
        // Check nasabah saldo first
        $saldo_resp = supabase_request('GET', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($user_id) . '&select=saldo', null, true);
        $saldo_val = 0;
        if ($saldo_resp && isset($saldo_resp['status']) && $saldo_resp['status'] >= 200 && $saldo_resp['status'] < 300) {
            $sb = $saldo_resp['body'] ?? [];
            if (is_array($sb) && count($sb) > 0) $saldo_val = floatval($sb[0]['saldo'] ?? 0);
        }
        if ($saldo_val < $nominal) {
            $pln_error = 'Saldo Anda tidak mencukupi untuk pembelian ini.';
        } else {
        $payload = [
            'id_nasabah' => $user_id,
            'jenis_penukaran' => 'PLN',
            'nominal' => $nominal,
            'deskripsi' => 'Token: ' . $token,
            'status' => 'menunggu',
            'tanggal_pengajuan' => date('Y-m-d H:i:s')
        ];
        $ins = supabase_request('POST', '/rest/v1/penarikan', $payload, true);
        if ($ins && isset($ins['status']) && $ins['status'] >= 200 && $ins['status'] < 300) {
            // Request created successfully; saldo akan dikurangi saat admin menyetujui.
            header('Location: pln.php?success=1');
            exit;
        } else {
            $raw = $ins['raw'] ?? '';
            $pln_error = 'Gagal mengirim permintaan. ' . ($raw ? substr($raw,0,300) : '');
        }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenPoint • PLN</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">
</head>
<body>
    <div class="app">
        <?php
            $activePage = 'pln';
            include "sidebar.php";
        ?>

        <main class="main">
            <!-- Header Section -->
            <div class="page-header">
                <div class="header-content">
                    <h2>PLN</h2>
                    <p class="subtle">Penuhi kebutuhan listrik anda</p>
                </div>
            </div>

            <!-- Main Content -->
            <div class="content-container">
                <!-- Form Section -->
                <div class="card">
                    <div class="table-header">
                        <h3>Isi Data Tujuan</h3>
                        <div class="table-actions">
                            <div class="card-header-section">
                                <a href="dashboard.php" class="btn-back">
                                    <i class="icon" data-lucide="arrow-left"></i>
                                    Kembali
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <form class="pln-form" method="POST">
                        <div class="form-group">
                            <label for="tokenNumber">No Token</label>
                            <div class="input-with-value">
                                <input type="text" id="tokenNumber" name="token" value="">
                                <button type="button" class="edit-btn">
                                    <i class="icon" data-lucide="edit-2"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="nominal">Nominal</label>
                            <div class="select-wrapper">
                                <select id="nominal" name="nominal">
                                    <option value="50000" selected>50.000</option>
                                    <option value="100000">100.000</option>
                                </select>
                                <i class="icon" data-lucide="chevron-down"></i>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="submit_pln" class="btn-primary">
                                <i class="icon" data-lucide="zap"></i>
                                Setor
                            </button>
                        </div>
                        <div style="margin-top:8px;">Saldo: <strong>Rp <?= number_format((float)$saldo_val,0,',','.') ?></strong></div>
                        <?php if (!empty($pln_error)): ?>
                          <div style="color: #b91c1c; margin-top:8px;"><?= htmlspecialchars($pln_error) ?></div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        lucide.createIcons();
        
        // Edit token number functionality
        document.querySelector('.edit-btn')?.addEventListener('click', function() {
            const input = document.getElementById('tokenNumber');
            input.removeAttribute('readonly');
            input.focus();
            input.select();
        });

        // Form submission: validate client-side but allow normal POST to server
        document.querySelector('.pln-form')?.addEventListener('submit', function(e) {
            const token = document.getElementById('tokenNumber').value.trim();
            if (!token) {
                e.preventDefault();
                alert('Harap masukkan nomor token!');
                return;
            }
            // let the form submit to server which will create penarikan
        });
    </script>
</body>
</html>