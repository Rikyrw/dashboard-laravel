<?php
session_start();
require_once './login/config.php';

// Ensure nasabah is logged in
if (!isset($_SESSION['id_nasabah'])) {
  header('Location: ./login/login.php');
  exit();
}

$user_id = $_SESSION['id_nasabah'];
$transactions = [];

// include supabase helper
require_once __DIR__ . '/../../server/supabase.php';

try {
  // Fetch transaksi_setor rows for this nasabah
  $resp = supabase_request('GET', '/rest/v1/transaksi_setor?id_nasabah=eq.' . urlencode($user_id) . '&select=*&order=tanggal_setor.desc', null, true);
  if ($resp && isset($resp['status']) && $resp['status'] >= 200 && $resp['status'] < 300) {
    $ts_rows = $resp['body'] ?? [];
    if (is_array($ts_rows)) {
      foreach ($ts_rows as $ts) {
        $id_trans = $ts['id_transaksi'] ?? null;
        $tanggal = $ts['tanggal_setor'] ?? null;
        $status = $ts['status'] ?? null;

        // Fetch detail_setor for this transaksi
        $dresp = supabase_request('GET', '/rest/v1/detail_setor?id_transaksi=eq.' . urlencode($id_trans), null, true);
        if ($dresp && isset($dresp['status']) && $dresp['status'] >= 200 && $dresp['status'] < 300) {
          $drows = $dresp['body'] ?? [];
          if (is_array($drows)) {
            foreach ($drows as $dr) {
              $id_jenis = $dr['id_jenis'] ?? null;
              $nama_jenis = null;
              // Fetch nama jenis
              if ($id_jenis) {
                $jresp = supabase_request('GET', '/rest/v1/jenis_sampah?id_jenis=eq.' . urlencode($id_jenis) . '&select=nama_jenis', null, true);
                if ($jresp && isset($jresp['status']) && $jresp['status'] >= 200 && $jresp['status'] < 300) {
                  $jbody = $jresp['body'] ?? [];
                  if (is_array($jbody) && count($jbody) > 0) $nama_jenis = $jbody[0]['nama_jenis'] ?? null;
                }
              }

              $transactions[] = [
                'id_transaksi' => $id_trans,
                'id_jenis' => $id_jenis,
                'nama_jenis' => $nama_jenis,
                'berat_kg' => $dr['berat_kg'] ?? 0,
                'harga_per_kg' => $dr['harga_per_kg'] ?? 0,
                'subtotal' => $dr['subtotal'] ?? 0,
                'tanggal_setor' => $tanggal,
                'status' => $status
              ];
            }
          }
        }
      }
    }
  }
} catch (Exception $e) {
  error_log('[riwayatstor] error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Setor - GreenPoint</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">
</head>

<body>
  <div class="app">
    <?php
      $activePage = 'riwayatstor';
      include "sidebar.php";
    ?>

    <main class="main">
      <div class="page-header">
        <div class="header-content">
          <h1>Riwayat Setor Sampah</h1>
          <p class="subtle">Daftar Riwayat Setor Sampah Anda</p>
        </div>
      </div>

      <div class="card table-section">
        <div class="table-header">
          <h3>Riwayat Transaksi Setor</h3>
        </div>

        <div class="card filter-section">
                <div class="filter-header">
                    <h3>Filter Transaksi</h3>
                </div>
                <div class="filter-controls">

                    <div class="filter-group">
                        <label for="tanggalMulai">Tanggal Mulai</label>
                        <input type="date" id="tanggalMulai" class="date-input">
                    </div>

                    <div class="filter-group">
                        <label for="tanggalAkhir">Tanggal Akhir</label>
                        <input type="date" id="tanggalAkhir" class="date-input">
                    </div>

                    <div class="filter-actions">
                        <button type="button" class="btn-secondary">
                            <i class="icon" data-lucide="filter"></i>
                            Terapkan Filter
                        </button>
                        <button type="button" class="btn-outline">
                            <i class="icon" data-lucide="refresh-cw"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        
        <div class="table-actions">
          <!-- Search Container -->
          <div class="search-container">
            <div class="search-icon" aria-hidden="true">
              <i class="icon" data-lucide="search"></i>
            </div>
            <input type="text" class="search-input" placeholder="Search..." id="searchInput" aria-label="Cari riwayat">
          </div>
          
          <button class="btn-export" aria-label="Export data">
            <i class="icon" data-lucide="download" aria-hidden="true"></i>
            Export
          </button>
        </div>
        
        <table role="table" aria-label="Riwayat Setor Sampah">
          <thead>
            <tr>
              <th>No. Transaksi</th>
              <th>Jenis Sampah</th>
              <th>Berat (kg)</th>
              <th>Harga/kg</th>
              <th>Subtotal</th>
              <th>Tanggal</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="tableBody">
            <?php if (empty($transactions)): ?>
              <tr>
                <td colspan="7" style="text-align: center; padding: 2rem;">
                  <i class="icon" data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 1rem; color: var(--text-light);"></i>
                  <p>Belum ada riwayat setor sampah</p>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($transactions as $transaction): ?>
                <tr>
                  <td>#<?php echo $transaction['id_transaksi']; ?></td>
                  <td><?php echo htmlspecialchars($transaction['nama_jenis'] ?? 'N/A'); ?></td>
                  <td><?php echo number_format($transaction['berat_kg'] ?? 0, 2); ?></td>
                  <td>Rp <?php echo number_format($transaction['harga_per_kg'] ?? 0, 0, ',', '.'); ?></td>
                  <td>Rp <?php echo number_format($transaction['subtotal'] ?? 0, 0, ',', '.'); ?></td>
                  <td><?php echo date('d/m/Y', strtotime($transaction['tanggal_setor'])); ?></td>
                  <td>
                    <?php 
                    $status_class = '';
                    switch($transaction['status']) {
                      case 'selesai': $status_class = 'success'; break;
                      case 'menunggu': $status_class = 'pending'; break;
                      case 'ditolak': $status_class = 'failed'; break;
                      default: $status_class = 'pending';
                    }
                    ?>
                    <span class="status <?php echo $status_class; ?>">
                      <?php echo ucfirst($transaction['status']); ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="pagination-info">
          <span id="paginationText">
            <?php echo count($transactions); ?> dari <?php echo count($transactions); ?> transaksi
          </span>
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

        // Elements
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');
        const allRows = Array.from(tableBody.querySelectorAll('tr'));
        const totalRows = allRows.length;
        const paginationText = document.getElementById('paginationText');
        const rowsPerPage = document.getElementById('rows-per-page');
        const pageIndicator = document.getElementById('pageIndicator');

        // Initialize pagination text
        paginationText.textContent = `${totalRows} of ${totalRows}`;

        // Search functionality
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim().toLowerCase();
            let visibleCount = 0;

            allRows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                const match = searchTerm === '' || rowText.includes(searchTerm);
                row.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });

            // Update pagination text
            paginationText.textContent = `${visibleCount} of ${totalRows}`;
            // reset page indicator
            pageIndicator.textContent = '1/1';
        });

        // Export functionality (CSV)
        document.querySelector('.btn-export').addEventListener('click', function() {
            const visibleRows = allRows.filter(r => r.style.display !== 'none');
            if (!visibleRows.length) {
                alert('Tidak ada data untuk diexport.');
                return;
            }

            const headers = Array.from(document.querySelectorAll('thead th')).map(th => th.textContent.trim());
            const csv = [
                headers.join(','),
                ...visibleRows.map(row => Array.from(row.children).map(td => `"${td.textContent.trim().replace(/"/g, '""')}"`).join(','))
            ].join('\n');

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'riwayat_setor.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });

        // Rows per page change (updates pagination text, no real paging implemented)
        rowsPerPage.addEventListener('change', function() {
            const perPage = parseInt(this.value, 10);
            const end = Math.min(perPage, totalRows);
            paginationText.textContent = `${end} of ${totalRows}`;
            pageIndicator.textContent = '1/1';
        });

        // Pagination buttons (placeholder behavior)
        document.querySelectorAll('.pagination-controls button').forEach(button => {
            button.addEventListener('click', function() {
                if (this.disabled) return;
                // no real paging implemented; keep behavior minimal
                alert('Paging belum diimplementasikan.');
            });
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