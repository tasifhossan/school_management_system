<?php
session_start();
// Use the session check you provided
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: loginn.php"); 
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
        <h1 class="h3 mb-3">My Sections</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Sections from My Course Offerings</h5>
                         <h6 class="card-subtitle text-muted">This is a list of all sections you are currently teaching.</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover my-0">
                            <thead>
                                <tr>
                                    <th>Section</th>
                                    <th>Associated Course</th>
                                    <th>Semester</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // This query now gets the sections directly from the courses the teacher is assigned to.
                                $stmt = $conn->prepare("SELECT 
                                                            co.id as offering_id, 
                                                            co.section, 
                                                            c.name as course_name, 
                                                            co.semester
                                                        FROM course_offerings co
                                                        JOIN courses c ON co.course_id = c.id
                                                        WHERE co.teacher_id = ?
                                                        ORDER BY co.section, c.name");
                                $stmt->bind_param("i", $teacher_id);
                                $stmt->execute();
                                $result = $stmt->get_result();

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row["section"]) . "</td>";
                                        echo "<td>" . htmlspecialchars($row["course_name"]) . "</td>";
                                        echo "<td>" . htmlspecialchars($row["semester"]) . "</td>";
                                        echo '<td>
                                                <a href="view_course_students.php?offering_id=' . $row['offering_id'] . '" class="btn btn-primary btn-sm">View Students</a>
                                              </td>';
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4'>You are not assigned to any course sections.</td></tr>";
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
</main>

<?php include "footer.php"; ?>