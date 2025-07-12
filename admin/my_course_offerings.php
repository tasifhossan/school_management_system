<?php
session_start();
// Use the session check you provided
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php"); 
    exit();
}

include "../lib/connection.php";
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



// Fetch the teacher's internal ID from the `teachers` table
$stmt_teacher_id = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt_teacher_id->bind_param("i", $userId);
$stmt_teacher_id->execute();
$result_teacher_id = $stmt_teacher_id->get_result();
if ($result_teacher_id->num_rows > 0) {
    $teacher_data = $result_teacher_id->fetch_assoc();
    $teacher_id = $teacher_data['id']; // This is the ID from the teachers table
} else {
    die("Teacher profile not found for the logged-in user.");
}
$stmt_teacher_id->close();
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">My Course Offerings</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                     <div class="card-header">
                        <h5 class="card-title mb-0">Courses I Am Teaching</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover my-0">
                                <thead>
                                    <tr>
                                        <th>Course Name</th>
                                        <th>Code</th>
                                        <th>Semester</th>
                                        <th>Section</th>
                                        <th>Meeting Time</th>
                                        <th>Location</th>
                                        <th>Enrolled / Capacity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // This query includes the meeting_time (class time) and a subquery to count enrolled students
                                    $stmt = $conn->prepare("SELECT 
                                                                co.id,
                                                                c.name, 
                                                                c.course_code, 
                                                                co.semester, 
                                                                co.section,
                                                                co.meeting_time, 
                                                                co.location,
                                                                co.capacity,
                                                                (SELECT COUNT(*) FROM student_course_enrollments sce WHERE sce.course_offering_id = co.id) as enrolled_students
                                                            FROM course_offerings co
                                                            JOIN courses c ON co.course_id = c.id
                                                            WHERE co.teacher_id = ?");
                                    $stmt->bind_param("i", $teacher_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row["name"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["course_code"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["semester"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["section"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["meeting_time"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["location"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["enrolled_students"]) . " / " . htmlspecialchars($row["capacity"]) . "</td>";
                                            echo '<td><a href="view_course_students.php?offering_id=' . $row['id'] . '" class="btn btn-primary btn-sm">View Students</a></td>';
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='8'>You are not assigned to any course offerings.</td></tr>";
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