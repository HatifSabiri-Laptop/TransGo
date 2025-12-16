<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    $travel_date = isset($_POST['travel_date']) ? clean_input($_POST['travel_date']) : '';
    
    if ($service_id && $travel_date) {
        $conn = getDBConnection();
        
        // Get all occupied seats for this service and date
        $stmt = $conn->prepare("SELECT selected_seats FROM reservations 
                               WHERE service_id = ? 
                               AND travel_date = ? 
                               AND booking_status = 'confirmed' 
                               AND payment_status = 'paid'");
        $stmt->bind_param("is", $service_id, $travel_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $occupied = [];
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['selected_seats'])) {
                $seats = explode(',', $row['selected_seats']);
                $occupied = array_merge($occupied, $seats);
            }
        }
        
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'occupied' => array_unique($occupied)]);
    } else {
        echo json_encode(['success' => false, 'occupied' => []]);
    }
} else {
    echo json_encode(['success' => false, 'occupied' => []]);
}
?>