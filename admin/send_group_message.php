<?php
session_start();
include "../lib/connection.php";

header('Content-Type: application/json');

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
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$message_text = isset($_POST['message']) ? trim($_POST['message']) : '';
$file = isset($_FILES['file']) ? $_FILES['file'] : null;

if ($group_id === 0 || (empty($message_text) && $file === null)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit();
}

// --- Security Check: Verify user is a member of the group ---
// (This is a simplified check; a more robust one would check both group_members and teacher_id)
$check_sql = "SELECT EXISTS(
    SELECT 1 FROM group_members gm JOIN students s ON gm.student_id = s.id WHERE gm.group_id = ? AND s.user_id = ?
    UNION
    SELECT 1 FROM message_groups g JOIN teachers t ON g.teacher_id = t.id WHERE g.id = ? AND t.user_id = ?
) as is_member";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("iiii", $group_id, $current_user_id, $group_id, $current_user_id);
$stmt->execute();
$is_member = $stmt->get_result()->fetch_assoc()['is_member'];
$stmt->close();

if (!$is_member) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Not a member of this group.']);
    exit();
}

// --- Handle File Upload ---
$file_path = null;
$original_file_name = null;
if ($file && $file['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/group_messages/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $original_file_name = basename($file['name']);
    $file_extension = pathinfo($original_file_name, PATHINFO_EXTENSION);
    $unique_filename = uniqid('groupfile_', true) . '.' . $file_extension;
    $file_path = $unique_filename;
    $destination = $upload_dir . $unique_filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error saving file.']);
        exit();
    }
}

// --- Database Operation ---
$insert_sql = "INSERT INTO messages (group_id, sender_id, message, file_path, original_file_name) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_sql);
$message_to_db = empty($message_text) ? null : $message_text;
$stmt->bind_param("iisss", $group_id, $current_user_id, $message_to_db, $file_path, $original_file_name);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save message.']);
}
$stmt->close();
?>
