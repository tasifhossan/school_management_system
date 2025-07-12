<?php
session_start();
// db connection
include "../lib/connection.php";

// Check if the user is logged in and has the role of 'teacher'.
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


// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $conn->prepare("INSERT INTO assignments (title, description, course_offering_id, teacher_id, due_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiis", $_POST['title'], $_POST['description'], $_POST['course_offering_id'], $teacher_id, $_POST['due_date']);
    
    if ($stmt->execute()) {
        header("Location: view_assignments.php?created=success");
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Create Assignment</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <form action="create_assignment.php" method="post">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5"></textarea>
                            </div>
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
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date">
                            </div>
                            <button type="submit" class="btn btn-primary">Create Assignment</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>