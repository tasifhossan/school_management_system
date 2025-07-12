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


// Define the directory where student photos are stored and new photos will be uploaded.
$upload_directory = "uploads/student_photos/";

// Ensure the upload directory exists. If not, attempt to create it.
// Set appropriate permissions (0777 is for development; use stricter permissions in production).
if (!is_dir($upload_directory)) {
    mkdir($upload_directory, 0777, true);
}

// Initialize variables to hold form data and messages.
$user_id = null;
$student_id = null; // This will be null if only user data exists for a student role
$name_value = $email_value = $phone_user_value = '';
$roll_number_value = $date_of_birth_value = $gender_value = $address_value = '';
$phone_student_value = $guardian_name_value = $guardian_contact_value = $enrollment_date_value = '';
$advisor_id_value = '';
$current_photo_path = ''; // To display the current photo and for deletion logic

$result_message = '';
$result_class = '';
$errors = [];

/**
 * Function to sanitize input data.
 * This helps prevent common web vulnerabilities like Cross-Site Scripting (XSS).
 *
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
 * Function to hash the password.
 * Uses PASSWORD_DEFAULT for best practices (currently uses bcrypt, which is strong and adaptive).
 *
 * @param string $password The plain text password.
 * @return string The hashed password.
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Function to check if an email already exists in the 'users' table.
 *
 * @param mysqli $conn The database connection object.
 * @param string $email The email address to check.
 * @param int|null $exclude_user_id Optional user ID to exclude (used when updating an existing user).
 * @return bool True if the email exists, false otherwise.
 */
function email_exists($conn, $email, $exclude_user_id = null) {
    $sql = "SELECT id FROM users WHERE email = ?";
    if ($exclude_user_id !== null) {
        $sql .= " AND id != ?";
    }
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Failed to prepare email_exists statement: " . $conn->error);
        return false;
    }
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
 * Function to check if a roll number already exists in the 'students' table.
 *
 * @param mysqli $conn The database connection object.
 * @param string $roll_number The roll number to check.
 * @param int|null $exclude_student_id Optional student ID to exclude (used when updating an existing student).
 * @return bool True if the roll number exists, false otherwise.
 */
function roll_number_exists($conn, $roll_number, $exclude_student_id = null) {
    $sql = "SELECT id FROM students WHERE roll_number = ?";
    if ($exclude_student_id !== null) {
        $sql .= " AND id != ?";
    }
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Failed to prepare roll_number_exists statement: " . $conn->error);
        return false;
    }
    if ($exclude_student_id !== null) {
        $stmt->bind_param("si", $roll_number, $exclude_student_id);
    } else {
        $stmt->bind_param("s", $roll_number);
    }
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * Function to validate form inputs for student update/profile completion.
 * Student-specific fields are required only if roll_number is provided or if
 * it's an update to an existing full student profile.
 *
 * @param array $data An associative array of input data (e.g., $_POST).
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user being updated.
 * @param int|null $student_id The ID of the student being updated (null if completing profile).
 * @return array An array of error messages, empty if no errors.
 */
function validate_student_input_for_update($data, $conn, $user_id, $student_id) {
    $errors = [];

    // Basic User Details Validation (Always required)
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

    // Check if the user is attempting to create/update a full student profile.
    // This is true if roll_number is provided OR if student_id already exists (editing existing profile).
    $is_full_student_profile_attempt = !empty($data['roll_number']) || $student_id !== null;

    if ($is_full_student_profile_attempt) {
        // These fields are required if a full student profile is being created/updated
        if (empty($data['roll_number'])) {
            $errors['roll_number'] = "Roll Number is required for a complete student profile.";
        } elseif (roll_number_exists($conn, $data['roll_number'], $student_id)) {
            $errors['roll_number'] = "This Roll Number is already registered for another student.";
        }

        if (empty($data['date_of_birth'])) {
            $errors['date_of_birth'] = "Date of Birth is required.";
        } elseif (!strtotime($data['date_of_birth']) || $data['date_of_birth'] > date('Y-m-d')) {
            $errors['date_of_birth'] = "Invalid Date of Birth or a future date.";
        }

        $allowed_genders = ['Male', 'Female', 'Other'];
        if (empty($data['gender'])) {
            $errors['gender'] = "Gender is required.";
        } elseif (!in_array($data['gender'], $allowed_genders)) {
            $errors['gender'] = "Invalid gender selected.";
        }

        if (empty($data['address'])) {
            $errors['address'] = "Address is required.";
        }

        // Student Phone (Optional, but if provided, validate format)
        if (!empty($data['phone_student']) && !preg_match("/^[0-9]{10,15}$/", $data['phone_student'])) {
            $errors['phone_student'] = "Invalid student phone number format (10-15 digits).";
        }

        if (empty($data['guardian_name'])) {
            $errors['guardian_name'] = "Guardian Name is required.";
        }

        if (empty($data['guardian_contact'])) {
            $errors['guardian_contact'] = "Guardian Contact is required.";
        } elseif (!preg_match("/^[0-9]{10,15}$/", $data['guardian_contact'])) {
            $errors['guardian_contact'] = "Invalid guardian contact format (10-15 digits).";
        }

        if (empty($data['enrollment_date'])) {
            $errors['enrollment_date'] = "Enrollment Date is required.";
        } elseif (!strtotime($data['enrollment_date']) || $data['enrollment_date'] > date('Y-m-d')) {
            $errors['enrollment_date'] = "Invalid Enrollment Date or a future date.";
        }

        if (empty($data['advisor_id'])) {
            $errors['advisor_id'] = "Advisor is required.";
        } elseif (!is_numeric($data['advisor_id'])) {
            $errors['advisor_id'] = "Invalid Advisor ID.";
        }
    }

    return $errors;
}

/**
 * Function to retrieve a single student's combined user and student data by user_id.
 * Uses LEFT JOIN to fetch student data, which might be NULL if only user data exists.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user (from the 'users' table) to fetch.
 * @return array|null An associative array of the student's data, or null if not found.
 */
function get_student_data_by_user_id($conn, $user_id) {
    $sql = "SELECT
                u.id AS user_id,
                u.name,
                u.email,
                u.phone AS user_phone,
                s.id AS student_id,
                s.roll_number,
                s.date_of_birth,
                s.gender,
                s.address,
                s.phone AS student_phone,
                s.guardian_name,
                s.guardian_contact,
                s.enrollment_date,
                s.advisor_id,
                s.photo
            FROM users u
            LEFT JOIN students s ON u.id = s.user_id -- Use LEFT JOIN here
            WHERE u.id = ? AND u.role = 'student'"; // Ensure it's a student role
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Failed to prepare get_student_data_by_user_id statement: " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_data = $result->fetch_assoc();
    $stmt->close();
    return $student_data;
}

/**
 * Function to update an existing user's data and optionally create/update a student profile.
 * Uses transactions for data integrity. Handles password and photo updates.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The user ID of the student.
 * @param int|null $student_id The existing student ID (null if completing profile).
 * @param array $user_data Associative array of user details to update.
 * @param array $student_data Associative array of student details to insert/update.
 * @param array $photo_file The $_FILES['photo'] array for the uploaded file.
 * @param string $upload_dir The directory to upload photos.
 * @param string|null $old_photo_path The path to the old photo file for deletion.
 * @return bool True on successful update/creation, false otherwise.
 */
function update_student($conn, $user_id, $existing_student_id, $user_data, $student_data, $photo_file, $upload_dir, $old_photo_path) {
    $conn->begin_transaction();

    try {
        // 1. Update 'users' table
        $sql_user = "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?";
        if (isset($user_data['password_hashed'])) { // Only update password if a new one is provided
            $sql_user = "UPDATE users SET name = ?, email = ?, password = ?, phone = ? WHERE id = ?";
        }
        $stmt_user = $conn->prepare($sql_user);
        if ($stmt_user === false) {
            throw new Exception("Prepare user update statement failed: " . $conn->error);
        }

        $user_phone_param = !empty($user_data['phone_user']) ? $user_data['phone_user'] : NULL;

        if (isset($user_data['password_hashed'])) {
            $stmt_user->bind_param(
                "ssssi",
                $user_data['name'],
                $user_data['email'],
                $user_data['password_hashed'],
                $user_phone_param,
                $user_id
            );
        } else {
            $stmt_user->bind_param(
                "sssi",
                $user_data['name'],
                $user_data['email'],
                $user_phone_param,
                $user_id
            );
        }

        if (!$stmt_user->execute()) {
            throw new Exception("Execute user update statement failed: " . $stmt_user->error);
        }
        $stmt_user->close();

        // 2. Handle photo upload/update/removal
        $new_photo_path = $old_photo_path; // Default to old path

        // Check if a new photo is uploaded
        if ($photo_file['size'] > 0 && $photo_file['error'] == UPLOAD_ERR_OK) {
            // Delete the old photo file if it exists and a new one is uploaded
            if ($old_photo_path && file_exists($old_photo_path)) {
                unlink($old_photo_path);
            }

            $file_extension = pathinfo($photo_file['name'], PATHINFO_EXTENSION);
            $new_file_name = uniqid('student_') . '.' . $file_extension;
            $destination = $upload_dir . $new_file_name;

            if (move_uploaded_file($photo_file['tmp_name'], $destination)) {
                $new_photo_path = $destination;
            } else {
                throw new Exception("Failed to upload new photo.");
            }
        } elseif (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
            // User explicitly requested to remove photo
            if ($old_photo_path && file_exists($old_photo_path)) {
                unlink($old_photo_path);
            }
            $new_photo_path = NULL; // Set photo path to NULL in DB
        }

        // 3. Insert or Update 'students' table
        // A. If no existing student_id, attempt to INSERT a new student profile IF roll_number is provided
        if ($existing_student_id === null) {
            if (!empty($student_data['roll_number'])) { // Only insert if student-specific data is provided
                $stmt_student = $conn->prepare(
                    "INSERT INTO students (user_id, roll_number, date_of_birth, gender, address, phone, guardian_name, guardian_contact, enrollment_date, advisor_id, photo)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                if ($stmt_student === false) {
                    throw new Exception("Prepare student insert statement failed: " . $conn->error);
                }

                $student_phone_param = !empty($student_data['phone_student']) ? $student_data['phone_student'] : NULL;
                $stmt_student->bind_param(
                    "issssssssss",
                    $user_id,
                    $student_data['roll_number'],
                    $student_data['date_of_birth'],
                    $student_data['gender'],
                    $student_data['address'],
                    $student_phone_param,
                    $student_data['guardian_name'],
                    $student_data['guardian_contact'],
                    $student_data['enrollment_date'],
                    $student_data['advisor_id'],
                    $new_photo_path
                );

                if (!$stmt_student->execute()) {
                    throw new Exception("Execute student insert statement failed: " . $stmt_student->error);
                }
                $stmt_student->close();
            }
        } else {
            // B. If an existing student_id, UPDATE the student profile
            $stmt_student = $conn->prepare(
                "UPDATE students SET roll_number = ?, date_of_birth = ?, gender = ?, address = ?, phone = ?, guardian_name = ?, guardian_contact = ?, enrollment_date = ?, advisor_id = ?, photo = ? WHERE id = ?"
            );
            if ($stmt_student === false) {
                throw new Exception("Prepare student update statement failed: " . $conn->error);
            }

            $student_phone_param = !empty($student_data['phone_student']) ? $student_data['phone_student'] : NULL;
            $stmt_student->bind_param(
                "ssssssssssi",
                $student_data['roll_number'],
                $student_data['date_of_birth'],
                $student_data['gender'],
                $student_data['address'],
                $student_phone_param,
                $student_data['guardian_name'],
                $student_data['guardian_contact'],
                $student_data['enrollment_date'],
                $student_data['advisor_id'],
                $new_photo_path,
                $existing_student_id
            );

            if (!$stmt_student->execute()) {
                throw new Exception("Execute student update statement failed: " . $stmt_student->error);
            }
            $stmt_student->close();
        }

        $conn->commit();
        return true;

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Student update/creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Function to get a list of teachers (ID and Name) for use in dropdown menus (e.g., as advisors).
 *
 * @param mysqli $conn The database connection object.
 * @return array An associative array where keys are teacher IDs and values are teacher names.
 */
function get_teachers_for_dropdown($conn) {
    $teachers = [];
    $sql = "SELECT t.id, u.name
            FROM teachers t
            JOIN users u ON t.user_id = u.id
            ORDER BY u.name ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $teachers[$row['id']] = $row['name'];
        }
        $result->free();
    } else {
        error_log("Error fetching teachers for dropdown: " . $conn->error);
    }
    return $teachers;
}

// --- Main script logic to handle form display and submission ---

// 1. Handle initial page load (GET request) to fetch existing student data
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Check if user_id is provided in the URL
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $user_id = (int)$_GET['id'];
        $student_data_db = get_student_data_by_user_id($conn, $user_id);

        if ($student_data_db) {
            // Populate form variables with data from the database
            $student_id = $student_data_db['student_id']; // This will be null if only user data exists
            $name_value = htmlspecialchars($student_data_db['name']);
            $email_value = htmlspecialchars($student_data_db['email']);
            $phone_user_value = htmlspecialchars($student_data_db['user_phone']);

            // Only populate these if student_id exists (meaning full profile exists)
            // or if they are not null from the LEFT JOIN
            $roll_number_value = htmlspecialchars($student_data_db['roll_number'] ?? '');
            $date_of_birth_value = htmlspecialchars($student_data_db['date_of_birth'] ?? '');
            $gender_value = htmlspecialchars($student_data_db['gender'] ?? '');
            $address_value = htmlspecialchars($student_data_db['address'] ?? '');
            $phone_student_value = htmlspecialchars($student_data_db['student_phone'] ?? '');
            $guardian_name_value = htmlspecialchars($student_data_db['guardian_name'] ?? '');
            $guardian_contact_value = htmlspecialchars($student_data_db['guardian_contact'] ?? '');
            $enrollment_date_value = htmlspecialchars($student_data_db['enrollment_date'] ?? '');
            $advisor_id_value = htmlspecialchars($student_data_db['advisor_id'] ?? '');
            $current_photo_path = htmlspecialchars($student_data_db['photo'] ?? '');

        } else {
            $result_message = "Error: Student user not found with the provided ID or is not a student role.";
            $result_class = "result-error";
        }
    } else {
        $result_message = "Error: No student ID provided for editing.";
        $result_class = "result-error";
    }
}

// 2. Handle form submission (POST request) to update student data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve hidden IDs first
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : null; // Can be null if only user role exists
    $current_photo_path = isset($_POST['current_photo_path']) ? sanitize_input($_POST['current_photo_path']) : '';

    if ($conn && $user_id) { // Only require user_id for submission
        // Sanitize all POST data received from the form
        $sanitized_data = [
            'name'             => sanitize_input($_POST['name'] ?? ''),
            'email'            => sanitize_input($_POST['email'] ?? ''),
            'password'         => $_POST['password'] ?? '', // Password not sanitized as it's hashed
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'phone_user'       => sanitize_input($_POST['phone_user'] ?? ''),

            // Student specific fields (can be empty if profile is being completed)
            'roll_number'      => sanitize_input($_POST['roll_number'] ?? ''),
            'date_of_birth'    => sanitize_input($_POST['date_of_birth'] ?? ''),
            'gender'           => sanitize_input($_POST['gender'] ?? ''),
            'address'          => sanitize_input($_POST['address'] ?? ''),
            'phone_student'    => sanitize_input($_POST['phone_student'] ?? ''),
            'guardian_name'    => sanitize_input($_POST['guardian_name'] ?? ''),
            'guardian_contact' => sanitize_input($_POST['guardian_contact'] ?? ''),
            'enrollment_date'  => sanitize_input($_POST['enrollment_date'] ?? ''),
            'advisor_id'       => sanitize_input($_POST['advisor_id'] ?? ''),
        ];

        // Validate the sanitized data specifically for update, passing existing student_id
        $errors = validate_student_input_for_update($sanitized_data, $conn, $user_id, $student_id);

        // Handle file upload validation
        $photo_file = $_FILES['photo'] ?? ['size' => 0, 'error' => UPLOAD_ERR_NO_FILE, 'name' => ''];
        if ($photo_file['size'] > 0) {
            if ($photo_file['error'] != UPLOAD_ERR_OK) {
                $errors['photo'] = "Error uploading new photo. Code: " . $photo_file['error'];
            } else {
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $file_extension = strtolower(pathinfo($photo_file['name'], PATHINFO_EXTENSION));
                if (!in_array($file_extension, $allowed_extensions)) {
                    $errors['photo'] = "Invalid photo file type. Only JPG, JPEG, PNG, GIF allowed.";
                }
                $max_file_size = 2 * 1024 * 1024; // 2 MB
                if ($photo_file['size'] > $max_file_size) {
                    $errors['photo'] = "New photo file is too large (max 2MB allowed).";
                }
            }
        }


        if (empty($errors)) {
            $user_data_to_update = [
                'name' => $sanitized_data['name'],
                'email' => $sanitized_data['email'],
                'phone_user' => $sanitized_data['phone_user']
            ];
            // Only add password to update data if it's provided
            if (!empty($sanitized_data['password'])) {
                $user_data_to_update['password_hashed'] = hash_password($sanitized_data['password']);
            }

            $student_data_to_process = [
                'roll_number'      => $sanitized_data['roll_number'],
                'date_of_birth'    => $sanitized_data['date_of_birth'],
                'gender'           => $sanitized_data['gender'],
                'address'          => $sanitized_data['address'],
                'phone_student'    => $sanitized_data['phone_student'],
                'guardian_name'    => $sanitized_data['guardian_name'],
                'guardian_contact' => $sanitized_data['guardian_contact'],
                'enrollment_date'  => $sanitized_data['enrollment_date'],
                'advisor_id'       => $sanitized_data['advisor_id']
            ];

            if (update_student($conn, $user_id, $student_id, $user_data_to_update, $student_data_to_process, $photo_file, $upload_directory, $current_photo_path)) {
                $result_message = "Student data updated successfully!";
                $result_class = "result-success";
                // Re-fetch data to show the latest changes, especially for photo path and new student_id if profile completed
                $updated_student_data = get_student_data_by_user_id($conn, $user_id);
                if ($updated_student_data) {
                    $student_id = $updated_student_data['student_id']; // Update student_id in case it was just created
                    $name_value = htmlspecialchars($updated_student_data['name']);
                    $email_value = htmlspecialchars($updated_student_data['email']);
                    $phone_user_value = htmlspecialchars($updated_student_data['user_phone']);
                    $roll_number_value = htmlspecialchars($updated_student_data['roll_number'] ?? '');
                    $date_of_birth_value = htmlspecialchars($updated_student_data['date_of_birth'] ?? '');
                    $gender_value = htmlspecialchars($updated_student_data['gender'] ?? '');
                    $address_value = htmlspecialchars($updated_student_data['address'] ?? '');
                    $phone_student_value = htmlspecialchars($updated_student_data['student_phone'] ?? '');
                    $guardian_name_value = htmlspecialchars($updated_student_data['guardian_name'] ?? '');
                    $guardian_contact_value = htmlspecialchars($updated_student_data['guardian_contact'] ?? '');
                    $enrollment_date_value = htmlspecialchars($updated_student_data['enrollment_date'] ?? '');
                    $advisor_id_value = htmlspecialchars($updated_student_data['advisor_id'] ?? '');
                    $current_photo_path = htmlspecialchars($updated_student_data['photo'] ?? ''); // Update current photo path
                }
            } else {
                $result_message = "Error: Could not update student data. Please check server logs.";
                $result_class = "result-error";
                // Retain POSTed values on error
                $name_value = $sanitized_data['name'];
                $email_value = $sanitized_data['email'];
                $phone_user_value = $sanitized_data['phone_user'];
                $roll_number_value = $sanitized_data['roll_number'];
                $date_of_birth_value = $sanitized_data['date_of_birth'];
                $gender_value = $sanitized_data['gender'];
                $address_value = $sanitized_data['address'];
                $phone_student_value = $sanitized_data['phone_student'];
                $guardian_name_value = $sanitized_data['guardian_name'];
                $guardian_contact_value = $sanitized_data['guardian_contact'];
                $enrollment_date_value = $sanitized_data['enrollment_date'];
                $advisor_id_value = $sanitized_data['advisor_id'];
            }
        } else {
            // If validation errors, display them and retain input
            $result_message = "Please correct the following errors:";
            $result_class = "result-error";

            // Retain form values on error
            $name_value = $sanitized_data['name'];
            $email_value = $sanitized_data['email'];
            $phone_user_value = $sanitized_data['phone_user'];
            $roll_number_value = $sanitized_data['roll_number'];
            $date_of_birth_value = $sanitized_data['date_of_birth'];
            $gender_value = $sanitized_data['gender'];
            $address_value = $sanitized_data['address'];
            $phone_student_value = $sanitized_data['phone_student'];
            $guardian_name_value = $sanitized_data['guardian_name'];
            $guardian_contact_value = $sanitized_data['guardian_contact'];
            $enrollment_date_value = $sanitized_data['enrollment_date'];
            $advisor_id_value = $sanitized_data['advisor_id'];
        }
    } else {
        $result_message = "Error: Invalid request or missing user ID.";
        $result_class = "result-error";
    }
}

// Fetch teachers for the advisor dropdown (done before closing connection)
$advisors = get_teachers_for_dropdown($conn);

// Close connection after all operations
$conn->close();
?>
<?php include "dashboard-top.php" ?>

		<?php include "sidebar_ad.php" ?>

			<main class="content">
				<div class="container-fluid p-0">
					<!-- add new students -->
					<h1 class="h3 mb-3">Edit Student Information</h1>

					<div class="row">
						<div class="col-12 col-lg-12 d-flex">
							<div class="card flex-fill">
								<div class="card-header">
									<h5 class="card-title mb-0">Student Details</h5>
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

                                <?php if ($user_id): // Only show form if student data is successfully loaded ?>
                                <form action="#" method="POST" enctype="multipart/form-data">
                                    <!-- Hidden inputs to carry user_id and student_id -->
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">
                                    <input type="hidden" name="current_photo_path" value="<?php echo htmlspecialchars($current_photo_path); ?>">

					                <div class="card-body space-y-6">
					                    <!-- Name Input -->
                                        <div class="pb-3">
                                            <label for="name" class="form-label">Full Name</label>
                                            <input type="text" id="name" name="name" class="form-control" placeholder="Enter student's full name" value="<?php echo htmlspecialchars($name_value); ?>" required>
                                            <?php if (isset($errors['name'])): ?><p class="error-message"><?php echo $errors['name']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- Email Input -->
                                        <div class="pb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" id="email" name="email" class="form-control" placeholder="Enter student's email address" value="<?php echo htmlspecialchars($email_value); ?>" required>
                                            <?php if (isset($errors['email'])): ?><p class="error-message"><?php echo $errors['email']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- Password Input (optional for update) -->
                                        <div class="pb-3">
                                            <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                                            <input type="password" id="password" name="password" class="form-control" placeholder="Enter new password (optional)">
                                            <?php if (isset($errors['password'])): ?><p class="error-message"><?php echo $errors['password']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- Confirm Password Input (optional for update) -->
                                        <div class="pb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm new password (optional)">
                                            <?php if (isset($errors['confirm_password'])): ?><p class="error-message"><?php echo $errors['confirm_password']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- User Phone Input -->
                                        <div class="pb-3">
                                            <label for="phone_user" class="form-label">User Phone (General Contact)</label>
                                            <input type="tel" id="phone_user" name="phone_user" class="form-control" placeholder="e.g., +1234567890" pattern="[0-9]{10,15}" title="Phone number (10-15 digits)" value="<?php echo htmlspecialchars($phone_user_value); ?>">
                                            <small class="text-gray-500 mt-1 block">Optional: General contact phone number associated with the user account.</small>
                                            <?php if (isset($errors['phone_user'])): ?><p class="error-message"><?php echo $errors['phone_user']; ?></p><?php endif; ?>
                                        </div>

                                        <hr class="my-4 border-gray-200">
                                        <h6 class="text-md font-semibold text-gray-700 mb-4">Student Profile Details</h6>

                                        <!-- Roll Number Input -->
                                        <div class="pb-3">
                                            <label for="roll_number" class="form-label">Roll Number</label>
                                            <input type="text" id="roll_number" name="roll_number" class="form-control" placeholder="Enter student's roll number" value="<?php echo htmlspecialchars($roll_number_value); ?>">
                                            <small class="text-gray-500 mt-1 block">Required for a complete student profile.</small>
                                            <?php if (isset($errors['roll_number'])): ?><p class="error-message"><?php echo $errors['roll_number']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- Date of Birth Input -->
                                        <div class="pb-3">
                                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($date_of_birth_value); ?>" max="<?php echo date('Y-m-d'); ?>">
                                            <small class="text-gray-500 mt-1 block">Required for a complete student profile.</small>
                                            <?php if (isset($errors['date_of_birth'])): ?><p class="error-message"><?php echo $errors['date_of_birth']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- Gender Select -->
                                        <div class="pb-3">
                                            <label for="gender" class="form-label">Gender</label>
                                            <select id="gender" name="gender" class="form-select">
                                                <option value="" disabled <?php echo empty($gender_value) ? 'selected' : ''; ?>>Select Gender</option>
                                                <option value="Male" <?php echo ($gender_value == 'Male') ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo ($gender_value == 'Female') ? 'selected' : ''; ?>>Female</option>
                                                <option value="Other" <?php echo ($gender_value == 'Other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                            <small class="text-gray-500 mt-1 block">Required for a complete student profile.</small>
                                            <?php if (isset($errors['gender'])): ?><p class="error-message"><?php echo $errors['gender']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- Address Input -->
                                        <div class="pb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea id="address" name="address" class="form-control" rows="3" placeholder="Enter student's full address"><?php echo htmlspecialchars($address_value); ?></textarea>
                                            <small class="text-gray-500 mt-1 block">Required for a complete student profile.</small>
                                            <?php if (isset($errors['address'])): ?><p class="error-message"><?php echo $errors['address']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- Student Phone Input -->
                                        <div class="pb-3">
                                            <label for="phone_student" class="form-label">Student Phone</label>
                                            <input type="tel" id="phone_student" name="phone_student" class="form-control" placeholder="e.g., +1234567890" pattern="[0-9]{10,15}" title="Student's personal phone number (10-15 digits)" value="<?php echo htmlspecialchars($phone_student_value); ?>">
                                            <small class="text-gray-500 mt-1 block">Optional: Student's personal contact number.</small>
                                            <?php if (isset($errors['phone_student'])): ?><p class="error-message"><?php echo $errors['phone_student']; ?></p><?php endif; ?>
                                        </div>

                                        <hr class="my-4 border-gray-200">

                                        <!-- Guardian Name Input -->
                                        <div class="pb-3">
                                            <label for="guardian_name" class="form-label">Guardian Name</label>
                                            <input type="text" id="guardian_name" name="guardian_name" class="form-control" placeholder="Enter guardian's full name" value="<?php echo htmlspecialchars($guardian_name_value); ?>">
                                            <small class="text-gray-500 mt-1 block">Required for a complete student profile.</small>
                                            <?php if (isset($errors['guardian_name'])): ?><p class="error-message"><?php echo $errors['guardian_name']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- Guardian Contact Input -->
                                        <div class="pb-3">
                                            <label for="guardian_contact" class="form-label">Guardian Contact</label>
                                            <input type="tel" id="guardian_contact" name="guardian_contact" class="form-control" placeholder="e.g., +1234567890" pattern="[0-9]{10,15}" title="Guardian's contact number (10-15 digits)" value="<?php echo htmlspecialchars($guardian_contact_value); ?>">
                                            <small class="text-gray-500 mt-1 block">Required for a complete student profile.</small>
                                            <?php if (isset($errors['guardian_contact'])): ?><p class="error-message"><?php echo $errors['guardian_contact']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- Enrollment Date Input -->
                                        <div class="pb-3">
                                            <label for="enrollment_date" class="form-label">Enrollment Date</label>
                                            <input type="date" id="enrollment_date" name="enrollment_date" class="form-control" value="<?php echo htmlspecialchars($enrollment_date_value); ?>" max="<?php echo date('Y-m-d'); ?>">
                                            <small class="text-gray-500 mt-1 block">Required for a complete student profile.</small>
                                            <?php if (isset($errors['enrollment_date'])): ?><p class="error-message"><?php echo $errors['enrollment_date']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- Academic Advisor Select -->
                                        <div class="pb-3">
                                            <label for="advisor_id" class="form-label">Academic Advisor</label>
                                            <select id="advisor_id" name="advisor_id" class="form-select">
                                                <option value="" disabled <?php echo empty($advisor_id_value) ? 'selected' : ''; ?>>Select an Advisor</option>
                                                <?php foreach ($advisors as $id => $name): ?>
                                                    <option value="<?php echo htmlspecialchars($id); ?>" <?php echo ($advisor_id_value == $id) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-gray-500 mt-1 block">Required for a complete student profile.</small>
                                            <?php if (isset($errors['advisor_id'])): ?><p class="error-message"><?php echo $errors['advisor_id']; ?></p><?php endif; ?>
                                        </div>
                                        <!-- Student Photo Input -->
                                        <div class="pb-3">
                                            <label for="photo" class="form-label">Student Photo</label>
                                            <?php if ($current_photo_path && file_exists($current_photo_path)): ?>
                                                <div class="mb-2">
                                                    <p class="text-sm text-gray-700">Current Photo:</p>
                                                    <img src="<?php echo htmlspecialchars($current_photo_path); ?>" alt="Current Student Photo" class="photo-preview">
                                                    <label class="block mt-2 text-sm text-gray-700">
                                                        <input type="checkbox" name="remove_photo" value="1" class="form-checkbox"> Remove current photo
                                                    </label>
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" id="photo" name="photo" class="form-control p-2">
                                            <small class="text-gray-500 mt-1 block">Max 2MB. Accepted formats: JPG, JPEG, PNG, GIF. Uploading a new photo will replace the current one.</small>
                                            <?php if (isset($errors['photo'])): ?><p class="error-message"><?php echo $errors['photo']; ?></p><?php endif; ?>
                                        </div>
						                
					                    <!-- Submit Button -->
                                        <div class="text-center mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            Update Student
                                        </button>
                                        <a href="users-students.php" class="btn btn-secondary">
                                            <i data-feather="arrow-left" class="me-1" style="width:16px; height:16px;"></i> Go Back to Student List
                                        </a>
                                    </div>
					                </div>
						        </form>
                                <?php else: ?>
                                    <div class="text-center text-gray-600 p-8 border border-gray-300 rounded-md bg-white">
                                        <p class="text-lg font-semibold mb-4">Error: Student information could not be loaded.</p>
                                        <p class="mb-4">Please ensure a valid student ID is provided in the URL (e.g., `edit-student.php?id=1`).</p>
                                        <a href="users-students.php" class="btn btn-primary">
                                           <i data-feather="arrow-left" class="me-1" style="width:16px; height:16px;"></i> Go Back to Student List
                                        </a>
                                    </div>
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