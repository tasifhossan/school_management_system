<?php
session_start();
include "../lib/connection.php";

// Redirect if not a logged-in student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
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

$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$submission_message = '';
$message_type = '';

// Check if assignment_id is valid
if ($assignment_id <= 0) {
    die("Invalid assignment ID.");
}

// Get student's primary ID
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

// Check if assignment has already been submitted by this student
$stmtCheck = $conn->prepare("SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
$stmtCheck->bind_param("ii", $assignment_id, $studentId);
$stmtCheck->execute();
if ($stmtCheck->get_result()->num_rows > 0) {
    // If already submitted, redirect to a view page or show a message.
    header("Location: student-my_assignments.php?message=already_submitted");
    exit();
}
$stmtCheck->close();


// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submission_content = trim($_POST['submission_content']);
    $file_path = null;
    $upload_dir = 'uploads/assignments/'; // Make sure this directory exists and is writable

    // Handle file upload
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] == UPLOAD_ERR_OK) {
        // Ensure upload directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_tmp_name = $_FILES['assignment_file']['tmp_name'];
        $file_name = basename($_FILES['assignment_file']['name']);
        // Create a unique filename to avoid overwrites
        $unique_file_name = uniqid() . '-' . time() . '-' . $file_name;
        $destination = $upload_dir . $unique_file_name;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            $file_path = $unique_file_name;
        } else {
            $submission_message = "Error: Failed to move uploaded file.";
            $message_type = 'danger';
        }
    }

    if (empty($submission_message)) { // Proceed only if file upload (if any) was successful
        // Fetch due date to determine status
        $stmtDue = $conn->prepare("SELECT due_date FROM assignments WHERE id = ?");
        $stmtDue->bind_param("i", $assignment_id);
        $stmtDue->execute();
        $assignmentDetails = $stmtDue->get_result()->fetch_assoc();
        $stmtDue->close();
        
        $status = (strtotime($assignmentDetails['due_date']) < time()) ? 'Late' : 'Submitted';

        // Insert into database
        $sqlInsert = "INSERT INTO assignment_submissions (assignment_id, student_id, submission_content, file_path, status) VALUES (?, ?, ?, ?, ?)";
        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->bind_param("iisss", $assignment_id, $studentId, $submission_content, $file_path, $status);

        if ($stmtInsert->execute()) {
            $submission_message = "Assignment submitted successfully!";
            $message_type = 'success';
            // Redirect after a short delay
            header("refresh:3;url=student-my_assignments.php");
        } else {
            $submission_message = "Error: Could not save submission. Please try again.";
            $message_type = 'danger';
        }
        $stmtInsert->close();
    }
}

// Fetch assignment details to display on the page
$stmtDetails = $conn->prepare("SELECT a.title, a.description, a.due_date, c.name as course_name FROM assignments a JOIN course_offerings co ON a.course_offering_id = co.id JOIN courses c ON co.course_id = c.id WHERE a.id = ?");
$stmtDetails->bind_param("i", $assignment_id);
$stmtDetails->execute();
$resultDetails = $stmtDetails->get_result();
if ($resultDetails->num_rows === 0) {
    die("Assignment not found.");
}
$assignment = $resultDetails->fetch_assoc();
$stmtDetails->close();
?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_student.php" ?>

<main class="content">
    <div class="container-fluid p-0">

        <h1 class="h3 mb-3">Submit Assignment</h1>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><?php echo htmlspecialchars($assignment['title']); ?></h5>
                        <h6 class="card-subtitle text-muted">
                            For Course: <?php echo htmlspecialchars($assignment['course_name']); ?> | 
                            Due Date: <?php echo date("F j, Y, g:i a", strtotime($assignment['due_date'])); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($submission_message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                                <?php echo $submission_message; ?>
                            </div>
                            <?php if ($message_type == 'success'): ?>
                                <p>You will be redirected back to your assignments list shortly.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="mb-3">
                                <strong>Description:</strong>
                                <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                            </div>
                            <hr>
                            <form action="student-submit_assignment.php?assignment_id=<?php echo $assignment_id; ?>" method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="submission_content" class="form-label">Your Submission</label>
                                    <textarea class="form-control" id="submission_content" name="submission_content" rows="5" placeholder="Type your response here or add comments about your file submission."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="assignment_file" class="form-label">Upload File (Optional)</label>
                                    <input class="form-control" type="file" id="assignment_file" name="assignment_file">
                                    <div class="form-text">You can upload documents, images, or zip files.</div>
                                </div>
                                <button type="submit" class="btn btn-primary">Submit Assignment</button>
                                <a href="student-my_assignments.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        <?php endif; ?>
                    </div>
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
