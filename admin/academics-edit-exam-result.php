<?php

session_start();
// db connection
include "../lib/connection.php";

// Check if the user is logged in and is a student.
// You might have a $_SESSION['role'] check here as well if you want to be more specific.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Redirect to login page
    exit();
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM exam_results WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $conn->prepare("UPDATE exam_results SET student_id = ?, course_offering_id = ?, marks = ?, exam_date = ? WHERE id = ?");
    $stmt->bind_param("iissi", $student_id, $course_offering_id, $marks, $exam_date, $id);

    $student_id = $_POST['student_id'];
    $course_offering_id = $_POST['course_offering_id'];
    $marks = $_POST['marks'];
    $exam_date = $_POST['exam_date'];

    if ($stmt->execute()) {
        header("Location: academics-exam-results.php");
    } else {
        echo "Error updating record: " . $stmt->error;
    }
    $stmt->close();
}
?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_ad.php" ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Edit Exam Result</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form action="academics-edit-exam-result.php?id=<?php echo $id; ?>" method="post">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Student</label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option disabled>Choose...</option>
                                    <?php
                                        $students_sql = "SELECT s.id, u.name FROM students s JOIN users u ON s.user_id = u.id";
                                        $students_result = $conn->query($students_sql);
                                        if ($students_result->num_rows > 0) {
                                            while($student_row = $students_result->fetch_assoc()) {
                                                $selected = ($student_row['id'] == $row['student_id']) ? 'selected' : '';
                                                echo "<option value='" . $student_row['id'] . "' " . $selected . ">" . $student_row['name'] . "</option>";
                                            }
                                        }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="course_offering_id" class="form-label">Course Offering</label>
                                <select class="form-select" id="course_offering_id" name="course_offering_id" required>
                                    <option disabled>Choose...</option>
                                    <?php
                                        $offerings_sql = "SELECT co.id, c.name as course_name, co.semester, co.section FROM course_offerings co JOIN courses c ON co.course_id = c.id";
                                        $offerings_result = $conn->query($offerings_sql);
                                        if ($offerings_result->num_rows > 0) {
                                            while($offering_row = $offerings_result->fetch_assoc()) {
                                                $selected = ($offering_row['id'] == $row['course_offering_id']) ? 'selected' : '';
                                                echo "<option value='" . $offering_row['id'] . "' " . $selected . ">" . $offering_row['course_name'] . " - " . $offering_row['semester'] . " (Sec: " . $offering_row['section'] . ")</option>";
                                            }
                                        }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="marks" class="form-label">Marks</label>
                                <input type="number" class="form-control" id="marks" name="marks" value="<?php echo $row['marks']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="exam_date" class="form-label">Exam Date</label>
                                <input type="date" class="form-control" id="exam_date" name="exam_date" value="<?php echo $row['exam_date']; ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Update</button>
                            <a href="academics-exam-results.php" class="btn btn-secondary">
                                <i data-feather="arrow-left" class="me-1" style="width:16px; height:16px;"></i> Back to Exam Results
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php" ?>