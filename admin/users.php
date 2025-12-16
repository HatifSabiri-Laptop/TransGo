<?php
$page_title = 'Manajemen User';
require_once '../config/config.php';
require_login();
require_admin();

$conn = getDBConnection();
$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $email = clean_input($_POST['email']);
        $full_name = clean_input($_POST['full_name']);
        $phone = clean_input($_POST['phone']);
        $password = $_POST['password'];
        $role = clean_input($_POST['role']);
        
        // Check if email exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = 'Email sudah terdaftar!';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (email, password, full_name, phone, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $email, $hashed_password, $full_name, $phone, $role);
            
            if ($stmt->execute()) {
                log_activity($conn, $_SESSION['user_id'], 'add_user', "Added user: $email");
                $success = 'User berhasil ditambahkan!';
            } else {
                $error = 'Gagal menambahkan user!';
            }
            $stmt->close();
        }
        $check->close();
    } elseif (isset($_POST['edit_user'])) {
        $user_id = intval($_POST['user_id']);
        $email = clean_input($_POST['email']);
        $full_name = clean_input($_POST['full_name']);
        $phone = clean_input($_POST['phone']);
        $role = clean_input($_POST['role']);
        
        $stmt = $conn->prepare("UPDATE users SET email = ?, full_name = ?, phone = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $email, $full_name, $phone, $role, $user_id);
        
        if ($stmt->execute()) {
            log_activity($conn, $_SESSION['user_id'], 'edit_user', "Edited user ID: $user_id");
            $success = 'User berhasil diupdate!';
        } else {
            $error = 'Gagal mengupdate user!';
        }
        $stmt->close();
    } elseif (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        
        if ($user_id === $_SESSION['user_id']) {
            $error = 'Tidak dapat menghapus akun sendiri!';
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                log_activity($conn, $_SESSION['user_id'], 'delete_user', "Deleted user ID: $user_id");
                $success = 'User berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus user!';
            }
            $stmt->close();
        }
    }
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

include '../includes/header.php';
?>

<style>
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
    max-width: 500px;
    width: 100%;
    margin: auto;
}

/* Table Responsive Styles */
.table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 0 -1rem;
    padding: 0 1rem;
}

.table {
    min-width: 800px;
    width: 100%;
}

/* Desktop table styles */
.desktop-table {
    display: table;
    width: 100%;
}

.mobile-cards {
    display: none;
}

/* Mobile card styles */
.mobile-user-card {
    background: white;
    border: 1px solid var(--light);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.mobile-user-card .user-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--light);
}

.mobile-user-card .user-id {
    font-weight: bold;
    color: var(--primary);
    font-size: 0.9rem;
}

.mobile-user-card .user-role {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.mobile-user-card .user-details {
    display: grid;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.mobile-user-card .user-detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.mobile-user-card .user-detail-item i {
    width: 20px;
    color: var(--gray);
}

.mobile-user-card .user-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    padding-top: 0.75rem;
    border-top: 1px solid var(--light);
}

@media (max-width: 968px) {
    .table {
        font-size: 0.875rem;
        min-width: 700px;
    }
    
    .table th,
    .table td {
        padding: 0.5rem !important;
    }
    
    .btn {
        padding: 0.4rem 0.6rem !important;
        font-size: 0.8rem !important;
    }
}

@media (max-width: 768px) {
    .add-user-grid {
        grid-template-columns: 1fr !important;
    }
    
    .desktop-table {
        display: none;
    }
    
    .mobile-cards {
        display: block;
    }
    
    .table-container {
        overflow-x: visible;
        margin: 0;
        padding: 0;
    }
    
    .mobile-user-card .user-actions .btn {
        padding: 0.4rem 0.8rem !important;
        font-size: 0.9rem !important;
        min-width: auto;
    }
}

@media (max-width: 480px) {
    .mobile-user-card .user-actions {
        flex-direction: column;
    }
    
    .mobile-user-card .user-actions .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Fix badge alignment */
.badge {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-info {
    background: #dbeafe;
    color: #1e40af;
}

.role-danger {
    background: #fee2e2;
    color: #991b1b;
}

.role-warning {
    background: #fef3c7;
    color: #92400e;
}

.role-info {
    background: #dbeafe;
    color: #1e40af;
}
</style>

<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1><i class="fas fa-users"></i> Manajemen User</h1>
        <p style="color: var(--gray);">Kelola pengguna dan hak akses</p>
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
        
        <!-- Add User Form -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h3 class="card-title">Tambah User Baru</h3>
            </div>
            <form method="POST" action="">
                <div class="add-user-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Nama Lengkap *</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Telepon *</label>
                        <input type="tel" name="phone" id="phone" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select name="role" id="role" class="form-control" required>
                            <option value="user">User</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="display: flex; align-items: end;">
                        <button type="submit" name="add_user" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-plus"></i> Tambah User
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar User</h3>
            </div>
            
            <!-- Desktop Table -->
            <div class="table-container desktop-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Nama</th>
                            <th>Telepon</th>
                            <th>Role</th>
                            <th>Bergabung</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo $user['full_name']; ?></td>
                            <td><?php echo $user['phone']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'staff' ? 'warning' : 'info'); ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td style="white-space: nowrap;"><?php echo format_date($user['created_at']); ?></td>
                            <td style="white-space: nowrap;">
                                <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                        class="btn btn-secondary" style="padding: 0.5rem 1rem; margin-right: 0.5rem;">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <form method="POST" action="" style="display: inline;" 
                                      onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn btn-danger" style="padding: 0.5rem 1rem;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Cards -->
            <div class="mobile-cards" style="padding: 1rem;">
                <?php 
                // Reset pointer to iterate again
                $users->data_seek(0);
                while ($user = $users->fetch_assoc()): 
                ?>
                <div class="mobile-user-card">
                    <div class="user-header">
                        <div class="user-id">ID: <?php echo $user['id']; ?></div>
                        <div class="user-role role-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'staff' ? 'warning' : 'info'); ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </div>
                    </div>
                    
                    <div class="user-details">
                        <div class="user-detail-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo $user['email']; ?></span>
                        </div>
                        <div class="user-detail-item">
                            <i class="fas fa-user"></i>
                            <span><?php echo $user['full_name']; ?></span>
                        </div>
                        <div class="user-detail-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo $user['phone']; ?></span>
                        </div>
                        <div class="user-detail-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Bergabung: <?php echo format_date($user['created_at']); ?></span>
                        </div>
                    </div>
                    
                    <div class="user-actions">
                        <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                class="btn btn-secondary">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" name="delete_user" class="btn btn-danger" 
                                    onclick="return confirm('Yakin ingin menghapus user ini?')">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </form>
                        <?php endif; ?>
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
        <div class="card-header px-3">
            <h3 class="card-title" style="margin:1rem 1rem;">Edit User</h3>
        </div>
        <form method="POST" action="" style="padding: 1.5rem;">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="form-group">
                <label for="edit_email">Email</label>
                <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="edit_full_name">Nama Lengkap</label>
                <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="edit_phone">Telepon</label>
                <input type="tel" name="phone" id="edit_phone" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="edit_role">Role</label>
                <select name="role" id="edit_role" class="form-control" required>
                    <option value="user">User</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 1rem; padding-top: 1rem; border-top: 2px solid var(--light); margin-top: 1rem;">
                <button type="submit" name="edit_user" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <button type="button" onclick="closeModal()" class="btn btn-danger" style="flex: 1;">
                    <i class="fas fa-times"></i> Batal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_phone').value = user.phone;
    document.getElementById('edit_role').value = user.role;
    
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