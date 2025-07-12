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
    $teacher_id = $teacher_data['id'];
} else {
    die("Teacher profile not found for the logged-in user.");
}
$stmt_teacher_id->close();
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">My Students</h1>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Students in My Courses</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover my-0">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Student Name</th>
                                <th>Roll Number</th>
                                <th>Course</th>
                                <th>Email</th>
                                <th>Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Updated query to fetch all necessary details
                            $stmt = $conn->prepare("SELECT 
                                                        s.id as student_id, 
                                                        u.name as student_name, 
                                                        s.roll_number, 
                                                        u.email,
                                                        s.phone,
                                                        s.photo,
                                                        c.name as course_name 
                                                    FROM student_course_enrollments sce
                                                    JOIN students s ON sce.student_id = s.id
                                                    JOIN users u ON s.user_id = u.id
                                                    JOIN course_offerings co ON sce.course_offering_id = co.id
                                                    JOIN courses c ON co.course_id = c.id
                                                    WHERE co.teacher_id = ?
                                                    ORDER BY u.name, c.name");
                            $stmt->bind_param("i", $teacher_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    // Use the photo path logic you provided
                                    $photo_path = !empty($row["photo"]) ? '/school/admin/' . htmlspecialchars($row["photo"]) : 'img/avatars/avatar.jpg';

                                    echo "<tr>";
                                    echo '<td><img src="' . $photo_path . '" width="48" height="48" class="rounded-circle me-2" alt="' . htmlspecialchars($row["student_name"]) . '"></td>';
                                    echo "<td>" . htmlspecialchars($row["student_name"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["roll_number"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["course_name"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["phone"]) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>No students are enrolled in your courses.</td></tr>";
                            }
                            $stmt->close();
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>