<?php
// edit-sampah.php
session_start();
require_once __DIR__ . '/auth_check.php';
requireAdmin();

$activePage = 'sampah';

// Ambil ID dari URL
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Include supabase helper
if (file_exists(__DIR__ . '/../../server/supabase.php')) {
    include_once __DIR__ . '/../../server/supabase.php';
}

$error = '';
$success = '';
$item = null;

// Load data sampah dari tabel 'jenis_sampah'
if ($id > 0 && function_exists('supabase_request')) {
    $r = supabase_request('GET', '/rest/v1/jenis_sampah?id_jenis=eq.' . urlencode($id) . '&select=id_jenis,nama_jenis,harga_per_kg,stok_kg,status', null, true);
    if ($r && isset($r['status']) && $r['status'] >= 200 && $r['status'] < 300) {
        $rows = $r['body'] ?? [];
        if (is_array($rows) && count($rows) > 0) {
            $item = $rows[0];
        }
    }
}

// Jika tidak ditemukan, redirect ke halaman sampah
if (!$item) {
    header('Location: sampah.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_jenis = trim($_POST['nama_jenis'] ?? '');
    $harga_per_kg = trim($_POST['harga_per_kg'] ?? '');
    $stok_kg = trim($_POST['stok_kg'] ?? '');
    $status = $_POST['status'] ?? 'aktif';
    
    // CSRF validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        $error = 'Token keamanan tidak valid';
    } elseif (empty($nama_jenis)) {
        $error = 'Nama jenis sampah harus diisi';
    } elseif (!is_numeric($harga_per_kg) || $harga_per_kg <= 0) {
        $error = 'Harga harus angka positif';
    } elseif (!is_numeric($stok_kg) || $stok_kg < 0) {
        $error = 'Stok harus angka positif';
    } else {
        // Convert to proper types
        $harga_per_kg = (float)$harga_per_kg;
        $stok_kg = (float)$stok_kg;
        
        // Prepare data for update
        $data = [
            'nama_jenis' => $nama_jenis,
            'harga_per_kg' => $harga_per_kg,
            'stok_kg' => $stok_kg,
            'status' => $status
        ];
        
        // Update in Supabase
        if (function_exists('supabase_request')) {
            $result = supabase_request('PATCH', '/rest/v1/jenis_sampah?id_jenis=eq.' . $id, $data, true);
            
            if ($result && isset($result['status']) && $result['status'] >= 200 && $result['status'] < 300) {
                $success = 'Data sampah berhasil diperbarui';
                // Reload item data
                $r = supabase_request('GET', '/rest/v1/jenis_sampah?id_jenis=eq.' . urlencode($id) . '&select=id_jenis,nama_jenis,harga_per_kg,stok_kg,status', null, true);
                if ($r && isset($r['status']) && $r['status'] >= 200 && $r['status'] < 300) {
                    $rows = $r['body'] ?? [];
                    if (is_array($rows) && count($rows) > 0) {
                        $item = $rows[0];
                    }
                }
            } else {
                $error = 'Gagal memperbarui data sampah';
            }
        } else {
            $error = 'Supabase helper tidak tersedia';
        }
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
  <title>GreenPoint • Edit Data Sampah</title>

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">

  <!-- Main Styles -->
  <link rel="stylesheet" href="./assets/css/style.css" />
  
  <style>
    .form-container {
      max-width: 600px;
      margin: 0 auto;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: #374151;
    }
    
    .form-group input,
    .form-group select {
      width: 100%;
      padding: 10px 12px;
      border: 2px solid #d1d5db;
      border-radius: 6px;
      font-size: 14px;
    }
    
    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #059669;
    }
    
    .form-actions {
      display: flex;
      gap: 12px;
      margin-top: 30px;
    }
    
    .btn-primary {
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
    }
    
    .btn-primary:hover {
      background: #047857;
    }
    
    .btn-secondary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      background: #6b7280;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
    }
    
    .btn-secondary:hover {
      background: #4b5563;
    }
    
    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
    }
    
    .alert-error {
      background: #fee2e2;
      color: #dc2626;
      border: 1px solid #fca5a5;
    }
    
    .alert-success {
      background: #d1fae5;
      color: #065f46;
      border: 1px solid #a7f3d0;
    }
    
    .input-group {
      display: flex;
      align-items: center;
    }
    
    .input-group span {
      background: #f3f4f6;
      padding: 10px 12px;
      border: 2px solid #d1d5db;
      border-right: none;
      border-radius: 6px 0 0 6px;
      font-size: 14px;
    }
    
    .input-group input {
      border-radius: 0 6px 6px 0;
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
        <h2>Edit Data Sampah</h2>
        <p>Ubah informasi jenis sampah</p>
      </div>

      <div class="card form-container">
        <?php if (!empty($error)): ?>
          <div class="alert alert-error">
            <i class="lucide-alert-circle"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
          <div class="alert alert-success">
            <i class="lucide-check-circle"></i> <?= htmlspecialchars($success) ?>
          </div>
        <?php endif; ?>
        
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          
          <div class="form-group">
            <label for="nama_jenis">Nama Jenis Sampah *</label>
            <input type="text" id="nama_jenis" name="nama_jenis" 
                   value="<?= htmlspecialchars($item['nama_jenis'] ?? '') ?>" 
                   placeholder="Contoh: Plastik, Kertas, Botol Kaca" required>
          </div>
          
          <div class="form-group">
            <label for="harga_per_kg">Harga per kg (Rp) *</label>
            <div class="input-group">
              <span>Rp</span>
              <input type="number" id="harga_per_kg" name="harga_per_kg" 
                     value="<?= htmlspecialchars($item['harga_per_kg'] ?? '') ?>" 
                     placeholder="5000" min="0" step="100" required>
            </div>
          </div>
          
          <div class="form-group">
            <label for="stok_kg">Stok (kg) *</label>
            <input type="number" id="stok_kg" name="stok_kg" 
                   value="<?= htmlspecialchars($item['stok_kg'] ?? '') ?>" 
                   placeholder="0" min="0" step="0.1" required>
          </div>
          
          <div class="form-group">
            <label for="status">Status *</label>
            <select id="status" name="status" required>
              <option value="aktif" <?= ($item['status'] ?? '') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
              <option value="nonaktif" <?= ($item['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
          </div>
          
          <div class="form-actions">
            <button type="submit" class="btn-primary">
              <i class="lucide-save"></i> Simpan Perubahan
            </button>
            <a href="sampah.php" class="btn-secondary">
              <i class="lucide-arrow-left"></i> Kembali
            </a>
          </div>
        </form>
      </div>
    </main>
  </div>
  
  <script>
    // Auto-focus on nama_jenis field
    document.getElementById('nama_jenis').focus();
    
    // Format number inputs
    document.getElementById('harga_per_kg').addEventListener('blur', function(e) {
      if (e.target.value) {
        e.target.value = parseFloat(e.target.value).toFixed(0);
      }
    });
    
    document.getElementById('stok_kg').addEventListener('blur', function(e) {
      if (e.target.value) {
        e.target.value = parseFloat(e.target.value).toFixed(1);
      }
    });
  </script>
</body>
</html>