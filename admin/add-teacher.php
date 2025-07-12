<?php

session_start();
// db connection
include "../lib/connection.php";

// Check if the user is logged in and is an admin.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Redirect to login page
    exit();
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';

// Define the directory for teacher photo uploads.
$upload_directory = "uploads/teacher_photos/";

// Initialize variables
$form_data = [];
$errors = [];
$result_message = '';
$result_class = '';

/**
 * Sanitizes input data to prevent XSS.
 * @param string $data The input string to sanitize.
 * @return string The sanitized string.
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Hashes a password using the default algorithm.
 * @param string $password The plain text password.
 * @return string The hashed password.
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Checks if an email already exists in the 'users' table.
 * @param mysqli $conn The database connection object.
 * @param string $email The email to check.
 * @return bool True if the email exists, false otherwise.
 */
function email_exists($conn, $email) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * Checks if an employee ID already exists in the 'teachers' table.
 * @param mysqli $conn The database connection object.
 * @param string $employee_id The employee ID to check.
 * @return bool True if the employee ID exists, false otherwise.
 */
function employee_id_exists($conn, $employee_id) {
    $stmt = $conn->prepare("SELECT id FROM teachers WHERE employee_id = ?");
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * Validates form inputs for teacher registration.
 * @param array $data An associative array of input data ($_POST).
 * @param array $files An associative array of uploaded file data ($_FILES).
 * @param mysqli $conn The database connection object.
 * @return array An array of error messages.
 */
function validate_teacher_input($data, $files, $conn) {
    $errors = [];

    // User Details Validation
    if (empty($data['name'])) $errors['name'] = "Name is required.";
    if (empty($data['email'])) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    } elseif ($conn && email_exists($conn, $data['email'])) {
        $errors['email'] = "This email is already registered.";
    }

    // Password Validation
    if (empty($data['password'])) $errors['password'] = "Password is required.";
    elseif (strlen($data['password']) < 8) $errors['password'] = "Password must be at least 8 characters long.";
    if (empty($data['confirm_password'])) $errors['confirm_password'] = "Confirm Password is required.";
    elseif ($data['password'] !== $data['confirm_password']) $errors['confirm_password'] = "Passwords do not match.";

    // Teacher Profile Validation
    if (!empty($data['employee_id'])) {
        if ($conn && employee_id_exists($conn, $data['employee_id'])) {
            $errors['employee_id'] = "This Employee ID is already registered.";
        }
        $required_fields = ['hire_date', 'department', 'qualifications', 'specialization', 'office_location', 'office_hours'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " is required for a complete profile.";
            }
        }
    }
    
    // Photo Validation
    if (isset($files['photo']) && $files['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($files['photo']['tmp_name']);
        if (!in_array($file_type, $allowed_types)) {
            $errors['photo'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        }
        if ($files['photo']['size'] > 5 * 1024 * 1024) { // 5 MB
            $errors['photo'] = "File size exceeds the 5MB limit.";
        }
    }

    return $errors;
}


/**
 * Creates a new teacher and handles photo upload.
 * @param mysqli $conn The database connection.
 * @param array $data Associative array of sanitized form data.
 * @param string|null $photo_path The path to the uploaded photo.
 * @return bool True on success, false on failure.
 */
function create_teacher($conn, $data, $photo_path) {
    $conn->begin_transaction();

    try {
        // 1. Insert into users table
        $stmt_user = $conn->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)");
        $password_hashed = hash_password($data['password']);
        $role = 'teacher';
        $phone = !empty($data['phone_user']) ? $data['phone_user'] : NULL;
        $stmt_user->bind_param("sssss", $data['name'], $data['email'], $password_hashed, $role, $phone);
        if (!$stmt_user->execute()) throw new Exception("User creation failed: " . $stmt_user->error);
        $user_id = $conn->insert_id;
        $stmt_user->close();

        // 2. Insert into teachers table if employee_id is provided
        if (!empty($data['employee_id'])) {
            $stmt_teacher = $conn->prepare(
                "INSERT INTO teachers (user_id, employee_id, hire_date, department, qualifications, specialization, office_location, office_hours, photo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $photo_to_db = $photo_path ?: NULL;
            $stmt_teacher->bind_param("issssssss", $user_id, $data['employee_id'], $data['hire_date'], $data['department'], $data['qualifications'], $data['specialization'], $data['office_location'], $data['office_hours'], $photo_to_db);
            if (!$stmt_teacher->execute()) throw new Exception("Teacher profile creation failed: " . $stmt_teacher->error);
            $stmt_teacher->close();
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Transaction failed: " . $e->getMessage());
        return false;
    }
}

// --- Main script logic to handle form submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($conn) {
        foreach ($_POST as $key => $value) {
            $form_data[$key] = ($key !== 'password' && $key !== 'confirm_password') ? sanitize_input($value) : $value;
        }
        
        $errors = validate_teacher_input($form_data, $_FILES, $conn);
        
        $photo_path = null;
        if (empty($errors) && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($upload_directory)) mkdir($upload_directory, 0755, true);
            $unique_filename = uniqid('teacher_', true) . '.' . pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $target_file = $upload_directory . $unique_filename;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                $photo_path = $target_file;
            } else {
                $errors['photo'] = "Failed to move uploaded file.";
            }
        }

        if (empty($errors)) {
            if (create_teacher($conn, $form_data, $photo_path)) {
                $_SESSION['status_message'] = "Teacher registered successfully!";
                $_SESSION['status_class'] = "result-success";
                header("Location: manage-teachers.php");
                exit();
            } else {
                $result_message = "Error: Could not register teacher. Please try again.";
                $result_class = "result-error";
            }
        } else {
            $result_message = "Please correct the following errors:";
            $result_class = "result-error";
        }
    } else {
        $result_message = "Error: Database connection failed.";
        $result_class = "result-error";
    }
}

$conn->close();
?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_ad.php" ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Register New Teacher</h1>

        <div class="row">
            <div class="col-12">
                <div class="card flex-fill">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Teacher Registration Details</h5>
                    </div>
                     <!-- Result Message Area -->
                     <?php
                    if (!empty($result_message)) {
                        echo "<div class=\"result {$result_class} m-3\">";
                        echo $result_message;
                        if (!empty($errors)) {
                            echo "<ul>";
                            foreach ($errors as $error_msg) {
                                echo "<li>" . htmlspecialchars($error_msg) . "</li>";
                            }
                            echo "</ul>";
                        }
                        echo "</div>";
                    }
                    ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                        <div class="card-body">
                            <div class="row g-3">
                                <!-- User Account Details -->
                                <h6 class="text-primary">User Account Details</h6>
                                <div class="col-12">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" id="name" name="name" class="form-control" placeholder="Enter teacher's full name" value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" id="email" name="email" class="form-control" placeholder="Enter teacher's email address" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="phone_user" class="form-label">User Phone (Optional)</label>
                                    <input type="tel" id="phone_user" name="phone_user" class="form-control" placeholder="e.g., 01234567890" value="<?php echo htmlspecialchars($form_data['phone_user'] ?? ''); ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter a strong password" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
                                </div>
                                
                                <hr class="my-4">

                                <!-- Teacher Profile Details -->
                                <h6 class="text-primary">Teacher Profile Details <small class="text-muted">(Optional)</small></h6>
                                <p class="text-muted small mt-0">Providing an Employee ID will require all other profile fields to be filled out.</p>

                                <div class="col-12">
                                    <label for="photo" class="form-label">Teacher Photo</label>
                                    <input type="file" id="photo" name="photo" class="form-control" accept="image/png, image/jpeg, image/gif">
                                    <small class="text-muted mt-1 block">Optional. Max file size: 5MB.</small>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="employee_id" class="form-label">Employee ID</label>
                                    <input type="text" id="employee_id" name="employee_id" class="form-control" placeholder="Enter employee ID" value="<?php echo htmlspecialchars($form_data['employee_id'] ?? ''); ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" id="department" name="department" class="form-control" placeholder="e.g., Science, Math" value="<?php echo htmlspecialchars($form_data['department'] ?? ''); ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="hire_date" class="form-label">Hire Date</label>
                                    <input type="date" id="hire_date" name="hire_date" class="form-control" value="<?php echo htmlspecialchars($form_data['hire_date'] ?? ''); ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="specialization" class="form-label">Specialization</label>
                                    <input type="text" id="specialization" name="specialization" class="form-control" placeholder="e.g., High School Math, AP Calculus" value="<?php echo htmlspecialchars($form_data['specialization'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <label for="qualifications" class="form-label">Qualifications</label>
                                    <textarea id="qualifications" name="qualifications" class="form-control" rows="2" placeholder="e.g., Master's in Education, PhD in Physics"><?php echo htmlspecialchars($form_data['qualifications'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="office_location" class="form-label">Office Location</label>
                                    <input type="text" id="office_location" name="office_location" class="form-control" placeholder="e.g., Building A, Room 205" value="<?php echo htmlspecialchars($form_data['office_location'] ?? ''); ?>">
                                </div>
                                 <div class="col-12 col-md-6">
                                    <label for="office_hours" class="form-label">Office Hours</label>
                                    <textarea id="office_hours" name="office_hours" class="form-control" rows="1" placeholder="e.g., Mon-Wed 10:00-11:00 AM"><?php echo htmlspecialchars($form_data['office_hours'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <!-- Submit Button -->
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary">Register Teacher</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php" ?>
<style>
    .result {
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 0.5rem;
        border: 1px solid transparent;
    }
    .result-success {
        color: #0f5132;
        background-color: #d1e7dd;
        border-color: #badbcc;
    }
    .result-error {
        color: #842029;
        background-color: #f8d7da;
        border-color: #f5c2c7;
    }
    .result ul {
        margin-top: 0.5rem;
        margin-bottom: 0;
        padding-left: 1.5rem;
    }
</style>
</body>
</html>