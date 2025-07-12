<?php  
session_start();
// db connection
include "../lib/connection.php";

// Check if the user is logged in and is a student.
// You might have a $_SESSION['role'] check here as well if you want to be more specific.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php"); // Redirect to login page
    exit();
}


$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'student';
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

$activity_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($activity_id <= 0) {
    die("Invalid activity ID.");
}

// Fetch activity details along with the creator's name
$sql = "SELECT 
            sa.title, sa.description, sa.activity_date,
            u.name AS created_by_name
        FROM school_activities sa
        JOIN users u ON sa.created_by = u.id
        WHERE sa.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Activity not found.");
}
$activity = $result->fetch_assoc();
$stmt->close();
$conn->close();

?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_student.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <div class="mb-3">
             <a href="student-school_activities.php" class="btn btn-outline-primary">&larr; Back to All Activities</a>
        </div>

        <h1 class="h3 mb-3"><?php echo htmlspecialchars($activity['title']); ?></h1>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title text-muted">
                            Organized by: <?php echo htmlspecialchars($activity['created_by_name']); ?>
                        </h5>
                        <h6 class="card-subtitle text-muted mt-1">
                            Date of Event: <?php echo date("F j, Y", strtotime($activity['activity_date'])); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="activity-description">
                            <p class="lead">
                                <?php echo nl2br(htmlspecialchars($activity['description'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<style>
    .activity-description {
        font-size: 1.1rem;
        line-height: 1.7;
    }
</style>

<?php include "footer.php"; ?>

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