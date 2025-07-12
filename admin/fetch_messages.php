<?php
session_start();
include "../lib/connection.php";

header('Content-Type: application/json');

// --- Security and Input Validation ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if ($receiver_id === 0) {
    echo json_encode([]);
    exit();
}

// --- Database Operation ---
$sql = "SELECT id, sender_id, message, file_path, original_file_name, sent_at
        FROM private_messages
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        AND id > ?
        ORDER BY sent_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $current_user_id, $receiver_id, $receiver_id, $current_user_id, $last_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

echo json_encode($messages);
?>
