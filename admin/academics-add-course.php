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
    $stmt = $conn->prepare("INSERT INTO courses (name, course_code, description, credits) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssd", $name, $course_code, $description, $credits);

    $name = $_POST['name'];
    $course_code = $_POST['course_code'];
    $description = $_POST['description'];
    $credits = $_POST['credits'];
    
    if ($stmt->execute()) {
        header("Location: academics-courses.php");
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
        <h1 class="h3 mb-3">Add New Course</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form action="academics-add-course.php" method="post">
                            <div class="mb-3">
                                <label for="name" class="form-label">Course Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="course_code" class="form-label">Course Code</label>
                                <input type="text" class="form-control" id="course_code" name="course_code" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="credits" class="form-label">Credits</label>
                                <input type="number" step="0.1" class="form-control" id="credits" name="credits">
                            </div>
                            <button type="submit" class="btn btn-primary">Submit</button>
                            <a href="academics-courses.php" class="btn btn-secondary">
                                <i data-feather="arrow-left" class="me-1" style="width:16px; height:16px;"></i> Back to Courses
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php" ?>