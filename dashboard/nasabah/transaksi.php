<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenPoint • Riwayat PPOB</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">
</head>
<body>
    <div class="app">
        <?php
            session_start();
            require_once __DIR__ . '/../../server/supabase.php';

            if (!isset($_SESSION['id_nasabah'])) {
                header('Location: ./login/login.php');
                exit();
            }

            $activePage = 'riwayat-ppob';
            include "sidebar.php";

            // Fetch history: penarikan (PPOB) and transaksi (created by admin on approve)
            $user_id = $_SESSION['id_nasabah'];
            $hist = [];

            // penarikan entries for this nasabah
            $p = supabase_request('GET', '/rest/v1/penarikan?select=id_penukaran,id_nasabah,jenis_penukaran,nominal,status,tanggal_pengajuan&id_nasabah=eq.' . urlencode($user_id) . '&order=tanggal_pengajuan.desc', null, true);
            $penarikan = ($p && isset($p['status']) && $p['status'] >= 200 && $p['status'] < 300) ? ($p['body'] ?? []) : [];
            foreach ($penarikan as $r) {
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

            // transaksi entries (PPOB transactions created on approve)
            $t = supabase_request('GET', '/rest/v1/transaksi?select=id,id_nasabah,jenis,total,status,created_at,deskripsi&id_nasabah=eq.' . urlencode($user_id) . '&order=created_at.desc', null, true);
            $trans = ($t && isset($t['status']) && $t['status'] >= 200 && $t['status'] < 300) ? ($t['body'] ?? []) : [];
            foreach ($trans as $r) {
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

            // sort by created_at desc
            usort($hist, function($a, $b) {
                $ta = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
                $tb = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
                return $tb <=> $ta;
            });
        ?>

        <main class="main">
            <!-- Header Section -->
            <div class="page-header">
                <div class="header-content">
                    <h2>Riwayat Transaksi PPOB</h2>
                    <p class="subtle">Lihat riwayat pembelian E-money, Pulsa, dan PLN</p>
                </div>
            </div>

        

            <!-- Transactions Table -->
            <div class="card">
                <div class="table-header">
                    <h3>Daftar Transaksi</h3>
                    <div class="table-actions">
                        <button class="btn-export">
                            <i class="icon" data-lucide="download"></i>
                            Export
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th>ID Transaksi</th>
                                <th>Detail Transaksi</th>
                                <th>Nominal</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($hist)): ?>
                                <tr><td colspan="4" style="text-align:center; padding:20px;">Belum ada transaksi PPOB.</td></tr>
                            <?php else: ?>
                                <?php foreach ($hist as $item):
                                    $idLabel = $item['type'] === 'penarikan' ? ('#PPOB' . ($item['id'] ?? '')) : ('#TX' . ($item['id'] ?? ''));
                                    $service = htmlspecialchars($item['service'] ?? 'PPOB');
                                    $amount = 'Rp ' . number_format((float)($item['amount'] ?? 0), 0, ',', '.');
                                    $status = strtolower($item['status'] ?? '');
                                    $statusClass = 'pending';
                                    $statusText = ucfirst($status ?: 'Menunggu');
                                    if (in_array($status, ['approved','success'])) { $statusClass = 'success'; $statusText = 'Berhasil'; }
                                    if (in_array($status, ['rejected','failed'])) { $statusClass = 'failed'; $statusText = 'Gagal'; }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($idLabel) ?></td>
                                    <td>
                                                <div class="transaction-detail">
                                                <div class="transaction-header">
                                                    <div class="service-badge <?= strtolower(str_replace(' ', '-', $service)) ?>">
                                                        <i class="icon" data-lucide="credit-card"></i>
                                                        <?= $service ?>
                                                    </div>
                                                    <span class="status <?= $statusClass ?>"><?= $statusText ?></span>
                                                </div>
                                                <strong><?= $service ?></strong>
                                                <span><?= ($item['type'] === 'penarikan') ? htmlspecialchars($item['service']) : '' ?></span>
                                                <?php if (!empty($item['deskripsi'])): ?>
                                                    <div style="display:block; color:#6b7280; font-size:13px; margin-top:6px;"><?= htmlspecialchars($item['deskripsi']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                    </td>
                                    <td class="amount"><?= $amount ?></td>
                                    <td><?= $item['created_at'] ? date('d M Y<br><span class="time">H:i WIB', strtotime($item['created_at'])) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-info">
                    <span id="paginationText">6 of 6</span>
                    <div class="pagination-controls">
                        <label for="rows-per-page">Baris per halaman:</label>
                        <select id="rows-per-page" aria-label="Baris per halaman">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                        </select>
                        <button aria-label="Halaman sebelumnya" disabled>
                            <i class="icon" data-lucide="chevron-left"></i>
                        </button>
                        <span id="pageIndicator">1/1</span>
                        <button aria-label="Halaman berikutnya">
                            <i class="icon" data-lucide="chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        lucide.createIcons();

        // Filter functionality
        document.querySelector('.btn-secondary').addEventListener('click', function() {
            const jenisLayanan = document.getElementById('jenisLayanan').value;
            const tanggalMulai = document.getElementById('tanggalMulai').value;
            const tanggalAkhir = document.getElementById('tanggalAkhir').value;
            
            alert(`Filter diterapkan:\nLayanan: ${jenisLayanan}\nTanggal: ${tanggalMulai} - ${tanggalAkhir}`);
        });

        // Reset filter
        document.querySelector('.btn-outline').addEventListener('click', function() {
            document.getElementById('jenisLayanan').value = 'all';
            document.getElementById('tanggalMulai').value = '';
            document.getElementById('tanggalAkhir').value = '';
        });

        // Export functionality
        document.querySelector('.btn-export').addEventListener('click', function() {
            alert('Data berhasil diexport!');
        });

        // Enhanced pagination functionality
        document.addEventListener('DOMContentLoaded', function() {
            const rowsPerPageSelect = document.getElementById('rows-per-page');
            const pageIndicator = document.getElementById('pageIndicator');
            const prevButton = document.querySelector('.pagination-controls button:first-of-type');
            const nextButton = document.querySelector('.pagination-controls button:last-of-type');
            
            if (rowsPerPageSelect && pageIndicator) {
                rowsPerPageSelect.addEventListener('change', function() {
                    updatePaginationDisplay();
                });
                
                if (prevButton) {
                    prevButton.addEventListener('click', goToPreviousPage);
                }
                
                if (nextButton) {
                    nextButton.addEventListener('click', goToNextPage);
                }
                
                updatePaginationDisplay();
            }
        });

        function updatePaginationDisplay() {
            // Update pagination display logic here
            const currentPage = 1;
            const totalPages = 1;
            const pageIndicator = document.getElementById('pageIndicator');
            if (pageIndicator) {
                pageIndicator.textContent = `${currentPage}/${totalPages}`;
            }
        }

        function goToPreviousPage() {
            // Previous page logic
            console.log('Previous page');
        }

        function goToNextPage() {
            // Next page logic
            console.log('Next page');
        }
    </script>
</body>
</html>