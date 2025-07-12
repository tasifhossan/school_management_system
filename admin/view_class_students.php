<?php
session_start();
// Use the session check you provided
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php"); 
    exit();
}

if (!isset($_GET['class_id']) || !is_numeric($_GET['class_id'])) {
    die("Error: Invalid class ID.");
}

include "../lib/connection.php";
$class_id = $_GET['class_id'];
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

// --- Security Check ---
// 1. Get the teacher's internal ID
$stmt_teacher_id = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt_teacher_id->bind_param("i", $userId);
$stmt_teacher_id->execute();
$result_teacher_id = $stmt_teacher_id->get_result();
if ($result_teacher_id->num_rows > 0) {
    $teacher_data = $result_teacher_id->fetch_assoc();
    $teacher_id = $teacher_data['id'];
} else {
    die("Error: Could not verify your teacher profile.");
}
$stmt_teacher_id->close();

// 2. Verify this teacher is assigned to the requested class_id
$class_info = null;
$stmt_class = $conn->prepare("SELECT name, section FROM classes WHERE id = ? AND teacher_id = ?");
$stmt_class->bind_param("ii", $class_id, $teacher_id);
$stmt_class->execute();
$result_class = $stmt_class->get_result();
if ($result_class->num_rows > 0) {
    $class_info = $result_class->fetch_assoc();
} else {
    die("Error: Class not found or you do not have permission to view its students.");
}
$stmt_class->close();
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">
            Students in Class: <strong><?php echo htmlspecialchars($class_info['name'] . ' - ' . $class_info['section']); ?></strong>
        </h1>

        <div class="row">
            <div class="col-12">
                <div class="card">
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
                                    $stmt = $conn->prepare("SELECT u.name as student_name, s.roll_number, u.email, s.phone, s.photo 
                                                            FROM student_class_enrollments sce
                                                            JOIN students s ON sce.student_id = s.id
                                                            JOIN users u ON s.user_id = u.id
                                                            WHERE sce.class_id = ?
                                                            ORDER BY u.name");
                                    $stmt->bind_param("i", $class_id);
                                    $stmt->execute();
                                    $result_students = $stmt->get_result();
                                    if ($result_students->num_rows > 0) {
                                        $serial_no = 1;
                                        while ($row = $result_students->fetch_assoc()) {
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
                                        echo "<tr><td colspan='6'>No students are currently enrolled in this class section.</td></tr>";
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