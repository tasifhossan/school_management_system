<?php
session_start();
// db connection
include "../lib/connection.php"; 

// Check if the user is logged in and is an admin.

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    header("Location: login.php"); // Redirect to login page
    exit();
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $conn->prepare("INSERT INTO attendance (student_id, course_offering_id, attendance_date, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $student_id, $course_offering_id, $attendance_date, $status);

    $student_id = $_POST['student_id'];
    $course_offering_id = $_POST['course_offering_id'];
    $attendance_date = $_POST['attendance_date'];
    $status = $_POST['status'];

    if ($stmt->execute()) {
        header("Location: academics-attendance.php");
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_ad.php" ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Add Attendance Record</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form action="academics-add-attendance.php" method="post">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Student</label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option selected disabled value="">Choose...</option>
                                    <?php
                                        // This query now explicitly checks for users with the 'student' role.
                                        $students_sql = "SELECT s.id, u.name FROM students s JOIN users u ON s.user_id = u.id WHERE u.role = 'student' ORDER BY u.name";
                                        $students_result = $conn->query($students_sql);
                                        if ($students_result && $students_result->num_rows > 0) {
                                            while($student_row = $students_result->fetch_assoc()) {
                                                echo "<option value='" . $student_row['id'] . "'>" . htmlspecialchars($student_row['name']) . "</option>";
                                            }
                                        } else {
                                            // More descriptive error message
                                            echo "<option disabled>No students found. Ensure users with the 'student' role are correctly linked in the 'students' table.</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="course_offering_id" class="form-label">Course Offering</label>
                                <select class="form-select" id="course_offering_id" name="course_offering_id" required>
                                    <option selected disabled value="">Choose...</option>
                                    <?php
                                        $offerings_sql = "SELECT co.id, c.name as course_name, co.semester, co.section FROM course_offerings co JOIN courses c ON co.course_id = c.id";
                                        $offerings_result = $conn->query($offerings_sql);
                                        if ($offerings_result && $offerings_result->num_rows > 0) {
                                            while($offering_row = $offerings_result->fetch_assoc()) {
                                                echo "<option value='" . $offering_row['id'] . "'>" . htmlspecialchars($offering_row['course_name']) . " - " . htmlspecialchars($offering_row['semester']) . " (Sec: " . htmlspecialchars($offering_row['section']) . ")</option>";
                                            }
                                        } else {
                                             echo "<option disabled>No course offerings found.</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="attendance_date" class="form-label">Attendance Date</label>
                                <input type="date" class="form-control" id="attendance_date" name="attendance_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option selected disabled value="">Choose...</option>
                                    <option value="Present">Present</option>
                                    <option value="Absent">Absent</option>
                                    <option value="Late">Late</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit</button>
                            <a href="academics-attendance.php" class="btn btn-secondary">
                                <i data-feather="arrow-left" class="me-1" style="width:16px; height:16px;"></i> Back to Attendance
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php" ?>