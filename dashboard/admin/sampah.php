<?php
// BARIS PALING ATAS
session_start();
require_once __DIR__ . '/auth_check.php';
requireAdmin();

$activePage = 'sampah';

// Include supabase helper
if (file_exists(__DIR__ . '/../../server/supabase.php')) {
    include_once __DIR__ . '/../../server/supabase.php';
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (isset($_POST['id']) && function_exists('supabase_request')) {
        $id = (int)$_POST['id'];
        
        // CSRF validation
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (validateCsrfToken($csrf_token)) {
            // Soft delete: update status to 'nonaktif'
            $result = supabase_request('PATCH', '/rest/v1/jenis_sampah?id_jenis=eq.' . $id, 
                ['status' => 'nonaktif'], true);
            
            if ($result && isset($result['status']) && $result['status'] >= 200 && $result['status'] < 300) {
                $_SESSION['flash_message'] = 'Data sampah berhasil dihapus';
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = 'Gagal menghapus data sampah';
                $_SESSION['flash_type'] = 'error';
            }
        } else {
            $_SESSION['flash_message'] = 'Token keamanan tidak valid';
            $_SESSION['flash_type'] = 'error';
        }
        
        header('Location: sampah.php');
        exit;
    }
}

// CSRF token
$csrf_token = generateCsrfToken();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>GreenPoint • Daftar Sampah</title>

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">

  <!-- Main Styles -->
  <link rel="stylesheet" href="./assets/css/style.css" />
  
  <style>
    .status {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
      display: inline-block;
    }
    
    .status.aktif {
      background: #d1fae5;
      color: #065f46;
    }
    
    .status.nonaktif {
      background: #fee2e2;
      color: #991b1b;
    }
    
    .btn-tambah {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      background: #059669;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      transition: background 0.2s;
    }
    
    .btn-tambah:hover {
      background: #047857;
      color: white;
      text-decoration: none;
    }
    
    .action-buttons {
      display: flex;
      gap: 8px;
    }
    
    .btn-edit {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 6px 12px;
      background: #3b82f6;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 12px;
      cursor: pointer;
      text-decoration: none;
    }
    
    .btn-edit:hover {
      background: #2563eb;
      color: white;
      text-decoration: none;
    }
    
    .btn-delete {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 6px 12px;
      background: #dc2626;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 12px;
      cursor: pointer;
    }
    
    .btn-delete:hover {
      background: #b91c1c;
    }
    
    .flash-message {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
    }
    
    .flash-success {
      background: #d1fae5;
      color: #065f46;
      border: 1px solid #a7f3d0;
    }
    
    .flash-error {
      background: #fee2e2;
      color: #dc2626;
      border: 1px solid #fca5a5;
    }
    
    .table-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .stok-warning {
      color: #dc2626;
      font-weight: 500;
    }
  </style>
</head>

<body>
  <div class="app">
    <!-- Sidebar -->
    <?php 
    $activePage = 'sampah';
    include 'sidebar.php'; 
    ?>

    <!-- Main Content -->
    <main class="main">
      <div class="page-header">
        <h2>Daftar Sampah</h2>
        <p>Kelola data jenis sampah</p>
      </div>

      <!-- Flash Message -->
      <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="flash-message flash-<?= $_SESSION['flash_type'] ?>">
          <?= htmlspecialchars($_SESSION['flash_message']) ?>
        </div>
        <?php 
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        ?>
      <?php endif; ?>

      <div class="table-header">
        <div>
          <h3>Data Jenis Sampah</h3>
          <p class="subtle">Kelola harga dan stok sampah</p>
        </div>
        <a href="tambah-sampah.php" class="btn-tambah">
          <i class="lucide-plus"></i> Tambah Sampah
        </a>
      </div>

      <div class="table-card">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Jenis Sampah</th>
              <th>Harga per kg (Rp)</th>
              <th>Stok (kg)</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Load sampah dari tabel 'jenis_sampah'
            $rows = [];
            if (function_exists('supabase_request')) {
              $r = supabase_request('GET', '/rest/v1/jenis_sampah?select=id_jenis,nama_jenis,harga_per_kg,stok_kg,status&order=nama_jenis.asc', null, true);
              if ($r && isset($r['status']) && $r['status'] >= 200 && $r['status'] < 300) {
                $rows = is_array($r['body']) ? $r['body'] : [];
              }
            }

            if (empty($rows)) {
              echo '<tr><td colspan="6" style="text-align: center; padding: 20px;">Tidak ada data sampah. <a href="tambah-sampah.php">Tambah sampah pertama</a></td></tr>';
            } else {
              foreach ($rows as $item) {
                // Skip deleted items
                if (isset($item['status']) && $item['status'] === 'nonaktif') {
                  continue;
                }
                
                // Determine status
                $status = $item['status'] ?? 'aktif';
                $statusClass = $status === 'aktif' ? 'aktif' : 'nonaktif';
                $statusText = ucfirst($status);
                
                // Format harga
                $harga = isset($item['harga_per_kg']) ? number_format((float)$item['harga_per_kg'], 0, ',', '.') : '0';
                
                // Format stok
                $stok = isset($item['stok_kg']) ? number_format((float)$item['stok_kg'], 1, ',', '.') : '0';
                $stokClass = ((float)$item['stok_kg'] ?? 0) < 5 ? 'stok-warning' : '';
                
                echo "
                <tr>
                  <td>" . ($item['id_jenis'] ?? '') . "</td>
                  <td>" . htmlspecialchars($item['nama_jenis'] ?? '') . "</td>
                  <td>" . $harga . "</td>
                  <td class='" . $stokClass . "'>" . $stok . "</td>
                  <td><span class='status $statusClass'>" . htmlspecialchars($statusText) . "</span></td>
                  <td>
                    <div class='action-buttons'>
                      <a href='edit-sampah.php?id=" . urlencode($item['id_jenis']) . "' class='btn-edit'>
                        <i class='lucide-pencil'></i> Edit
                      </a>
                      <form method='POST' action='' style='display:inline;' onsubmit='return confirm(\"Hapus sampah " . htmlspecialchars(addslashes($item['nama_jenis'] ?? '')) . "?\")'>
                        <input type='hidden' name='action' value='delete'>
                        <input type='hidden' name='id' value='" . ($item['id_jenis'] ?? '') . "'>
                        <input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf_token) . "'>
                        <button type='submit' class='btn-delete'>
                          <i class='lucide-trash-2'></i> Hapus
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
                ";
              }
            }
            ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>