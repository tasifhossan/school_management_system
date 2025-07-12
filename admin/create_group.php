<?php

session_start();
// db connection
include "../lib/connection.php";

// Check if the user is logged in and is a student.
// You might have a $_SESSION['role'] check here as well if you want to be more specific.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Redirect to login page
    exit();
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';


$error_message = '';

// --- Fetch ALL Course Offerings that have a teacher assigned ---
$offerings = [];
$offerings_sql = "SELECT co.id, c.name, co.semester, co.section, u.name as teacher_name
                  FROM course_offerings co
                  JOIN courses c ON co.course_id = c.id
                  JOIN teachers t ON co.teacher_id = t.id
                  JOIN users u ON t.user_id = u.id
                  WHERE co.teacher_id IS NOT NULL
                  ORDER BY c.name, co.semester";
$offerings_result = $conn->query($offerings_sql);
while ($row = $offerings_result->fetch_assoc()) {
    $offerings[] = $row;
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_name = trim($_POST['group_name']);
    $course_offering_id = (int)$_POST['course_offering_id'];

    if (!empty($group_name) && $course_offering_id > 0) {
        
        // 1. Find the teacher_id associated with the course offering
        $teacher_stmt = $conn->prepare("SELECT teacher_id FROM course_offerings WHERE id = ?");
        $teacher_stmt->bind_param("i", $course_offering_id);
        $teacher_stmt->execute();
        $teacher_result = $teacher_stmt->get_result();
        $teacher_row = $teacher_result->fetch_assoc();
        $teacher_id = $teacher_row ? $teacher_row['teacher_id'] : null;
        $teacher_stmt->close();

        if ($teacher_id) {
            // 2. Create the message_groups record
            $stmt = $conn->prepare("INSERT INTO message_groups (name, course_offering_id, teacher_id) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $group_name, $course_offering_id, $teacher_id);
            $stmt->execute();
            $new_group_id = $stmt->insert_id;
            $stmt->close();

            // 3. Get all students enrolled in the course offering
            $stmt = $conn->prepare("SELECT student_id FROM student_course_enrollments WHERE course_offering_id = ?");
            $stmt->bind_param("i", $course_offering_id);
            $stmt->execute();
            $students_result = $stmt->get_result();
            
            // 4. Add each student to the group_members table
            $member_stmt = $conn->prepare("INSERT INTO group_members (group_id, student_id) VALUES (?, ?)");
            while ($student_row = $students_result->fetch_assoc()) {
                $member_stmt->bind_param("ii", $new_group_id, $student_row['student_id']);
                $member_stmt->execute();
            }
            $member_stmt->close();
            $stmt->close();

            // Redirect to the group messaging page
            header("Location: messages_group.php?group_id=" . $new_group_id);
            exit();
        } else {
            $error_message = "This course offering does not have an assigned teacher. A group cannot be created.";
        }
    } else {
        $error_message = "Please fill out all fields.";
    }
}
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Create a New Group</h1>
        <div class="row">
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Group Details</h5>
                    </div>
                    <div class="card-body">
                        <?php if(!empty($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Group Name</label>
                                <input type="text" name="group_name" class="form-control" placeholder="e.g., MATH101 - Fall 2025 Help" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Link to Course Offering</label>
                                <select name="course_offering_id" class="form-select" required>
                                    <option value="" disabled selected>Select a course offering...</option>
                                    <?php foreach ($offerings as $offering): ?>
                                        <option value="<?php echo $offering['id']; ?>">
                                            <?php echo htmlspecialchars($offering['name'] . ' - ' . $offering['semester'] . ' (Sec ' . $offering['section'] . ') - Managed by ' . $offering['teacher_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">The teacher assigned to the course offering will automatically become the group manager.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Group and Add Students</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php" ?>

<style>
    .result { padding: 1rem; border-radius: 0.5rem; border: 1px solid transparent; }
    .result-success { color: #0f5132; background-color: #d1e7dd; border-color: #badbcc; }
    .result-error { color: #842029; background-color: #f8d7da; border-color: #f5c2c7; }
    .action-buttons a { margin-right: 0.25rem; }
    th a { text-decoration: none; color: inherit; }
    th a:hover { color: #0d6efd; }
</style>

</body>
</html>