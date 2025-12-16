-- ============================================
-- TransGo Transportation System Database
-- COMPLETE FIXED SCHEMA - IMPORT THIS FILE
-- ============================================

-- Drop database if exists (fresh start)
DROP DATABASE IF EXISTS transportation_app;

-- Create database
CREATE DATABASE transportation_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE transportation_app;

-- ============================================
-- Table: users (MUST BE FIRST - referenced by others)
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('user', 'staff', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin123)
INSERT INTO users (email, password, full_name, phone, role) VALUES
('admin@transport.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', '081234567890', 'admin'),
('user@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User', '081234567891', 'user'),
('staff@transport.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff Member', '081234567892', 'staff');

-- ============================================
-- Table: services
-- ============================================
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(255) NOT NULL,
    route VARCHAR(255) NOT NULL,
    departure_time TIME NOT NULL,
    arrival_time TIME NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    capacity INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_route (route)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample services
INSERT INTO services (service_name, route, departure_time, arrival_time, price, capacity, status) VALUES
('Express Bus A', 'Jakarta - Bandung', '06:00:00', '09:00:00', 150000.00, 40, 'active'),
('Luxury Coach B', 'Jakarta - Surabaya', '08:00:00', '16:00:00', 350000.00, 30, 'active'),
('Economy Bus C', 'Jakarta - Yogyakarta', '20:00:00', '06:00:00', 200000.00, 45, 'active'),
('Executive D', 'Jakarta - Semarang', '07:30:00', '13:30:00', 180000.00, 35, 'active'),
('Premium E', 'Bandung - Jakarta', '15:00:00', '18:00:00', 150000.00, 40, 'active'),
('Night Express F', 'Surabaya - Jakarta', '21:00:00', '05:00:00', 350000.00, 30, 'active');

-- ============================================
-- Table: reservations
-- ============================================
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    booking_code VARCHAR(20) UNIQUE NOT NULL,
    travel_date DATE NOT NULL,
    num_passengers INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    contact_name VARCHAR(255) NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    passenger_names TEXT DEFAULT NULL,
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    booking_status ENUM('confirmed', 'cancelled', 'completed') DEFAULT 'confirmed',
    checked_in BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
    INDEX idx_user_id (user_id),
    INDEX idx_service_id (service_id),
    INDEX idx_booking_code (booking_code),
    INDEX idx_travel_date (travel_date),
    INDEX idx_booking_status (booking_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: cancellation_requests
-- ============================================
CREATE TABLE cancellation_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    user_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    processed_by INT DEFAULT NULL,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reservation_id (reservation_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: blog_articles
-- ============================================
CREATE TABLE blog_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content TEXT NOT NULL,
    excerpt TEXT DEFAULT NULL,
    featured_image VARCHAR(255) DEFAULT NULL,
    author_id INT NOT NULL,
    status ENUM('draft', 'published') DEFAULT 'draft',
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_author_id (author_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample blog articles
INSERT INTO blog_articles (title, slug, content, excerpt, author_id, status, published_at) VALUES
('Tips Perjalanan Nyaman dengan Bus', 'tips-perjalanan-nyaman-bus', 
'Perjalanan dengan bus bisa menjadi pengalaman yang menyenangkan jika Anda tahu cara mempersiapkannya dengan baik. Berikut adalah beberapa tips untuk membuat perjalanan bus Anda lebih nyaman:\n\n1. Pilih Kursi yang Tepat\nJika memungkinkan, pilih kursi di bagian tengah bus untuk mengurangi goncangan. Hindari kursi paling belakang karena biasanya lebih bergoyang.\n\n2. Bawa Bantal Leher\nBantal leher akan sangat membantu terutama untuk perjalanan jarak jauh. Ini akan membantu Anda tidur dengan lebih nyaman.\n\n3. Siapkan Hiburan\nBawa buku, musik, atau download film favorit Anda untuk mengisi waktu selama perjalanan.\n\n4. Kenakan Pakaian yang Nyaman\nPilih pakaian yang longgar dan nyaman. Jangan lupa membawa jaket karena AC bus biasanya cukup dingin.\n\n5. Bawa Makanan Ringan\nSiapkan camilan dan air minum untuk perjalanan. Hindari makanan yang terlalu berat atau berbau menyengat.\n\n6. Datang Lebih Awal\nTiba di terminal minimal 30 menit sebelum keberangkatan untuk menghindari ketinggalan bus.\n\n7. Amankan Barang Berharga\nSimpan barang berharga di tas yang Anda bawa ke dalam bus, jangan taruh di bagasi.\n\nDengan mengikuti tips di atas, perjalanan bus Anda akan lebih menyenangkan dan bebas stres!', 
'Panduan lengkap untuk membuat perjalanan bus Anda lebih nyaman dan menyenangkan', 1, 'published', NOW()),

('Keuntungan Booking Online untuk Tiket Bus', 'keuntungan-booking-online', 
'Di era digital ini, booking tiket bus secara online telah menjadi pilihan utama banyak orang. Berikut adalah keuntungan-keuntungan yang bisa Anda dapatkan:\n\n1. Hemat Waktu\nTidak perlu mengantri di loket atau terminal. Cukup pesan dari smartphone Anda di mana saja dan kapan saja.\n\n2. Bandingkan Harga dengan Mudah\nAnda bisa membandingkan harga dari berbagai operator dan jadwal keberangkatan dengan cepat.\n\n3. Pilih Kursi Favorit\nSistem booking online biasanya memungkinkan Anda memilih nomor kursi sesuai preferensi.\n\n4. Pembayaran Fleksibel\nTersedia berbagai metode pembayaran: transfer bank, e-wallet, atau kartu kredit.\n\n5. E-Ticket Praktis\nTiket elektronik bisa disimpan di ponsel, tidak perlu khawatir kehilangan tiket fisik.\n\n6. Promo dan Diskon\nSeringkali ada promo khusus untuk pemesanan online yang tidak tersedia di pembelian langsung.\n\n7. Riwayat Pemesanan Tersimpan\nSemua riwayat booking Anda tercatat dan bisa diakses kapan saja.\n\n8. Konfirmasi Instan\nSetelah pembayaran, Anda langsung mendapat konfirmasi dan kode booking.\n\n9. Customer Service 24/7\nBantuan tersedia kapan saja jika ada kendala dengan booking Anda.\n\n10. Ramah Lingkungan\nMengurangi penggunaan kertas dengan e-ticket.\n\nJadi tunggu apa lagi? Mulai booking online sekarang dan rasakan kemudahannya!', 
'Mengapa Anda harus booking tiket bus secara online? Simak manfaatnya di sini', 1, 'published', NOW()),

('Panduan Check-in Online yang Mudah dan Cepat', 'panduan-checkin-mudah', 
'Check-in online adalah fitur yang sangat memudahkan penumpang. Berikut panduan lengkap menggunakan fitur check-in online:\n\n1. Persiapan Check-in\n- Pastikan Anda memiliki kode booking\n- Siapkan email yang digunakan saat pemesanan\n- Check-in bisa dilakukan 24 jam sebelum keberangkatan\n\n2. Langkah-langkah Check-in:\na) Kunjungi halaman check-in di website\nb) Masukkan kode booking dan email\nc) Sistem akan menampilkan detail perjalanan Anda\nd) Verifikasi informasi penumpang\ne) Klik tombol konfirmasi check-in\nf) Simpan e-ticket yang muncul\n\n3. Keuntungan Check-in Online:\n- Tidak perlu mengantri di loket\n- Proses lebih cepat saat di terminal\n- Konfirmasi langsung via email\n- Kursi terjamin\n\n4. Tips Penting:\n- Screenshot atau print e-ticket Anda\n- Datang tetap 30 menit sebelum keberangkatan\n- Bawa identitas yang sama dengan yang didaftarkan\n- Jika ada perubahan, hubungi customer service\n\n5. Troubleshooting:\n- Jika lupa kode booking, cek email konfirmasi pemesanan\n- Kode tidak valid? Pastikan tidak ada typo\n- Gagal check-in? Hubungi customer service\n\n6. Di Terminal:\n- Tunjukkan e-ticket ke petugas\n- Petugas akan memverifikasi identitas\n- Anda akan diarahkan ke bus\n\nDengan check-in online, perjalanan Anda akan jauh lebih efisien!', 
'Cara melakukan check-in online dengan cepat dan mudah untuk perjalanan yang lancar', 1, 'published', NOW());

-- ============================================
-- Table: activity_logs
-- ============================================
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial activity log
INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES
(1, 'system_setup', 'Database initialized with sample data', '127.0.0.1');

-- ============================================
-- Verify Installation
-- ============================================
SELECT 'Database created successfully!' as Status;
SELECT CONCAT('Total users: ', COUNT(*)) as UserCount FROM users;
SELECT CONCAT('Total services: ', COUNT(*)) as ServiceCount FROM services;
SELECT CONCAT('Total blog articles: ', COUNT(*)) as ArticleCount FROM blog_articles;

-- ============================================
-- Display Default Credentials
-- ============================================
SELECT '=== DEFAULT LOGIN CREDENTIALS ===' as Info;
SELECT 'Admin Account' as Type, 'admin@transport.com' as Email, 'admin123' as Password
UNION ALL
SELECT 'Test User', 'user@test.com', 'admin123'
UNION ALL
SELECT 'Staff Account', 'staff@transport.com', 'admin123';

-- ============================================
-- Success Message
-- ============================================
SELECT '✓ Database setup complete!' as Message, 
       '✓ All tables created successfully' as Status1,
       '✓ Sample data inserted' as Status2,
       '✓ Foreign keys configured' as Status3,
       '✓ Ready to use!' as Status4;