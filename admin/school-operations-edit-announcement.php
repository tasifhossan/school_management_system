<?php 
session_start();
// db connection
include "../lib/connection.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Redirect to login page
    exit();
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';

$announcement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$message_type = '';
$announcement = null;

if ($announcement_id <= 0) {
    header("Location: school-operations-announcements.php");
    exit();
}

// --- Fetch existing announcement data ---
// CORRECTED: Changed 'photo' to 'photo_path'
$stmt = $conn->prepare("SELECT title, content, photo_path FROM announcements WHERE id = ?");
$stmt->bind_param("i", $announcement_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $announcement = $result->fetch_assoc();
} else {
    // No announcement found, redirect
    header("Location: school-operations-announcements.php");
    exit();
}
$stmt->close();

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $current_photo = $_POST['current_photo'];
    $photo_path = $current_photo; // Default to the current photo

    if (empty($title) || empty($content)) {
        $message = "Error: Title and Content fields cannot be empty.";
        $message_type = 'danger';
    } else {
        // --- Handle Optional File Upload ---
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/announcements/';
            // Delete the old photo if it exists
            if (!empty($current_photo) && file_exists($upload_dir . $current_photo)) {
                unlink($upload_dir . $current_photo);
            }

            $file_tmp_name = $_FILES['photo']['tmp_name'];
            $file_name = basename($_FILES['photo']['name']);
            $unique_file_name = uniqid() . '-' . time() . '-' . preg_replace('/[^A-Za-z0-9\.\-]/', '_', $file_name);
            $destination = $upload_dir . $unique_file_name;

            if (move_uploaded_file($file_tmp_name, $destination)) {
                $photo_path = $unique_file_name; // Set the new photo path
            } else {
                $message = "Error: Failed to move uploaded photo.";
                $message_type = 'danger';
            }
        }

        // Proceed with database update if no upload errors
        if (empty($message)) {
            // CORRECTED: Changed 'photo' to 'photo_path'
            $sql = "UPDATE announcements SET title = ?, content = ?, photo_path = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $title, $content, $photo_path, $announcement_id);

            if ($stmt->execute()) {
                $_SESSION['status_message'] = "Announcement updated successfully!";
                header("Location: school-operations-announcements.php");
                exit();
            } else {
                $message = "Error: Could not update the announcement.";
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
    // Refresh data with submitted values in case of error
    $announcement['title'] = $title;
    $announcement['content'] = $content;
}

?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Edit Announcement</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Announcement Details</h5>
                        <h6 class="card-subtitle text-muted">Modify the details for this announcement.</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        <form action="school-operations-edit-announcement.php?id=<?php echo $announcement_id; ?>" method="POST" enctype="multipart/form-data">
                            <!-- Hidden field to keep track of the current photo for deletion -->
                            <!-- CORRECTED: Changed ['photo'] to ['photo_path'] -->
                            <input type="hidden" name="current_photo" value="<?php echo htmlspecialchars($announcement['photo_path']); ?>">
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($announcement['title']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="content" class="form-label">Content</label>
                                <textarea class="form-control" id="content" name="content" rows="8" required><?php echo htmlspecialchars($announcement['content']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="photo" class="form-label">Change Featured Photo (Optional)</label>
                                <!-- CORRECTED: Changed ['photo'] to ['photo_path'] -->
                                <?php if (!empty($announcement['photo_path'])): ?>
                                    <div class="mb-2">
                                        <!-- CORRECTED: Changed ['photo'] to ['photo_path'] -->
                                        <img src="uploads/announcements/<?php echo htmlspecialchars($announcement['photo_path']); ?>" alt="Current Photo" style="max-width: 200px; max-height: 200px; border-radius: 0.25rem;">
                                        <p class="form-text mt-1">Current photo. Uploading a new file will replace this one.</p>
                                    </div>
                                <?php endif; ?>
                                <input class="form-control" type="file" id="photo" name="photo" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="school-operations-announcements.php" class="btn btn-secondary">Cancel</a>
                        </form>
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