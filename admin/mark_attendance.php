<?php
session_start();
include "../lib/connection.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php"); 
    exit();
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


$teacher_id = null;
$error = null;
$success = null;
$course_offering_id = $_POST['course_offering_id'] ?? null;
$attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');

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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_attendance'])) {
    $students_status = $_POST['status'];
    $course_offering_id = $_POST['course_offering_id_hidden'];
    $attendance_date_hidden = $_POST['attendance_date_hidden'];

    foreach ($students_status as $student_id => $status) {
        $stmt_check = $conn->prepare("SELECT id FROM attendance WHERE student_id = ? AND course_offering_id = ? AND attendance_date = ?");
        $stmt_check->bind_param("iis", $student_id, $course_offering_id, $attendance_date_hidden);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $stmt_update = $conn->prepare("UPDATE attendance SET status = ? WHERE student_id = ? AND course_offering_id = ? AND attendance_date = ?");
            $stmt_update->bind_param("siis", $status, $student_id, $course_offering_id, $attendance_date_hidden);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO attendance (student_id, course_offering_id, attendance_date, status) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("iiss", $student_id, $course_offering_id, $attendance_date_hidden, $status);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
    $success = "Attendance for " . htmlspecialchars($attendance_date_hidden) . " has been saved successfully!";
}
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Mark Attendance</h1>
        <div class="card">
            <div class="card-body">
                <form action="mark_attendance.php" method="post" class="mb-4">
                    <div class="row">
                        <div class="col-md-5">
                            <label for="course_offering_id" class="form-label">Course Offering</label>
                            <select class="form-select" id="course_offering_id" name="course_offering_id" required>
                                <option value="">Choose a course...</option>
                                <?php
                                    $stmt_offerings = $conn->prepare("SELECT co.id, c.name as course_name, co.semester, co.section FROM course_offerings co JOIN courses c ON co.course_id = c.id WHERE co.teacher_id = ?");
                                    $stmt_offerings->bind_param("i", $teacher_id);
                                    $stmt_offerings->execute();
                                    $offerings_result = $stmt_offerings->get_result();
                                    while($row = $offerings_result->fetch_assoc()) {
                                        $selected = ($course_offering_id == $row['id']) ? 'selected' : '';
                                        echo "<option value='" . $row['id'] . "' " . $selected . ">" . htmlspecialchars($row['course_name']) . " - " . htmlspecialchars($row['section']) . "</option>";
                                    }
                                    $stmt_offerings->close();
                                ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="attendance_date" class="form-label">Attendance Date</label>
                            <input type="date" class="form-control" id="attendance_date" name="attendance_date" value="<?php echo htmlspecialchars($attendance_date); ?>" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" name="fetch_roster" class="btn btn-primary w-100">Fetch Roster</button>
                        </div>
                    </div>
                </form>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if ($course_offering_id && $attendance_date): ?>
                <hr/>
                <form action="mark_attendance.php" method="post">
                    <input type="hidden" name="course_offering_id_hidden" value="<?php echo htmlspecialchars($course_offering_id); ?>">
                    <input type="hidden" name="attendance_date_hidden" value="<?php echo htmlspecialchars($attendance_date); ?>">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Roll Number</th>
                                <th class="text-center">Present</th>
                                <th class="text-center">Absent</th>
                                <th class="text-center">Late</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt_students = $conn->prepare("SELECT s.id, u.name, s.roll_number, att.status 
                                                            FROM student_course_enrollments sce
                                                            JOIN students s ON sce.student_id = s.id
                                                            JOIN users u ON s.user_id = u.id
                                                            LEFT JOIN attendance att ON s.id = att.student_id AND att.course_offering_id = ? AND att.attendance_date = ?
                                                            WHERE sce.course_offering_id = ? ORDER BY u.name");
                            $stmt_students->bind_param("isi", $course_offering_id, $attendance_date, $course_offering_id);
                            $stmt_students->execute();
                            $result_students = $stmt_students->get_result();
                            if ($result_students->num_rows > 0) {
                                while($student = $result_students->fetch_assoc()) {
                                    $status = $student['status'] ?? 'Present'; // Default to 'Present'
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($student['name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($student['roll_number']) . "</td>";
                                    echo "<td class='text-center'><input class='form-check-input' type='radio' name='status[" . $student['id'] . "]' value='Present' " . ($status == 'Present' ? 'checked' : '') . "></td>";
                                    echo "<td class='text-center'><input class='form-check-input' type='radio' name='status[" . $student['id'] . "]' value='Absent' " . ($status == 'Absent' ? 'checked' : '') . "></td>";
                                    echo "<td class='text-center'><input class='form-check-input' type='radio' name='status[" . $student['id'] . "]' value='Late' " . ($status == 'Late' ? 'checked' : '') . "></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>No students found in this course.</td></tr>";
                            }
                            $stmt_students->close();
                            ?>
                        </tbody>
                    </table>
                    <button type="submit" name="submit_attendance" class="btn btn-success mt-3">Save Attendance</button>
                    <a href="view_attendance.php" class="btn btn-secondary mt-3">View Full History</a>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>