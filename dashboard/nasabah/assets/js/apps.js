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

    // Array to store waste items
let wasteItems = [];

// Open modal for adding waste
function openWasteModal() {
    const modal = document.getElementById('wasteModal');
    modal.classList.add('active');
    document.getElementById('wasteForm').reset();
}

// Close modal for adding waste
function closeWasteModal() {
    const modal = document.getElementById('wasteModal');
    modal.classList.remove('active');
}

// Open modal for editing waste
function openEditModal(index) {
    const item = wasteItems[index];
    const modal = document.getElementById('editWasteModal');
    
    document.getElementById('editIndex').value = index;
    document.getElementById('editWasteType').value = item.typeId;
    document.getElementById('editWasteWeight').value = item.weight;
    
    modal.classList.add('active');
}

// Close modal for editing waste
function closeEditModal() {
    const modal = document.getElementById('editWasteModal');
    modal.classList.remove('active');
}

// Add waste item
function addWaste(event) {
    event.preventDefault();
    
    const typeSelect = document.getElementById('wasteType');
    const selectedOption = typeSelect.options[typeSelect.selectedIndex];
    const weight = parseFloat(document.getElementById('wasteWeight').value);
    
    const typeId = selectedOption.value;
    const typeName = selectedOption.getAttribute('data-name');
    const pricePerKg = parseFloat(selectedOption.getAttribute('data-price'));
    const totalPrice = weight * pricePerKg;
    
    const wasteItem = {
        typeId: typeId,
        typeName: typeName,
        weight: weight,
        pricePerKg: pricePerKg,
        totalPrice: totalPrice
    };
    
    wasteItems.push(wasteItem);
    renderWasteTable();
    closeWasteModal();
    
    // Show success message
    showNotification('Sampah berhasil ditambahkan!', 'success');
}

// Update waste item
function updateWaste(event) {
    event.preventDefault();
    
    const index = parseInt(document.getElementById('editIndex').value);
    const typeSelect = document.getElementById('editWasteType');
    const selectedOption = typeSelect.options[typeSelect.selectedIndex];
    const weight = parseFloat(document.getElementById('editWasteWeight').value);
    
    const typeId = selectedOption.value;
    const typeName = selectedOption.getAttribute('data-name');
    const pricePerKg = parseFloat(selectedOption.getAttribute('data-price'));
    const totalPrice = weight * pricePerKg;
    
    wasteItems[index] = {
        typeId: typeId,
        typeName: typeName,
        weight: weight,
        pricePerKg: pricePerKg,
        totalPrice: totalPrice
    };
    
    renderWasteTable();
    closeEditModal();
    
    // Show success message
    showNotification('Sampah berhasil diupdate!', 'success');
}

// Delete waste item
function deleteWaste(index) {
    if (confirm('Apakah Anda yakin ingin menghapus item ini?')) {
        wasteItems.splice(index, 1);
        renderWasteTable();
        showNotification('Sampah berhasil dihapus!', 'success');
    }
}

// Render waste table
function renderWasteTable() {
    const tbody = document.getElementById('wasteTableBody');
    tbody.innerHTML = '';
    
    if (wasteItems.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-light);">
                    <div class="empty-state">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 48px; height: 48px; margin: 0 auto 0.5rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <p>Belum ada sampah yang ditambahkan</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    wasteItems.forEach((item, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${item.typeName}</td>
            <td>${item.weight} kg</td>
            <td>Rp ${formatNumber(item.totalPrice)}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-edit" onclick="openEditModal(${index})">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Setujui
                    </button>
                    <button class="btn-delete" onclick="deleteWaste(${index})">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Tolak
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Calculate total
function calculateTotal() {
    const total = wasteItems.reduce((sum, item) => sum + item.totalPrice, 0);
    document.getElementById('totalAmount').value = formatNumber(total);
    
    showNotification('Total harga berhasil dihitung!', 'success');
}

// Submit waste
function submitWaste() {
    if (wasteItems.length === 0) {
        showNotification('Mohon tambahkan sampah terlebih dahulu!', 'error');
        return;
    }
    
    // Calculate total first
    calculateTotal();
    
    // Prepare data for submission
    const formData = new FormData();
    formData.append('waste_items', JSON.stringify(wasteItems));
    
    // Send AJAX request
    fetch('process_setor.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Sampah berhasil disetor!', 'success');
            // Reset form after 1.5 seconds
            setTimeout(() => {
                wasteItems = [];
                renderWasteTable();
                document.getElementById('totalAmount').value = '0';
                // Redirect to transaction history or dashboard
                window.location.href = 'dashboard.php';
            }, 1500);
        } else {
            showNotification(data.message || 'Terjadi kesalahan!', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Terjadi kesalahan koneksi!', 'error');
    });
}

// Format number with thousand separator
function formatNumber(number) {
    return new Intl.NumberFormat('id-ID').format(Math.round(number));
}

// Show notification
function showNotification(message, type = 'success') {
    // Remove existing notification if any
    const existing = document.querySelector('.notification');
    if (existing) {
        existing.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <svg class="notification-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                ${type === 'success' 
                    ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>'
                    : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>'}
            </svg>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Add styles dynamically
    if (!document.getElementById('notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 9999;
                animation: slideInRight 0.3s ease;
            }
            
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            .notification-success {
                background: #10b981;
                color: white;
            }
            
            .notification-error {
                background: #ef4444;
                color: white;
            }
            
            .notification-content {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            
            .notification-icon {
                width: 20px;
                height: 20px;
                flex-shrink: 0;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const addModal = document.getElementById('wasteModal');
    const editModal = document.getElementById('editWasteModal');
    
    if (event.target === addModal) {
        closeWasteModal();
    }
    if (event.target === editModal) {
        closeEditModal();
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    renderWasteTable();
});