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

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM course_offerings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $conn->prepare("UPDATE course_offerings SET course_id = ?, teacher_id = ?, semester = ?, section = ?, start_date = ?, end_date = ?, meeting_time = ?, location = ?, capacity = ? WHERE id = ?");
    $stmt->bind_param("iissssssii", $course_id, $teacher_id, $semester, $section, $start_date, $end_date, $meeting_time, $location, $capacity, $id);

    $course_id = $_POST['course_id'];
    $teacher_id = $_POST['teacher_id'];
    $semester = $_POST['semester'];
    $section = $_POST['section'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $meeting_time = $_POST['meeting_time'];
    $location = $_POST['location'];
    $capacity = $_POST['capacity'];
    
    if ($stmt->execute()) {
        header("Location: academics-course-offerings.php");
    } else {
        echo "Error updating record: " . $stmt->error;
    }
    $stmt->close();
}
?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_ad.php" ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Edit Course Offering</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form action="academics-edit-course-offering.php?id=<?php echo $id; ?>" method="post">
                            <div class="mb-3">
                                <label for="course_id" class="form-label">Course</label>
                                <select class="form-select" id="course_id" name="course_id" required>
                                    <option disabled>Choose...</option>
                                    <?php
                                        $courses_sql = "SELECT id, name FROM courses";
                                        $courses_result = $conn->query($courses_sql);
                                        if ($courses_result->num_rows > 0) {
                                            while($course_row = $courses_result->fetch_assoc()) {
                                                $selected = ($course_row['id'] == $row['course_id']) ? 'selected' : '';
                                                echo "<option value='" . $course_row['id'] . "' " . $selected . ">" . $course_row['name'] . "</option>";
                                            }
                                        }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="teacher_id" class="form-label">Teacher</label>
                                <select class="form-select" id="teacher_id" name="teacher_id">
                                    <option disabled>Choose...</option>
                                    <?php
                                        $teachers_sql = "SELECT id, user_id FROM teachers";
                                        $teachers_result = $conn->query($teachers_sql);
                                        if ($teachers_result->num_rows > 0) {
                                            while($teacher_row = $teachers_result->fetch_assoc()) {
                                                $user_sql = "SELECT name FROM users WHERE id = " . $teacher_row['user_id'];
                                                $user_result = $conn->query($user_sql);
                                                $user_row = $user_result->fetch_assoc();
                                                $selected = ($teacher_row['id'] == $row['teacher_id']) ? 'selected' : '';
                                                echo "<option value='" . $teacher_row['id'] . "' " . $selected . ">" . $user_row['name'] . "</option>";
                                            }
                                        }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="semester" class="form-label">Semester</label>
                                <input type="text" class="form-control" id="semester" name="semester" value="<?php echo $row['semester']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="section" class="form-label">Section</label>
                                <input type="text" class="form-control" id="section" name="section" value="<?php echo $row['section']; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $row['start_date']; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $row['end_date']; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="meeting_time" class="form-label">Meeting Time</label>
                                <input type="text" class="form-control" id="meeting_time" name="meeting_time" value="<?php echo $row['meeting_time']; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo $row['location']; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="capacity" class="form-label">Capacity</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" value="<?php echo $row['capacity']; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Update</button>
                            <a href="academics-course-offerings.php" class="btn btn-secondary">
                                <i data-feather="arrow-left" class="me-1" style="width:16px; height:16px;"></i> Back to Course Offerings
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php" ?>