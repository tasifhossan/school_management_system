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

$activity_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$message_type = '';
$activity = null;

if ($activity_id <= 0) {
    header("Location: school-operations-activities.php");
    exit();
}

// Fetch existing activity data
$stmt = $conn->prepare("SELECT title, description, activity_date FROM school_activities WHERE id = ?");
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $activity = $result->fetch_assoc();
} else {
    // No activity found, redirect
    header("Location: school-operations-activities.php");
    exit();
}
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $activity_date = trim($_POST['activity_date']);

    if (empty($title) || empty($activity_date)) {
        $message = "Error: Title and Activity Date are required fields.";
        $message_type = 'danger';
    } else {
        $sql = "UPDATE school_activities SET title = ?, description = ?, activity_date = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $title, $description, $activity_date, $activity_id);

        if ($stmt->execute()) {
            $_SESSION['status_message'] = "Activity updated successfully!";
            header("Location: school-operations-activities.php");
            exit();
        } else {
            $message = "Error: Could not update the activity. Please try again.";
            $message_type = 'danger';
            // Refresh activity data with submitted values in case of error
            $activity['title'] = $title;
            $activity['description'] = $description;
            $activity['activity_date'] = $activity_date;
        }
        $stmt->close();
    }
}
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Edit School Activity</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Activity Details</h5>
                        <h6 class="card-subtitle text-muted">Modify the details for this event or activity.</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        <form action="school-operations-edit-activity.php?id=<?php echo $activity_id; ?>" method="POST">
                            <div class="mb-3">
                                <label for="title" class="form-label">Activity Title</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($activity['title']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($activity['description']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="activity_date" class="form-label">Date of Activity</label>
                                <input type="date" class="form-control" id="activity_date" name="activity_date" value="<?php echo htmlspecialchars($activity['activity_date']); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="school-operations-activities.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
$conn->close();
include "footer.php";  ?>

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