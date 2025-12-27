<?php
require_once '../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    $service_id = intval($_POST['service_id']);
    $travel_date = clean_input($_POST['travel_date']);
    
    $stmt = $conn->prepare("
        SELECT seat_number 
        FROM reservation_seats 
        WHERE service_id = ? 
        AND travel_date = ?
    ");
    $stmt->bind_param("is", $service_id, $travel_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $occupied = [];
    while ($row = $result->fetch_assoc()) {
        $occupied[] = $row['seat_number'];
    }
    
    echo json_encode(['occupied' => $occupied]);
    $stmt->close();
    $conn->close();
    exit();
}
?>