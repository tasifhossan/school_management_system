<?php  

session_start();

// db connection
include "../lib/connection.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php"); // Redirect to login page
    exit();
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Student';

$PhotoDir = '';
$defaultAvatar = 'img/avatars/avatar.jpg';


$sql = "SELECT photo FROM students WHERE user_id = ?"; 
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

$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

// Validate submission_id
if ($submission_id <= 0) {
    die("Invalid submission ID.");
}

// Get student's primary ID for validation
$stmtStudent = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmtStudent->bind_param("i", $userId);
$stmtStudent->execute();
$resultStudent = $stmtStudent->get_result();
if ($resultStudent->num_rows === 0) {
    die("Could not find student record.");
}
$student = $resultStudent->fetch_assoc();
$studentId = $student['id'];
$stmtStudent->close();

// Fetch submission details.
// This complex query joins all necessary tables to get assignment, course, and submission info.
// Crucially, it validates that the submission belongs to the logged-in student (sub.student_id = ?).
$sql = "
    SELECT 
        a.title AS assignment_title,
        a.description AS assignment_description,
        a.due_date,
        c.name AS course_name,
        sub.submission_date,
        sub.submission_content,
        sub.file_path,
        sub.status,
        sub.grade,
        sub.feedback
    FROM 
        assignment_submissions AS sub
    JOIN 
        assignments AS a ON sub.assignment_id = a.id
    JOIN 
        course_offerings AS co ON a.course_offering_id = co.id
    JOIN 
        courses AS c ON co.course_id = c.id
    WHERE 
        sub.id = ? AND sub.student_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $submission_id, $studentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // This stops students from viewing submissions that aren't theirs.
    die("Submission not found or you do not have permission to view it.");
}
$submission = $result->fetch_assoc();
$stmt->close();

?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_student.php" ?>

<main class="content">
    <div class="container-fluid p-0">

        <h1 class="h3 mb-3">Submission Details</h1>

        <div class="row">
            <!-- Left Column for Submission Details -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($submission['assignment_title']); ?></h5>
                         <h6 class="card-subtitle text-muted mt-1">
                            For Course: <?php echo htmlspecialchars($submission['course_name']); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <strong>Original Assignment Description:</strong>
                            <p class="mt-2 text-muted"><?php echo nl2br(htmlspecialchars($submission['assignment_description'])); ?></p>
                        </div>
                        <hr>
                        <div class="mb-4">
                            <strong>Your Submission Content:</strong>
                            <?php if (!empty($submission['submission_content'])): ?>
                                <p class="mt-2">
                                    <?php echo nl2br(htmlspecialchars($submission['submission_content'])); ?>
                                </p>
                            <?php else: ?>
                                <p class="mt-2 text-muted"><em>No text content was submitted.</em></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($submission['file_path']): ?>
                        <hr>
                        <div class="mb-3">
                            <strong>Submitted File:</strong><br>
                            <a href="uploads/assignments/<?php echo htmlspecialchars($submission['file_path']); ?>" class="btn btn-outline-primary mt-2" download>
                                <i class="align-middle" data-feather="download"></i> Download File
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column for Status and Grade -->
            <div class="col-lg-4">
                <div class="card">
                     <div class="card-header">
                        <h5 class="card-title mb-0">Status & Grade</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Due Date:
                                <span><?php echo date("M j, Y", strtotime($submission['due_date'])); ?></span>
                            </li>
                             <li class="list-group-item d-flex justify-content-between align-items-center">
                                Submitted On:
                                <span><?php echo date("M j, Y, g:i a", strtotime($submission['submission_date'])); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Status:
                                <?php
                                    if ($submission['status'] == 'Graded') {
                                        echo '<span class="badge bg-success">Graded</span>';
                                    } elseif ($submission['status'] == 'Submitted') {
                                        echo '<span class="badge bg-info">Submitted</span>';
                                    } elseif ($submission['status'] == 'Late') {
                                        echo '<span class="badge bg-warning text-dark">Submitted (Late)</span>';
                                    }
                                ?>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Grade:
                                <strong><?php echo ($submission['status'] == 'Graded' && isset($submission['grade'])) ? htmlspecialchars($submission['grade']) : 'Not Graded Yet'; ?></strong>
                            </li>
                        </ul>
                    </div>
                    <?php if ($submission['status'] == 'Graded' && !empty($submission['feedback'])): ?>
                    <div class="card-header border-top">
                        <h5 class="card-title mb-0">Teacher's Feedback</h5>
                    </div>
                    <div class="card-body">
                         <p>
                           <?php echo nl2br(htmlspecialchars($submission['feedback'])); ?>
                         </p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="text-center">
                    <a href="student-my_assignments.php" class="btn btn-primary">Back to Assignments</a>
                </div>
            </div>
        </div>

    </div>
</main>

<?php 
$conn->close();
include "footer.php"; 
?>

</body>
</html>