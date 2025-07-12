<?php
session_start();
include "../lib/connection.php";

header('Content-Type: application/json');

// --- Security and Input Validation ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$message_text = isset($_POST['message']) ? trim($_POST['message']) : '';
$file = isset($_FILES['file']) ? $_FILES['file'] : null;

if ($receiver_id === 0 || (empty($message_text) && $file === null)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input: receiver or message content missing']);
    exit();
}

$file_path = null;
$original_file_name = null;

// --- Handle File Upload ---
if ($file && $file['error'] === UPLOAD_ERR_OK) {
    // CORRECTED PATH: Assumes student/ and uploads/ are in the same parent directory.
    $upload_dir = '../uploads/personal_massages/';

    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        // The `true` parameter allows for recursive creation.
        mkdir($upload_dir, 0777, true);
    }

    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if ($file['size'] > 5000000) { // 5MB limit
        echo json_encode(['status' => 'error', 'message' => 'File is too large.']);
        exit();
    }
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file type.']);
        exit();
    }

    $original_file_name = basename($file['name']);
    $file_extension = pathinfo($original_file_name, PATHINFO_EXTENSION);
    $unique_filename = uniqid('file_', true) . '.' . $file_extension;
    $file_path = $unique_filename;
    $destination = $upload_dir . $unique_filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        http_response_code(500);
        error_log("Failed to move uploaded file to: " . $destination);
        echo json_encode(['status' => 'error', 'message' => 'Server error while saving file. Check folder permissions.']);
        exit();
    }
}

// --- Database Operation ---
$insert_sql = "INSERT INTO private_messages (sender_id, receiver_id, message, file_path, original_file_name) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_sql);

$message_to_db = empty($message_text) ? null : $message_text;
$stmt->bind_param("iisss", $current_user_id, $receiver_id, $message_to_db, $file_path, $original_file_name);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message_id' => $stmt->insert_id]);
} else {
    http_response_code(500);
    error_log("Message sending failed: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save message to database.']);
}
$stmt->close();
?>
