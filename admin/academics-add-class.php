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

/**
 * Function to get a list of teachers (ID and Name) for use in dropdown menus (e.g., as advisors).
 *
 * @param mysqli $conn The database connection object.
 * @return array An associative array where keys are teacher IDs and values are teacher names.
 */
function get_teachers_for_dropdown($conn) {
    $teachers = [];
    $sql = "SELECT t.id, u.name
            FROM teachers t
            JOIN users u ON t.user_id = u.id
            ORDER BY u.name ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $teachers[$row['id']] = $row['name'];
        }
        $result->free();
    } else {
        error_log("Error fetching teachers for dropdown: " . $conn->error);
    }
    return $teachers;
}

// Fetch teachers for the advisor dropdown (done before closing connection)
$advisors = get_teachers_for_dropdown($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $conn->prepare("INSERT INTO classes (name, section, teacher_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $name, $section, $teacher_id);

    $name = $_POST['name'];
    $section = $_POST['section'];
    $teacher_id = $_POST['teacher_id'];
    
    if ($stmt->execute()) {
        header("Location: academics-classes.php");
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
        <h1 class="h3 mb-3">Add New Class</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body"> 
                        <form action="academics-add-class.php" method="post">
                            <div class="mb-3">
                                <label for="name" class="form-label">Class Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="section" class="form-label">Section</label>
                                <input type="text" class="form-control" id="section" name="section">
                            </div>
                            <div class="mb-3">
                                <label for="teacher_id" class="form-label">Select Teacher</label>
                                <select class="form-select" id="teacher_id" name="teacher_id" required>
                                    <option selected disabled value="">Choose a teacher...</option>
                                    
                                    <?php
                                    // Check if the query returned any results
                                    if ($teacher_result && mysqli_num_rows($teacher_result) > 0) {
                                        // Loop through each row of the result set
                                        while ($teacher = mysqli_fetch_assoc($teacher_result)) {
                                            // Create an option for each teacher
                                            // The value is the user's ID, and the text displays their name and employee ID
                                            echo '<option value="' . htmlspecialchars($teacher['id']) . '">';
                                            echo htmlspecialchars($teacher['name']) . ' (ID: ' . htmlspecialchars($teacher['employee_id']) . ')';
                                            echo '</option>';
                                        }
                                    } else {
                                        // Optional: Show a message if no teachers are found
                                        echo '<option disabled>No teachers found</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit</button>
                            <a href="academics-classes.php" class="btn btn-secondary">
                                <i data-feather="arrow-left" class="me-1" style="width:16px; height:16px;"></i> Back to Classes
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php" ?>