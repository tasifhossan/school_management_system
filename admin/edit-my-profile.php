<?php
session_start();
// db connection
include "../lib/connection.php";

// --- AUTHENTICATION & AUTHORIZATION ---
// Redirect if user is not logged in or is not a student.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// --- INITIALIZATION ---
$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Student';
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

$upload_directory = "uploads/student_photos/";
$message = '';
$message_type = ''; // 'success' or 'error'
$errors = [];

// --- HANDLE FORM SUBMISSION (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize all text inputs to prevent XSS
    $name = htmlspecialchars(trim($_POST['name']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $phone_user = htmlspecialchars(trim($_POST['phone_user']));
    
    // Student-specific details
    $address = htmlspecialchars(trim($_POST['address']));
    $phone_student = htmlspecialchars(trim($_POST['phone_student']));
    $guardian_name = htmlspecialchars(trim($_POST['guardian_name']));
    // The current photo path from the hidden input
    $photo_path = $_POST['current_photo']; 

    // --- Basic Validation ---
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";

    // --- Password Validation ---
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
            $new_filename = 'student_' . $userId . '_' . uniqid() . '.' . $file_ext;
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
            // 1. Update the 'users' table
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_user = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
                $stmt_user->bind_param("ssssi", $name, $email, $phone_user, $hashed_password, $userId);
            } else {
                $stmt_user = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt_user->bind_param("sssi", $name, $email, $phone_user, $userId);
            }
            $stmt_user->execute();
            $stmt_user->close();

            // 2. Update the 'students' table
            $stmt_student = $conn->prepare("UPDATE students SET address = ?, phone = ?, guardian_name = ?, photo = ? WHERE user_id = ?");
            $stmt_student->bind_param("ssssi", $address, $phone_student, $guardian_name, $photo_path, $userId);
            $stmt_student->execute();
            $stmt_student->close();

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

// --- Fetch Current Student Data for Form Display (Always happens) ---
$student_data = null;
// IMPORTANT: Use prepared statements for fetching data as well.
$stmt_fetch = $conn->prepare(
    "SELECT u.name, u.email, u.phone AS user_phone, s.address, s.phone, s.guardian_name, s.guardian_contact, s.photo
     FROM users u
     LEFT JOIN students s ON u.id = s.user_id
     WHERE u.id = ?"
);

if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $userId);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();

    if ($result->num_rows > 0) {
        $student_data = $result->fetch_assoc();
    } else {
        // This case should ideally not happen for a logged-in student.
        // Destroy session and redirect to avoid loop or errors.
        session_destroy();
        header("Location: login.php?error=datanotfound");
        exit();
    }
    $stmt_fetch->close();
}
$conn->close();
?>
<?php include "dashboard-top.php" ?>

		<?php include "sidebar_student.php" ?>

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
                                    <?php if (!empty($result_message)): ?>
                                        <div class="alert <?php echo $result_class; ?>">
                                            <?php 
                                                echo $result_message; 
                                                if(!empty($errors)) {
                                                    echo '<ul>';
                                                    foreach($errors as $error) {
                                                        echo '<li>' . $error . '</li>';
                                                    }
                                                    echo '</ul>';
                                                }
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($student_data): ?>
                                    <form action="edit-my-profile.php" method="POST" enctype="multipart/form-data">
                                        <!-- Basic Info -->
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="name" class="form-label">Full Name</label>
                                                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($student_data['name']); ?>" required>
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="email" class="form-label">Email Address</label>
                                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student_data['email']); ?>" required>
                                            </div>
                                        </div>

                                        <!-- Password Fields -->
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
                                        <h5 class="card-title mt-4 mb-3">Contact & Address</h5>

                                        <!-- Contact Info -->
                                        <div class="row">
                                             <div class="mb-3 col-md-6">
                                                <label for="phone_user" class="form-label">My General Phone</label>
                                                <input type="tel" id="phone_user" name="phone_user" class="form-control" value="<?php echo htmlspecialchars($student_data['user_phone'] ?? ''); ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="phone_student" class="form-label">My Student Phone</label>
                                                <input type="tel" id="phone_student" name="phone_student" class="form-control" value="<?php echo htmlspecialchars($student_data['phone'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($student_data['address'] ?? ''); ?></textarea>
                                        </div>

                                        <hr>
                                        <h5 class="card-title mt-4 mb-3">Guardian Information</h5>
                                        
                                         <!-- Guardian Info -->
                                        <div class="row">
                                             <div class="mb-3 col-md-6">
                                                <label for="guardian_name" class="form-label">Guardian Name</label>
                                                <input type="text" id="guardian_name" name="guardian_name" class="form-control" value="<?php echo htmlspecialchars($student_data['guardian_name'] ?? ''); ?>">
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="guardian_contact" class="form-label">Guardian Contact</label>
                                                <input type="tel" id="guardian_contact" name="guardian_contact" class="form-control" value="<?php echo htmlspecialchars($student_data['guardian_contact'] ?? ''); ?>" disabled>
                                            </div>
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