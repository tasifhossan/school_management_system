<?php
session_start();
include "../lib/connection.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php"); 
    exit();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid result ID.");
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


$result_id = $_GET['id'];

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


// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $conn->prepare("UPDATE exam_results SET marks = ?, exam_date = ? WHERE id = ?");
    $stmt->bind_param("isi", $_POST['marks'], $_POST['exam_date'], $result_id);
    
    if ($stmt->execute()) {
        header("Location: view_results.php?updated=success");
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch existing result data
$stmt_fetch = $conn->prepare("SELECT er.*, u.name as student_name, c.name as course_name 
                                FROM exam_results er
                                JOIN students s ON er.student_id = s.id
                                JOIN users u ON s.user_id = u.id
                                JOIN course_offerings co ON er.course_offering_id = co.id
                                JOIN courses c ON co.course_id = c.id
                                WHERE er.id = ? AND co.teacher_id = ?");
$stmt_fetch->bind_param("ii", $result_id, $teacher_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
if ($result->num_rows > 0) {
    $exam_result = $result->fetch_assoc();
} else {
    die("Exam result not found or you do not have permission to edit it.");
}
$stmt_fetch->close();
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Edit Exam Result</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                         <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form action="edit_exam_result.php?id=<?php echo $result_id; ?>" method="post">
                            <div class="mb-3">
                                <label class="form-label">Student</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($exam_result['student_name']); ?>" readonly>
                            </div>
                             <div class="mb-3">
                                <label class="form-label">Course</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($exam_result['course_name']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="marks" class="form-label">Marks</label>
                                <input type="number" class="form-control" id="marks" name="marks" value="<?php echo htmlspecialchars($exam_result['marks']); ?>" required>
                            </div>
                             <div class="mb-3">
                                <label for="exam_date" class="form-label">Exam Date</label>
                                <input type="date" class="form-control" id="exam_date" name="exam_date" value="<?php echo htmlspecialchars($exam_result['exam_date']); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Marks</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>