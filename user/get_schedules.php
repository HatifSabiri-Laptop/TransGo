<?php
require_once '../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    $route = clean_input($_POST['route']);
    
    $stmt = $conn->prepare("
        SELECT id, service_name, route, departure_time, arrival_time, price, 
               departure_date, capacity, status
        FROM services 
        WHERE route = ? 
        AND status = 'active'
        AND (departure_date >= CURDATE() OR departure_date IS NULL)
        ORDER BY departure_time
    ");
    $stmt->bind_param("s", $route);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    
    echo json_encode(['schedules' => $schedules]);
    $stmt->close();
    $conn->close();
    exit();
}
?>