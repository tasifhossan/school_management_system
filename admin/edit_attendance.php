<?php
session_start();
include "../lib/connection.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php"); 
    exit();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid attendance ID.");
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Teacher';
// $PhotoDir = 'uploads/teacher_photos/';
$defaultAvatar = 'img/avatars/avatar.jpg';
$PhotoDir = '';


$sql = "SELECT photo FROM teachers WHERE id = ?"; 
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$imageSrc = $defaultAvatar;

if ($row && !empty($row['photo'])) {
    $PhotoPath = $PhotoDir . $row['photo'];
    if (file_exists($PhotoPath)) {
        $imageSrc = $PhotoPath;
    }
}


$attendance_id = $_GET['id'];
$teacher_id = null;

// Fetch the teacher's internal ID
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


// Handle form submission for update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_status = $_POST['status'];
    
    // Security check: Ensure the teacher owns the course for this attendance record
    $stmt_verify = $conn->prepare("SELECT att.id FROM attendance att JOIN course_offerings co ON att.course_offering_id = co.id WHERE att.id = ? AND co.teacher_id = ?");
    $stmt_verify->bind_param("ii", $attendance_id, $teacher_id);
    $stmt_verify->execute();
    if ($stmt_verify->get_result()->num_rows > 0) {
        $stmt_update = $conn->prepare("UPDATE attendance SET status = ? WHERE id = ?");
        $stmt_update->bind_param("si", $new_status, $attendance_id);
        if ($stmt_update->execute()) {
            header("Location: view_attendance.php?success=updated");
            exit();
        } else {
            $error = "Error updating record: " . $stmt_update->error;
        }
        $stmt_update->close();
    } else {
        $error = "You do not have permission to edit this record.";
    }
    $stmt_verify->close();
}


// Fetch existing attendance data for the form
$stmt_fetch = $conn->prepare("SELECT att.id, att.attendance_date, att.status, u.name as student_name, c.name as course_name 
                                FROM attendance att
                                JOIN students s ON att.student_id = s.id
                                JOIN users u ON s.user_id = u.id
                                JOIN course_offerings co ON att.course_offering_id = co.id
                                JOIN courses c ON co.course_id = c.id
                                WHERE att.id = ? AND co.teacher_id = ?");
$stmt_fetch->bind_param("ii", $attendance_id, $teacher_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
if ($result->num_rows > 0) {
    $attendance_record = $result->fetch_assoc();
} else {
    die("Attendance record not found or you do not have permission to edit it.");
}
$stmt_fetch->close();
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Edit Attendance Record</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form action="edit_attendance.php?id=<?php echo $attendance_id; ?>" method="post">
                            <div class="mb-3">
                                <label class="form-label">Student</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($attendance_record['student_name']); ?>" readonly>
                            </div>
                             <div class="mb-3">
                                <label class="form-label">Course</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($attendance_record['course_name']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($attendance_record['attendance_date']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="Present" <?php echo ($attendance_record['status'] == 'Present') ? 'selected' : ''; ?>>Present</option>
                                    <option value="Absent" <?php echo ($attendance_record['status'] == 'Absent') ? 'selected' : ''; ?>>Absent</option>
                                    <option value="Late" <?php echo ($attendance_record['status'] == 'Late') ? 'selected' : ''; ?>>Late</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                             <a href="view_attendance.php?filter_date=<?php echo htmlspecialchars($attendance_record['attendance_date']); ?>" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>