<?php
session_start();
require_once __DIR__ . '/../../server/supabase.php';

if (!isset($_SESSION['id_nasabah'])) {
    header('Location: ../login/login.php');
    exit;
}
$user_id = $_SESSION['id_nasabah'];

$pulsa_error = '';
$saldo_val = 0;
// fetch current saldo for display
$saldo_resp_display = supabase_request('GET', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($user_id) . '&select=saldo', null, true);
if ($saldo_resp_display && isset($saldo_resp_display['status']) && $saldo_resp_display['status'] >= 200 && $saldo_resp_display['status'] < 300) {
    $sb = $saldo_resp_display['body'] ?? [];
    if (is_array($sb) && count($sb) > 0) $saldo_val = floatval($sb[0]['saldo'] ?? 0);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pulsa'])) {
    $target = trim($_POST['target'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $nominal = intval($_POST['nominal'] ?? 0);
    if ($target === '' || $category === '' || $nominal < 5000 || $nominal > 100000 || ($nominal % 5000) !== 0) {
        $pulsa_error = 'Data tidak valid. Pastikan nomor dan nominal benar.';
    } else {
        // Check nasabah saldo first
        $saldo_resp = supabase_request('GET', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($user_id) . '&select=saldo', null, true);
        $saldo_val = 0;
        if ($saldo_resp && isset($saldo_resp['status']) && $saldo_resp['status'] >= 200 && $saldo_resp['status'] < 300) {
            $sb = $saldo_resp['body'] ?? [];
            if (is_array($sb) && count($sb) > 0) $saldo_val = floatval($sb[0]['saldo'] ?? 0);
        }
        if ($saldo_val < $nominal) {
            $pulsa_error = 'Saldo Anda tidak mencukupi untuk pembelian pulsa ini.';
        } else {
        $payload = [
            'id_nasabah' => $user_id,
            'jenis_penukaran' => 'PULSA',
            'nominal' => $nominal,
            'deskripsi' => 'Pulsa: ' . strtoupper($category) . ' - ' . $target,
            'status' => 'menunggu',
            'tanggal_pengajuan' => date('Y-m-d H:i:s')
        ];
        $ins = supabase_request('POST', '/rest/v1/penarikan', $payload, true);
        if ($ins && isset($ins['status']) && $ins['status'] >= 200 && $ins['status'] < 300) {
            // Request created; saldo akan dikurangi saat admin menyetujui permintaan penarikan.
            header('Location: pulsa.php?success=1');
            exit;
        } else {
            $raw = $ins['raw'] ?? '';
            $pulsa_error = 'Gagal mengirim permintaan. ' . ($raw ? substr($raw,0,300) : '');
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
    <title>GreenPoint • Pulsa</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">
</head>
<body>
    <div class="app">
        <?php
            $activePage = 'pulsa';
            include "sidebar.php";
        ?>

        <main class="main">
            <!-- Header Section -->
            <div class="page-header">
                <div class="header-content">
                    <h2>Pulsa</h2>
                    <p class="subtle">Kami siap melayani kebutuhan internet anda</p>
                </div>
            </div>

            <!-- Main Content -->
            <div class="pulsa-container">
                <!-- Form Section -->
                <div class="card col-12">
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
                    
                    <form class="pulsa-form" method="POST">
                        <!-- No Tujuan -->
                        <div class="form-group">
                            <label for="phoneNumber">No Tujuan</label>
                                <div class="input-with-value">
                                <input type="text" id="phoneNumber" name="target" value="">
                                <button type="button" class="edit-btn">
                                    <i class="icon" data-lucide="edit-2"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-row">
                            <!-- Kategori -->
                            <div class="form-group">
                                <label for="category">Kategori</label>
                                <div class="select-wrapper">
                                    <select id="category" name="category">
                                        <option value="telkomsel" selected>Telkomsel</option>
                                        <option value="xl">XL</option>
                                        <option value="indosat">Indosat</option>
                                        <option value="three">3</option>
                                        <option value="smartfren">Smartfren</option>
                                        <option value="axis">Axis</option>
                                    </select>
                                    <i class="icon" data-lucide="chevron-down"></i>
                                </div>
                            </div>
                        
                            <!-- Nominal -->
                            <div class="form-group">
                                <label for="nominal">Nominal</label>
                                <div class="select-wrapper">
                                    <select id="nominal" name="nominal">
                                    <?php for ($v=5000;$v<=100000;$v+=5000) {
                                        $sel = $v===25000 ? ' selected' : '';
                                        echo "<option value=\"{$v}\"{$sel}>" . number_format($v,0,',','.') . "</option>";
                                    } ?>
                                    </select>
                                    <i class="icon" data-lucide="chevron-down"></i>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="submit_pulsa" class="btn-primary">
                                <i class="icon" data-lucide="zap"></i>
                                Setor
                            </button>
                        </div>
                        <div style="margin-top:8px;">Saldo: <strong>Rp <?= number_format((float)$saldo_val,0,',','.') ?></strong></div>
                        <?php if (!empty($pulsa_error)): ?>
                          <div style="color: #b91c1c; margin-top:8px;"><?= htmlspecialchars($pulsa_error) ?></div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        lucide.createIcons();
        
        // Edit phone number functionality
        document.querySelector('.edit-btn')?.addEventListener('click', function() {
            const input = document.getElementById('phoneNumber');
            input.removeAttribute('readonly');
            input.focus();
            input.select();
        });

        // Form submission: validate but allow normal POST to server
        document.querySelector('.pulsa-form')?.addEventListener('submit', function(e) {
            const phoneNumber = document.getElementById('phoneNumber').value.trim();
            const nominal = parseInt(document.getElementById('nominal').value || '0', 10);
            if (!phoneNumber) {
                e.preventDefault();
                alert('Harap masukkan nomor tujuan!');
                return;
            }
            const phoneRegex = /^[0-9]{6,13}$/;
            if (!phoneRegex.test(phoneNumber)) {
                e.preventDefault();
                alert('Format nomor telepon tidak valid!');
                return;
            }
            if (isNaN(nominal) || nominal < 5000 || nominal > 100000 || (nominal % 5000) !== 0) {
                e.preventDefault();
                alert('Nominal tidak valid. Pilih kelipatan 5.000 hingga 100.000.');
                return;
            }
            // allow submit to server
        });

        // Auto-format phone number
        document.getElementById('phoneNumber').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 13) {
                value = value.substring(0, 13);
            }
            e.target.value = value;
        });
    </script>
</body>
</html>