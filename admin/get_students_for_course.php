<?php
session_start();
include "../lib/connection.php";

// Security check: ensure a teacher is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Authentication required.']);
    exit();
}

if (!isset($_GET['course_offering_id']) || !is_numeric($_GET['course_offering_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid course offering ID.']);
    exit();
}

$course_offering_id = $_GET['course_offering_id'];
$students = [];

// This query uses a LEFT JOIN to check if an exam_results record exists for each student in this specific course.
$stmt = $conn->prepare("SELECT 
                            s.id, 
                            u.name as student_name, 
                            CASE WHEN er.id IS NOT NULL THEN 1 ELSE 0 END as has_marks
                        FROM student_course_enrollments sce
                        JOIN students s ON sce.student_id = s.id
                        JOIN users u ON s.user_id = u.id
                        LEFT JOIN exam_results er ON s.id = er.student_id AND sce.course_offering_id = er.course_offering_id
                        WHERE sce.course_offering_id = ?
                        ORDER BY u.name");

$stmt->bind_param("i", $course_offering_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $students[] = ['id' => $row['id'], 'name' => $row['student_name'], 'has_marks' => $row['has_marks']];
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode($students);
?>