<?php
// user/get_schedules.php
require_once '../config/config.php';
header('Content-Type: application/json');

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['route'])) {
    $route = clean_input($_POST['route']);

    // Get all active schedules for this route that are today or in the future
    $stmt = $conn->prepare("SELECT id, service_name, route, departure_date, departure_time, arrival_time, price, capacity 
                           FROM services 
                           WHERE route = ? AND status = 'active' AND departure_date >= CURDATE()
                           ORDER BY departure_date ASC, departure_time ASC");
    $stmt->bind_param("s", $route);
    $stmt->execute();
    $result = $stmt->get_result();

    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }

    echo json_encode(['schedules' => $schedules]);
    $stmt->close();
} else {
    echo json_encode(['schedules' => []]);
}

closeDBConnection($conn);
