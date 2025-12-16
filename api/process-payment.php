<?php

/**
 * Dummy Payment Gateway API
 * This is a sandbox/dummy payment integration
 * In production, integrate with real payment gateway like Midtrans, Xendit, etc.
 */

header('Content-Type: application/json');
require_once '../config/config.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Get POST data
$booking_code = isset($_POST['booking_code']) ? clean_input($_POST['booking_code']) : '';
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$payment_method = isset($_POST['payment_method']) ? clean_input($_POST['payment_method']) : 'credit_card';

// Validation
if (empty($booking_code) || $amount <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment data'
    ]);
    exit();
}

$conn = getDBConnection();

// Verify booking exists
$stmt = $conn->prepare("SELECT id, total_price, payment_status FROM reservations WHERE booking_code = ?");
$stmt->bind_param("s", $booking_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Booking not found'
    ]);
    $stmt->close();
    closeDBConnection($conn);
    exit();
}

$booking = $result->fetch_assoc();
$stmt->close();

// Check if already paid
if ($booking['payment_status'] === 'paid') {
    echo json_encode([
        'success' => false,
        'message' => 'Payment already completed'
    ]);
    closeDBConnection($conn);
    exit();
}

// Verify amount
if ($amount != $booking['total_price']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Payment amount mismatch'
    ]);
    closeDBConnection($conn);
    exit();
}

// Simulate payment processing delay
sleep(2);

// Simulate random success/failure (90% success rate for demo)
$success_rate = 90;
$is_successful = (rand(1, 100) <= $success_rate);

if ($is_successful) {
    // Update payment status
    $update_stmt = $conn->prepare("UPDATE reservations SET payment_status = 'paid' WHERE booking_code = ?");
    $update_stmt->bind_param("s", $booking_code);
    $update_stmt->execute();
    $update_stmt->close();

    // Generate dummy transaction ID
    $transaction_id = 'TXN' . date('YmdHis') . rand(1000, 9999);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment successful',
        'data' => [
            'transaction_id' => $transaction_id,
            'booking_code' => $booking_code,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    // Simulate payment failure
    $error_messages = [
        'Insufficient funds',
        'Card declined',
        'Transaction timeout',
        'Invalid card details'
    ];

    $error_message = $error_messages[array_rand($error_messages)];

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Payment failed: ' . $error_message,
        'data' => [
            'booking_code' => $booking_code,
            'amount' => $amount,
            'status' => 'failed'
        ]
    ]);
}

closeDBConnection($conn);
