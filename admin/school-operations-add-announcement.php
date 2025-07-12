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

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $created_by = $_SESSION['user_id']; // The creator is the logged-in admin
    $photo_path = null;

    // Basic validation
    if (empty($title) || empty($content)) {
        $message = "Error: Title and Content fields cannot be empty.";
        $message_type = 'danger';
    } else {
        // Handle file upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/announcements/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_tmp_name = $_FILES['photo']['tmp_name'];
            $file_name = basename($_FILES['photo']['name']);
            // Create a unique filename to prevent overwrites
            $unique_file_name = uniqid() . '-' . time() . '-' . preg_replace('/[^A-Za-z0-9\.\-]/', '_', $file_name);
            $destination = $upload_dir . $unique_file_name;

            if (move_uploaded_file($file_tmp_name, $destination)) {
                $photo_path = $unique_file_name;
            } else {
                $message = "Error: Failed to move uploaded photo.";
                $message_type = 'danger';
            }
        }

        // Proceed only if there were no upload errors
        if (empty($message)) {
            // Note the 'photo' column name from your schema
            $sql = "INSERT INTO announcements (title, photo, content, created_by) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $title, $photo_path, $content, $created_by);

            if ($stmt->execute()) {
                // Set a session message for the list page
                $_SESSION['status_message'] = "Announcement published successfully!";
                header("Location: school-operations-announcements.php");
                exit();
            } else {
                $message = "Error: Could not publish announcement. Please try again.";
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}

?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">

        <h1 class="h3 mb-3">Add New Announcement</h1>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <!-- <h5 class="card-title">Announcement Details</h5> -->
                        <h5 class="card-title">Fill out the form below to publish a new announcement for the entire school.</h5>
                        <!-- <h6 class="card-subtitle text-muted">Fill out the form below to publish a new announcement for the entire school.</h6> -->
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="content" class="form-label">Content</label>
                                <textarea class="form-control" id="content" name="content" rows="8" required></textarea>
                            </div>
                             <div class="mb-3">
                                <label for="photo" class="form-label">Featured Photo (Optional)</label>
                                <input class="form-control" type="file" id="photo" name="photo" accept="image/*">
                                <div class="form-text">Upload an image to be displayed with the announcement.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">Publish Announcement</button>
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
				
        /* Custom styles for result messages */
        .result {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
        }
        .result-success {
            color: #047857;
            background-color: #d1fae5;
        }
        .result-error {
            color: #b91c1c;
            background-color: #fee2e2;
        }
        /* Custom styles for table actions */
        .action-buttons a {
            margin-right: 0.5rem;
            text-decoration: none;
            padding: 0.375rem 0.75rem 0.375rem 0;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out;
        }
        .edit-button {
            color: #4f46e5; /* indigo-600 */
            margin-right: 0.5rem; /* mr-2 */
            padding: 0.5rem 0 0.5rem 0; /* p-2 */
            border-radius: 0.375rem; /* rounded-md */
            transition: all 150ms ease-in-out; /* transition duration-150 ease-in-out */
        }

        .edit-button:hover {
            color: #4338ca; /* indigo-700 or a darker shade for hover */
            background-color: #eef2ff; /* indigo-50 */
        }

        .delete-button {
            color: #ef4444; /* red-600 */
            padding: 0.5rem; /* p-2 */
            border-radius: 0.375rem; /* rounded-md */
            transition: all 150ms ease-in-out; /* transition duration-150 ease-in-out */
        }

        .delete-button:hover {
            color: #dc2626; /* red-700 or a darker shade for hover */
            background-color: #fef2f2; /* red-50 */
        }
	</style>

</body>

</html>