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

// Define the directory for student photo uploads.
$upload_directory = "uploads/student_photos/";

// Initialize variables
$form_data = [];
$errors = [];
$result_message = '';
$result_class = '';

// --- PHP Functions ---

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function email_exists($conn, $email) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function roll_number_exists($conn, $roll_number) {
    $stmt = $conn->prepare("SELECT id FROM students WHERE roll_number = ?");
    $stmt->bind_param("s", $roll_number);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function get_teachers_for_dropdown($conn) {
    $teachers = [];
    $sql = "SELECT t.id, u.name FROM teachers t JOIN users u ON t.user_id = u.id ORDER BY u.name ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $teachers[$row['id']] = $row['name'];
        }
        $result->free();
    }
    return $teachers;
}

function validate_student_input($data, $files, $conn) {
    $errors = [];
    // User validation
    if (empty($data['name'])) $errors['name'] = "Name is required.";
    if (empty($data['email'])) $errors['email'] = "Email is required.";
    elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = "Invalid email format.";
    elseif ($conn && email_exists($conn, $data['email'])) $errors['email'] = "This email is already registered.";

    // Password validation
    if (empty($data['password'])) $errors['password'] = "Password is required.";
    elseif (strlen($data['password']) < 8) $errors['password'] = "Password must be at least 8 characters long.";
    if ($data['password'] !== $data['confirm_password']) $errors['confirm_password'] = "Passwords do not match.";

    // Student profile validation (if roll_number is entered)
    if (!empty($data['roll_number'])) {
        if ($conn && roll_number_exists($conn, $data['roll_number'])) $errors['roll_number'] = "This Roll Number is already registered.";
        // These fields are required if a full profile is being created
        $required_fields = ['date_of_birth', 'gender', 'address', 'guardian_name', 'guardian_contact', 'enrollment_date', 'advisor_id'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " is required for a complete profile.";
            }
        }
    }
    
    // Photo validation
    if (isset($files['photo']) && $files['photo']['error'] === UPLOAD_ERR_OK) {
        if (!in_array(mime_content_type($files['photo']['tmp_name']), ['image/jpeg', 'image/png', 'image/gif'])) $errors['photo'] = "Invalid file type. Only JPG, PNG, GIF allowed.";
        if ($files['photo']['size'] > 5 * 1024 * 1024) $errors['photo'] = "File size exceeds the 5MB limit.";
    }
    return $errors;
}

function create_student($conn, $data, $photo_path) {
    $conn->begin_transaction();
    try {
        // Insert into users table
        $stmt_user = $conn->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)");
        $password_hashed = hash_password($data['password']);
        $role = 'student';
        $phone = !empty($data['phone_user']) ? $data['phone_user'] : NULL;
        $stmt_user->bind_param("sssss", $data['name'], $data['email'], $password_hashed, $role, $phone);
        if (!$stmt_user->execute()) throw new Exception("User creation failed.");
        $user_id = $conn->insert_id;
        $stmt_user->close();

        // Insert into students table if roll_number is provided
        if (!empty($data['roll_number'])) {
            $stmt_student = $conn->prepare("INSERT INTO students (user_id, roll_number, date_of_birth, gender, address, phone, guardian_name, guardian_contact, enrollment_date, advisor_id, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $student_phone = !empty($data['phone_student']) ? $data['phone_student'] : NULL;
            $photo_to_db = $photo_path ?: NULL;
            $stmt_student->bind_param("issssssssss", $user_id, $data['roll_number'], $data['date_of_birth'], $data['gender'], $data['address'], $student_phone, $data['guardian_name'], $data['guardian_contact'], $data['enrollment_date'], $data['advisor_id'], $photo_to_db);
            if (!$stmt_student->execute()) throw new Exception("Student profile creation failed.");
            $stmt_student->close();
        }
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Transaction failed: " . $e->getMessage());
        return false;
    }
}

// --- Form Submission Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($conn) {
        // Sanitize POST data
        foreach ($_POST as $key => $value) {
            $form_data[$key] = ($key !== 'password' && $key !== 'confirm_password') ? sanitize_input($value) : $value;
        }
        
        $errors = validate_student_input($form_data, $_FILES, $conn);
        
        $photo_path = null;
        if (empty($errors) && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($upload_directory)) mkdir($upload_directory, 0755, true);
            $unique_filename = uniqid('student_', true) . '.' . pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $target_file = $upload_directory . $unique_filename;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                $photo_path = $target_file;
            } else {
                $errors['photo'] = "Failed to move uploaded file.";
            }
        }

        if (empty($errors)) {
            if (create_student($conn, $form_data, $photo_path)) {
                $_SESSION['status_message'] = "Student registered successfully!";
                header("Location: manage-students.php");
                exit();
            } else {
                $result_message = "Error: Could not register student. Please try again.";
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

// Fetch advisors for the dropdown
$advisors = get_teachers_for_dropdown($conn);
$conn->close();
?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_ad.php" ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Register New Student</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Student Registration Form</h5>
                    </div>
                    <!-- Result Message Area -->
                    <?php
                    if (!empty($result_message)) {
                        echo "<div class=\"result {$result_class} m-3\">";
                        echo htmlspecialchars($result_message);
                        if (!empty($errors)) {
                            echo "<ul>";
                            foreach ($errors as $error_msg) echo "<li>" . htmlspecialchars($error_msg) . "</li>";
                            echo "</ul>";
                        }
                        echo "</div>";
                    }
                    ?>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                            <div class="row g-3">
                                <!-- User Account Details -->
                                <h6 class="text-primary">User Account Details</h6>
                                <div class="col-12"><label for="name" class="form-label">Full Name</label><input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required></div>
                                <div class="col-md-6"><label for="email" class="form-label">Email Address</label><input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required></div>
                                <div class="col-md-6"><label for="phone_user" class="form-label">User Phone (Optional)</label><input type="tel" id="phone_user" name="phone_user" class="form-control" value="<?php echo htmlspecialchars($form_data['phone_user'] ?? ''); ?>"></div>
                                <div class="col-md-6"><label for="password" class="form-label">Password</label><input type="password" id="password" name="password" class="form-control" required></div>
                                <div class="col-md-6"><label for="confirm_password" class="form-label">Confirm Password</label><input type="password" id="confirm_password" name="confirm_password" class="form-control" required></div>

                                <hr class="my-4">

                                <!-- Student Profile Details -->
                                <h6 class="text-primary">Student Profile Details <small class="text-muted">(Optional)</small></h6>
                                <p class="text-muted small mt-0">Providing a Roll Number requires all other profile fields.</p>
                                <div class="col-12"><label for="photo" class="form-label">Student Photo</label><input type="file" id="photo" name="photo" class="form-control" accept="image/*"><small class="text-muted">Max 5MB.</small></div>
                                <div class="col-md-6"><label for="roll_number" class="form-label">Roll Number</label><input type="text" id="roll_number" name="roll_number" class="form-control" value="<?php echo htmlspecialchars($form_data['roll_number'] ?? ''); ?>"></div>
                                <div class="col-md-6"><label for="phone_student" class="form-label">Student Phone (Optional)</label><input type="tel" id="phone_student" name="phone_student" class="form-control" value="<?php echo htmlspecialchars($form_data['phone_student'] ?? ''); ?>"></div>
                                <div class="col-md-6"><label for="date_of_birth" class="form-label">Date of Birth</label><input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($form_data['date_of_birth'] ?? ''); ?>"></div>
                                <div class="col-md-6"><label for="gender" class="form-label">Gender</label><select id="gender" name="gender" class="form-select"><option value="" selected disabled>Select Gender</option><option value="Male" <?php echo (($form_data['gender'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option><option value="Female" <?php echo (($form_data['gender'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option><option value="Other" <?php echo (($form_data['gender'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option></select></div>
                                <div class="col-12"><label for="address" class="form-label">Address</label><textarea id="address" name="address" class="form-control" rows="2"><?php echo htmlspecialchars($form_data['address'] ?? ''); ?></textarea></div>

                                <hr class="my-4">
                                
                                <!-- Guardian & Enrollment Details -->
                                <h6 class="text-primary">Guardian & Enrollment</h6>
                                <div class="col-md-6"><label for="guardian_name" class="form-label">Guardian Name</label><input type="text" id="guardian_name" name="guardian_name" class="form-control" value="<?php echo htmlspecialchars($form_data['guardian_name'] ?? ''); ?>"></div>
                                <div class="col-md-6"><label for="guardian_contact" class="form-label">Guardian Contact</label><input type="tel" id="guardian_contact" name="guardian_contact" class="form-control" value="<?php echo htmlspecialchars($form_data['guardian_contact'] ?? ''); ?>"></div>
                                <div class="col-md-6"><label for="enrollment_date" class="form-label">Enrollment Date</label><input type="date" id="enrollment_date" name="enrollment_date" class="form-control" value="<?php echo htmlspecialchars($form_data['enrollment_date'] ?? ''); ?>"></div>
                                <div class="col-md-6"><label for="advisor_id" class="form-label">Academic Advisor</label><select id="advisor_id" name="advisor_id" class="form-select"><option value="" selected disabled>Select an Advisor</option><?php foreach ($advisors as $id => $name) : ?><option value="<?php echo htmlspecialchars($id); ?>" <?php echo (($form_data['advisor_id'] ?? '') == $id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option><?php endforeach; ?></select></div>
                            </div>
                            <!-- Submit Button -->
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary">Register Student</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php" ?>
<style>
    .result { padding: 1rem; margin-bottom: 1rem; border-radius: 0.5rem; border: 1px solid transparent; }
    .result-success { color: #0f5132; background-color: #d1e7dd; border-color: #badbcc; }
    .result-error { color: #842029; background-color: #f8d7da; border-color: #f5c2c7; }
    .result ul { margin-top: 0.5rem; margin-bottom: 0; padding-left: 1.5rem; }
</style>
</body>
</html>