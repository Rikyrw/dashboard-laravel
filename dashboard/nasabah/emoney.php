<?php
session_start();
require_once __DIR__ . '/../../server/supabase.php';

if (!isset($_SESSION['id_nasabah'])) {
    header('Location: ../login/login.php');
    exit;
}
$user_id = $_SESSION['id_nasabah'];

$emoney_error = '';
$saldo_val = 0;
// fetch current saldo for display
$saldo_resp_display = supabase_request('GET', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($user_id) . '&select=saldo', null, true);
if ($saldo_resp_display && isset($saldo_resp_display['status']) && $saldo_resp_display['status'] >= 200 && $saldo_resp_display['status'] < 300) {
    $sb = $saldo_resp_display['body'] ?? [];
    if (is_array($sb) && count($sb) > 0) $saldo_val = floatval($sb[0]['saldo'] ?? 0);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_emoney'])) {
    $target = trim($_POST['target'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $nominal = intval($_POST['nominal'] ?? 0);
    if ($target === '' || $nominal < 5000 || $nominal > 100000 || ($nominal % 5000) !== 0) {
        $emoney_error = 'Data tidak valid. Pastikan tujuan dan nominal benar.';
    } else {
        // Check nasabah saldo first
        $saldo_resp = supabase_request('GET', '/rest/v1/nasabah?id_nasabah=eq.' . urlencode($user_id) . '&select=saldo', null, true);
        $saldo_val = 0;
        if ($saldo_resp && isset($saldo_resp['status']) && $saldo_resp['status'] >= 200 && $saldo_resp['status'] < 300) {
            $sb = $saldo_resp['body'] ?? [];
            if (is_array($sb) && count($sb) > 0) $saldo_val = floatval($sb[0]['saldo'] ?? 0);
        }
        if ($saldo_val < $nominal) {
            $emoney_error = 'Saldo Anda tidak mencukupi untuk pembelian ini.';
        } else {
        $payload = [
            'id_nasabah' => $user_id,
            'jenis_penukaran' => 'EMONEY',
            'nominal' => $nominal,
            'deskripsi' => 'E-MONEY: ' . $category . ' - ' . $target,
            'status' => 'menunggu',
            'tanggal_pengajuan' => date('Y-m-d H:i:s')
        ];
        $ins = supabase_request('POST', '/rest/v1/penarikan', $payload, true);
        if ($ins && isset($ins['status']) && $ins['status'] >= 200 && $ins['status'] < 300) {
            // Request created; saldo akan dikurangi saat admin menyetujui penarikan.
            header('Location: emoney.php?success=1');
            exit;
        } else {
            $raw = $ins['raw'] ?? '';
            $emoney_error = 'Gagal mengirim permintaan. ' . ($raw ? substr($raw,0,300) : '');
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
    <title>GreenPoint • E-money</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">

</head>
<body>
    <div class="app">
        <?php
            $activePage = 'emoney';
            include "sidebar.php";
        ?>

        <main class="main">
            <!-- Header Section -->
            <div class="page-header">
                <div class="header-content">
                    <h2>E-money</h2>
                    <p class="subtle">Jelajahi kebutuhannu</p>
                </div>
            </div>

            <!-- Main Content -->
            <div class="emoney-container">
                <!-- Form Section -->
                <div class="card">
                    <div class="table-header">
                        <h3>Isi Data Tujuan</h3>
                        <div class="table-actions">
                                    <div class="card-header-section">
                                        <!-- removed back link per request -->
                                    </div>
                        </div>
                    </div>
                    
                    <form class="emoney-form" method="POST">
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
                            <div class="form-group">
                                <label for="category">Kategori</label>
                                <div class="select-wrapper">
                                    <select id="category" name="category">
                                        <option value="OVO" selected>OVO</option>
                                        <option value="DANA">DANA</option>
                                        <option value="GOPAY">GoPay</option>
                                        <option value="LINKAJA">LinkAja</option>
                                    </select>
                                    <i class="icon" data-lucide="chevron-down"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="service">Nominal</label>
                                <div class="select-wrapper">
                                    <select id="nominal" name="nominal">
                                    <?php for ($v=5000;$v<=100000;$v+=5000) {
                                        $sel = $v===50000 ? ' selected' : '';
                                        echo "<option value=\"{$v}\"{$sel}>" . number_format($v,0,',','.') . "</option>";
                                    } ?>
                                </select>
                                    <i class="icon" data-lucide="chevron-down"></i>
                                </div>
                            </div>
                        </div>

                                                <div class="form-actions">
                                                        <button type="submit" name="submit_emoney" class="btn-primary">
                                                                <i class="icon" data-lucide="zap"></i>
                                                                Setor
                                                        </button>
                                                </div>
                                                <div style="margin-top:8px;">Saldo: <strong>Rp <?= number_format((float)$saldo_val,0,',','.') ?></strong></div>
                                                <?php if (!empty($emoney_error)): ?>
                                                    <div style="color: #b91c1c; margin-top:8px;"><?= htmlspecialchars($emoney_error) ?></div>
                                                <?php endif; ?>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        lucide.createIcons();
        
        // Edit target number functionality (enable editing when click)
        document.querySelectorAll('.edit-btn').forEach(function(btn){
            btn.addEventListener('click', function() {
                // try both tokenNumber and phoneNumber ids
                const t = document.getElementById('phoneNumber') || document.getElementById('tokenNumber');
                if (t) {
                    t.removeAttribute('readonly');
                    t.focus();
                    t.select();
                }
            });
        });

        // Emoney form validation: only prevent submission on invalid data
        document.querySelector('.emoney-form')?.addEventListener('submit', function(e) {
            const target = document.getElementById('phoneNumber')?.value.trim() || '';
            const nominal = parseInt(document.getElementById('nominal')?.value || '0', 10);
            if (!target) {
                e.preventDefault();
                alert('Harap masukkan nomor tujuan!');
                return;
            }
            if (isNaN(nominal) || nominal < 5000 || nominal > 100000 || (nominal % 5000) !== 0) {
                e.preventDefault();
                alert('Nominal tidak valid. Pilih kelipatan 5.000 hingga 100.000.');
                return;
            }
            // allow submit to server which will create penarikan
        });
    </script>
</body>
</html>