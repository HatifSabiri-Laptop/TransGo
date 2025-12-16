<?php
$page_title = 'Manajemen Layanan';
require_once '../config/config.php';
require_login();
require_admin();

$conn = getDBConnection();
$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_service'])) {
        $service_name = clean_input($_POST['service_name']);
        $route = clean_input($_POST['route']);
        $departure_date = clean_input($_POST['departure_date']);
        $departure_time = clean_input($_POST['departure_time']);
        $arrival_time = clean_input($_POST['arrival_time']);
        $price = floatval($_POST['price']);
        $capacity = intval($_POST['capacity']);

        $stmt = $conn->prepare("INSERT INTO services (service_name, route, departure_date, departure_time, arrival_time, price, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("sssssdi", $service_name, $route, $departure_date, $departure_time, $arrival_time, $price, $capacity);

        if ($stmt->execute()) {
            log_activity($conn, $_SESSION['user_id'], 'add_service', "Added service: $service_name");
            $success = 'Layanan berhasil ditambahkan!';
        } else {
            $error = 'Gagal menambahkan layanan!';
        }
        $stmt->close();
    } elseif (isset($_POST['edit_service'])) {
        $service_id = intval($_POST['service_id']);
        $service_name = clean_input($_POST['service_name']);
        $route = clean_input($_POST['route']);
        $departure_date = clean_input($_POST['departure_date']);
        $departure_time = clean_input($_POST['departure_time']);
        $arrival_time = clean_input($_POST['arrival_time']);
        $price = floatval($_POST['price']);
        $capacity = intval($_POST['capacity']);
        $status = clean_input($_POST['status']);

        $stmt = $conn->prepare("UPDATE services SET service_name = ?, route = ?, departure_date = ?, departure_time = ?, arrival_time = ?, price = ?, capacity = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssssdisi", $service_name, $route, $departure_date, $departure_time, $arrival_time, $price, $capacity, $status, $service_id);

        if ($stmt->execute()) {
            log_activity($conn, $_SESSION['user_id'], 'edit_service', "Edited service ID: $service_id");
            $success = 'Layanan berhasil diupdate!';
        } else {
            $error = 'Gagal mengupdate layanan!';
        }
        $stmt->close();
    } elseif (isset($_POST['delete_service'])) {
        $service_id = intval($_POST['service_id']);

        $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
        $stmt->bind_param("i", $service_id);

        if ($stmt->execute()) {
            log_activity($conn, $_SESSION['user_id'], 'delete_service', "Deleted service ID: $service_id");
            $success = 'Layanan berhasil dihapus!';
        } else {
            $error = 'Gagal menghapus layanan!';
        }
        $stmt->close();
    }
}

// Get all services
$services = $conn->query("SELECT s.*, 
    (SELECT COUNT(*) FROM reservations WHERE service_id = s.id) as total_bookings,
    (SELECT COUNT(*) FROM reservations WHERE service_id = s.id AND travel_date >= CURDATE() AND booking_status = 'confirmed') as upcoming_bookings
    FROM services s 
    ORDER BY s.departure_date DESC, s.departure_time DESC");

include '../includes/header.php';
?>

<style>
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr !important;
    }
}

.modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    overflow-y: auto;
    padding: 1rem;
}

.modal-backdrop.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    max-width: 650px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    margin: auto;
}

/* Table Responsive Styles */
.table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    width: 100%;
    display: block;
}

.table {
    min-width: 1200px;
    width: 100%;
    border-collapse: collapse;
}

/* Ensure table cells have proper alignment */
.table th,
.table td {
    vertical-align: middle !important;
    padding: 0.75rem !important;
}

/* Desktop and tablet table styles (always visible) */
.desktop-table {
    display: table;
    width: 100%;
}

.mobile-cards {
    display: none;
}

/* Mobile card styles (only for phones) */
@media (max-width: 768px) {
    .mobile-service-card {
        background: white;
        border: 1px solid var(--light);
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .mobile-service-card .service-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--light);
    }
    
    .mobile-service-card .service-id {
        font-weight: bold;
        color: var(--primary);
        font-size: 0.9rem;
    }
    
    .mobile-service-card .service-status {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .mobile-service-card .service-main {
        margin-bottom: 1rem;
    }
    
    .mobile-service-card .service-name {
        font-weight: bold;
        font-size: 1.1rem;
        margin-bottom: 0.25rem;
        color: var(--dark);
    }
    
    .mobile-service-card .service-route {
        color: var(--gray);
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
    }
    
    .mobile-service-card .service-details {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .mobile-service-card .service-detail-item {
        display: flex;
        flex-direction: column;
        font-size: 0.9rem;
    }
    
    .mobile-service-card .detail-label {
        font-size: 0.8rem;
        color: var(--gray);
        margin-bottom: 0.25rem;
    }
    
    .mobile-service-card .detail-value {
        font-weight: 600;
        color: var(--dark);
    }
    
    .mobile-service-card .service-bookings {
        background: #f8fafc;
        padding: 0.75rem;
        border-radius: 6px;
        margin-bottom: 1rem;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        text-align: center;
    }
    
    .mobile-service-card .booking-count {
        font-weight: bold;
        font-size: 1.2rem;
        color: var(--primary);
    }
    
    .mobile-service-card .booking-label {
        font-size: 0.8rem;
        color: var(--gray);
    }
    
    .mobile-service-card .service-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        padding-top: 0.75rem;
        border-top: 1px solid var(--light);
    }
    
    .mobile-service-card .service-actions .btn {
        padding: 0.4rem 0.8rem !important;
        font-size: 0.9rem !important;
        min-width: auto;
    }
}

/* Hide desktop table on mobile */
@media (max-width: 768px) {
    .desktop-table {
        display: none;
    }
    
    .mobile-cards {
        display: block;
    }
    
    .table-container {
        overflow-x: visible;
    }
}

@media (max-width: 480px) {
    .mobile-service-card .service-details {
        grid-template-columns: 1fr;
    }
    
    .mobile-service-card .service-actions {
        flex-direction: column;
    }
    
    .mobile-service-card .service-actions .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Fix status badge alignment */
.badge {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
}

.badge-success {
    background: #d1fae5;
    color: #065f46;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-inactive {
    background: #fee2e2;
    color: #991b1b;
}

/* Ensure table actions are properly aligned */
.table td:last-child {
    white-space: nowrap;
    text-align: center;
}

.table td:last-child form {
    display: inline-block;
}

/* Make sure buttons in table are properly spaced */
.table .btn {
    margin: 2px;
    padding: 0.4rem 0.8rem !important;
}

/* Add scrollbar styling */
.table-container::-webkit-scrollbar {
    height: 8px;
}

.table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1><i class="fas fa-bus"></i> Manajemen Layanan</h1>
        <p style="color: var(--gray);">Kelola layanan transportasi dan jadwal</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Add Service Form -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h3 class="card-title">Tambah Layanan Baru</h3>
            </div>
            <form method="POST" action="">
                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="service_name">Nama Layanan *</label>
                        <input type="text" name="service_name" id="service_name" class="form-control"
                            placeholder="Express Bus A" required>
                    </div>

                    <div class="form-group">
                        <label for="route">Rute *</label>
                        <input type="text" name="route" id="route" class="form-control"
                            placeholder="Jakarta - Bandung" required>
                    </div>

                    <div class="form-group">
                        <label for="departure_date">Tanggal Keberangkatan *</label>
                        <input type="date" name="departure_date" id="departure_date" class="form-control" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="departure_time">Jam Berangkat *</label>
                        <input type="time" name="departure_time" id="departure_time" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="arrival_time">Jam Tiba *</label>
                        <input type="time" name="arrival_time" id="arrival_time" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="price">Harga (Rp) *</label>
                        <input type="number" name="price" id="price" class="form-control"
                            min="0" step="1000" placeholder="150000" required>
                    </div>

                    <div class="form-group">
                        <label for="capacity">Kapasitas *</label>
                        <input type="number" name="capacity" id="capacity" class="form-control"
                            min="1" placeholder="20" value="20" required>
                    </div>
                </div>

                <button type="submit" name="add_service" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Tambah Layanan
                </button>
            </form>
        </div>

        <!-- Services Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Layanan</h3>
            </div>
            
            <!-- Desktop & Tablet Table (with horizontal scroll) -->
            <div class="table-container">
                <table class="table desktop-table">
                    <thead>
                        <tr>
                            <th style="min-width: 50px;">ID</th>
                            <th style="min-width: 150px;">Layanan</th>
                            <th style="min-width: 150px;">Rute</th>
                            <th style="min-width: 100px;">Tanggal</th>
                            <th style="min-width: 120px;">Jadwal</th>
                            <th style="min-width: 120px;">Harga</th>
                            <th style="min-width: 100px;">Kapasitas</th>
                            <th style="min-width: 100px;">Status</th>
                            <th style="min-width: 120px;">Booking</th>
                            <th style="min-width: 150px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($service = $services->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $service['id']; ?></td>
                                <td><strong><?php echo $service['service_name']; ?></strong></td>
                                <td><?php echo $service['route']; ?></td>
                                <td><?php echo format_date($service['departure_date'] ?? date('Y-m-d')); ?></td>
                                <td style="white-space: nowrap;">
                                    <?php echo date('H:i', strtotime($service['departure_time'])); ?> -
                                    <?php echo date('H:i', strtotime($service['arrival_time'])); ?>
                                </td>
                                <td style="white-space: nowrap;"><?php echo format_currency($service['price']); ?></td>
                                <td><?php echo $service['capacity']; ?> kursi</td>
                                <td>
                                    <span class="badge badge-<?php echo $service['status'] === 'active' ? 'success' : 'danger'; ?>" style="vertical-align: middle;">
                                        <?php echo $service['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <strong><?php echo $service['total_bookings']; ?></strong> total<br>
                                    <small style="color: var(--gray);"><?php echo $service['upcoming_bookings']; ?> mendatang</small>
                                </td>
                                <td style="white-space: nowrap; text-align: center;">
                                    <button onclick="editService(<?php echo htmlspecialchars(json_encode($service)); ?>)"
                                        class="btn btn-secondary" style="padding: 0.5rem 1rem; margin-right: 0.25rem;">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" action="" style="display: inline;"
                                        onsubmit="return confirm('Yakin ingin menghapus layanan ini?')">
                                        <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                        <button type="submit" name="delete_service" class="btn btn-danger" style="padding: 0.5rem 1rem;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Cards (only for phones) -->
            <div class="mobile-cards" style="padding: 1rem;">
                <?php 
                // Reset pointer to iterate again
                $services->data_seek(0);
                while ($service = $services->fetch_assoc()): 
                ?>
                <div class="mobile-service-card">
                    <div class="service-header">
                        <div class="service-id">ID: <?php echo $service['id']; ?></div>
                        <div class="service-status status-<?php echo $service['status']; ?>">
                            <?php echo $service['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                        </div>
                    </div>
                    
                    <div class="service-main">
                        <div class="service-name"><?php echo $service['service_name']; ?></div>
                        <div class="service-route"><?php echo $service['route']; ?></div>
                    </div>
                    
                    <div class="service-details">
                        <div class="service-detail-item">
                            <span class="detail-label">Tanggal</span>
                            <span class="detail-value"><?php echo format_date($service['departure_date'] ?? date('Y-m-d')); ?></span>
                        </div>
                        <div class="service-detail-item">
                            <span class="detail-label">Jadwal</span>
                            <span class="detail-value">
                                <?php echo date('H:i', strtotime($service['departure_time'])); ?> - 
                                <?php echo date('H:i', strtotime($service['arrival_time'])); ?>
                            </span>
                        </div>
                        <div class="service-detail-item">
                            <span class="detail-label">Harga</span>
                            <span class="detail-value"><?php echo format_currency($service['price']); ?></span>
                        </div>
                        <div class="service-detail-item">
                            <span class="detail-label">Kapasitas</span>
                            <span class="detail-value"><?php echo $service['capacity']; ?> kursi</span>
                        </div>
                    </div>
                    
                    <div class="service-bookings">
                        <div>
                            <div class="booking-count"><?php echo $service['total_bookings']; ?></div>
                            <div class="booking-label">Total Booking</div>
                        </div>
                        <div>
                            <div class="booking-count"><?php echo $service['upcoming_bookings']; ?></div>
                            <div class="booking-label">Mendatang</div>
                        </div>
                    </div>
                    
                    <div class="service-actions">
                        <button onclick="editService(<?php echo htmlspecialchars(json_encode($service)); ?>)"
                                class="btn btn-secondary">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                            <button type="submit" name="delete_service" class="btn btn-danger" 
                                    onclick="return confirm('Yakin ingin menghapus layanan ini?')">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</section>

<!-- Edit Modal -->
<div id="editModal" class="modal-backdrop">
    <div class="modal-content">
        <div class="card-header" style="position: sticky;margin: 1rem 1rem; z-index: 10; background: white; border-radius: 12px 12px 0 0;">
            <h3 class="card-title">Edit Layanan</h3>
        </div>
        <form method="POST" action="" id="editForm" style="padding: 1.5rem;">
            <input type="hidden" name="service_id" id="edit_service_id">

            <div class="form-group">
                <label for="edit_service_name">Nama Layanan *</label>
                <input type="text" name="service_name" id="edit_service_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="edit_route">Rute *</label>
                <input type="text" name="route" id="edit_route" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="edit_departure_date">Tanggal Keberangkatan *</label>
                <input type="date" name="departure_date" id="edit_departure_date" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="edit_departure_time">Jam Berangkat *</label>
                    <input type="time" name="departure_time" id="edit_departure_time" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="edit_arrival_time">Jam Tiba *</label>
                    <input type="time" name="arrival_time" id="edit_arrival_time" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="edit_price">Harga (Rp) *</label>
                    <input type="number" name="price" id="edit_price" class="form-control" min="0" step="1000" required>
                </div>

                <div class="form-group">
                    <label for="edit_capacity">Kapasitas *</label>
                    <input type="number" name="capacity" id="edit_capacity" class="form-control" min="1" required>
                </div>
            </div>

            <div class="form-group">
                <label for="edit_status">Status *</label>
                <select name="status" id="edit_status" class="form-control" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div style="display: flex; gap: 1rem; padding-top: 1rem; border-top: 2px solid var(--light); margin-top: 1rem;">
                <button type="submit" name="edit_service" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
                <button type="button" onclick="closeModal()" class="btn btn-danger" style="flex: 1;">
                    <i class="fas fa-times"></i> Batal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editService(service) {
    document.getElementById('edit_service_id').value = service.id;
    document.getElementById('edit_service_name').value = service.service_name;
    document.getElementById('edit_route').value = service.route;
    document.getElementById('edit_departure_date').value = service.departure_date || '<?php echo date('Y-m-d'); ?>';
    document.getElementById('edit_departure_time').value = service.departure_time;
    document.getElementById('edit_arrival_time').value = service.arrival_time;
    document.getElementById('edit_price').value = service.price;
    document.getElementById('edit_capacity').value = service.capacity;
    document.getElementById('edit_status').value = service.status;
    
    document.getElementById('editModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('editModal').classList.remove('show');
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>