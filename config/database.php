<?php
function getDBConnection() {
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "transportation_app";

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli($host, $user, $pass, $db);
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (mysqli_sql_exception $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
