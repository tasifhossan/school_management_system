<?php
session_start();
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Do not display errors to the user
ini_set('log_errors', 1); // Log errors to a file
// IMPORTANT: Set a real path to an accessible log file for your server
// Example for Linux Apache: ini_set('error_log', '/var/log/apache2/php_error_log_custom.log');
// Example for cPanel/shared hosting: ini_set('error_log', '/home/youruser/public_html/php_error.log');
ini_set('error_log', dirname(__DIR__) . '/php_error_log.log'); // Adjust this path as needed for your setup

include "../lib/connection.php"; // Correct path assuming connection.php is in school/lib/

// Set content type to JSON
header('Content-Type: application/json');

// Function to send JSON error response and exit
function sendJsonError($message, $details = '', $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message, 'details' => $details]);
    exit();
}

// Check if the user is logged in and is a teacher.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    sendJsonError('Unauthorized access.', 'User not logged in or not a teacher.', 403);
}

$userId = $_SESSION['user_id']; // The user_id from the users table

// Fetch the teacher's internal ID from the `teachers` table
$stmt_teacher_id = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
if (!$stmt_teacher_id) {
    sendJsonError('Database error preparing teacher ID statement.', $conn->error, 500);
}
$stmt_teacher_id->bind_param("i", $userId);
$stmt_teacher_id->execute();
$result_teacher_id = $stmt_teacher_id->get_result();
if ($result_teacher_id->num_rows > 0) {
    $teacherId = $result_teacher_id->fetch_assoc()['id']; // This is the ID from the teachers table
} else {
    sendJsonError('Teacher profile not found.', 'No teacher entry for logged-in user with ID: ' . $userId, 404);
}
$stmt_teacher_id->close();

$courseOfferingId = isset($_GET['course_offering_id']) ? intval($_GET['course_offering_id']) : 0;

$students = [];

if ($courseOfferingId <= 0) {
    sendJsonError('Invalid course offering ID.', 'Course_offering_id parameter is missing or invalid.', 400);
}

// Verify that the course offering belongs to the logged-in teacher
$verify_sql = "SELECT id FROM course_offerings WHERE id = ? AND teacher_id = ?";
$verify_stmt = $conn->prepare($verify_sql);
if (!$verify_stmt) {
    sendJsonError('Database error preparing course offering verification statement.', $conn->error, 500);
}
$verify_stmt->bind_param("ii", $courseOfferingId, $teacherId); // Use the fetched teacherId
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    sendJsonError('Access denied.', 'Course offering ' . $courseOfferingId . ' not found or does not belong to your assigned courses.', 403);
}
$verify_stmt->close();

// Fetch students enrolled in this course offering
// CORRECTED: Changed 'student_enrollments' to 'student_course_enrollments' based on your provided schema
$sql = "
    SELECT u.id, u.name 
    FROM users u
    JOIN students s ON u.id = s.user_id
    JOIN student_course_enrollments se ON s.id = se.student_id
    WHERE se.course_offering_id = ?
    ORDER BY u.name
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    sendJsonError('Database error preparing student fetch statement.', $conn->error, 500);
}
$stmt->bind_param("i", $courseOfferingId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

// If everything is successful, encode and return the students array
echo json_encode($students);
?>