<?php
// pengaturan.php - VERSION DIPERBAIKI
include 'auth_check.php';

// Cek apakah user adalah Super Admin untuk mengakses halaman ini
requireSuperAdmin();

// Generate CSRF token
$csrf_token = generateCsrfToken();
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>GreenPoint • Pengaturan Admin</title>

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/lucide-static@0.469.0/font/lucide.css" rel="stylesheet">

  <!-- Styles -->
  <link rel="stylesheet" href="./assets/css/style.css" />
</head>

<body>

  <div class="app">
    <?php
    $activePage = 'pengaturan';
    include "sidebar.php";
    ?>

    <main class="main">

      <!-- HEADER -->
      <div class="header">
        <div>
          <h1>Pengaturan Admin</h1>
          <p>Kelola akun administrator <span style="background: #7c3aed; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">Super Admin Only</span></p>
        </div>

        <button class="btn btn-primary" onclick="openTambahAdmin()">
          <i class="lucide-plus"></i> Tambah Admin
        </button>
      </div>

      <!-- CARD LIST ADMIN -->
      <section class="card">
        <h3>Daftar Admin</h3>

        <?php
        // Fetch data admin dari Supabase
        $admins = [];
        if (file_exists(__DIR__ . '/../../server/supabase.php')) {
          include_once __DIR__ . '/../../server/supabase.php';
          if (function_exists('supabase_request')) {
            $result = supabase_request('GET', '/rest/v1/admin?select=id_admin,username,nama_lengkap,email,role,status&order=nama_lengkap.asc', null, true);
            if ($result && isset($result['status']) && $result['status'] >= 200 && $result['status'] < 300) {
              $admins = is_array($result['body']) ? $result['body'] : [];
            }
          }
        }

        // Fallback dummy data jika Supabase tidak tersedia
        if (empty($admins)) {
          $admins = [
            ["id_admin" => 1, "nama_lengkap" => "Rizky Saputra", "email" => "rizky@mail.com", "role" => "superadmin", "username" => "rizky", "status" => "aktif"],
            ["id_admin" => 2, "nama_lengkap" => "Dewi Lestari", "email" => "dewi@mail.com", "role" => "admin", "username" => "dewi", "status" => "aktif"],
            ["id_admin" => 3, "nama_lengkap" => "Bagas Pratama", "email" => "bagas@mail.com", "role" => "operator", "username" => "bagas", "status" => "aktif"],
          ];
        }
        ?>

        <table>
          <thead>
            <tr>
              <th>Username</th>
              <th>Nama</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>

          <tbody>
            <?php
            $admin_info = getAdminInfo();
            $current_admin_id = $admin_info['id'];
            ?>

            <?php foreach ($admins as $a):
              $role_display = normalizeRole($a['role']);
              $role_display = ucfirst($role_display);
            ?>
              <tr>
                <td><?= htmlspecialchars($a['username'] ?? '') ?></td>
                <td><?= htmlspecialchars($a['nama_lengkap']) ?></td>
                <td><?= htmlspecialchars($a['email']) ?></td>
                <td><?= htmlspecialchars($role_display) ?></td>
                <td>
                  <span class="status <?= ($a['status'] ?? 'aktif') === 'aktif' ? 'aktif' : 'nonaktif' ?>">
                    <?= htmlspecialchars($a['status'] ?? 'aktif') ?>
                  </span>
                </td>
                <td>
                  <div class="action-buttons">
                    <?php if ($current_admin_id == $a['id_admin'] || isSuperAdmin()): ?>
                      <button class="btn-edit" onclick="openEditAdmin(
                    <?= $a['id_admin'] ?>, 
                    '<?= htmlspecialchars($a['nama_lengkap'], ENT_QUOTES) ?>', 
                    '<?= htmlspecialchars($a['email'], ENT_QUOTES) ?>', 
                    '<?= htmlspecialchars($a['role'], ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($a['username'], ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($a['status'] ?? 'aktif', ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($a['no_hp'] ?? '', ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($a['alamat'] ?? '', ENT_QUOTES) ?>'
                  )">Edit</button>
                    <?php endif; ?>

                    <?php if (isSuperAdmin() && $current_admin_id != $a['id_admin']): ?>
                      <button class="btn-delete" onclick="deleteAdmin(<?= $a['id_admin'] ?>)">Delete</button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>

        </table>
      </section>

    </main>
  </div>


  <!-- ========================== -->
  <!-- OVERLAY -->
  <!-- ========================== -->
  <div id="overlay" class="overlay"></div>


  <!-- ========================== -->
  <!-- MODAL TAMBAH ADMIN -->
  <!-- ========================== -->
  <div id="modalTambah" class="modal">
    <div class="modal-content card">

      <h2>Tambah Admin</h2>

      <form class="edit-form" id="formTambah" method="POST" action="admin_action.php">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" placeholder="Masukkan username" required />
        </div>

        <div class="form-group">
          <label>Nama Lengkap</label>
          <input type="text" name="nama_lengkap" placeholder="Masukkan nama lengkap" required />
        </div>

        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" placeholder="Masukkan email" required />
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Masukkan password" required />
        </div>

        <div class="form-group">
          <label>No. HP</label>
          <input type="text" name="no_hp" placeholder="Masukkan nomor HP" />
        </div>

        <div class="form-group">
          <label>Alamat</label>
          <textarea name="alamat" placeholder="Masukkan alamat"></textarea>
        </div>

        <div class="form-group">
          <label>Role</label>
          <div class="select-wrapper">
            <select name="role">
              <option value="operator">Operator</option>
              <option value="admin">Admin</option>
              <option value="superadmin">Super Admin</option>
            </select>
          </div>
        </div>

        <div class="form-actions">
          <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
          <button type="submit" class="btn-primary">Simpan</button>
        </div>
      </form>

    </div>
  </div>


  <!-- ========================== -->
  <!-- MODAL EDIT ADMIN -->
  <!-- ========================== -->
  <div id="modalEdit" class="modal">
    <div class="modal-content card">

      <h2>Edit Admin</h2>

      <form class="edit-form" id="formEdit" method="POST" action="admin_action.php">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="id_admin" id="edit_id">

        <div class="form-group">
          <label>Username</label>
          <input id="edit_username" type="text" name="username" required />
        </div>

        <div class="form-group">
          <label>Nama Lengkap</label>
          <input id="edit_nama" type="text" name="nama_lengkap" required />
        </div>

        <div class="form-group">
          <label>Email</label>
          <input id="edit_email" type="email" name="email" required />
        </div>

        <div class="form-group">
          <label>Password (kosongkan jika tidak diubah)</label>
          <input type="password" name="password" placeholder="Kosongkan jika tidak ingin mengubah" />
        </div>

        <div class="form-group">
          <label>No. HP</label>
          <input id="edit_no_hp" type="text" name="no_hp" />
        </div>

        <div class="form-group">
          <label>Alamat</label>
          <textarea id="edit_alamat" name="alamat"></textarea>
        </div>

        <div class="form-group">
          <label>Role</label>
          <div class="select-wrapper">
            <select id="edit_role" name="role">
              <option value="operator">Operator</option>
              <option value="admin">Admin</option>
              <option value="superadmin">Super Admin</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Status</label>
          <div class="select-wrapper">
            <select id="edit_status" name="status">
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Nonaktif</option>
            </select>
          </div>
        </div>

        <div class="form-actions">
          <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
          <button type="submit" class="btn-primary">Update</button>
        </div>
      </form>

    </div>
  </div>

  <!-- ========================== -->
  <!-- SCRIPT MODAL -->
  <!-- ========================== -->
  <script>
    const overlay = document.getElementById("overlay");
    const modalTambah = document.getElementById("modalTambah");
    const modalEdit = document.getElementById("modalEdit");

    // BUKA TAMBAH
    function openTambahAdmin() {
      overlay.style.display = "block";
      modalTambah.style.display = "flex";
      document.getElementById('formTambah').reset();
    }

    // BUKA EDIT
    function openEditAdmin(id, nama, email, role, username, status, no_hp, alamat) {
      overlay.style.display = "block";
      modalEdit.style.display = "flex";

      document.getElementById("edit_id").value = id;
      document.getElementById("edit_username").value = username || '';
      document.getElementById("edit_nama").value = nama;
      document.getElementById("edit_email").value = email;
      document.getElementById("edit_role").value = role;
      document.getElementById("edit_status").value = status;
      document.getElementById("edit_no_hp").value = no_hp || '';
      document.getElementById("edit_alamat").value = alamat || '';
    }

    // TUTUP
    function closeModal() {
      overlay.style.display = "none";
      modalTambah.style.display = "none";
      modalEdit.style.display = "none";
    }

    // DELETE ADMIN
    function deleteAdmin(id) {
      if (confirm('Apakah Anda yakin ingin menghapus admin ini?')) {
        // Buat form data dengan CSRF token
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id_admin', id);
        formData.append('csrf_token', '<?= htmlspecialchars($csrf_token) ?>');

        fetch('admin_action.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.status === 'success') {
              alert(data.message);
              location.reload();
            } else {
              alert('Error: ' + data.message);
            }
          })
          .catch(error => {
            alert('Terjadi kesalahan: ' + error);
          });
      }
    }
  </script>
</body>

</html>