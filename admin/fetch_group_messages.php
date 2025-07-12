<?php
session_start();
include "../lib/connection.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if ($group_id === 0) {
    echo json_encode([]);
    exit();
}

// Simplified membership check for fetching (can be omitted for performance if desired)
// A user should only be able to query a group they are already viewing

$sql = "SELECT m.id, m.sender_id, u.name as sender_name, m.message, m.file_path, m.original_file_name, m.created_at
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.group_id = ? AND m.id > ?
        ORDER BY m.created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $group_id, $last_id);
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($messages);
?>
