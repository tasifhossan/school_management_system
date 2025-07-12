<?php
session_start();
include "../lib/connection.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];
$action = $_REQUEST['action'] ?? '';
$group_id = (int)($_REQUEST['group_id'] ?? 0);

if (empty($action) || $group_id === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    exit();
}

// --- Authorization Check: Is user an admin or the managing teacher? ---
$auth_stmt = $conn->prepare("SELECT teacher_id FROM message_groups WHERE id = ?");
$auth_stmt->bind_param("i", $group_id);
$auth_stmt->execute();
$group_info = $auth_stmt->get_result()->fetch_assoc();
$auth_stmt->close();

if (!$group_info) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Group not found.']);
    exit();
}

$teacher_check_stmt = $conn->prepare("SELECT EXISTS(SELECT 1 FROM teachers WHERE id = ? AND user_id = ?) as is_manager");
$teacher_check_stmt->bind_param("ii", $group_info['teacher_id'], $current_user_id);
$teacher_check_stmt->execute();
$is_manager = $teacher_check_stmt->get_result()->fetch_assoc()['is_manager'];
$teacher_check_stmt->close();

if ($current_user_role !== 'admin' && !$is_manager) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'You do not have permission to manage this group.']);
    exit();
}

// --- Handle Actions ---
switch ($action) {
    case 'get_members':
        // Get current members
        $members_sql = "SELECT s.id as student_id, u.name FROM group_members gm JOIN students s ON gm.student_id = s.id JOIN users u ON s.user_id = u.id WHERE gm.group_id = ?";
        $stmt = $conn->prepare($members_sql);
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get students not in the group
        $non_members_sql = "SELECT s.id as student_id, u.name FROM students s JOIN users u ON s.user_id = u.id WHERE s.id NOT IN (SELECT student_id FROM group_members WHERE group_id = ?)";
        $stmt = $conn->prepare($non_members_sql);
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $non_members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        echo json_encode(['status' => 'success', 'members' => $members, 'non_members' => $non_members]);
        break;

    case 'add_member':
        $student_id = (int)($_POST['student_id'] ?? 0);
        if ($student_id > 0) {
            $stmt = $conn->prepare("INSERT IGNORE INTO group_members (group_id, student_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $group_id, $student_id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['status' => 'success']);
        }
        break;

    case 'remove_member':
        $student_id = (int)($_POST['student_id'] ?? 0);
        if ($student_id > 0) {
            $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND student_id = ?");
            $stmt->bind_param("ii", $group_id, $student_id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['status' => 'success']);
        }
        break;

    case 'delete_group':
        // Admin-only action
        if ($current_user_role !== 'admin') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Only admins can delete groups.']);
            exit();
        }
        $stmt = $conn->prepare("DELETE FROM message_groups WHERE id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
        break;
}
?>
