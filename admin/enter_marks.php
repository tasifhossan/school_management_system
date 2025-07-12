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

$stmt_teacher_id = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt_teacher_id->bind_param("i", $userId);
$stmt_teacher_id->execute();
$result_teacher_id = $stmt_teacher_id->get_result();
if ($result_teacher_id->num_rows > 0) {
    $teacher_data = $result_teacher_id->fetch_assoc();
    $teacher_id = $teacher_data['id'];
} else {
    die("Teacher profile not found for the logged-in user.");
}
$stmt_teacher_id->close();


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_POST['student_id'];
    $course_offering_id = $_POST['course_offering_id'];
    $marks = $_POST['marks'];
    $exam_date = $_POST['exam_date'];

    // Server-side check to prevent duplicate entries
    $stmt_check = $conn->prepare("SELECT id FROM exam_results WHERE student_id = ? AND course_offering_id = ?");
    $stmt_check->bind_param("ii", $student_id, $course_offering_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        $error = "Marks for this student have already been entered. You can edit them from the 'View Results' page.";
    } else {
        $stmt_insert = $conn->prepare("INSERT INTO exam_results (student_id, course_offering_id, marks, exam_date) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("iiss", $student_id, $course_offering_id, $marks, $exam_date);
        
        if ($stmt_insert->execute()) {
            $success = "Marks entered successfully!";
        } else {
            $error = "Error entering marks: " . $stmt_insert->error;
        }
        $stmt_insert->close();
    }
    $stmt_check->close();
}
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Enter Exam Marks</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <form action="enter_marks.php" method="post">
                            <div class="mb-3">
                                <label for="course_offering_id" class="form-label">Course Offering</label>
                                <select class="form-select" id="course_offering_id" name="course_offering_id" required>
                                    <option selected disabled value="">Choose a course...</option>
                                    <?php
                                        $offerings_sql = "SELECT co.id, c.name as course_name, co.semester, co.section FROM course_offerings co JOIN courses c ON co.course_id = c.id WHERE co.teacher_id = ?";
                                        $stmt_offerings = $conn->prepare($offerings_sql);
                                        $stmt_offerings->bind_param("i", $teacher_id);
                                        $stmt_offerings->execute();
                                        $offerings_result = $stmt_offerings->get_result();
                                        if ($offerings_result->num_rows > 0) {
                                            while($offering_row = $offerings_result->fetch_assoc()) {
                                                echo "<option value='" . $offering_row['id'] . "'>" . htmlspecialchars($offering_row['course_name']) . " - " . htmlspecialchars($offering_row['semester']) . " (Sec: " . htmlspecialchars($offering_row['section']) . ")</option>";
                                            }
                                        }
                                        $stmt_offerings->close();
                                    ?>
                                </select>
                            </div>
                             <div class="mb-3">
                                <label for="student_id" class="form-label">Student</label>
                                <select class="form-select" id="student_id" name="student_id" required disabled>
                                    <option selected disabled value="">Select a course first...</option>
                                </select>
                                <div class="form-text">Students with existing marks are disabled.</div>
                            </div>
                            <div class="mb-3">
                                <label for="marks" class="form-label">Marks</label>
                                <input type="number" class="form-control" id="marks" name="marks" required>
                            </div>
                             <div class="mb-3">
                                <label for="exam_date" class="form-label">Exam Date</label>
                                <input type="date" class="form-control" id="exam_date" name="exam_date" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Marks</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const courseSelect = document.getElementById('course_offering_id');
    const studentSelect = document.getElementById('student_id');

    courseSelect.addEventListener('change', function() {
        const courseId = this.value;
        studentSelect.innerHTML = '<option>Loading...</option>';
        studentSelect.disabled = true;

        if (courseId) {
            fetch('get_students_for_course.php?course_offering_id=' + courseId)
                .then(response => response.json())
                .then(data => {
                    studentSelect.innerHTML = '<option selected disabled value="">Choose a student...</option>';
                    if (data.length > 0) {
                        data.forEach(student => {
                            const option = document.createElement('option');
                            option.value = student.id;
                            
                            // Block (disable) students who already have marks
                            if (student.has_marks == 1) {
                                option.textContent = student.name + ' (Marks Entered)';
                                option.disabled = true;
                                option.style.color = '#6c757d'; // Bootstrap's gray color
                            } else {
                                option.textContent = student.name;
                            }
                            studentSelect.appendChild(option);
                        });
                    } else {
                         studentSelect.innerHTML = '<option disabled>No students found for this course.</option>';
                    }
                    studentSelect.disabled = false;
                });
        }
    });
});
</script>