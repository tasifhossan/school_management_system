<?php
session_start();
// db connection
include "../lib/connection.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php"); 
    exit();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid assignment ID.");
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Teacher';
$assignment_id = $_GET['id'];

// Get teacher_id to ensure the teacher owns this assignment
$stmt_teacher_id = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt_teacher_id->bind_param("i", $userId);
$stmt_teacher_id->execute();
$result_teacher_id = $stmt_teacher_id->get_result();
if ($result_teacher_id->num_rows > 0) {
    $teacher_data = $result_teacher_id->fetch_assoc();
    $teacher_id = $teacher_data['id'];
} else {
    die("Teacher profile not found.");
}
$stmt_teacher_id->close();


// Prepare and execute the delete statement
$stmt_delete = $conn->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?");
$stmt_delete->bind_param("ii", $assignment_id, $teacher_id);

if ($stmt_delete->execute()) {
    // Check if a row was actually deleted
    if ($stmt_delete->affected_rows > 0) {
        header("Location: view_assignments.php?deleted=success");
    } else {
        // This means the assignment didn't exist or didn't belong to this teacher
        header("Location: view_assignments.php?error=notfound");
    }
} else {
    // Handle SQL error
    header("Location: view_assignments.php?error=sql");
}
$stmt_delete->close();
?>