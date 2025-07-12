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


$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $activity_date = trim($_POST['activity_date']);
    $created_by = $_SESSION['user_id']; // Logged-in admin is the creator

    if (empty($title) || empty($activity_date)) {
        $message = "Error: Title and Activity Date are required fields.";
        $message_type = 'danger';
    } else {
        $sql = "INSERT INTO school_activities (title, description, activity_date, created_by) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $title, $description, $activity_date, $created_by);

        if ($stmt->execute()) {
            $_SESSION['status_message'] = "New activity created successfully!";
            header("Location: school-operations-activities.php");
            exit();
        } else {
            $message = "Error: Could not create the activity. Please try again.";
            $message_type = 'danger';
        }
        $stmt->close();
    }
}
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Add New School Activity</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Activity Details</h5>
                        <h6 class="card-subtitle text-muted">Fill out the form to add a new event or activity.</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                            <div class="mb-3">
                                <label for="title" class="form-label">Activity Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="activity_date" class="form-label">Date of Activity</label>
                                <input type="date" class="form-control" id="activity_date" name="activity_date" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Activity</button>
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
include "footer.php";   ?>

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