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

// Define the path for photo uploads.
define('UPLOAD_PATH', 'uploads/announcement_photos/');

// Initialize variables
$announcement_id = null;
$title = "";
$content = "";
$current_photo_path = "";
$creator_name = "";
$errors = [];
$result_message = '';
$result_class = '';

// Check if an ID is passed in the URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $announcement_id = (int)$_GET['id'];
} else {
    // If no ID, redirect back to the main page
    header("Location: school-operations-announcements.php");
    exit;
}

// --- Handle POST request for updating the announcement ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_announcement'])) {
    // Sanitize and retrieve POST data
    $title = htmlspecialchars(trim($_POST['title'] ?? ''));
    $content = htmlspecialchars(trim($_POST['content'] ?? ''));
    $current_photo_path = $_POST['current_photo_path'] ?? ''; // Get the path of the old photo

    // --- Validation ---
    if (empty($title)) {
        $errors['title'] = "Title is required.";
    }
    if (empty($content)) {
        $errors['content'] = "Content cannot be empty.";
    }

    $new_photo_path = $current_photo_path; // Assume the photo is not changed initially

    // --- New Photo Upload Handling ---
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0777, true);
        }

        $photo_name = basename($_FILES["photo"]["name"]);
        $target_file = UPLOAD_PATH . time() . '_' . $photo_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["photo"]["tmp_name"]);
        if ($check === false) {
            $errors['photo'] = "File is not an image.";
        }
        if ($_FILES["photo"]["size"] > 5000000) {
            $errors['photo'] = "Sorry, your file is too large (max 5MB).";
        }
        if (!in_array($imageFileType, ["jpg", "png", "jpeg", "gif"])) {
            $errors['photo'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        }

        if (!isset($errors['photo'])) {
            if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                // If upload is successful, set the new path
                $new_photo_path = $target_file;
                // And delete the old photo if it exists
                if (!empty($current_photo_path) && file_exists($current_photo_path)) {
                    unlink($current_photo_path);
                }
            } else {
                $errors['photo'] = "Sorry, there was an error uploading your file.";
            }
        }
    }
    
    // If there are no validation errors, update the database
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, photo_path = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("sssi", $title, $content, $new_photo_path, $announcement_id);
            if ($stmt->execute()) {
                // Success: set message and redirect
                session_start();
                $_SESSION['update_success_message'] = "Announcement updated successfully!";
                header("Location: school-operations-announcements.php");
                exit;
            } else {
                $result_message = "Error: Could not update the announcement. " . $stmt->error;
                $result_class = "result-error";
            }
            $stmt->close();
        } else {
             $result_message = "Error: Could not prepare the statement. " . $conn->error;
             $result_class = "result-error";
        }
    } else {
        $result_message = "Please correct the errors below.";
        $result_class = "result-error";
    }

} else {
    // --- Handle GET request to fetch existing data ---
    $stmt = $conn->prepare("SELECT a.title, a.content, a.photo_path, u.name AS creator_name FROM announcements a JOIN users u ON a.created_by = u.id WHERE a.id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $announcement_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $announcement = $result->fetch_assoc();
            $title = $announcement['title'];
            $content = $announcement['content'];
            $current_photo_path = $announcement['photo_path'];
            $creator_name = $announcement['creator_name'];
        } else {
            // No announcement found with that ID
            $result_message = "Error: Announcement not found.";
            $result_class = "result-error";
        }
        $stmt->close();
    } else {
        $result_message = "Error: Could not prepare the statement. " . $conn->error;
        $result_class = "result-error";
    }
}
$conn->close();
?>
<?php include "dashboard-top.php" ?>

		<?php include "sidebar_ad.php" ?>

			<main class="content">
				<div class="container-fluid p-0">
					<!-- Edit Announcement -->
					<h1 class="h3 mb-3">Edit Announcement</h1>

					<div class="row">
						<div class="col-12 col-lg-12 d-flex">
							<div class="card flex-fill">
								<div class="card-header">
									<h5 class="card-title mb-0">Editing Announcement #<?php echo $announcement_id; ?></h5>
								</div>
								<?php if (!empty($result_message)): ?>
                                    <div class="<?php echo $result_class; ?>">
                                        <?php echo $result_message; ?>
                                        <?php if ($result_message === "Error: Announcement not found."): ?>
                                            <a href="school-operations-announcements.php" class="font-bold underline">Return to Announcements</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($result_message !== "Error: Announcement not found."): ?>
                                <form action="#" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="current_photo_path" value="<?php echo htmlspecialchars($current_photo_path); ?>">
                                    <div class="card-body space-y-6">
                    
                                        <div class="mb-3">
                                            <label for="title" class="form-label">Title</label>
                                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" required>
                                            <?php if (isset($errors['title'])): ?><div class="text-danger mt-1"><?php echo $errors['title']; ?></div><?php endif; ?>
                                        </div>

                                        <div class="mb-3">
                                            <label for="content" class="form-label">Content</label>
                                            <textarea id="content" name="content" rows="8" class="form-control <?php echo isset($errors['content']) ? 'is-invalid' : ''; ?>" required><?php echo htmlspecialchars($content); ?></textarea>
                                            <?php if (isset($errors['content'])): ?><div class="text-danger mt-1"><?php echo $errors['content']; ?></div><?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="photo" class="form-label">Change Photo (Optional)</label>
                                            <input type="file" id="photo" name="photo" class="form-control <?php echo isset($errors['photo']) ? 'is-invalid' : ''; ?>">
                                            <div class="form-text">Upload a new image to replace the current one. Leave empty to keep the existing photo.</div>
                                            <?php if (isset($errors['photo'])): ?><div class="text-danger mt-1"><?php echo $errors['photo']; ?></div><?php endif; ?>
                                        </div>

                                        <?php if (!empty($current_photo_path) && file_exists($current_photo_path)): ?>
                                        <div class="mb-4">
                                            <label class="form-label">Current Photo</label>
                                            <div>
                                               <img src="<?php echo htmlspecialchars($current_photo_path); ?>" alt="Current Announcement Photo" style="max-height: 150px; border-radius: 0.375rem;">
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Created By</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($creator_name); ?>" readonly disabled>
                                        </div>
                                        
                                        <div class="text-center">
                                            <button type="submit" name="update_announcement" class="btn btn-success d-inline-flex align-items-center">
                                                <i data-feather="check-circle" class="me-2"></i>
                                                Save Changes
                                            </button>
                                            <a href="school-operations-announcements.php" class="btn btn-secondary">
                                                <i data-feather="arrow-left" class="me-1" style="width:16px; height:16px;"></i> Back to List
                                            </a>
                                        </div>
					                   </div>
                                </form>
                                <?php endif; ?>
							</div>
						</div>
					</div>

				</div>
			</main>

			<?php include "footer.php" ?>

	<style>
		/* --- Raw CSS for Result Message Area --- */
        .result {
            padding: 1rem; /* Equivalent to p-4 */
            margin-bottom: 1rem; /* Equivalent to mb-4 */
            font-size: 0.875rem; /* Equivalent to text-sm */
            border-radius: 0.5rem; /* Equivalent to rounded-lg */
        }

        .result-success {
            color: #047857; /* Dark green text - text-green-700 */
            background-color: #d1fae5; /* Light green background - bg-green-100 */
        }

        .result-error {
            color: #b91c1c; /* Dark red text - text-red-700 */
            background-color: #fee2e2; /* Light red background - bg-red-100 */
        }
        .photo-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 0.25rem;
            margin-top: 0.5rem;
            border: 1px solid #e5e7eb;
            object-fit: cover;
        }
    </style>

</body>

</html>