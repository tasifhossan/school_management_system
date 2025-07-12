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

// --- Handle Form Submission for Creating a New Program ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $photo_filename = null;
    $errors = [];

    // --- Validation ---
    if (empty($name)) { $errors[] = "Program Name is required."; }
    if (empty($start_date)) { $errors[] = "Start Date is required."; }
    if (empty($end_date)) { $errors[] = "End Date is required."; }
    if (!empty($start_date) && !empty($end_date) && strtotime($end_date) < strtotime($start_date)) {
        $errors[] = "End Date cannot be before the Start Date.";
    }

    // --- Handle Optional File Upload ---
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/programs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_tmp_name = $_FILES['photo']['tmp_name'];
        $file_name = basename($_FILES['photo']['name']);
        $unique_file_name = uniqid() . '-' . time() . '-' . preg_replace('/[^A-Za-z0-9\.\-]/', '_', $file_name);
        $destination = $upload_dir . $unique_file_name;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            $photo_filename = $unique_file_name;
        } else {
            $errors[] = "Error: Failed to move uploaded photo.";
        }
    }

    // --- Insert into Database ---
    if (empty($errors)) {
        $sql = "INSERT INTO school_programs (name, description, start_date, end_date, photo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $name, $description, $start_date, $end_date, $photo_filename);

        if ($stmt->execute()) {
            $_SESSION['status_message'] = "New school program added successfully!";
            header("Location: school-operations-programs.php");
            exit();
        } else {
            $message = "Error: Could not add the program. Please try again.";
            $message_type = 'danger';
        }
        $stmt->close();
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'danger';
    }
}
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">

        <h1 class="h3 mb-3">Add New School Program</h1>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Program Details</h5>
                        <h6 class="card-subtitle text-muted">Fill out the form below to create a new school program.</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="name" class="form-label">Program Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5"></textarea>
                            </div>
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                            </div>
                             <div class="mb-3">
                                <label for="photo" class="form-label">Program Photo (Optional)</label>
                                <input class="form-control" type="file" id="photo" name="photo" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-primary">Add Program</button>
                            <a href="school-operations-programs.php" class="btn btn-secondary">Cancel</a>
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