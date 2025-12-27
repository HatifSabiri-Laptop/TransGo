<?php
$page_title = 'Reservasi';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$error = '';
$success = '';

// Helper function to check if column exists
function column_exists($conn, $table, $column)
{
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result->num_rows > 0;
}

// Get available routes
$routes_query = $conn->query("
    SELECT DISTINCT route 
    FROM services 
    WHERE status = 'active' 
    ORDER BY route
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['final_confirm'])) {

    $service_id      = intval($_POST['service_id']);
    $num_passengers  = intval($_POST['num_passengers']);
    $contact_name    = clean_input($_POST['contact_name']);
    $contact_phone   = clean_input($_POST['contact_phone']);
    $contact_email   = clean_input($_POST['contact_email']);
    $selected_seats  = isset($_POST['selected_seats']) ? clean_input($_POST['selected_seats']) : '';

    // Collect passenger names
    $passenger_names = [];
    for ($i = 1; $i <= $num_passengers; $i++) {
        if (!empty($_POST["passenger_name_$i"])) {
            $passenger_names[] = clean_input($_POST["passenger_name_$i"]);
        }
    }

    /* ================= VALIDATION ================= */
    if ($service_id <= 0 || $num_passengers < 1) {
        $error = 'Data pemesanan tidak lengkap!';
    } elseif (count($passenger_names) !== $num_passengers) {
        $error = 'Semua nama penumpang harus diisi!';
    } elseif (empty($selected_seats)) {
        $error = 'Silakan pilih kursi terlebih dahulu!';
    } else {

        $seats_array = array_map('trim', explode(',', $selected_seats));

        if (count($seats_array) !== $num_passengers) {
            $error = 'Jumlah kursi harus sama dengan jumlah penumpang!';
        } else {

            // Get service detail
            $service_query = $conn->prepare("SELECT * FROM services WHERE id = ?");
            $service_query->bind_param("i", $service_id);
            $service_query->execute();
            $service = $service_query->get_result()->fetch_assoc();
            $service_query->close();

            if (!$service) {
                $error = 'Layanan tidak ditemukan!';
            } else {

                // Check if departure_date column exists, otherwise use current date
                $travel_date = isset($service['departure_date']) ? $service['departure_date'] : date('Y-m-d');
                $total_price  = $service['price'] * $num_passengers;
                $booking_code = generate_booking_code();
                $user_id      = $_SESSION['user_id'];

                $passenger_names_json = json_encode($passenger_names, JSON_UNESCAPED_UNICODE);

                try {
                    // 🔐 START TRANSACTION
                    $conn->begin_transaction();

                    // Check if reservation_seats table exists
                    $table_check = $conn->query("SHOW TABLES LIKE 'reservation_seats'");
                    if ($table_check->num_rows == 0) {
                        // Create table if doesn't exist
                        $create_table_sql = "
                            CREATE TABLE IF NOT EXISTS reservation_seats (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                reservation_id INT NOT NULL,
                                service_id INT NOT NULL,
                                travel_date DATE NOT NULL,
                                seat_number VARCHAR(10) NOT NULL,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                UNIQUE KEY unique_seat (service_id, travel_date, seat_number),
                                FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
                                FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
                                INDEX idx_reservation_id (reservation_id),
                                INDEX idx_service_date (service_id, travel_date)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                        ";
                        $conn->query($create_table_sql);
                    }

                    // ✅ INSERT RESERVATION
                    if (column_exists($conn, 'reservations', 'selected_seats')) {
                        // If selected_seats column exists
                        $stmt = $conn->prepare("
                            INSERT INTO reservations (
                                user_id,
                                service_id,
                                booking_code,
                                travel_date,
                                num_passengers,
                                total_price,
                                contact_name,
                                contact_phone,
                                contact_email,
                                passenger_names,
                                selected_seats,
                                payment_status,
                                booking_status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'confirmed')
                        ");

                        if (!$stmt) {
                            throw new Exception($conn->error);
                        }

                        $stmt->bind_param(
                            "iissiisssss",
                            $user_id,
                            $service_id,
                            $booking_code,
                            $travel_date,
                            $num_passengers,
                            $total_price,
                            $contact_name,
                            $contact_phone,
                            $contact_email,
                            $passenger_names_json,
                            $selected_seats
                        );
                    } else {
                        // If selected_seats column doesn't exist
                        $stmt = $conn->prepare("
                            INSERT INTO reservations (
                                user_id,
                                service_id,
                                booking_code,
                                travel_date,
                                num_passengers,
                                total_price,
                                contact_name,
                                contact_phone,
                                contact_email,
                                passenger_names,
                                payment_status,
                                booking_status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'confirmed')
                        ");

                        if (!$stmt) {
                            throw new Exception($conn->error);
                        }

                        $stmt->bind_param(
                            "iissiissss",
                            $user_id,
                            $service_id,
                            $booking_code,
                            $travel_date,
                            $num_passengers,
                            $total_price,
                            $contact_name,
                            $contact_phone,
                            $contact_email,
                            $passenger_names_json
                        );
                    }

                    if (!$stmt->execute()) {
                        throw new Exception($stmt->error);
                    }

                    $reservation_id = $stmt->insert_id;
                    $stmt->close();

                    // ✅ INSERT SEATS with CONFLICT CHECKING
                    foreach ($seats_array as $seat) {
                        // Check if seat is still available (prevent race condition)
                        $checkStmt = $conn->prepare("
                            SELECT COUNT(*) as seat_count 
                            FROM reservation_seats 
                            WHERE service_id = ? 
                            AND travel_date = ? 
                            AND seat_number = ?
                        ");
                        $checkStmt->bind_param("iss", $service_id, $travel_date, $seat);
                        $checkStmt->execute();
                        $checkResult = $checkStmt->get_result();
                        $seatData = $checkResult->fetch_assoc();
                        $checkStmt->close();

                        if ($seatData['seat_count'] > 0) {
                            // Seat was taken between selection and submission
                            throw new Exception("Kursi <strong>$seat</strong> sudah dipesan orang lain. Silakan kembali ke halaman sebelumnya dan pilih kursi lain.");
                        }

                        // Insert the seat
                        $seatStmt = $conn->prepare("
                            INSERT INTO reservation_seats 
                            (reservation_id, service_id, travel_date, seat_number)
                            VALUES (?, ?, ?, ?)
                        ");

                        if (!$seatStmt) {
                            throw new Exception($conn->error);
                        }

                        $seatStmt->bind_param(
                            "iiss",
                            $reservation_id,
                            $service_id,
                            $travel_date,
                            $seat
                        );

                        if (!$seatStmt->execute()) {
                            // Handle duplicate seat error gracefully
                            if (strpos($seatStmt->error, 'Duplicate entry') !== false) {
                                throw new Exception("Kursi <strong>$seat</strong> sudah dipesan. Silakan refresh halaman dan pilih kursi lain.");
                            } else {
                                throw new Exception("Gagal memesan kursi $seat: " . $seatStmt->error);
                            }
                        }
                        $seatStmt->close();
                    }

                    // ✅ LOG ACTIVITY
                    log_activity(
                        $conn,
                        $user_id,
                        'create_reservation',
                        "Created reservation: $booking_code with seats: $selected_seats"
                    );

                    // ✅ COMMIT
                    $conn->commit();

                    // ✅ REDIRECT
                    header('Location: ' . SITE_URL . '/user/payment.php?booking=' . $booking_code);
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Pemesanan gagal: ' . $e->getMessage();

                    // Log the error for debugging
                    error_log("Reservation Error: " . $e->getMessage() . " | Service: $service_id | Seats: $selected_seats");
                }
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
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .bus-layout {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        max-width: 400px;
        margin: 0 auto;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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

    .reservation-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }

    /* Mobile booking summary - DEFAULT HIDDEN */
    .mobile-summary {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        margin: 0 0 1.5rem 0;
        display: none;
    }

    .mobile-summary .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid #dee2e6;
    }

    .mobile-summary .summary-row:last-child {
        border-bottom: none;
        margin-top: 0.5rem;
        padding-top: 1rem;
        border-top: 2px solid #dee2e6;
    }

    .mobile-summary .summary-label {
        font-weight: 500;
        color: #6c757d;
    }

    .mobile-summary .summary-value {
        font-weight: 600;
        color: #212529;
    }

    .mobile-summary .total-value {
        font-size: 1.25rem;
        color: var(--primary);
        font-weight: 700;
    }

    /* Mobile seat title - DEFAULT HIDDEN */
    .mobile-seat-title {
        display: none;
        background: #a2ef8dff ;
    }

    /* ========== MOBILE RESPONSIVE STYLES ========== */
    @media (max-width: 768px) {

        /* Reset grid to single column */
        .reservation-grid {
            grid-template-columns: 1fr !important;
            gap: 0 !important;
            padding: 0 !important;
        }

        /* Hide desktop booking summary sidebar on mobile */
        .reservation-grid>div:last-child {
            display: none !important;
        }

        /* Container adjustments - remove side padding */
        .container {
            padding-left: 0 !important;
            padding-right: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        section[style*="padding: 2rem 0;"] .container {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        /* Card styling - full width, no borders on sides */
        .card {
            margin: 0 !important;
            border-radius: 0 !important;
            border-left: none !important;
            border-right: none !important;
            box-shadow: none !important;
        }

        /* Card body padding */
        .card-body {
            padding: 1.25rem 1rem !important;
        }

        /* Card header styling */
        .card-header {
            padding: 1rem !important;
            margin: 0 0 1rem 0 !important;
            border-radius: 0 !important;
        }

        /* Sticky header for step 2 and 3 */
        #step2 .card-header,
        #step3 .card-header {
            display: none !important;
            position: sticky;
            top: 60px;
            z-index: 50;
            margin-bottom: 0 !important;
        }

        /* Form groups spacing */
        .form-group {
            margin-bottom: 1.25rem !important;
        }

        /* STEP 2 - PASSENGER FIELDS FIX */
        #step2 .card-body {
            padding-top: 1.5rem !important;
            padding-bottom: 100px !important;
        }

        #passengerFields {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        #passengerFields>div {
            padding: 1.25rem 1rem !important;
            margin-bottom: 1rem !important;
            border-radius: 8px !important;
        }

        #passengerFields>div label {
            margin-bottom: 0 !important;
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
        }

        #passengerFields>div label span:first-child {
            flex-shrink: 0;
        }

        #passengerFields>div input {
            margin-top: 0.75rem !important;
            width: 100%;
        }

         /* STEP 3 - SEAT SELECTION */
        #step3 .card-body {
            padding-top: 0 !important;
            padding-bottom: 100px !important;
        }

        /* Show mobile summary in step 2 and 3 */
        #step2 .mobile-summary,
        #step3 .mobile-summary {
            display: block !important;
            margin: 1.5rem 1rem 1.5rem 1rem !important;
            padding: 1rem !important;
            background: #f8f9fa;
            border-radius: 8px;
        }

        /* Show mobile seat title in step 3 */
        .mobile-seat-title {
            display: block !important;
            margin: 1.5rem 1rem 1.5rem 1rem !important;
            padding: 1rem !important;
            background: #a2ef8dff ;
            border-radius: 8px;
        }

        .mobile-summary .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0.625rem 0;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.9rem;
        }

        .mobile-summary .summary-row:last-child {
            border-bottom: none;
            margin-top: 0.5rem;
            padding-top: 1rem;
            border-top: 2px solid #dee2e6;
            font-weight: bold;
        }

        .mobile-summary .summary-label {
            font-weight: 500;
            color: #6c757d;
            flex-shrink: 0;
            margin-right: 1rem;
        }

        .mobile-summary .summary-value {
            font-weight: 600;
            color: #212529;
            text-align: right;
            word-break: break-word;
            flex: 1;
        }

        .mobile-summary .total-value {
            font-size: 1.3rem;
            color: var(--primary);
            font-weight: 700;
        }

        /* Bus layout - COMPACT SIZE */
        .bus-container {
            padding: 0.75rem !important;
            margin: 0 1rem 1.5rem 1rem !important;
            border-radius: 12px !important;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        }

        .bus-layout {
            padding: 0.875rem !important;
            max-width: 100% !important;
            width: 100% !important;
            background: white;
            border-radius: 12px;
        }

        .driver-section {
            padding: 0.75rem !important;
            margin-bottom: 1.25rem !important;
            font-size: 0.875rem !important;
            background: linear-gradient(135deg, #1e293b, #334155);
            color: white;
            border-radius: 8px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .driver-section i {
            font-size: 1.25rem !important;
        }

        /* Seat grid - OPTIMAL SIZE */
        .seats-grid {
            display: grid !important;
            grid-template-columns: repeat(5, 1fr) !important;
            gap: 6px !important;
            max-width: 320px !important;
            margin: 0 auto !important;
        }

        .seat {
            width: 100% !important;
            aspect-ratio: 1 !important;
            height: auto !important;
            padding: 0 !important;
            position: relative !important;
            font-size: 0.75rem !important;
            border: 2px solid #cbd5e1 !important;
            border-radius: 8px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 600 !important;
            cursor: pointer;
            transition: all 0.3s;
            background: #3b82f6;
            color: white;
        }

        .seat.occupied {
            background: #ef4444 !important;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .seat.selected {
            background: #10b981 !important;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.5);
            border-color: #059669 !important;
        }

        .aisle {
            background: transparent !important;
            border: none !important;
        }

        
        .seat::before {
            content: none !important;
        }

        /* Seat legend - COMPACT */
        .seat-legend {
            display: flex !important;
            justify-content: center !important;
            flex-wrap: wrap !important;
            gap: 0.875rem !important;
            padding: 0.875rem !important;
            font-size: 0.8rem !important;
            margin-top: 1.25rem !important;
            background: #f8fafc;
            border-radius: 8px;
        }

        .legend-item {
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            font-size: 0.8rem !important;
        }

        .legend-box {
            width: 24px !important;
            height: 24px !important;
            border-radius: 6px;
            border: 2px solid #cbd5e1;
            flex-shrink: 0;
        }

        /* Selected seats info - COMPACT */
        .selected-seats-info {
            background: #dbeafe !important;
            padding: 0.875rem 1rem !important;
            border-radius: 8px !important;
            margin: 1.25rem 1rem 0 1rem !important;
            font-size: 0.875rem !important;
            border-left: 4px solid #2563eb;
        }

        #selectedSeatsDisplay {
            font-size: 1rem !important;
            margin-top: 0.5rem !important;
            font-weight: 600 !important;
            color: #2563eb;
        }

        /* Schedule cards */
        .schedule-card {
            padding: 1rem !important;
            margin: 0 0 0.75rem 0 !important;
            border-radius: 8px !important;
        }

        .schedule-info {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 0.75rem !important;
        }

        /* Step 1 continue button */
        #step1 button[type="button"] {
            margin-top: 1.5rem !important;
            padding: 1rem !important;
            font-size: 1rem !important;
            width: 100%;
        }

        /* Fixed bottom buttons for step 2 and 3 */
        .passenger-buttons {
            display: flex !important;
            gap: 0.75rem !important;
            margin: 2rem auto 0 auto !important;
            padding: 1rem !important;
            justify-content: center !important;
            max-width: 600px !important;
            position: relative !important;
            bottom: auto !important;
            left: auto !important;
            right: auto !important;
            background: transparent !important;
            box-shadow: none !important;
            z-index: auto !important;
            border-top: none;
        }

        .passenger-buttons button {
            flex: 0 1 auto !important;
            margin: 0 !important;
            padding: 0.875rem 2rem !important;
            font-size: 0.95rem !important;
            border-radius: 8px !important;
            font-weight: 600;
            min-width: 140px;
        }

        /* Fix title positioning - move it down so navbar doesn't cover it */
        section[style*="background: var(--light);"] {
            padding-top: 80px !important;
        }

        section[style*="background: var(--light);"] .container h1 {
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        /* Ensure booking summary doesn't stay sticky on small screens */
        .booking-summary {
            position: static !important;
            margin-bottom: 2rem !important;
        }
    }

    /* For very small phones (iPhone SE, etc) */
    @media (max-width: 480px) {
        .card-body {
            padding: 1rem 0.75rem !important;
        }

        #step2 .mobile-summary,
        #step3 .mobile-summary {
            margin: 1.25rem 0.75rem !important;
            padding: 0.875rem !important;
        }

        .mobile-summary .summary-row {
            font-size: 0.85rem;
            padding: 0.5rem 0;
        }

        .mobile-summary .total-value {
            font-size: 1.2rem;
        }

        .bus-container {
            padding: 0.625rem !important;
            margin: 0 0.75rem 1.5rem 0.75rem !important;
        }

        .bus-layout {
            padding: 0.75rem !important;
        }

        .seats-grid {
            max-width: 290px !important;
            gap: 5px !important;
        }

        .seat {
            font-size: 0.7rem !important;
            border-width: 1.5px !important;
        }

        .driver-section {
            font-size: 0.8rem !important;
            padding: 0.625rem !important;
        }

        .driver-section i {
            font-size: 1.1rem !important;
        }

        .seat-legend {
            gap: 0.625rem !important;
            padding: 0.75rem !important;
            font-size: 0.75rem !important;
        }

        .legend-item {
            font-size: 0.75rem !important;
        }

        .legend-box {
            width: 22px !important;
            height: 22px !important;
        }

        .selected-seats-info {
            padding: 0.75rem !important;
            margin: 1rem 0.75rem 0 0.75rem !important;
            font-size: 0.8rem !important;
        }

        #selectedSeatsDisplay {
            font-size: 0.95rem !important;
        }

        .schedule-info {
            grid-template-columns: 1fr !important;
        }

        .passenger-buttons {
            padding: 0.875rem !important;
        }

        .passenger-buttons button {
            padding: 0.75rem 0.875rem !important;
            font-size: 0.9rem !important;
        }

        #passengerFields>div {
            padding: 1rem 0.875rem !important;
        }

        .reservation-title-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.25rem;
        }

        .reservation-title-row .reservation-sub {
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        .booking-summary {
            position: static !important;
            margin-bottom: 2.5rem !important;
        }

        /* Extra bottom space so mobile footer doesn't overlap content */
        .container {
            padding-bottom: 4rem !important;
        }

        #step2 .card-body {
            padding-top: 2.5rem !important;
        }

        #passengerFields {
            margin-top: 0.75rem !important;
        }

        /* Footer text margin for mobile */
        footer .container p,
        footer .container a,
        footer .container h3,
        footer .container h4 {
            margin-left: 1rem !important;
        }

        footer .container ul li {
            margin-left: 1rem !important;
        }
    }

    /* Extra small devices (iPhone 5/SE, Galaxy Fold) */
    @media (max-width: 360px) {
        .seats-grid {
            max-width: 270px !important;
            gap: 4px !important;
        }

        .seat {
            font-size: 0.65rem !important;
            border-width: 1px !important;
        }

        .bus-container {
            padding: 0.5rem !important;
            margin: 0 0.5rem 1.25rem 0.5rem !important;
        }

        .mobile-summary .summary-row {
            font-size: 0.8rem;
        }

        .mobile-summary .total-value {
            font-size: 1.1rem;
        }

        .selected-seats-info {
            margin: 1rem 0.5rem 0 0.5rem !important;
        }

        #step2 .mobile-summary,
        #step3 .mobile-summary {
            margin: 1rem 0.5rem !important;
        }
    }

    /* ========== DESKTOP/LAPTOP BUTTON CENTERING ========== */
    @media (min-width: 769px) {

        /* Add padding to step headers */
        #step2 .card-header,
        #step3 .card-header {
            padding: 2rem 2rem !important;
        }

        #step2 .card-header h3,
        #step2 .card-header p,
        #step3 .card-header h3,
        #step3 .card-header p {
            padding-left: 0.5rem;
        }

        /* Center buttons in Step 2 (Data Penumpang) */
        .passenger-buttons {
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 1rem !important;
            padding: 2rem 1.5rem !important;
            margin: 0 !important;
        }

        .passenger-buttons button {
            flex: 0 0 auto !important;
            min-width: 180px !important;
            padding: 0.875rem 2rem !important;
        }

        /* Ensure form doesn't interfere */
        #step2 form,
        #step3 form {
            display: block !important;
        }
    }

    /* ========== MOBILE BEHAVIOR ========== */
    @media (max-width: 768px) {

        /* Keep mobile stacked buttons */
        .passenger-buttons {
            display: flex !important;
            flex-direction: column !important;
            gap: 1rem !important;
            padding: 1rem !important;
            margin: 2rem auto 0 auto !important;
        }

        .passenger-buttons button {
            width: 100% !important;
            padding: 0.875rem 1rem !important;
        }
    }
</style>

<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <div class="reservation-title-row" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <h1 style="margin:0;">Reservasi Tiket</h1>
            <p class="reservation-sub" style="color: var(--gray);margin:0;">Pesan tiket perjalanan Anda dengan mudah</p>
        </div>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <div class="reservation-grid">
            <!-- Booking Form -->
            <div>
                <div class="card">
                    <?php if ($error): ?>
                        <div class="alert alert-error" style="margin: 1rem;"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <!-- Step 1: Basic Information -->
                    <form method="POST" action="" id="reservationForm">
                        <input type="hidden" name="selected_seats" id="selected_seats_input" value="">
                        <input type="hidden" name="service_id" id="service_id_input" value="">

                        <div id="step1">
                            <div class="card-header step-header">
                                <h3 class="card-title"><i class="fas fa-ticket-alt"></i> Pilih Jadwal</h3>
                            </div>

                            <div class="card-body">
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

                                <div style="margin-top: 2rem;">
                                    <h4 style="margin-bottom: 1rem;"><i class="fas fa-user"></i> Informasi Kontak</h4>
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
                        </div>

                        <!-- Step 2: Passengers -->
                        <div id="step2" style="display: none;">
                            <div class="card-header step-header" style="background: var(--primary); color: white;">
                                <h3 style="margin: 0; color: white;"><i class="fas fa-users"></i> Data Penumpang</h3>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">Isi nama semua penumpang</p>
                            </div>

                            <div class="card-body">
                                <!-- Mobile Summary - Shows booking details -->
                                <div class="mobile-summary">
                                    <h4 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #212529;">
                                        <i class="fas fa-info-circle"></i> Detail Pemesanan
                                    </h4>
                                    <div class="summary-row">
                                        <span class="summary-label">Layanan:</span>
                                        <span class="summary-value" id="mobile_summary_service_step2">-</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Rute:</span>
                                        <span class="summary-value" id="mobile_summary_route_step2">-</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Tanggal:</span>
                                        <span class="summary-value" id="mobile_summary_date_step2">-</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Berangkat:</span>
                                        <span class="summary-value" id="mobile_summary_departure_step2">-</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Tiba:</span>
                                        <span class="summary-value" id="mobile_summary_arrival_step2">-</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Penumpang:</span>
                                        <span class="summary-value" id="mobile_summary_passengers_step2">1</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Total:</span>
                                        <span class="summary-value total-value" id="mobile_total_display_step2">Rp 0</span>
                                    </div>
                                </div>

                                <div id="passengerFields"></div>
                            </div>

                            <div class="passenger-buttons">
                                <button type="button" onclick="backToStep1()" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </button>
                                <button type="button" onclick="showSeatSelection()" class="btn btn-primary">
                                    Pilih Kursi <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Seats -->
                        <div id="step3" style="display: none;">
                            <div class="card-header step-header" style="background: var(--secondary); color: white;">
                                <h3 style="margin: 0; color: white;"><i class="fas fa-chair"></i> Pilih Kursi</h3>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">Pilih <span id="seats_needed">1</span> kursi</p>
                            </div>

                            <div class="card-body">
                                <!-- Mobile Summary - Passenger Details -->
                                <div class="mobile-summary" style="background: #e8f5e9; border-left: 4px solid #4caf50;">
                                    <h4 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #2e7d32;">
                                        <i class="fas fa-users"></i> Detail Penumpang
                                    </h4>
                                    <div class="summary-row">
                                        <span class="summary-label">Layanan:</span>
                                        <span class="summary-value" id="mobile_summary_service_step3">-</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Rute:</span>
                                        <span class="summary-value" id="mobile_summary_route_step3">-</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Tanggal:</span>
                                        <span class="summary-value" id="mobile_summary_date_step3">-</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Berangkat:</span>
                                        <span class="summary-value" id="mobile_summary_departure_step3">-</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Tiba:</span>
                                        <span class="summary-value" id="mobile_summary_arrival_step3">-</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Penumpang:</span>
                                        <span class="summary-value" id="mobile_summary_passengers_step3">1</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Total:</span>
                                        <span class="summary-value total-value" id="mobile_total_display_step3">Rp 0</span>
                                    </div>
                                </div>

                                <!-- Seat Selection Title (Mobile) -->
                                <div style="margin: 1.5rem 1rem 2rem 1rem; display: none;" class="mobile-seat-title">
                                    <h4 style="margin: 0 0 0.5rem 0; font-size: 1.1rem; font-weight: 600; color: #1976d2;">
                                        <i class="fas fa-chair"></i> Pilih Kursi
                                    </h4>
                                    <p style="margin: 0; font-size: 0.9rem; color: #666;">
                                        Silakan pilih <strong id="seats_needed_mobile">1</strong> kursi untuk penumpang Anda
                                    </p>
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

                                <div class="passenger-buttons">
                                    <button type="button" onclick="backToStep2()" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Kembali
                                    </button>
                                    <button type="submit" name="final_confirm" class="btn btn-primary" id="confirmBtn" disabled>
                                        <i class="fas fa-check-circle"></i> Konfirmasi
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Desktop Summary -->
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
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
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

        // Update desktop summary
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

        // Update mobile summary
        updateMobileSummary();
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        const options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
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

        // Update mobile summary
        updateMobileSummary();
    }

    function formatCurrency(amt) {
        return 'Rp ' + amt.toLocaleString('id-ID');
    }

    function updateMobileSummary() {
        // Step 2 - Data Penumpang
        const mobileServiceStep2 = document.getElementById('mobile_summary_service_step2');
        const mobileRouteStep2 = document.getElementById('mobile_summary_route_step2');
        const mobileDateStep2 = document.getElementById('mobile_summary_date_step2');
        const mobileDepartureStep2 = document.getElementById('mobile_summary_departure_step2');
        const mobileArrivalStep2 = document.getElementById('mobile_summary_arrival_step2');
        const mobilePassengersStep2 = document.getElementById('mobile_summary_passengers_step2');
        const mobileTotalStep2 = document.getElementById('mobile_total_display_step2');

        // Step 3 - Pilih Kursi
        const mobileServiceStep3 = document.getElementById('mobile_summary_service_step3');
        const mobileRouteStep3 = document.getElementById('mobile_summary_route_step3');
        const mobileDateStep3 = document.getElementById('mobile_summary_date_step3');
        const mobileDepartureStep3 = document.getElementById('mobile_summary_departure_step3');
        const mobileArrivalStep3 = document.getElementById('mobile_summary_arrival_step3');
        const mobilePassengersStep3 = document.getElementById('mobile_summary_passengers_step3');
        const mobileTotalStep3 = document.getElementById('mobile_total_display_step3');

        const num = parseInt(document.getElementById('num_passengers').value) || 1;
        const totalAmount = formatCurrency(servicePrice * num);
        const passengersText = num + ' orang';

        if (selectedService) {
            // Update Step 2
            if (mobileServiceStep2) {
                mobileServiceStep2.textContent = selectedService.name;
                mobileRouteStep2.textContent = selectedService.route;
                mobileDateStep2.textContent = formatDate(selectedService.date);
                mobileDepartureStep2.textContent = selectedService.departure;
                mobileArrivalStep2.textContent = selectedService.arrival;
                mobilePassengersStep2.textContent = passengersText;
                mobileTotalStep2.textContent = totalAmount;
            }

            // Update Step 3
            if (mobileServiceStep3) {
                mobileServiceStep3.textContent = selectedService.name;
                mobileRouteStep3.textContent = selectedService.route;
                mobileDateStep3.textContent = formatDate(selectedService.date);
                mobileDepartureStep3.textContent = selectedService.departure;
                mobileArrivalStep3.textContent = selectedService.arrival;
                mobilePassengersStep3.textContent = passengersText;
                mobileTotalStep3.textContent = totalAmount;
            }
        }

        const seatsNeededMobile = document.getElementById('seats_needed_mobile');
        if (seatsNeededMobile) {
            seatsNeededMobile.textContent = num;
        }
    }

    function showPassengerForm() {
        if (!document.getElementById('service_id_input').value ||
            !document.getElementById('contact_name').value ||
            !document.getElementById('contact_phone').value ||
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

        
        updateMobileSummary();

        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    function backToStep1() {
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step1').style.display = 'block';
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
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

        // Update mobile summary when entering step 3
        updateMobileSummary();

        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
        generateSeats();
        loadSeats();
    }

    function backToStep2() {
        document.getElementById('step3').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    function generateSeats() {
        const grid = document.getElementById('seatsGrid');
        grid.innerHTML = '';
        const rows = ['A', 'B', 'C', 'D', 'E'];

        rows.forEach(row => {
            for (let i = 1; i <= 4; i++) {
                const seat = row + i;
                const div = document.createElement('div');
                div.className = 'seat';
                div.id = 'seat_' + seat;
                div.textContent = seat;
                div.setAttribute('data-seat', seat);
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
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
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

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
        calculateTotal();
        updateMobileSummary();

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                updateMobileSummary();
            }
        });
    });
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
include '../includes/footer.php';
?>