<?php
session_start();
require_once './login/config.php';

// Check authentication
if (!isset($_SESSION['id_nasabah'])) {
    header("Location: ./login/login.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ./login/login.php");
    exit();
}

$user_id = $_SESSION['id_nasabah'];
$user = [];
$waste_types = [];

// Include supabase_request function
require_once __DIR__ . '/../../server/supabase.php';

try {
    // Ambil data user dari Supabase menggunakan supabase_request
    $user_resp = supabase_request('GET', '/rest/v1/nasabah?select=*&id_nasabah=eq.' . $user_id, null, true);
    
    if ($user_resp && $user_resp['status'] === 200 && !empty($user_resp['body'])) {
        $user = $user_resp['body'][0];
        $_SESSION['nama_nasabah'] = $user['nama_nasabah'];
        $_SESSION['saldo'] = $user['saldo'] ?? 0;
    } else {
        session_destroy();
        header("Location: ./login/login.php");
        exit();
    }

    // Ambil data jenis sampah yang aktif dari Supabase
    $waste_resp = supabase_request('GET', '/rest/v1/jenis_sampah?select=*&status=eq.aktif&order=nama_jenis.asc', null, true);
    if ($waste_resp && $waste_resp['status'] === 200) {
        $waste_types = $waste_resp['body'];
    }
} catch (Exception $e) {
    error_log("Setor error: " . $e->getMessage());
}

// Handle update profil saat setor sampah
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $updateData = [
            'nama_nasabah' => $_POST['nama_nasabah'],
            'alamat' => $_POST['alamat']
        ];
        
        $update_result = supabase_request('PATCH', '/rest/v1/nasabah?id_nasabah=eq.' . $user_id, $updateData, true);
        
        if ($update_result && $update_result['status'] === 200) {
            // Refresh user data
            $user_resp = supabase_request('GET', '/rest/v1/nasabah?select=*&id_nasabah=eq.' . $user_id, null, true);
            if ($user_resp && !empty($user_resp['body'])) {
                $user = $user_resp['body'][0];
                $_SESSION['nama_nasabah'] = $user['nama_nasabah'];
                $profile_success = "Profil berhasil diperbarui!";
            }
        } else {
            $profile_error = "Gagal memperbarui profil.";
        }
    } catch (Exception $e) {
        $profile_error = "Error: " . $e->getMessage();
    }
}

// Handle form submission setor sampah
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_transaction'])) {
    try {
        // Compute totals: prefer posted totals, otherwise compute from waste_items[]
        $total_berat = 0.0;
        $total_nilai = 0.0;
        if (isset($_POST['total_berat']) || isset($_POST['total_nilai'])) {
            $total_berat = isset($_POST['total_berat']) ? floatval($_POST['total_berat']) : 0.0;
            $total_nilai = isset($_POST['total_nilai']) ? floatval($_POST['total_nilai']) : 0.0;
        } elseif (isset($_POST['waste_items']) && is_array($_POST['waste_items'])) {
            foreach ($_POST['waste_items'] as $it) {
                $b = isset($it['berat']) ? floatval($it['berat']) : 0.0;
                $s = isset($it['subtotal']) ? floatval($it['subtotal']) : 0.0;
                $total_berat += $b;
                $total_nilai += $s;
            }
        }

        // Data untuk transaksi_setor - status sekarang 'menunggu'
        $transaksi_data = [
            'id_nasabah' => $user_id,
            'id_admin' => null,
            'total_berat' => $total_berat,
            'total_nilai' => $total_nilai,
            'tanggal_setor' => date('Y-m-d H:i:s'),
            'status' => 'menunggu' // <-- INI YANG PERLU DIUBAH
        ];

        // Insert transaksi_setor ke Supabase menggunakan supabase_request
        $transaksi_result = supabase_request('POST', '/rest/v1/transaksi_setor', $transaksi_data, true);
        
        if ($transaksi_result && $transaksi_result['status'] === 201) {
            // Prefer: return=representation should make body contain inserted row(s)
            if (isset($transaksi_result['body'][0]['id_transaksi'])) {
                $transaksi_id = $transaksi_result['body'][0]['id_transaksi'];
            } else {
                // Fallback: query the last transaksi_setor for this nasabah by timestamp
                $fallback = supabase_request('GET', '/rest/v1/transaksi_setor?id_nasabah=eq.' . urlencode($user_id) . '&order=tanggal_setor.desc&limit=1', null, true);
                if ($fallback && isset($fallback['status']) && $fallback['status'] >= 200 && $fallback['status'] < 300 && !empty($fallback['body'][0]['id_transaksi'])) {
                    $transaksi_id = $fallback['body'][0]['id_transaksi'];
                } else {
                    $transaksi_id = null;
                }
            }

            if (!empty($transaksi_id)) {
                // Insert detail_setor untuk setiap item
                if (isset($_POST['waste_items']) && is_array($_POST['waste_items'])) {
                    foreach ($_POST['waste_items'] as $item) {
                        $detail_data = [
                            'id_transaksi' => $transaksi_id,
                            'id_jenis' => intval($item['id_jenis']),
                            'berat_kg' => floatval($item['berat']),
                            'harga_per_kg' => floatval($item['harga']),
                            'subtotal' => floatval($item['subtotal'])
                        ];
                        supabase_request('POST', '/rest/v1/detail_setor', $detail_data, true);
                    }
                }

                // Refresh user data
                $user_resp = supabase_request('GET', '/rest/v1/nasabah?select=*&id_nasabah=eq.' . $user_id, null, true);
                if ($user_resp && !empty($user_resp['body'])) {
                    $user = $user_resp['body'][0];
                }

                $success = "Transaksi setor sampah berhasil diajukan! Status: Menunggu persetujuan admin.";
                // Reset form
                echo '<script>if (typeof wasteItems !== "undefined") wasteItems = [];</script>';
            } else {
                $error = "Gagal menyimpan transaksi. Silakan coba lagi.";
                if ($transaksi_result) {
                    error_log("Transaksi error: " . print_r($transaksi_result, true));
                }
            }
        } else {
            $error = "Gagal menyimpan transaksi. Silakan coba lagi.";
            if ($transaksi_result) {
                error_log("Transaksi error: " . print_r($transaksi_result, true));
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Setor exception: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>GreenPoint • Setor Sampah</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">
</head>
<body>
    <div class="app">
        <?php
            $activePage = 'setor';
            include "sidebar.php";
        ?>

        <main class="main">
            <div class="page-header">
                <div class="header-content">
                    <h2>Setor Sampah</h2>
                    <p class="subtle">Ajukan setor sampah, tunggu persetujuan admin</p>
                </div>
            </div>

            <section class="grid" style="gap:24px; margin-top:12px;">
                <div class="card col-6" style="padding:16px;">
                    <h3>Profil</h3>
                    <form method="POST" style="margin-top:8px;">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="form-group">
                            <label>Nama</label>
                            <input type="text" name="nama_nasabah" value="<?= htmlspecialchars($user['nama_nasabah'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Alamat</label>
                            <input type="text" name="alamat" value="<?= htmlspecialchars($user['alamat'] ?? '') ?>">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Update Profil</button>
                        </div>
                        <?php if (!empty($profile_success)): ?>
                            <div style="color: #065f46; margin-top:8px;"><?= htmlspecialchars($profile_success) ?></div>
                        <?php elseif (!empty($profile_error)): ?>
                            <div style="color: #b91c1c; margin-top:8px;"><?= htmlspecialchars($profile_error) ?></div>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card col-6" style="padding:16px;">
                    <h3>Form Setor</h3>
                    <div style="margin-top:8px;">Saldo: <strong>Rp <?= number_format((float)($user['saldo'] ?? 0),0,',','.') ?></strong></div>

                    <form id="setorForm" method="POST" style="margin-top:12px;">
                        <input type="hidden" name="submit_transaction" value="1">

                        <div class="form-group">
                            <label>Pilih Jenis Sampah</label>
                            <select id="jenisSelect">
                                <option value="">-- Pilih jenis --</option>
                                <?php foreach ($waste_types as $wt): ?>
                                    <option value="<?= intval($wt['id_jenis']) ?>" data-harga="<?= floatval($wt['harga_per_kg'] ?? 0) ?>"><?= htmlspecialchars($wt['nama_jenis']) ?> - Rp <?= number_format(floatval($wt['harga_per_kg'] ?? 0),0,',','.') ?>/kg</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Berat (kg)</label>
                            <input type="number" id="beratInput" min="0.01" step="0.01" value="0">
                        </div>

                        <div class="form-actions" style="display:flex; gap:8px; align-items:center;">
                            <button type="button" id="addItemBtn" class="btn-primary">Tambah Item</button>
                            <span id="formMsg" style="color:#374151;"></span>
                        </div>

                        <h4 style="margin-top:12px;">Daftar Item</h4>
                        <table id="itemsTable" class="table" style="margin-top:8px;">
                            <thead>
                                <tr><th>Jenis</th><th>Berat (kg)</th><th>Harga/kg</th><th>Subtotal</th><th>Aksi</th></tr>
                            </thead>
                            <tbody></tbody>
                        </table>

                        <div style="margin-top:12px; display:flex; justify-content:space-between; align-items:center;">
                            <div>Total Berat: <strong id="totalBerat">0</strong> kg</div>
                            <div>Total Nilai: <strong id="totalNilai">Rp 0</strong></div>
                        </div>

                        <div style="margin-top:12px;">
                            <button type="submit" class="btn-primary">Ajukan Setor</button>
                        </div>
                    </form>

                    <?php if (!empty($success)): ?>
                        <div style="color:#065f46; margin-top:8px;"><?= htmlspecialchars($success) ?></div>
                    <?php elseif (!empty($error)): ?>
                        <div style="color:#b91c1c; margin-top:8px;"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        lucide.createIcons();

        const addItemBtn = document.getElementById('addItemBtn');
        const jenisSelect = document.getElementById('jenisSelect');
        const beratInput = document.getElementById('beratInput');
        const itemsTableBody = document.querySelector('#itemsTable tbody');
        const totalBeratEl = document.getElementById('totalBerat');
        const totalNilaiEl = document.getElementById('totalNilai');
        const setorForm = document.getElementById('setorForm');

        let items = [];

        function renderItems() {
            itemsTableBody.innerHTML = '';
            let totalBerat = 0;
            let totalNilai = 0;
            items.forEach((it, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${it.nama}</td><td>${it.berat}</td><td>Rp ${Number(it.harga).toLocaleString('id-ID')}</td><td>Rp ${Number(it.subtotal).toLocaleString('id-ID')}</td><td><button type="button" data-idx="${idx}" class="btn btn-danger btn-sm remove-btn">Hapus</button></td>`;
                itemsTableBody.appendChild(tr);
                totalBerat += parseFloat(it.berat);
                totalNilai += parseFloat(it.subtotal);
            });
            totalBeratEl.textContent = totalBerat.toFixed(2);
            totalNilaiEl.textContent = 'Rp ' + totalNilai.toLocaleString('id-ID');

            // ensure hidden inputs exist for server
            // remove existing dynamic inputs
            document.querySelectorAll('input[name^="waste_items"]').forEach(n => n.remove());
            // remove previous total inputs if present
            document.querySelectorAll('input[name="total_berat"], input[name="total_nilai"]').forEach(n => n.remove());
            items.forEach((it, i) => {
                const idJenis = document.createElement('input'); idJenis.type='hidden'; idJenis.name=`waste_items[${i}][id_jenis]`; idJenis.value=it.id;
                const berat = document.createElement('input'); berat.type='hidden'; berat.name=`waste_items[${i}][berat]`; berat.value=it.berat;
                const harga = document.createElement('input'); harga.type='hidden'; harga.name=`waste_items[${i}][harga]`; harga.value=it.harga;
                const subtotal = document.createElement('input'); subtotal.type='hidden'; subtotal.name=`waste_items[${i}][subtotal]`; subtotal.value=it.subtotal;
                setorForm.appendChild(idJenis);
                setorForm.appendChild(berat);
                setorForm.appendChild(harga);
                setorForm.appendChild(subtotal);
            });

            // add/update total hidden inputs expected by server
            const totalBeratInput = document.createElement('input'); totalBeratInput.type='hidden'; totalBeratInput.name='total_berat'; totalBeratInput.value = totalBerat.toFixed(2);
            const totalNilaiInput = document.createElement('input'); totalNilaiInput.type='hidden'; totalNilaiInput.name='total_nilai'; totalNilaiInput.value = totalNilai.toFixed(2);
            setorForm.appendChild(totalBeratInput);
            setorForm.appendChild(totalNilaiInput);

            // attach remove handlers
            document.querySelectorAll('.remove-btn').forEach(b => b.addEventListener('click', function(){
                const i = parseInt(this.dataset.idx,10);
                items.splice(i,1);
                renderItems();
            }));
        }

        addItemBtn.addEventListener('click', function(){
            const sel = jenisSelect.options[jenisSelect.selectedIndex];
            if (!sel || !sel.value) {
                alert('Pilih jenis sampah terlebih dahulu');
                return;
            }
            const id = sel.value;
            const nama = sel.textContent.split(' - ')[0].trim();
            const harga = parseFloat(sel.dataset.harga || 0);
            const berat = parseFloat(beratInput.value || 0);
            if (berat <= 0) { alert('Masukkan berat yang valid'); return; }
            const subtotal = (harga * berat).toFixed(2);
            items.push({id, nama, berat: berat.toFixed(2), harga: harga.toFixed(2), subtotal});
            renderItems();
            beratInput.value = '0';
            jenisSelect.selectedIndex = 0;
        });

        setorForm.addEventListener('submit', function(e){
            if (items.length === 0) {
                e.preventDefault();
                alert('Tambahkan minimal 1 item sebelum mengajukan setor');
            }
        });
    </script>
</body>
</html>
