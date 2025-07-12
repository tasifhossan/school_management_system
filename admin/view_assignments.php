<?php
session_start();
// db connection
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
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">View Assignments</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <a href="create_assignment.php" class="btn btn-primary">Create New Assignment</a>
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover my-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Course</th>
                                    <th>Due Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT a.id, a.title, a.due_date, c.name as course_name, co.section 
                                                        FROM assignments a
                                                        JOIN course_offerings co ON a.course_offering_id = co.id
                                                        JOIN courses c ON co.course_id = c.id
                                                        WHERE a.teacher_id = ?
                                                        ORDER BY a.due_date DESC");
                                $stmt->bind_param("i", $teacher_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row["title"]) . "</td>";
                                        echo "<td>" . htmlspecialchars($row["course_name"]) . " (" . htmlspecialchars($row["section"]) . ")</td>";
                                        echo "<td>" . htmlspecialchars($row["due_date"]) . "</td>";
                                        echo '<td>
                                                <a href="edit_assignment.php?id=' . $row["id"] . '" class="btn btn-sm btn-primary">Edit</a>
                                                <a href="delete_assignment.php?id=' . $row["id"] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure?\')">Delete</a>
                                              </td>';
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4'>No assignments found.</td></tr>";
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