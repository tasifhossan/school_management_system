<?php
session_start();
// db connection
include "../lib/connection.php";

// --- AUTHENTICATION & AUTHORIZATION ---
// Redirect if user is not logged in or is not a teacher.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

// --- INITIALIZATION ---
$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Teacher';
// $PhotoDir = 'uploads/teacher_photos/';
$defaultAvatar = 'img/avatars/avatar.jpg';
$PhotoDir = '';


$sql = "SELECT photo FROM teachers WHERE id = ?"; 
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


$upload_directory = "uploads/teacher_photos/";
$message = '';
$message_type = ''; // 'success' or 'error'
$errors = [];

// --- HANDLE FORM SUBMISSION (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize all text inputs to prevent XSS
    $name = htmlspecialchars(trim($_POST['name']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(trim($_POST['phone']));
    
    // Teacher-specific details
    $department = htmlspecialchars(trim($_POST['department']));
    $qualifications = htmlspecialchars(trim($_POST['qualifications']));
    $specialization = htmlspecialchars(trim($_POST['specialization']));
    $office_location = htmlspecialchars(trim($_POST['office_location']));
    $office_hours = htmlspecialchars(trim($_POST['office_hours']));
    $photo_path = $_POST['current_photo']; // The current photo path from the hidden input

    // --- Basic Validation ---
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";

    // --- FIX: Re-added Password Validation ---
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    if (!empty($new_password) && ($new_password !== $confirm_password)) {
        $errors[] = "Passwords do not match.";
    }

    // --- Profile Photo Upload Handling ---
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        if (!is_dir($upload_directory)) {
            mkdir($upload_directory, 0755, true); // Create directory if it doesn't exist
        }
        
        $file_info = pathinfo($_FILES['photo']['name']);
        $file_ext = strtolower($file_info['extension']);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (in_array($file_ext, $allowed_exts) && $_FILES['photo']['size'] <= $max_file_size) {
            // Generate a unique filename to avoid overwrites
            $new_filename = 'teacher_' . $userId . '_' . uniqid() . '.' . $file_ext;
            $destination = $upload_directory . $new_filename;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $photo_path = $destination; // Update photo path with the new file
            } else {
                $errors[] = "There was an error moving the uploaded file.";
            }
        } else {
            $errors[] = "Invalid file format or size. Allowed: JPG, PNG, GIF. Max size: 5MB.";
        }
    }

    // --- DATABASE UPDATE (only if validation passes) ---
    if (empty($errors)) {
        // Use a transaction to ensure both updates succeed or fail together
        $conn->begin_transaction();
        
        try {
            // --- FIX: Re-added conditional password update logic ---
            // 1. Update the 'users' table
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_user = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
                $stmt_user->bind_param("ssssi", $name, $email, $phone, $hashed_password, $userId);
            } else {
                $stmt_user = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt_user->bind_param("sssi", $name, $email, $phone, $userId);
            }
            $stmt_user->execute();
            $stmt_user->close();

            // 2. Update the 'teachers' table
            $stmt_teacher = $conn->prepare("UPDATE teachers SET department = ?, qualifications = ?, specialization = ?, office_location = ?, office_hours = ?, photo = ? WHERE user_id = ?");
            $stmt_teacher->bind_param("ssssssi", $department, $qualifications, $specialization, $office_location, $office_hours, $photo_path, $userId);
            $stmt_teacher->execute();
            $stmt_teacher->close();

            // If both queries were successful, commit the transaction
            $conn->commit();
            $message = "Profile updated successfully!";
            $message_type = 'success';

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback(); // Rollback on error
            $message = "Error updating profile: " . $exception->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "Please fix the validation errors.";
        $message_type = 'error';
    }
}

// --- Fetch Current Teacher Data for Form Display (Always happens) ---
$teacher_data = null;
$stmt_fetch = $conn->prepare(
    "SELECT u.name, u.email, u.phone, t.department, t.qualifications, t.specialization, t.office_location, t.office_hours, t.photo
     FROM users u
     LEFT JOIN teachers t ON u.id = t.user_id
     WHERE u.id = ?"
);

if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $userId);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
    } else {
        session_destroy();
        header("Location: login.php?error=datanotfound");
        exit();
    }
    $stmt_fetch->close();
}
$conn->close();
?>
<?php include "dashboard-top.php" ?>

<?php include "sidebar_teacher.php" ?>

            <main class="content">
                <div class="container-fluid p-0">
                    <h1 class="h3 mb-3">Edit My Profile</h1>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">My Details</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($message)): ?>
                                        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>">
                                            <?php  
                                                echo htmlspecialchars($message); 
                                                if(!empty($errors)) {
                                                    echo '<ul>';
                                                    foreach($errors as $error) {
                                                        echo '<li>' . htmlspecialchars($error) . '</li>';
                                                    }
                                                    echo '</ul>';
                                                }
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($teacher_data): ?>
                                    <form action="edit-my-profile.php" method="POST" enctype="multipart/form-data">
                                        <!-- Basic Info -->
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="name" class="form-label">Full Name</label>
                                                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($teacher_data['name']); ?>" required>
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="email" class="form-label">Email Address</label>
                                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($teacher_data['email']); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($teacher_data['phone'] ?? ''); ?>">
                                        </div>

                                        <!-- FIX: Re-added Password Fields -->
                                        <hr>
                                        <h5 class="card-title mt-4 mb-3">Change Password</h5>
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                                                <input type="password" id="password" name="password" class="form-control">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        <h5 class="card-title mt-4 mb-3">Professional Information</h5>

                                        <!-- Professional Info -->
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="department" class="form-label">Department</label>
                                                <input type="text" id="department" name="department" class="form-control" value="<?php echo htmlspecialchars($teacher_data['department'] ?? ''); ?>" disabled>
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="office_location" class="form-label">Office Location</label>
                                                <input type="text" id="office_location" name="office_location" class="form-control" value="<?php echo htmlspecialchars($teacher_data['office_location'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="qualifications" class="form-label">Qualifications</label>
                                            <textarea id="qualifications" name="qualifications" class="form-control" rows="1"><?php echo htmlspecialchars($teacher_data['qualifications'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="specialization" class="form-label">Specialization</label>
                                            <textarea id="specialization" name="specialization" class="form-control" rows="1"><?php echo htmlspecialchars($teacher_data['specialization'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="office_hours" class="form-label">Office Hours</label>
                                            <input type="text" id="office_hours" name="office_hours" class="form-control" value="<?php echo htmlspecialchars($teacher_data['office_hours'] ?? ''); ?>">
                                        </div>
                                        
                                        <hr>
                                        <h5 class="card-title mt-4 mb-3">Profile Photo</h5>
                                        
                                        <div class="mb-3">
                                            <label for="photo" class="form-label">Upload New Photo</label>
                                            <input type="file" id="photo" name="photo" class="form-control">
                                            <input type="hidden" name="current_photo" value="<?php echo htmlspecialchars($teacher_data['photo'] ?? ''); ?>">
                                            <small class="form-text text-muted">Leave blank to keep the current photo.</small>
                                            <?php if (!empty($teacher_data['photo']) && file_exists($teacher_data['photo'])): ?>
                                                <div class="mt-2">
                                                    <p>Current Photo:</p>
                                                    <img src="<?php echo htmlspecialchars($teacher_data['photo']); ?>" alt="Current Profile Photo" class="photo-preview">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        

                                        <!-- Submit Button -->
                                        <div class="text-center mt-3">
                                            <button type="submit" class="btn btn-primary">Update Profile</button>
                                            <a href="my_profile.php" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </form>
                                    <?php else: ?>
                                        <div class="alert alert-danger">Could not load profile data to edit.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <?php include "footer.php" ?>

    <style>
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
