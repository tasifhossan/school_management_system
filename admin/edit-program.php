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

// Initialize variables
$program = null;
$message = '';
$message_type = '';
$program_id = 0;

// --- Check for Program ID from GET request ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $program_id = (int)$_GET['id'];
} else {
    // If no ID, redirect or show an error
    header("Location: school-operations-programs.php");
    exit();
}

// --- Handle Form Submission for Updating a Program ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_program'])) {
    // Sanitize and retrieve form data
    $program_id = (int)$_POST['program_id'];
    $name = htmlspecialchars(trim($_POST['name']));
    $description = htmlspecialchars(trim($_POST['description']));
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $existing_photo = trim($_POST['existing_photo']);
    $photo_filename = $existing_photo;

    // --- Validation ---
    $errors = [];
    if (empty($name)) {
        $errors[] = "Program Name is required.";
    }
    if (empty($start_date)) {
        $errors[] = "Start Date is required.";
    }
    if (empty($end_date)) {
        $errors[] = "End Date is required.";
    }
    if (!empty($start_date) && !empty($end_date) && strtotime($end_date) < strtotime($start_date)) {
        $errors[] = "End Date cannot be before the Start Date.";
    }

    // --- Handle Optional File Upload for Update ---
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($file_info, $_FILES['photo']['tmp_name']);
        finfo_close($file_info);

        if (in_array($file_type, $allowed_types)) {
            // New photo is uploaded, process it
            $new_photo_filename = uniqid() . '-' . basename($_FILES['photo']['name']);
            $target_path = $upload_dir . $new_photo_filename;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
                // Delete the old photo if it exists
                if (!empty($existing_photo) && file_exists($upload_dir . $existing_photo)) {
                    unlink($upload_dir . $existing_photo);
                }
                $photo_filename = $new_photo_filename; // Set the new filename for DB update
            } else {
                $errors[] = "Failed to move the new uploaded file.";
            }
        } else {
            $errors[] = "Invalid new file type. Only JPG, PNG, and GIF are allowed.";
        }
    }

    if (empty($errors)) {
        try {
            $sql = "UPDATE school_programs SET name = ?, description = ?, start_date = ?, end_date = ?, photo = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Failed to prepare the SQL statement.");
            }
            $stmt->bind_param("sssssi", $name, $description, $start_date, $end_date, $photo_filename, $program_id);

            if ($stmt->execute()) {
                $message = "Program updated successfully!";
                $message_type = 'success';
            } else {
                throw new Exception("Failed to execute the SQL statement.");
            }
            $stmt->close();
        } catch (Exception $e) {
            $message = "Error: Could not update the program. " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'error';
    }
}

// --- Fetch the program data for the form ---
if ($program_id > 0) {
    $sql_fetch = "SELECT id, name, description, start_date, end_date, photo FROM school_programs WHERE id = ?";
    $stmt_fetch = $conn->prepare($sql_fetch);
    $stmt_fetch->bind_param("i", $program_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();

    if ($result->num_rows === 1) {
        $program = $result->fetch_assoc();
    } else {
        $message = "Program not found.";
        $message_type = 'error';
    }
    $stmt_fetch->close();
}

$conn->close();
?>
<?php include "dashboard-top.php" ?>

		<?php include "sidebar_ad.php" ?>

			<main class="content">
				<div class="container-fluid p-0">
					<!-- Edit Announcement -->
					<h1 class="h3 mb-3">Edit School Program</h1>
                    <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo ($message_type == 'success' ? 'success' : 'danger'); ?>" role="alert">
                        <?php echo $message; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($program): ?>
					<div class="row">
						<div class="col-12 col-lg-12 d-flex">
							<div class="card flex-fill">
								<div class="card-header">
									<h5 class="card-title mb-0">Modify Program Details</h5>
								</div>
								
                                <form action="#" method="POST" enctype="multipart/form-data">
                                    <div class="card-body space-y-6">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label for="name" class="form-label">Program Name</label>
                                                <input type="text" id="name" name="name" required class="form-control" value="<?php echo htmlspecialchars($program['name']); ?>">
                                            </div>
                                            <div class="col-12">
                                                <label for="description" class="form-label">Description</label>
                                                <textarea id="description" name="description" rows="4" class="form-control"><?php echo htmlspecialchars($program['description']); ?></textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="start_date" class="form-label">Start Date</label>
                                                <input type="date" id="start_date" name="start_date" required class="form-control" value="<?php echo htmlspecialchars($program['start_date']); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="end_date" class="form-label">End Date</label>
                                                <input type="date" id="end_date" name="end_date" required class="form-control" value="<?php echo htmlspecialchars($program['end_date']); ?>">
                                            </div>
                                            
                                            <div class="col-12">
                                                <label class="form-label">Current Photo</label>
                                                <div>
                                                    <?php if (!empty($program['photo'])): ?>
                                                        <img src="uploads/<?php echo htmlspecialchars($program['photo']); ?>" alt="Current Photo" class="img-thumbnail mb-2" style="max-width: 150px;">
                                                    <?php else: ?>
                                                        <p class="text-muted">No photo currently uploaded.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <label for="photo" class="form-label">Upload New Photo (Optional)</label>
                                                <input type="file" id="photo" name="photo" class="form-control" accept="image/png, image/jpeg, image/gif">
                                                <div class="form-text">Uploading a new photo will replace the old one.</div>
                                            </div>
                                        </div>
                                            
                                            <div class="text-center mt-6">
                                                <button type="submit" name="update_program" class="btn btn-success px-4">
                                                    Save Changes
                                                </button>
                                                <a href="school-operations-programs.php" class="btn btn-secondary"><i data-feather="arrow-left" class="me-1" style="width:16px; height:16px;"></i> Back to Programs List</a>
                                            </div>
                                    </div>       
                                </form>
                                
							</div>
						</div>
					</div>
                    <?php else: ?>
                        <div class="alert alert-warning">The requested program could not be found.</div>
                    <?php endif; ?>

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