
<?php
// BARIS PALING ATAS - SEBELUM APA PUN
session_start();

// Debug session
if (isset($_GET['debug'])) {
    echo "<pre>Session Debug:\n";
    print_r($_SESSION);
    echo "\n</pre>";
}

// Include auth check
require_once __DIR__ . '/auth_check.php';

// Cek apakah user adalah admin yang valid
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Redirect ke login jika bukan admin
    header('Location: login.php');
    exit;
}

// Set $activePage untuk sidebar (sesuaikan dengan halaman)
$activePage = 'dashboard'; // GANTI sesuai halaman
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Daftar Nasabah | GreenPoint</title>

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">

  <!-- Main Styles -->
  <link rel="stylesheet" href="./assets/css/style.css" />
</head>

<body>
  <div class="app">
    <?php
      // Ensure session is started for auth checks and flash messages
      if (session_status() === PHP_SESSION_NONE) {
        session_start();
      }

      $activePage = 'nasabah';
      // Use an explicit include path to avoid issues with include_path
      include __DIR__ . '/sidebar.php';

      // Use Supabase for admin data operations
      if (file_exists(__DIR__ . '/../../server/supabase.php')) {
        include_once __DIR__ . '/../../server/supabase.php';
      }

      // CSRF token
      if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
      }

      // Helper: allow Firebase token-based admin requests when available
      $admin_verified_via_firebase = false;
      if (file_exists(__DIR__ . '/../../server/supabase.php')) {
        include_once __DIR__ . '/../../server/supabase.php';
        if (function_exists('supabase_is_admin_request')) {
          $fbCheck = supabase_is_admin_request();
          if (is_array($fbCheck) && !empty($fbCheck['ok'])) {
            $admin_verified_via_firebase = true;
          }
        }
      }

      // Handle POST action: approve or reject nasabah
      $flash = '';
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && !empty($_POST['id_nasabah'])) {
        $id = (int) $_POST['id_nasabah'];
        $action = $_POST['action']; // expected: aktifkan | tolak

        // If not verified via Firebase token, validate CSRF (session-based admin)
        if (!$admin_verified_via_firebase) {
          $token = $_POST['csrf_token'] ?? '';
          if (!hash_equals($_SESSION['csrf_token'], $token)) {
            $flash = 'Token keamanan tidak valid.';
          }
        }

        if (empty($flash)) {
          if ($action === 'aktifkan') {
            $newStatus = 'aktif';
          } elseif ($action === 'tolak') {
            $newStatus = 'nonaktif';
          } else {
            $newStatus = null;
          }

          if ($newStatus) {
            // Use Supabase REST to update nasabah status
            if (function_exists('supabase_request')) {
              $patch = ['status_akun' => $newStatus];
              $res = supabase_request('PATCH', '/rest/v1/nasabah?id=eq.' . urlencode($id), $patch, true);
              if ($res && isset($res['status']) && ($res['status'] >= 200 && $res['status'] < 300)) {
                $flash = 'Status nasabah berhasil diperbarui.';
              } else {
                $flash = 'Gagal memperbarui status nasabah.';
              }
            } else {
              $flash = 'Supabase helper tidak tersedia.';
            }
          } else {
            $flash = 'Aksi tidak dikenali.';
          }
        }
      }
      
      // session flash (from other pages, e.g. delete)
      if (!empty($_SESSION['flash_nasabah'])) {
        $flash = $_SESSION['flash_nasabah'];
        unset($_SESSION['flash_nasabah']);
      }
    ?>
 <!-- ppp -->
    <main class="main">
      <div class="header">
        <div>
          <h1>Daftar Nasabah</h1>
          <p>Kelola data nasabah</p>
        </div>
      </div>

      <section class="card">
        <div class="table-header">
          <h3>Daftar Semua Nasabah</h3>
         
        <?php if (!empty($flash)): ?>
          <div style="margin-top:12px; color:#065f46; font-weight:600;"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <div class="table-filter">
          <div class="select-wrapper">
            <select class="filter-select">
              <option>Semua</option>
              <option>Aktif</option>
              <option>Nonaktif</option>
            </select>
          </div>
          <input
            type="text"
            class="filter-search"
            placeholder="Cari nasabah..."
          />
        </div>

        <?php
              // Fetch nasabah rows from Supabase
              $nasabahs = [];
              if (function_exists('supabase_request')) {
                $q = '/rest/v1/nasabah?select=id_nasabah,nama_nasabah,alamat,no_hp,saldo,status_akun&order=tanggal_daftar.desc';
                $r = supabase_request('GET', $q, null, true);
                if ($r && isset($r['status']) && $r['status'] >= 200 && $r['status'] < 300) {
                  $nasabahs = is_array($r['body']) ? $r['body'] : [];
                }
              }
        ?>

        <table>
          <thead>
            <tr>
              <th>No. Rekening</th>
              <th>Nama</th>
              <th>Alamat</th>
              <th>No. HP</th>
              <th>Saldo</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($nasabahs as $n): ?>
              <tr>
                <td><?= htmlspecialchars($n['id_nasabah']) ?></td>
                <td><?= htmlspecialchars($n['nama_nasabah']) ?></td>
                <td><?= htmlspecialchars($n['alamat']) ?></td>
                <td><?= htmlspecialchars($n['no_hp']) ?></td>
                <td>Rp <?= number_format((float)$n['saldo'], 0, ',', '.') ?></td>
                <td>
                  <?php if ($n['status_akun'] === 'aktif'): ?>
                    <span class="status aktif">Aktif</span>
                  <?php elseif ($n['status_akun'] === 'menunggu'): ?>
                    <span class="status menunggu">Menunggu</span>
                  <?php elseif ($n['status_akun'] === 'nonaktif'): ?>
                    <span class="status nonaktif">Ditolak</span>
                  <?php else: ?>
                    <span class="status nonaktif"><?= htmlspecialchars($n['status_akun']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="action-buttons">
                  <?php if ($n['status_akun'] === 'menunggu'): ?>
                    <form method="POST" class="action-form" style="display:inline-block; margin-right:6px;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="id_nasabah" value="<?= htmlspecialchars($n['id_nasabah']) ?>">
                      <input type="hidden" name="action" value="aktifkan">
                      <button type="submit" class="btn btn-success btn-sm">Aktifkan</button>
                    </form>
                    <form method="POST" class="action-form" style="display:inline-block;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="id_nasabah" value="<?= htmlspecialchars($n['id_nasabah']) ?>">
                      <input type="hidden" name="action" value="tolak">
                      <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                    </form>
                  <?php else: ?>
                    <a class="btn btn-outline-secondary btn-sm" href="edit-nasabah.php?id=<?= htmlspecialchars($n['id_nasabah']) ?>">Edit</a>
                     <form method="POST" action="hapus-nasabah.php" class="action-form" style="display:inline-block; margin:0 6px;"> 
                       <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                       <input type="hidden" name="id" value="<?= htmlspecialchars($n['id_nasabah']) ?>">
                       <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                     </form>
                    <a class="btn btn-outline-primary btn-sm" href="riwayat-nasabah.php?id=<?= htmlspecialchars($n['id_nasabah']) ?>">Riwayat</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>
  <script>
    // Attach confirmation to admin action forms
      (function(){
        let pendingForm = null;
        const modal = document.getElementById('adminConfirmModal');
        const msgEl = document.getElementById('adminConfirmMessage');
        const titleEl = document.getElementById('adminConfirmTitle');

        function showModal(title, message) {
          titleEl.textContent = title || 'Konfirmasi';
          msgEl.textContent = message || '';
          modal.style.display = 'flex';
        }
        function hideModal() {
          modal.style.display = 'none';
        }

        document.querySelectorAll('.action-form').forEach(function(f){
          f.addEventListener('submit', function(e){
            e.preventDefault();
            pendingForm = f;
            // Determine message
            const actionInput = f.querySelector('input[name="action"]');
            let message = 'Tindakan akan diproses. Lanjutkan?';
            if (actionInput) {
              const a = actionInput.value;
              if (a === 'aktifkan') message = 'Aktifkan akun nasabah ini?';
              else if (a === 'tolak') message = 'Tolak (nonaktifkan) akun nasabah ini?';
            } else {
              // likely delete form
              if (f.getAttribute('action') && f.getAttribute('action').includes('hapus')) {
                message = 'Hapus nasabah ini? Tindakan tidak dapat dibatalkan.';
              }
            }
            showModal('Konfirmasi Aksi', message);
          });
        });

        document.getElementById('adminConfirmCancel').addEventListener('click', function(){
          pendingForm = null; hideModal();
        });

        document.getElementById('adminConfirmOk').addEventListener('click', function(){
          if (!pendingForm) { hideModal(); return; }
          const btn = pendingForm.querySelector('button[type=submit]');
          if (btn) { btn.disabled = true; btn.innerHTML = 'Memproses...'; }
          // submit the form
          pendingForm.submit();
          pendingForm = null;
          hideModal();
        });
      })();
  </script>
    <!-- Confirmation Modal -->
    <div id="adminConfirmModal" class="modal-backdrop" style="display:none;">
      <div class="modal-box">
        <h3 id="adminConfirmTitle">Konfirmasi</h3>
        <p id="adminConfirmMessage">Apakah Anda yakin?</p>
        <div class="modal-actions">
          <button id="adminConfirmCancel" class="btn btn-outline-secondary">Batal</button>
          <button id="adminConfirmOk" class="btn btn-primary">Lanjutkan</button>
        </div>
      </div>
    </div>
</body>
</html>
