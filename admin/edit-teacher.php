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


    // Initialize variables to hold form data and messages.
    $user_id = null;
    $teacher_id = null; // This will be null if only user data exists for a teacher role
    $name_value = $email_value = $phone_user_value = '';
    $employee_id_value = $hire_date_value = $department_value = '';
    $qualifications_value = $specialization_value = $office_location_value = $office_hours_value = '';
    $photo_path_value = ''; // New variable for photo path
    $photo_preview = 'img/avatars/avatar.jpg'; // Default preview image

    $result_message = '';
    $result_class = '';
    $errors = [];

    /**
     * Function to sanitize input data.
     */
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    /**
     * Function to hash the password.
     */
    function hash_password($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Function to check if an email already exists.
     */
    function email_exists($conn, $email, $exclude_user_id = null) {
        $sql = "SELECT id FROM users WHERE email = ?";
        if ($exclude_user_id !== null) {
            $sql .= " AND id != ?";
        }
        $stmt = $conn->prepare($sql);
        if ($exclude_user_id !== null) {
            $stmt->bind_param("si", $email, $exclude_user_id);
        } else {
            $stmt->bind_param("s", $email);
        }
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Function to check if an employee ID already exists.
     */
    function employee_id_exists($conn, $employee_id, $exclude_teacher_id = null) {
        $sql = "SELECT id FROM teachers WHERE employee_id = ?";
        if ($exclude_teacher_id !== null) {
            $sql .= " AND id != ?";
        }
        $stmt = $conn->prepare($sql);
        if ($exclude_teacher_id !== null) {
            $stmt->bind_param("si", $employee_id, $exclude_teacher_id);
        } else {
            $stmt->bind_param("s", $employee_id);
        }
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Function to validate form inputs for teacher update.
     */
    function validate_teacher_input_for_update($data, $conn, $user_id, $teacher_id) {
        $errors = [];

        // Basic User Details Validation
        if (empty($data['name'])) {
            $errors['name'] = "Name is required.";
        }
        if (empty($data['email'])) {
            $errors['email'] = "Email is required.";
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format.";
        } elseif (email_exists($conn, $data['email'], $user_id)) {
            $errors['email'] = "This email is already registered by another user.";
        }

        // Password Validation (only if password fields are not empty)
        if (!empty($data['password']) || !empty($data['confirm_password'])) {
            if (empty($data['password'])) {
                $errors['password'] = "Password is required if you are changing it.";
            } elseif (strlen($data['password']) < 8) {
                $errors['password'] = "Password must be at least 8 characters long.";
            }
            if (empty($data['confirm_password'])) {
                $errors['confirm_password'] = "Confirm Password is required if you are changing it.";
            } elseif ($data['password'] !== $data['confirm_password']) {
                $errors['confirm_password'] = "Passwords do not match.";
            }
        }

        // Check if creating/updating a full teacher profile
        $is_full_teacher_profile_attempt = !empty($data['employee_id']) || $teacher_id !== null;
        if ($is_full_teacher_profile_attempt) {
            if (empty($data['employee_id'])) {
                $errors['employee_id'] = "Employee ID is required for a complete teacher profile.";
            } elseif ($conn && employee_id_exists($conn, $data['employee_id'], $teacher_id)) {
                $errors['employee_id'] = "This Employee ID is already registered for another teacher.";
            }
            if (empty($data['hire_date'])) {
                $errors['hire_date'] = "Hire Date is required.";
            }
            if (empty($data['department'])) {
                $errors['department'] = "Department is required.";
            }
            if (empty($data['qualifications'])) {
                $errors['qualifications'] = "Qualifications are required.";
            }
            if (empty($data['specialization'])) {
                $errors['specialization'] = "Specialization is required.";
            }
            if (empty($data['office_location'])) {
                $errors['office_location'] = "Office Location is required.";
            }
            if (empty($data['office_hours'])) {
                $errors['office_hours'] = "Office Hours are required.";
            }
        }

        if (!empty($data['phone_user']) && !preg_match("/^[0-9]{10,15}$/", $data['phone_user'])) {
            $errors['phone_user'] = "Invalid phone number format (10-15 digits).";
        }
        return $errors;
    }

    /**
     * Function to retrieve teacher's data by user_id.
     * UPDATED: Now fetches the 'photo' column.
     */
    function get_teacher_data_by_user_id($conn, $user_id) {
        $sql = "SELECT
                    u.id AS user_id, u.name, u.email, u.phone AS user_phone,
                    t.id AS teacher_id, t.employee_id, t.hire_date, t.department,
                    t.qualifications, t.specialization, t.office_location, t.office_hours,
                    t.photo -- Fetches the photo path
                FROM users u
                LEFT JOIN teachers t ON u.id = t.user_id
                WHERE u.id = ? AND u.role = 'teacher'";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Failed to prepare get_teacher_data_by_user_id statement: " . $conn->error);
            return null;
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $teacher_data = $result->fetch_assoc();
        $stmt->close();
        return $teacher_data;
    }

    /**
     * Function to update user and teacher data.
     * UPDATED: Handles the 'photo' data.
     */
    function update_teacher($conn, $user_id, $existing_teacher_id, $user_data, $teacher_data) {
        $conn->begin_transaction();
        try {
            // 1. Update 'users' table
            $sql_user = "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?";
            if (isset($user_data['password_hashed'])) {
                $sql_user = "UPDATE users SET name = ?, email = ?, password = ?, phone = ? WHERE id = ?";
            }
            $stmt_user = $conn->prepare($sql_user);
            if ($stmt_user === false) {
                throw new Exception("Prepare user update failed: " . $conn->error);
            }

            $user_phone_param = !empty($user_data['phone_user']) ? $user_data['phone_user'] : NULL;
            if (isset($user_data['password_hashed'])) {
                $stmt_user->bind_param("ssssi", $user_data['name'], $user_data['email'], $user_data['password_hashed'], $user_phone_param, $user_id);
            } else {
                $stmt_user->bind_param("sssi", $user_data['name'], $user_data['email'], $user_phone_param, $user_id);
            }
            if (!$stmt_user->execute()) {
                throw new Exception("Execute user update failed: " . $stmt_user->error);
            }
            $stmt_user->close();

            // 2. Insert or Update 'teachers' table
            if ($existing_teacher_id === null) {
                if (!empty($teacher_data['employee_id'])) { // Only insert if teacher-specific data provided
                    $stmt_teacher = $conn->prepare(
                        "INSERT INTO teachers (user_id, employee_id, hire_date, department, qualifications, specialization, office_location, office_hours, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    if ($stmt_teacher === false) {
                        throw new Exception("Prepare teacher insert failed: " . $conn->error);
                    }
                    $stmt_teacher->bind_param("issssssss", $user_id, $teacher_data['employee_id'], $teacher_data['hire_date'], $teacher_data['department'], $teacher_data['qualifications'], $teacher_data['specialization'], $teacher_data['office_location'], $teacher_data['office_hours'], $teacher_data['photo']);
                    if (!$stmt_teacher->execute()) {
                        throw new Exception("Execute teacher insert failed: " . $stmt_teacher->error);
                    }
                    $stmt_teacher->close();
                }
            } else { // Update existing teacher profile
                $stmt_teacher = $conn->prepare(
                    "UPDATE teachers SET employee_id = ?, hire_date = ?, department = ?, qualifications = ?, specialization = ?, office_location = ?, office_hours = ?, photo = ? WHERE id = ?"
                );
                if ($stmt_teacher === false) {
                    throw new Exception("Prepare teacher update failed: " . $conn->error);
                }
                $stmt_teacher->bind_param("ssssssssi", $teacher_data['employee_id'], $teacher_data['hire_date'], $teacher_data['department'], $teacher_data['qualifications'], $teacher_data['specialization'], $teacher_data['office_location'], $teacher_data['office_hours'], $teacher_data['photo'], $existing_teacher_id);
                if (!$stmt_teacher->execute()) {
                    throw new Exception("Execute teacher update failed: " . $stmt_teacher->error);
                }
                $stmt_teacher->close();
            }

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Teacher update/creation failed: " . $e->getMessage());
            return false;
        }
    }

    // --- Main script logic ---

    // 1. Handle GET request to fetch existing data
    if ($_SERVER["REQUEST_METHOD"] == "GET") {
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $user_id = (int)$_GET['id'];
            $teacher_data_db = get_teacher_data_by_user_id($conn, $user_id);

            if ($teacher_data_db) {
                // Populate form variables
                $teacher_id = $teacher_data_db['teacher_id'];
                $name_value = htmlspecialchars($teacher_data_db['name']);
                $email_value = htmlspecialchars($teacher_data_db['email']);
                $phone_user_value = htmlspecialchars($teacher_data_db['user_phone']);
                $employee_id_value = htmlspecialchars($teacher_data_db['employee_id'] ?? '');
                $hire_date_value = htmlspecialchars($teacher_data_db['hire_date'] ?? '');
                $department_value = htmlspecialchars($teacher_data_db['department'] ?? '');
                $qualifications_value = htmlspecialchars($teacher_data_db['qualifications'] ?? '');
                $specialization_value = htmlspecialchars($teacher_data_db['specialization'] ?? '');
                $office_location_value = htmlspecialchars($teacher_data_db['office_location'] ?? '');
                $office_hours_value = htmlspecialchars($teacher_data_db['office_hours'] ?? '');
                
                // Populate photo path and preview
                $photo_path_value = htmlspecialchars($teacher_data_db['photo'] ?? '');
                if (!empty($photo_path_value) && file_exists($photo_path_value)) {
                    $photo_preview = $photo_path_value;
                }

            } else {
                $result_message = "Error: Teacher user not found.";
                $result_class = "result-error";
            }
        } else {
            $result_message = "Error: No teacher ID provided.";
            $result_class = "result-error";
        }
    }

    // 2. Handle POST request to update data
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $teacher_id = isset($_POST['teacher_id']) && !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        $current_photo_path = $_POST['current_photo_path'] ?? '';
        $new_photo_path = $current_photo_path; // Default to current path

        if ($conn && $user_id) {
            // Handle Photo Upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $photo_tmp_name = $_FILES['photo']['tmp_name'];
                $photo_name = $_FILES['photo']['name'];
                $photo_size = $_FILES['photo']['size'];

                // Validate file type and size
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_info = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($file_info, $photo_tmp_name);
                finfo_close($file_info);

                if (!in_array($mime_type, $allowed_types)) {
                    $errors['photo'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                } elseif ($photo_size > 2097152) { // 2MB limit
                    $errors['photo'] = "File size exceeds the 2MB limit.";
                } else {
                    // Create a unique filename and move the file
                    $upload_dir = '../uploads/teachers/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $file_extension = pathinfo($photo_name, PATHINFO_EXTENSION);
                    $unique_filename = uniqid('teacher_', true) . '.' . $file_extension;
                    $new_photo_path = $upload_dir . $unique_filename;

                    if (move_uploaded_file($photo_tmp_name, $new_photo_path)) {
                        // Successfully uploaded, delete old photo if it exists and is not the default
                        if (!empty($current_photo_path) && file_exists($current_photo_path)) {
                            unlink($current_photo_path);
                        }
                    } else {
                        $errors['photo'] = "Failed to upload the photo.";
                        $new_photo_path = $current_photo_path; // Revert to old path on failure
                    }
                }
            }

            // Sanitize all POST data
            $sanitized_data = [
                'name' => sanitize_input($_POST['name'] ?? ''),
                'email' => sanitize_input($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'confirm_password' => $_POST['confirm_password'] ?? '',
                'phone_user' => sanitize_input($_POST['phone_user'] ?? ''),
                'employee_id' => sanitize_input($_POST['employee_id'] ?? ''),
                'hire_date' => sanitize_input($_POST['hire_date'] ?? ''),
                'department' => sanitize_input($_POST['department'] ?? ''),
                'qualifications' => sanitize_input($_POST['qualifications'] ?? ''),
                'specialization' => sanitize_input($_POST['specialization'] ?? ''),
                'office_location' => sanitize_input($_POST['office_location'] ?? ''),
                'office_hours' => sanitize_input($_POST['office_hours'] ?? ''),
                'photo' => $new_photo_path
            ];

            // Validate the rest of the sanitized data
            $validation_errors = validate_teacher_input_for_update($sanitized_data, $conn, $user_id, $teacher_id);
            $errors = array_merge($errors, $validation_errors);

            if (empty($errors)) {
                $user_data_to_update = [
                    'name' => $sanitized_data['name'],
                    'email' => $sanitized_data['email'],
                    'phone_user' => $sanitized_data['phone_user']
                ];
                if (!empty($sanitized_data['password'])) {
                    $user_data_to_update['password_hashed'] = hash_password($sanitized_data['password']);
                }

                if (update_teacher($conn, $user_id, $teacher_id, $user_data_to_update, $sanitized_data)) {
                    $result_message = "Teacher data updated successfully!";
                    $result_class = "result-success";
                    // Re-fetch data to show the latest changes
                    $updated_teacher_data = get_teacher_data_by_user_id($conn, $user_id);
                    if ($updated_teacher_data) {
                        $teacher_id = $updated_teacher_data['teacher_id'];
                        $name_value = htmlspecialchars($updated_teacher_data['name']);
                        $email_value = htmlspecialchars($updated_teacher_data['email']);
                        $phone_user_value = htmlspecialchars($updated_teacher_data['user_phone']);
                        $employee_id_value = htmlspecialchars($updated_teacher_data['employee_id'] ?? '');
                        $hire_date_value = htmlspecialchars($updated_teacher_data['hire_date'] ?? '');
                        $department_value = htmlspecialchars($updated_teacher_data['department'] ?? '');
                        $qualifications_value = htmlspecialchars($updated_teacher_data['qualifications'] ?? '');
                        $specialization_value = htmlspecialchars($updated_teacher_data['specialization'] ?? '');
                        $office_location_value = htmlspecialchars($updated_teacher_data['office_location'] ?? '');
                        $office_hours_value = htmlspecialchars($updated_teacher_data['office_hours'] ?? '');
                        $photo_path_value = htmlspecialchars($updated_teacher_data['photo'] ?? '');
                         if (!empty($photo_path_value) && file_exists($photo_path_value)) {
                            $photo_preview = $photo_path_value;
                        } else {
                            $photo_preview = '../assets/img/avatars/avatar.jpg';
                        }
                    }
                } else {
                    $result_message = "Error: Could not update teacher data.";
                    $result_class = "result-error";
                }
            } else {
                $result_message = "Please correct the following errors:";
                $result_class = "result-error";
            }

            // Retain POSTed values on any error
            $name_value = $sanitized_data['name'];
            $email_value = $sanitized_data['email'];
            $phone_user_value = $sanitized_data['phone_user'];
            $employee_id_value = $sanitized_data['employee_id'];
            $hire_date_value = $sanitized_data['hire_date'];
            $department_value = $sanitized_data['department'];
            $qualifications_value = $sanitized_data['qualifications'];
            $specialization_value = $sanitized_data['specialization'];
            $office_location_value = $sanitized_data['office_location'];
            $office_hours_value = $sanitized_data['office_hours'];
            $photo_path_value = $new_photo_path;
            if (!empty($photo_path_value) && file_exists($photo_path_value)) {
                $photo_preview = $photo_path_value;
            }
        } else {
            $result_message = "Error: Invalid request.";
            $result_class = "result-error";
        }
    }

    // Close connection after all operations
    if ($conn) {
        $conn->close();
    }
    ?>
    <?php include "dashboard-top.php" ?>

    <?php include "sidebar_ad.php" ?>

    <main class="content">
        <div class="container-fluid p-0">
            <h1 class="h3 mb-3">Edit Teacher Information</h1>

            <div class="row">
                <div class="col-12 col-lg-12 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Teacher Details</h5>
                        </div>
                        <?php
                        if (!empty($result_message)) {
                            echo "<div class=\"result {$result_class}\">";
                            echo $result_message;
                            if (!empty($errors)) {
                                echo "<ul>";
                                foreach ($errors as $field => $error_msg) {
                                    echo "<li>" . htmlspecialchars($error_msg) . "</li>";
                                }
                                echo "</ul>";
                            }
                            echo "</div>";
                        }
                        ?>

                        <?php if ($user_id): // Only show form if a user is being edited ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $user_id; ?>" method="POST" enctype="multipart/form-data">
                            <!-- Hidden inputs to carry IDs and current photo path -->
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                            <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($teacher_id ?? ''); ?>">
                            <input type="hidden" name="current_photo_path" value="<?php echo htmlspecialchars($photo_path_value); ?>">

                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <!-- General and Teacher Profile Fields -->
                                        <div class="pb-3">
                                            <label for="name" class="form-label">Full Name</label>
                                            <input type="text" id="name" name="name" class="form-control" placeholder="Enter teacher's full name" value="<?php echo htmlspecialchars($name_value); ?>" required>
                                            <?php if (isset($errors['name'])): ?><p class="text-danger small"><?php echo $errors['name']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- Other fields like email, password etc. go here -->
                                        <div class="pb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" id="email" name="email" class="form-control" placeholder="Enter teacher's email address" value="<?php echo htmlspecialchars($email_value); ?>" required>
                                            <?php if (isset($errors['email'])): ?><p class="text-danger small"><?php echo $errors['email']; ?></p><?php endif; ?>
                                        </div>
                                        <div class="pb-3">
                                            <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                                            <input type="password" id="password" name="password" class="form-control" placeholder="Enter new password (optional)">
                                            <?php if (isset($errors['password'])): ?><p class="text-danger small"><?php echo $errors['password']; ?></p><?php endif; ?>
                                        </div>
                                        <div class="pb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm new password (optional)">
                                            <?php if (isset($errors['confirm_password'])): ?><p class="text-danger small"><?php echo $errors['confirm_password']; ?></p><?php endif; ?>
                                        </div>
                                        <div class="pb-3">
                                            <label for="phone_user" class="form-label">User Phone</label>
                                            <input type="tel" id="phone_user" name="phone_user" class="form-control" placeholder="e.g., 1234567890" value="<?php echo htmlspecialchars($phone_user_value); ?>">
                                            <?php if (isset($errors['phone_user'])): ?><p class="text-danger small"><?php echo $errors['phone_user']; ?></p><?php endif; ?>
                                        </div>
                                        
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <!-- Photo Upload and Preview -->
                                        <h6 class="text-md font-semibold text-gray-700 mb-2">Teacher Photo</h6>
                                        <img src="<?php echo $photo_preview; ?>" alt="Teacher Photo Preview" class="photo-preview mb-3" id="photoPreview">
                                        <div class="pb-3">
                                            <label for="photo" class="form-label">Update Photo</label>
                                            <input type="file" id="photo" name="photo" class="form-control" onchange="previewFile()">
                                            <small class="text-muted mt-1 d-block">Max 2MB. Allowed: JPG, PNG, GIF.</small>
                                            <?php if (isset($errors['photo'])): ?><p class="text-danger small"><?php echo $errors['photo']; ?></p><?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">
                                <h6 class="text-md font-semibold text-gray-700 mb-4">Teacher Profile Details</h6>

                                <div class="row">
                                    <div class="col-md-6 pb-3">
                                        <label for="employee_id" class="form-label">Employee ID</label>
                                        <input type="text" id="employee_id" name="employee_id" class="form-control" placeholder="Enter employee ID" value="<?php echo htmlspecialchars($employee_id_value); ?>">
                                        <?php if (isset($errors['employee_id'])): ?><p class="text-danger small"><?php echo $errors['employee_id']; ?></p><?php endif; ?>
                                    </div>
                                    <div class="col-md-6 pb-3">
                                        <label for="hire_date" class="form-label">Hire Date</label>
                                        <input type="date" id="hire_date" name="hire_date" class="form-control" value="<?php echo htmlspecialchars($hire_date_value); ?>">
                                        <?php if (isset($errors['hire_date'])): ?><p class="text-danger small"><?php echo $errors['hire_date']; ?></p><?php endif; ?>
                                    </div>
                                    <div class="col-md-6 pb-3">
                                        <label for="department" class="form-label">Department</label>
                                        <input type="text" id="department" name="department" class="form-control" placeholder="e.g., Science, Math" value="<?php echo htmlspecialchars($department_value); ?>">
                                        <?php if (isset($errors['department'])): ?><p class="text-danger small"><?php echo $errors['department']; ?></p><?php endif; ?>
                                    </div>
                                     <div class="col-md-6 pb-3">
                                        <label for="specialization" class="form-label">Specialization</label>
                                        <input type="text" id="specialization" name="specialization" class="form-control" placeholder="e.g., High School Math" value="<?php echo htmlspecialchars($specialization_value); ?>">
                                        <?php if (isset($errors['specialization'])): ?><p class="text-danger small"><?php echo $errors['specialization']; ?></p><?php endif; ?>
                                    </div>
                                    <div class="col-md-12 pb-3">
                                        <label for="qualifications" class="form-label">Qualifications</label>
                                        <textarea id="qualifications" name="qualifications" class="form-control" rows="3" placeholder="e.g., Master's in Education"><?php echo htmlspecialchars($qualifications_value); ?></textarea>
                                        <?php if (isset($errors['qualifications'])): ?><p class="text-danger small"><?php echo $errors['qualifications']; ?></p><?php endif; ?>
                                    </div>
                                    <div class="col-md-6 pb-3">
                                        <label for="office_location" class="form-label">Office Location</label>
                                        <input type="text" id="office_location" name="office_location" class="form-control" placeholder="e.g., Building A, Room 205" value="<?php echo htmlspecialchars($office_location_value); ?>">
                                        <?php if (isset($errors['office_location'])): ?><p class="text-danger small"><?php echo $errors['office_location']; ?></p><?php endif; ?>
                                    </div>
                                    <div class="col-md-6 pb-3">
                                        <label for="office_hours" class="form-label">Office Hours</label>
                                        <textarea id="office_hours" name="office_hours" class="form-control" rows="1" placeholder="e.g., Mon-Wed 10:00-11:00 AM"><?php echo htmlspecialchars($office_hours_value); ?></textarea>
                                        <?php if (isset($errors['office_hours'])): ?><p class="text-danger small"><?php echo $errors['office_hours']; ?></p><?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button type="submit" class="btn btn-primary">Update Teacher</button>
                                    <a href="users-teachers.php" class="btn btn-secondary">Go Back to Teacher List</a>
                                </div>
                            </div>
                        </form>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <p>Error: Teacher information could not be loaded.</p>
                                <a href="users-teachers.php" class="btn btn-primary">Go Back to Teacher List</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include "footer.php" ?>

    <style>
        .result {
            padding: 1rem;
            margin: 1rem;
            border-radius: 0.5rem;
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
            margin-bottom: 0;
            padding-left: 1.5rem;
        }
        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 3px solid #dee2e6;
            object-fit: cover;
        }
    </style>

    <script>
        // JavaScript function to show a preview of the selected image before uploading
        function previewFile() {
            const preview = document.getElementById('photoPreview');
            const file = document.querySelector('input[type=file]').files[0];
            const reader = new FileReader();

            reader.addEventListener("load", function () {
                // convert image file to base64 string
                preview.src = reader.result;
            }, false);

            if (file) {
                reader.readAsDataURL(file);
            }
        }
    </script>

</body>

</html>