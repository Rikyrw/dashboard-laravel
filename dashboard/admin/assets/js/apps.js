 const ctx = document.getElementById('lineChart');
    new Chart(ctx, {
      type:'line',
      data:{labels:['Sen','Sel','Rab','Kam','Jum','Sab','Min'],datasets:[{label:'Minggu Ini',data:[210,215,230,190,260,220,240],borderColor:'#2ecc71',fill:true,backgroundColor:'rgba(46,204,113,0.1)',tension:0.3}]},
      options:{scales:{x:{grid:{color:'#f0f2f4'}},y:{grid:{color:'#f0f2f4'}}}}
    });

    const pieCtx=document.getElementById('pieChart');
    new Chart(pieCtx, {
      type:'doughnut',
      data:{labels:['Plastik','Kertas','Besi','Botol','Lainnya'],datasets:[{data:[28,24,18,12,18],backgroundColor:['#2ecc71','#58d68d','#82e0aa','#a9dfbf','#d5f5e3']}]},
      options:{plugins:{legend:{position:'bottom'}}}
    });

    const customers=[{nama:'Ahmad Bayu',norek:'999220202',trx:18,kg:12.4,saldo:'Rp 625.000'},{nama:'Siti Aulia',norek:'888110303',trx:15,kg:9.2,saldo:'Rp 410.000'}];
    const tbody=document.querySelector('#customerTable tbody');
    tbody.innerHTML=customers.map((c,i)=>`<tr><td>${i+1}</td><td>${c.nama}</td><td>${c.norek}</td><td>${c.trx}</td><td>${c.kg}</td><td>${c.saldo}</td></tr>`).join('');
    // === MODAL HANDLER UNTUK TAMBAH & EDIT ADMIN ===
// Ambil elemen
const overlay = document.getElementById("overlay");
const modalForm = document.getElementById("modalForm");
// Tombol
const btnTambah = document.getElementById("btnTambahAdmin");
// Tombol close di modal
const closeModalBtn = document.getElementById("closeModal");
// Fungsi buka modal
function openModal(mode, data = null) {
  overlay.style.display = "flex";

  const title = document.getElementById("modalTitle");

  // Mode Tambah Admin
  if (mode === "tambah") {
    title.innerText = "Tambah Admin Baru";

    document.getElementById("admin_id").value = "";
    document.getElementById("nama").value = "";
    document.getElementById("email").value = "";
    document.getElementById("username").value = "";
  }

  // Mode Edit Admin
  if (mode === "edit" && data) {
    title.innerText = "Edit Data Admin";

    document.getElementById("admin_id").value = data.id;
    document.getElementById("nama").value = data.nama;
    document.getElementById("email").value = data.email;
    document.getElementById("username").value = data.username;
  }
}

// Tutup modal
function closeModal() {
  overlay.style.display = "none";
}

// Event tombol tambah
if (btnTambah) {
  btnTambah.addEventListener("click", () => openModal("tambah"));
}

// Event close
if (closeModalBtn) {
  closeModalBtn.addEventListener("click", closeModal);
}

// Tutup modal jika klik area overlay
overlay?.addEventListener("click", (e) => {
  if (e.target === overlay) closeModal();
});

// === BIND TOMBOL EDIT (UNTUK DUMMY DULU) ===
const editButtons = document.querySelectorAll(".btn-edit-admin");

editButtons.forEach((btn) => {
  btn.addEventListener("click", () => {
    const dummyData = {
      id: btn.dataset.id,
      nama: btn.dataset.nama,
      email: btn.dataset.email,
      username: btn.dataset.username,
    };

    openModal("edit", dummyData);
  });
});
