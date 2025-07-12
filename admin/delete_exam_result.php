<?php
session_start();
include "../lib/connection.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php"); 
    exit();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid result ID.");
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Teacher';
$result_id = $_GET['id'];

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


// Verify this teacher owns the course for this result before deleting
$stmt_verify = $conn->prepare("SELECT er.id FROM exam_results er JOIN course_offerings co ON er.course_offering_id = co.id WHERE er.id = ? AND co.teacher_id = ?");
$stmt_verify->bind_param("ii", $result_id, $teacher_id);
$stmt_verify->execute();
$result_verify = $stmt_verify->get_result();

if ($result_verify->num_rows > 0) {
    $stmt_delete = $conn->prepare("DELETE FROM exam_results WHERE id = ?");
    $stmt_delete->bind_param("i", $result_id);
    if ($stmt_delete->execute()) {
        header("Location: view_results.php?deleted=success");
    } else {
        header("Location: view_results.php?error=sql");
    }
    $stmt_delete->close();
} else {
    header("Location: view_results.php?error=permission");
}
$stmt_verify->close();
?>