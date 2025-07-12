<?php
session_start();
// Use the session check you provided
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php"); 
    exit();
}

// Check if the offering_id is provided in the URL
if (!isset($_GET['offering_id']) || !is_numeric($_GET['offering_id'])) {
    die("Error: Invalid course offering ID.");
}

include "../lib/connection.php";
$offering_id = $_GET['offering_id'];
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

// Fetch the teacher's internal ID to verify they teach this course
$stmt_teacher_id = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt_teacher_id->bind_param("i", $userId);
$stmt_teacher_id->execute();
$result_teacher_id = $stmt_teacher_id->get_result();
if ($result_teacher_id->num_rows > 0) {
    $teacher_data = $result_teacher_id->fetch_assoc();
    $teacher_id = $teacher_data['id'];
}
$stmt_teacher_id->close();
if ($teacher_id === null) {
     die("Error: Could not verify teacher profile.");
}


// Fetch Course Offering details to display on the page
$course_info = null;
$stmt_course = $conn->prepare("SELECT c.name, c.course_code, co.semester, co.section 
                                FROM course_offerings co
                                JOIN courses c ON co.course_id = c.id
                                WHERE co.id = ? AND co.teacher_id = ?");
$stmt_course->bind_param("ii", $offering_id, $teacher_id);
$stmt_course->execute();
$result_course = $stmt_course->get_result();
if ($result_course->num_rows > 0) {
    $course_info = $result_course->fetch_assoc();
} else {
    // This prevents a teacher from viewing students of a course they don't teach
    die("Error: Course not found or you do not have permission to view it.");
}
$stmt_course->close();

?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">
            Enrolled Students in: <strong><?php echo htmlspecialchars($course_info['name'] . ' - ' . $course_info['section']); ?></strong>
        </h1>
        <p class="mb-3">
            <strong>Course Code:</strong> <?php echo htmlspecialchars($course_info['course_code']); ?> | 
            <strong>Semester:</strong> <?php echo htmlspecialchars($course_info['semester']); ?>
        </p>

        <div class="row">
            <div class="col-12">
                <div class="card">
                     <div class="card-header">
                        <h5 class="card-title mb-0">Student List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover my-0">
                                <thead>
                                    <tr>
                                        <th>Sl. No.</th>
                                        <th>Photo</th>
                                        <th>Student Name</th>
                                        <th>Roll Number</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Updated query to fetch the student's photo path
                                    $stmt = $conn->prepare("SELECT u.name as student_name, s.roll_number, u.email, s.phone, s.photo 
                                                            FROM student_course_enrollments sce
                                                            JOIN students s ON sce.student_id = s.id
                                                            JOIN users u ON s.user_id = u.id
                                                            WHERE sce.course_offering_id = ?
                                                            ORDER BY u.name");
                                    $stmt->bind_param("i", $offering_id);
                                    $stmt->execute();
                                    $result_students = $stmt->get_result();
                                    if ($result_students->num_rows > 0) {
                                        $serial_no = 1;
                                        while ($row = $result_students->fetch_assoc()) {
                                            // Define a default photo if the student doesn't have one
                                            $photo_path = !empty($row["photo"]) ? '/school/admin/' . htmlspecialchars($row["photo"]) : 'img/avatars/avatar.jpg';
                                            
                                            echo "<tr>";
                                            echo "<td>" . $serial_no++ . "</td>";
                                            echo '<td><img src="' . $photo_path . '" width="48" height="48" class="rounded-circle me-2" alt="Avatar"></td>';
                                            echo "<td>" . htmlspecialchars($row["student_name"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["roll_number"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["phone"]) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6'>No students are currently enrolled in this course offering.</td></tr>";
                                    }
                                    $stmt->close();
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>