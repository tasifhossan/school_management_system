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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $conn->prepare("INSERT INTO assignments (title, description, course_offering_id, teacher_id, due_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiis", $title, $description, $course_offering_id, $teacher_id, $due_date);

    $title = $_POST['title'];
    $description = $_POST['description'];
    $course_offering_id = $_POST['course_offering_id'];
    $teacher_id = $_POST['teacher_id'];
    $due_date = $_POST['due_date'];
    
    if ($stmt->execute()) {
        header("Location: academics-assignments.php");
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_ad.php" ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Add New Assignment</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form action="academics-add-assignment.php" method="post">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="course_offering_id" class="form-label">Course Offering</label>
                                <select class="form-select" id="course_offering_id" name="course_offering_id" required>
                                    <option selected disabled value="">Choose...</option>
                                    <?php
                                        $offerings_sql = "SELECT co.id, c.name as course_name, co.semester, co.section FROM course_offerings co JOIN courses c ON co.course_id = c.id";
                                        $offerings_result = $conn->query($offerings_sql);
                                        if ($offerings_result->num_rows > 0) {
                                            while($offering_row = $offerings_result->fetch_assoc()) {
                                                echo "<option value='" . $offering_row['id'] . "'>" . $offering_row['course_name'] . " - " . $offering_row['semester'] . " (Sec: " . $offering_row['section'] . ")</option>";
                                            }
                                        }
                                    ?>
                                </select>
                            </div>
                             <div class="mb-3">
                                <label for="teacher_id" class="form-label">Teacher</label>
                                <select class="form-select" id="teacher_id" name="teacher_id" required>
                                    <option selected disabled value="">Choose...</option>
                                    <?php
                                        $teachers_sql = "SELECT t.id, u.name FROM teachers t JOIN users u ON t.user_id = u.id";
                                        $teachers_result = $conn->query($teachers_sql);
                                        if ($teachers_result->num_rows > 0) {
                                            while($teacher_row = $teachers_result->fetch_assoc()) {
                                                echo "<option value='" . $teacher_row['id'] . "'>" . $teacher_row['name'] . "</option>";
                                            }
                                        }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date">
                            </div>
                            <button type="submit" class="btn btn-primary">Submit</button>
                            <a href="academics-assignments.php" class="btn btn-secondary">
                                <i data-feather="arrow-left" class="me-1" style="width:16px; height:16px;"></i> Back to Assignments
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php" ?>