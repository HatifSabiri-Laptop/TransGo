<?php
$page_title = 'Reservasi';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$error = '';
$success = '';

// Get available routes (unique combinations)
$routes_query = $conn->query("SELECT DISTINCT route FROM services WHERE status = 'active' ORDER BY route");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is final confirmation with passenger details and seats
    if (isset($_POST['final_confirm'])) {
        $service_id = intval($_POST['service_id']);
        $num_passengers = intval($_POST['num_passengers']);
        $contact_name = clean_input($_POST['contact_name']);
        $contact_phone = clean_input($_POST['contact_phone']);
        $contact_email = clean_input($_POST['contact_email']);
        $selected_seats = isset($_POST['selected_seats']) ? clean_input($_POST['selected_seats']) : '';
        
        // Collect passenger names
        $passenger_names = [];
        for ($i = 1; $i <= $num_passengers; $i++) {
            if (isset($_POST["passenger_name_$i"]) && !empty($_POST["passenger_name_$i"])) {
                $passenger_names[] = clean_input($_POST["passenger_name_$i"]);
            }
        }
        
        // Validation
        if (empty($service_id) || $num_passengers < 1) {
            $error = 'Data pemesanan tidak lengkap!';
        } elseif (count($passenger_names) != $num_passengers) {
            $error = 'Semua nama penumpang harus diisi!';
        } elseif (empty($selected_seats)) {
            $error = 'Silakan pilih kursi terlebih dahulu!';
        } else {
            // Verify seats count matches passengers
            $seats_array = explode(',', $selected_seats);
            if (count($seats_array) != $num_passengers) {
                $error = 'Jumlah kursi harus sama dengan jumlah penumpang!';
            } else {
                // Get service details
                $service_query = $conn->prepare("SELECT * FROM services WHERE id = ?");
                $service_query->bind_param("i", $service_id);
                $service_query->execute();
                $service = $service_query->get_result()->fetch_assoc();
                
                if (!$service) {
                    $error = 'Layanan tidak ditemukan!';
                } else {
                    $total_price = $service['price'] * $num_passengers;
                    $booking_code = generate_booking_code();
                    $user_id = $_SESSION['user_id'];
                    $travel_date = $service['departure_date'];
                    
                    // Store passenger names as JSON
                    $passenger_names_json = json_encode($passenger_names, JSON_UNESCAPED_UNICODE);
                    
                    // Insert reservation with seats
                    try {
                        $stmt = $conn->prepare("INSERT INTO reservations (user_id, service_id, booking_code, travel_date, num_passengers, total_price, contact_name, contact_phone, contact_email, passenger_names, selected_seats, payment_status, booking_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'confirmed')");
                        
                        if (!$stmt) {
                            $error = 'Prepare statement failed: ' . $conn->error;
                        } else {
                            $stmt->bind_param("iissiisssss", $user_id, $service_id, $booking_code, $travel_date, $num_passengers, $total_price, $contact_name, $contact_phone, $contact_email, $passenger_names_json, $selected_seats);
                            
                            if ($stmt->execute()) {
                                $reservation_id = $stmt->insert_id;
                                log_activity($conn, $user_id, 'create_reservation', "Created reservation: $booking_code with seats: $selected_seats");
                                
                                // Redirect to payment page
                                header('Location: ' . SITE_URL . '/user/payment.php?booking=' . $booking_code);
                                exit();
                            } else {
                                $error = 'Gagal menyimpan reservasi: ' . $stmt->error;
                            }
                            $stmt->close();
                        }
                    } catch (Exception $e) {
                        $error = 'Error: ' . $e->getMessage();
                    }
                }
                $service_query->close();
            }
        }
    }
}

include '../includes/header.php';
?>

<style>
/* Seat Selection Styles */
.bus-container {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-radius: 16px;
    padding: 2rem;
    margin: 2rem 0;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.bus-layout {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    max-width: 400px;
    margin: 0 auto;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.driver-section {
    background: linear-gradient(135deg, #1e293b, #334155);
    color: white;
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.seats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 0.3fr 1fr 1fr;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.seat {
    aspect-ratio: 1;
    border: 2px solid #cbd5e1;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s;
    background: #3b82f6;
    color: white;
}

.seat:hover:not(.occupied):not(.selected) {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.seat.occupied {
    background: #ef4444;
    cursor: not-allowed;
    opacity: 0.7;
}

.seat.selected {
    background: #10b981;
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.5);
    border-color: #059669;
}

.aisle {
    background: transparent;
    border: none;
}

.seat-legend {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-top: 1.5rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.legend-box {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    border: 2px solid #cbd5e1;
}

.selected-seats-info {
    background: #dbeafe;
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
    border-left: 4px solid #2563eb;
}

.schedule-card {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
}

.schedule-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    transform: translateY(-2px);
}

.schedule-card.selected {
    border-color: #10b981;
    background: #d1fae5;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.schedule-card input[type="radio"] {
    display: none;
}

.schedule-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 0.8rem;
    color: #6b7280;
}

.info-value {
    font-weight: 600;
    color: #1f2937;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .reservation-grid { grid-template-columns: 1fr !important; }
    .booking-summary { position: static !important; margin-top: 2rem; }
    .passenger-buttons { flex-direction: column !important; }
    .passenger-buttons button { width: 100% !important; }
    .bus-layout { padding: 1rem; }
    .seats-grid { gap: 0.3rem; }
    .seat { font-size: 0.75rem; }
}
</style>

<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1>Reservasi Tiket</h1>
        <p style="color: var(--gray);">Pesan tiket perjalanan Anda dengan mudah</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <div class="reservation-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Booking Form -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Form Pemesanan</h3>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <!-- Step 1: Basic Information -->
                    <form method="POST" action="" id="reservationForm">
                        <input type="hidden" name="selected_seats" id="selected_seats_input" value="">
                        <input type="hidden" name="service_id" id="service_id_input" value="">
                        
                        <div id="step1">
                            <div class="form-group">
                                <label for="route_select">Pilih Rute *</label>
                                <select name="route_select" id="route_select" class="form-control" required onchange="loadSchedules()">
                                    <option value="">-- Pilih Rute --</option>
                                    <?php while ($route = $routes_query->fetch_assoc()): ?>
                                        <option value="<?php echo $route['route']; ?>">
                                            <?php echo $route['route']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" id="schedules_container" style="display: none;">
                                <label>Pilih Jadwal Keberangkatan *</label>
                                <div id="schedules_list"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="num_passengers">Jumlah Penumpang *</label>
                                <input type="number" name="num_passengers" id="num_passengers" class="form-control" 
                                       min="1" max="10" value="1" required onchange="calculateTotal()">
                                <small style="color: var(--gray);">Maksimal 10 penumpang</small>
                            </div>
                            
                            <div class="card-header" style="margin-top: 1.5rem;">
                                <h4>Informasi Kontak</h4>
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_name">Nama Lengkap *</label>
                                <input type="text" name="contact_name" id="contact_name" class="form-control" 
                                       value="<?php echo $_SESSION['full_name']; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_phone">Nomor Telepon *</label>
                                <input type="tel" name="contact_phone" id="contact_phone" class="form-control" 
                                       placeholder="08123456789" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_email">Email *</label>
                                <input type="email" name="contact_email" id="contact_email" class="form-control" 
                                       value="<?php echo $_SESSION['email']; ?>" required>
                            </div>
                            
                            <button type="button" onclick="showPassengerForm()" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-arrow-right"></i> Lanjut ke Data Penumpang
                            </button>
                        </div>
                        
                        <!-- Step 2: Passengers -->
                        <div id="step2" style="display: none;">
                            <div class="card-header" style="background: var(--primary); color: white; margin: -2rem -2rem 2rem -2rem; padding: 1.5rem 2rem;">
                                <h4 style="margin: 0; color: white;"><i class="fas fa-users"></i> Data Penumpang</h4>
                            </div>
                            
                            <div id="passengerFields"></div>
                            
                            <div class="passenger-buttons" style="display: flex; gap: 1rem; margin-top: 2rem;">
                                <button type="button" onclick="backToStep1()" class="btn btn-secondary" style="flex: 1;">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </button>
                                <button type="button" onclick="showSeatSelection()" class="btn btn-primary" style="flex: 2;">
                                    <i class="fas fa-arrow-right"></i> Pilih Kursi
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 3: Seats -->
                        <div id="step3" style="display: none;">
                            <div class="card-header" style="background: var(--secondary); color: white; margin: -2rem -2rem 2rem -2rem; padding: 1.5rem 2rem;">
                                <h4 style="margin: 0; color: white;"><i class="fas fa-chair"></i> Pilih Kursi</h4>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem;">Pilih <span id="seats_needed">1</span> kursi</p>
                            </div>
                            
                            <div class="bus-container">
                                <div class="bus-layout">
                                    <div class="driver-section">
                                        <i class="fas fa-steering-wheel" style="font-size: 1.5rem;"></i>
                                        <span>SUPIR</span>
                                    </div>
                                    
                                    <div class="seats-grid" id="seatsGrid"></div>
                                    
                                    <div class="seat-legend">
                                        <div class="legend-item">
                                            <div class="legend-box" style="background: #3b82f6;"></div>
                                            <span>Tersedia</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-box" style="background: #10b981;"></div>
                                            <span>Dipilih</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-box" style="background: #ef4444;"></div>
                                            <span>Terisi</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="selected-seats-info">
                                    <strong><i class="fas fa-chair"></i> Kursi Terpilih:</strong>
                                    <div id="selectedSeatsDisplay" style="margin-top: 0.5rem; font-size: 1.1rem; color: #2563eb;">
                                        Belum ada kursi dipilih
                                    </div>
                                </div>
                            </div>
                            
                            <div class="passenger-buttons" style="display: flex; gap: 1rem; margin-top: 2rem;">
                                <button type="button" onclick="backToStep2()" class="btn btn-secondary" style="flex: 1;">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </button>
                                <button type="submit" name="final_confirm" class="btn btn-primary" style="flex: 2;" id="confirmBtn" disabled>
                                    <i class="fas fa-check-circle"></i> Konfirmasi
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Summary -->
            <div>
                <div class="card booking-summary" style="position: sticky; top: 100px;">
                    <div class="card-header">
                        <h3 class="card-title">Detail Pemesanan</h3>
                    </div>
                    
                    <div id="bookingSummary" style="display: none;">
                        <div style="margin-bottom: 1rem;">
                            <strong>Layanan:</strong>
                            <p id="summary_service" style="color: var(--gray); margin-top: 0.25rem;">-</p>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Rute:</strong>
                            <p id="summary_route" style="color: var(--gray); margin-top: 0.25rem;">-</p>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Tanggal Keberangkatan:</strong>
                            <p id="summary_date" style="color: var(--gray); margin-top: 0.25rem;">-</p>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <strong>Berangkat:</strong>
                                <p id="summary_departure" style="color: var(--gray); margin-top: 0.25rem;">-</p>
                            </div>
                            <div>
                                <strong>Tiba:</strong>
                                <p id="summary_arrival" style="color: var(--gray); margin-top: 0.25rem;">-</p>
                            </div>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Harga per Penumpang:</strong>
                            <p id="summary_price" style="color: var(--gray); margin-top: 0.25rem;">-</p>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Jumlah Penumpang:</strong>
                            <p id="summary_passengers" style="color: var(--gray); margin-top: 0.25rem;">1</p>
                        </div>
                        <div style="border-top: 2px solid var(--light); padding-top: 1rem; margin-top: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="font-size: 1.25rem;">Total:</strong>
                                <strong id="total_display" style="font-size: 1.5rem; color: var(--primary);">Rp 0</strong>
                            </div>
                        </div>
                    </div>
                    
                    <div id="noServiceSelected">
                        <p style="text-align: center; color: var(--gray); padding: 2rem;">
                            <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                            Pilih rute dan jadwal
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
let servicePrice = 0;
let selectedService = null;
let selectedSeats = [];
let occupiedSeats = [];
let requiredSeats = 1;

function loadSchedules() {
    const route = document.getElementById('route_select').value;
    if (!route) {
        document.getElementById('schedules_container').style.display = 'none';
        return;
    }
    
    fetch('<?php echo SITE_URL; ?>/user/get_schedules.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `route=${encodeURIComponent(route)}`
    })
    .then(r => r.json())
    .then(data => {
        const container = document.getElementById('schedules_list');
        container.innerHTML = '';
        
        if (data.schedules && data.schedules.length > 0) {
            data.schedules.forEach(schedule => {
                const card = document.createElement('label');
                card.className = 'schedule-card';
                card.innerHTML = `
                    <input type="radio" name="schedule" value="${schedule.id}" 
                           data-price="${schedule.price}"
                           data-name="${schedule.service_name}"
                           data-route="${schedule.route}"
                           data-date="${schedule.departure_date}"
                           data-departure="${schedule.departure_time}"
                           data-arrival="${schedule.arrival_time}"
                           onchange="selectSchedule(this)">
                    <div style="font-weight: bold; margin-bottom: 0.5rem;">${schedule.service_name}</div>
                    <div class="schedule-info">
                        <div class="info-item">
                            <span class="info-label">Tanggal</span>
                            <span class="info-value">${formatDate(schedule.departure_date)}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Berangkat</span>
                            <span class="info-value">${schedule.departure_time}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tiba</span>
                            <span class="info-value">${schedule.arrival_time}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Harga</span>
                            <span class="info-value">${formatCurrency(schedule.price)}</span>
                        </div>
                    </div>
                `;
                container.appendChild(card);
                
                // Add click event to label
                card.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                });
            });
            
            document.getElementById('schedules_container').style.display = 'block';
        } else {
            container.innerHTML = '<p style="text-align: center; color: var(--gray); padding: 1rem;">Tidak ada jadwal tersedia untuk rute ini.</p>';
            document.getElementById('schedules_container').style.display = 'block';
        }
    })
    .catch(e => {
        console.error('Error:', e);
        alert('Gagal memuat jadwal');
    });
}

function selectSchedule(radio) {
    // Remove selected class from all cards
    document.querySelectorAll('.schedule-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selected class to parent label
    radio.parentElement.classList.add('selected');
    
    servicePrice = parseFloat(radio.dataset.price);
    const serviceId = radio.value;
    
    selectedService = {
        id: serviceId,
        name: radio.dataset.name,
        route: radio.dataset.route,
        date: radio.dataset.date,
        departure: radio.dataset.departure,
        arrival: radio.dataset.arrival
    };
    
    document.getElementById('service_id_input').value = serviceId;
    document.getElementById('summary_service').textContent = radio.dataset.name;
    document.getElementById('summary_route').textContent = radio.dataset.route;
    document.getElementById('summary_date').textContent = formatDate(radio.dataset.date);
    document.getElementById('summary_departure').textContent = radio.dataset.departure;
    document.getElementById('summary_arrival').textContent = radio.dataset.arrival;
    document.getElementById('summary_price').textContent = formatCurrency(servicePrice);
    
    document.getElementById('bookingSummary').style.display = 'block';
    document.getElementById('noServiceSelected').style.display = 'none';
    
    calculateTotal();
    loadSeats();
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('id-ID', options);
}

function calculateTotal() {
    const num = parseInt(document.getElementById('num_passengers').value) || 1;
    requiredSeats = num;
    document.getElementById('summary_passengers').textContent = num + ' orang';
    document.getElementById('total_display').textContent = formatCurrency(servicePrice * num);
    if (document.getElementById('seats_needed')) {
        document.getElementById('seats_needed').textContent = num;
    }
}

function formatCurrency(amt) {
    return 'Rp ' + amt.toLocaleString('id-ID');
}

function showPassengerForm() {
    if (!document.getElementById('service_id_input').value ||
        !document.getElementById('contact_name').value || !document.getElementById('contact_phone').value ||
        !document.getElementById('contact_email').value) {
        alert('Mohon lengkapi data pemesanan dan pilih jadwal!');
        return;
    }
    
    const num = parseInt(document.getElementById('num_passengers').value);
    const fields = document.getElementById('passengerFields');
    fields.innerHTML = '';
    
    for (let i = 1; i <= num; i++) {
        fields.innerHTML += `
            <div class="form-group" style="background: ${i%2?'white':'var(--light)'}; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
                <label for="passenger_name_${i}" style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="background: var(--primary); color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">${i}</span>
                    <span>Penumpang ${i} *</span>
                </label>
                <input type="text" name="passenger_name_${i}" id="passenger_name_${i}" class="form-control" placeholder="Nama lengkap" required style="margin-top: 0.5rem;">
            </div>`;
    }
    
    setTimeout(() => {
        const first = document.getElementById('passenger_name_1');
        if (first) first.value = document.getElementById('contact_name').value;
    }, 100);
    
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function backToStep1() {
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step1').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showSeatSelection() {
    const num = parseInt(document.getElementById('num_passengers').value);
    for (let i = 1; i <= num; i++) {
        const field = document.getElementById(`passenger_name_${i}`);
        if (!field || !field.value.trim()) {
            alert('Isi semua nama penumpang!');
            return;
        }
    }
    
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step3').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    generateSeats();
    loadSeats();
}

function backToStep2() {
    document.getElementById('step3').style.display = 'none';
    document.getElementById('step2').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function generateSeats() {
    const grid = document.getElementById('seatsGrid');
    grid.innerHTML = '';
    const rows = ['A','B','C','D','E'];
    
    rows.forEach(row => {
        for (let i = 1; i <= 4; i++) {
            const seat = row + i;
            const div = document.createElement('div');
            div.className = 'seat';
            div.id = 'seat_' + seat;
            div.textContent = seat;
            div.onclick = () => toggleSeat(seat);
            grid.appendChild(div);
            
            if (i === 2) {
                const aisle = document.createElement('div');
                aisle.className = 'aisle';
                grid.appendChild(aisle);
            }
        }
    });
}

function loadSeats() {
    const sid = document.getElementById('service_id_input').value;
    if (!sid || !selectedService) return;
    
    fetch('<?php echo SITE_URL; ?>/user/get_occupied_seats.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `service_id=${sid}&travel_date=${selectedService.date}`
    })
    .then(r => r.json())
    .then(data => {
        occupiedSeats = data.occupied || [];
        updateSeatsDisplay();
    })
    .catch(e => console.error('Error:', e));
}

function updateSeatsDisplay() {
    const allSeats = document.querySelectorAll('.seat');
    allSeats.forEach(seat => {
        const seatNum = seat.textContent;
        seat.classList.remove('occupied', 'selected');
        
        if (occupiedSeats.includes(seatNum)) {
            seat.classList.add('occupied');
        } else if (selectedSeats.includes(seatNum)) {
            seat.classList.add('selected');
        }
    });
    
    updateSelectedDisplay();
}

function toggleSeat(seatNum) {
    if (occupiedSeats.includes(seatNum)) {
        alert('Kursi sudah terisi!');
        return;
    }
    
    const idx = selectedSeats.indexOf(seatNum);
    if (idx > -1) {
        selectedSeats.splice(idx, 1);
    } else {
        if (selectedSeats.length >= requiredSeats) {
            alert(`Maksimal ${requiredSeats} kursi!`);
            return;
        }
        selectedSeats.push(seatNum);
    }
    
    updateSeatsDisplay();
}

function updateSelectedDisplay() {
    const display = document.getElementById('selectedSeatsDisplay');
    const input = document.getElementById('selected_seats_input');
    const btn = document.getElementById('confirmBtn');
    
    if (selectedSeats.length === 0) {
        display.textContent = 'Belum ada kursi dipilih';
        input.value = '';
        btn.disabled = true;
    } else {
        display.textContent = selectedSeats.sort().join(', ');
        input.value = selectedSeats.sort().join(',');
        btn.disabled = selectedSeats.length !== requiredSeats;
    }
}
</script>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>