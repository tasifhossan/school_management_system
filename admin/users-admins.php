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

// --- Variables for User Registration Form ---
$result_message = '';
$result_class = '';
$name_value = '';
$email_value = '';
$phone_value = '';
$role_selected = '';
$registration_errors = []; // Specific array for registration validation errors

// --- Variables for User List Table ---
$users = [];
$list_error_message = ''; // Error message specific to user listing

/**
 * Function to sanitize input data.
 * Prevents common attacks like XSS.
 *
 * //@param string $data The input string to sanitize.
 * //@return string The sanitized string.
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Function to validate form inputs for user registration, including password confirmation.
 * Checks for required fields, email format, password strength, matching passwords, and valid role.
 *
 * //@param array $data An associative array of input data (e.g., $_POST).
 * //@return array An array of error messages, empty if no errors.
 */
function validate_user_input($data) {
    $errors = [];

    if (empty($data['name'])) {
        $errors['name'] = "Name is required.";
    }

    if (empty($data['email'])) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }

    if (empty($data['password'])) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($data['password']) < 8) {
        $errors['password'] = "Password must be at least 8 characters long.";
    }

    if (empty($data['confirm_password'])) {
        $errors['confirm_password'] = "Confirm password is required.";
    } elseif ($data['password'] !== $data['confirm_password']) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    $allowed_roles = ['admin', 'teacher', 'student'];
    if (empty($data['role'])) {
        $errors['role'] = "Role is required.";
    } elseif (!in_array($data['role'], $allowed_roles)) {
        $errors['role'] = "Invalid role selected.";
    }

    if (!empty($data['phone']) && !preg_match("/^[0-9]{10,15}$/", $data['phone'])) {
        $errors['phone'] = "Invalid phone number format. Must be 10-15 digits.";
    }

    return $errors;
}

/**
 * Function to hash the password.
 * Uses PASSWORD_DEFAULT for best practices (currently bcrypt).
 *
 * //@param string $password The plain text password.
 * //@return string The hashed password.
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Function to check if email already exists in the database.
 *
 * //@param mysqli $conn The database connection object.
 * //@param string $email The email to check.
 * //@return bool True if email exists, false otherwise.
 */
function email_exists($conn, $email) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $email_exists = $stmt->num_rows > 0;
    $stmt->close();
    return $email_exists;
}

/**
 * Function to insert a new user into the database.
 *
 * //@param mysqli $conn The database connection object.
 * //@param array $user_data An associative array containing user details.
 * //@return bool True on successful insertion, false otherwise.
 */
function create_user($conn, $user_data) {
    $phone_to_insert = !empty($user_data['phone']) ? $user_data['phone'] : null;

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param(
        "sssss",
        $user_data['name'],
        $user_data['email'],
        $user_data['password_hashed'],
        $user_data['role'],
        $phone_to_insert
    );

    $success = $stmt->execute();
    if ($success === false) {
        error_log("Execute failed: " . $stmt->error);
    }
    $stmt->close();
    return $success;
}

/**
 * Function to fetch all users from the database.
 *
 * //@param mysqli $conn The database connection object.
 * //@return array An array of user data, or an empty array on failure.
 */
function get_all_users($conn) {
    global $list_error_message;
    $users_data = [];

    // Ensure connection is valid before querying
    if (!isset($conn) || !$conn instanceof mysqli || $conn->connect_error) {
        $list_error_message = "Database connection failed for user list. Please ensure 'connection.php' is correctly configured.";
        return [];
    }

    $sql = "SELECT id, name, email, role FROM users ORDER BY created_at DESC";
    $result = $conn->query($sql);

    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $users_data[] = $row;
            }
        }
        $result->free();
    } else {
        $list_error_message = "Error fetching users: " . $conn->error;
        error_log("Error fetching users: " . $conn->error);
    }
    return $users_data;
}


// --- Main script logic to handle form submission ---
// Check if the database connection from connection.php is available
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {

    // Handle POST request for user registration
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Sanitize all POST data and store for displaying values back in form
        $name_value = sanitize_input($_POST['name'] ?? '');
        $email_value = sanitize_input($_POST['email'] ?? '');
        $role_selected = sanitize_input($_POST['role'] ?? '');
        $phone_value = sanitize_input($_POST['phone'] ?? '');

        $sanitized_data = [
            'name'             => $name_value,
            'email'            => $email_value,
            'password'         => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'role'             => $role_selected,
            'phone'            => $phone_value
        ];

        // Validate sanitized data
        $registration_errors = validate_user_input($sanitized_data);

        if (empty($registration_errors)) {
            if (email_exists($conn, $sanitized_data['email'])) {
                $result_message = "Error: This email is already registered.";
                $result_class = "result-error";
            } else {
                $sanitized_data['password_hashed'] = hash_password($sanitized_data['password']);

                if (create_user($conn, $sanitized_data)) {
                    $result_message = "User created successfully!";
                    $result_class = "result-success";
                    // Clear form fields on successful submission
                    $name_value = '';
                    $email_value = '';
                    $phone_value = '';
                    $role_selected = '';
                } else {
                    $result_message = "Error: Could not create user. Please try again.";
                    $result_class = "result-error";
                }
            }
        } else {
            $result_message = "Please correct the following errors:<br>";
            foreach ($registration_errors as $field => $error_msg) {
                $result_message .= "- " . htmlspecialchars($error_msg) . "<br>";
            }
            $result_class = "result-error";
        }
    }

    // Fetch users for the list
    $users = get_all_users($conn);

    // Close the connection after all operations are done
    $conn->close();

} else {
    // This handles the case if connection.php itself failed
    $result_message = "Error: Database connection failed for registration form. Please ensure 'connection.php' is correctly configured.";
    $result_class = "result-error";
    $list_error_message = "Error: Database connection failed for user list. Please ensure 'connection.php' is correctly configured.";
}
?>
<?php include "dashboard-top.php" ?>

		<?php include "sidebar_ad.php" ?>

			<main class="content">
				<div class="container-fluid p-0">
					<!-- users -->
					<h1 class="h3 mb-3">Users</h1>

					<!-- add new users -->
					<h1 class="h3 mb-3">Add New User</h1>

					<div class="row">
						<div class="col-12 col-lg-12 d-flex">
							<div class="card flex-fill">
								<div class="card-header">
									<h5 class="card-title mb-0">User Registration</h5>
								</div>
								<!-- Result Message Area -->
						        <?php
						            if (!empty($result_message)) {
						                echo "<div class=\"result {$result_class}\">{$result_message}</div>";
						            }
						        ?>
								<form action="#" method="POST" enctype="multipart/form-data">
					                <div class="card-body space-y-6">
                                        <div class="row g-3">
						                    <!-- Name Input -->
						                    <div class="col-12  col-md-6">
						                        <label for="name" class="form-label">Full Name</label>
						                        <input type="text" id="name" name="name" class="form-control" placeholder="Enter your full name" value="<?php echo htmlspecialchars($name_value); ?>" required>
						                        <?php if (isset($errors['name'])): ?><small class="text-danger mt-1"><?php echo htmlspecialchars($errors['name']); ?></small><?php endif; ?>
						                    </div>
						                    <!-- Role Select -->
						                    <div class="col-12 col-md-6">
						                        <label for="role" class="form-label">Select Role</label>
						                        <select id="role" name="role" class="form-select" required>
						                            <option value="" disabled <?php echo ($role_selected == '') ? 'selected' : ''; ?>>Choose a role</option>
						                            <option value="admin" <?php echo ($role_selected == 'admin') ? 'selected' : ''; ?>>Admin</option>
						                            <option value="teacher" <?php echo ($role_selected == 'teacher') ? 'selected' : ''; ?>>Teacher</option>
						                            <option value="student" <?php echo ($role_selected == 'student') ? 'selected' : ''; ?>>Student</option>
						                        </select>
						                        <?php if (isset($errors['role'])): ?><small class="text-danger mt-1"><?php echo htmlspecialchars($errors['role']); ?></small><?php endif; ?>
						                    </div>
						                    <!-- Email Input -->
						                    <div class="col-12 col-md-6">
						                        <label for="email" class="form-label">Email Address</label>
						                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email address" value="<?php echo htmlspecialchars($email_value); ?>" required>
						                        <?php if (isset($errors['email'])): ?><small class="text-danger mt-1"><?php echo htmlspecialchars($errors['email']); ?></small><?php endif; ?>
						                    </div>
						                    

						                    <!-- Phone Input -->
						                    <div class="col-12 col-md-6">
						                        <label for="phone" class="form-label">Phone Number</label>
						                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="e.g., +1234567890" pattern="[0-9]{10,15}" title="Phone number (10-15 digits)" value="<?php echo htmlspecialchars($phone_value); ?>">
						                        <small class="text-gray-500 mt-1 block">Optional: Enter a 10-15 digit phone number.</small>
						                        <?php if (isset($errors['phone'])): ?><small class="text-danger mt-1"><?php echo htmlspecialchars($errors['phone']); ?></small><?php endif; ?>
						                    </div>
						                    <!-- Password Input -->
						                    <div class="col-12 col-md-6">
						                        <label for="password" class="form-label">Password</label>
						                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter a strong password" required>
						                        <?php if (isset($errors['password'])): ?><small class="text-danger mt-1"><?php echo htmlspecialchars($errors['password']); ?></small><?php endif; ?>
						                    </div>

						                    <!-- Confirm Password Input -->
						                    <div class="col-12 col-md-6">
						                        <label for="confirm_password" class="form-label">Confirm Password</label>
						                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
						                        <?php if (isset($errors['confirm_password'])): ?><small class="text-danger mt-1"><?php echo htmlspecialchars($errors['confirm_password']); ?></small><?php endif; ?>
						                    </div>

						                    
						                </div>    
					                    <!-- Submit Button -->
							            <div class="text-center mt-3">
							                <button type="submit" class="btn btn-primary">
							                    Register User
							                </button>
							            </div>
					                </div>
						        </form>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-12 col-lg-12 d-flex">
							<div class="card flex-fill">
								<div class="card-header pb-0">

									<h5 class="card-title mb-0">Users list</h5>

									<?php if (!empty($error_message)): ?>
							            <div class="alert alert-danger" role="alert">
							                <?php echo htmlspecialchars($error_message); ?>
							            </div>
							        <?php endif; ?>
							        
								</div>
								<div class="card-body space-y-6">
									<table class="table table-hover my-0">
										<thead>
											<tr>
												<th>Name</th>
												<th class="d-none d-md-table-cell">Email</th>
												<th>Role</th>
												<th>Action</th>
											</tr>
										</thead>
										<tbody>
											<?php if (empty($users) && empty($error_message)): ?>
					                            <tr>
					                                <td colspan="5" class="px-4 py-4">No users found in the database.</td>
					                            </tr>
					                        <?php else: ?>
					                        <?php foreach ($users as $user): ?>
					                            <tr>
					                                <td><?php echo htmlspecialchars($user['name']); ?></td>
					                                <td class="d-none d-sm-table-cell"><?php echo htmlspecialchars($user['email']); ?></td>
					                                <td>
					                                    <?php
					                                        $role_class = '';
					                                        switch ($user['role']) {
					                                            case 'admin':
					                                                $role_class = 'role-admin-badge';
					                                                break;
					                                            case 'teacher':
					                                                $role_class = 'role-teacher-badge';
					                                                break;
					                                            case 'student':
					                                                $role_class = 'role-student-badge';
					                                                break;
					                                            default:
					                                                $role_class = 'badge bg-secondary'; // Default Bootstrap badge
					                                        }
					                                    ?>
					                                    <span class="role-badge <?php echo $role_class; ?>">
					                                        <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
					                                    </span>
					                                </td>
					                                <td class="action-buttons">
					                                    <a href="edit_user.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="edit-button">Edit</a>
					                                    <a href="delete_user.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="delete-button" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
					                                </td>
					                            </tr>
					                        <?php endforeach; ?>
					                    </tbody>
									</table>
								</div>	
							<?php endif; ?>
							</div>

						</div>
					</div>

					<!-- add new users -->
					<h1 class="h3 mb-3">Add New User</h1>

					<div class="row">
						<div class="col-12 col-lg-12 d-flex">
							<div class="card flex-fill">
								<div class="card-header">
									<h5 class="card-title mb-0">User Registration</h5>
								</div>
								<!-- Result Message Area -->
						        <?php
						            if (!empty($result_message)) {
						                echo "<div class=\"result {$result_class}\">{$result_message}</div>";
						            }
						        ?>
								<form action="#" method="POST" enctype="multipart/form-data">
					                <div class="card-body space-y-6">
                                        <div class="row g-3">
						                    <!-- Name Input -->
						                    <div class="col-12  col-md-6">
						                        <label for="name" class="form-label">Full Name</label>
						                        <input type="text" id="name" name="name" class="form-control" placeholder="Enter your full name" value="<?php echo htmlspecialchars($name_value); ?>" required>
						                        <?php if (isset($errors['name'])): ?><small class="text-danger mt-1"><?php echo htmlspecialchars($errors['name']); ?></small><?php endif; ?>
						                    </div>
						                    <!-- Role Select -->
						                    <div class="col-12 col-md-6">
						                        <label for="role" class="form-label">Select Role</label>
						                        <select id="role" name="role" class="form-select" required>
						                            <option value="" disabled <?php echo ($role_selected == '') ? 'selected' : ''; ?>>Choose a role</option>
						                            <option value="admin" <?php echo ($role_selected == 'admin') ? 'selected' : ''; ?>>Admin</option>
						                            <option value="teacher" <?php echo ($role_selected == 'teacher') ? 'selected' : ''; ?>>Teacher</option>
						                            <option value="student" <?php echo ($role_selected == 'student') ? 'selected' : ''; ?>>Student</option>
						                        </select>
						                        <?php if (isset($errors['role'])): ?><small class="text-danger mt-1"><?php echo htmlspecialchars($errors['role']); ?></small><?php endif; ?>
						                    </div>
						                    <!-- Email Input -->
						                    <div class="col-12 col-md-6">
						                        <label for="email" class="form-label">Email Address</label>
						                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email address" value="<?php echo htmlspecialchars($email_value); ?>" required>
						                        <?php if (isset($errors['email'])): ?><small class="text-danger mt-1"><?php echo htmlspecialchars($errors['email']); ?></small><?php endif; ?>
						                    </div>
						                    

						                    <!-- Phone Input -->
						                    <div class="col-12 col-md-6">
						                        <label for="phone" class="form-label">Phone Number</label>
						                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="e.g., +1234567890" pattern="[0-9]{10,15}" title="Phone number (10-15 digits)" value="<?php echo htmlspecialchars($phone_value); ?>">
						                        <small class="text-gray-500 mt-1 block">Optional: Enter a 10-15 digit phone number.</small>
						                        <?php if (isset($errors['phone'])): ?><small class="text-danger mt-1"><?php echo htmlspecialchars($errors['phone']); ?></small><?php endif; ?>
						                    </div>
						                    <!-- Password Input -->
						                    <div class="col-12 col-md-6">
						                        <label for="password" class="form-label">Password</label>
						                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter a strong password" required>
						                        <?php if (isset($errors['password'])): ?><small class="text-danger mt-1"><?php echo htmlspecialchars($errors['password']); ?></small><?php endif; ?>
						                    </div>

						                    <!-- Confirm Password Input -->
						                    <div class="col-12 col-md-6">
						                        <label for="confirm_password" class="form-label">Confirm Password</label>
						                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
						                        <?php if (isset($errors['confirm_password'])): ?><small class="text-danger mt-1"><?php echo htmlspecialchars($errors['confirm_password']); ?></small><?php endif; ?>
						                    </div>

						                    
						                </div>    
					                    <!-- Submit Button -->
							            <div class="text-center mt-3">
							                <button type="submit" class="btn btn-primary">
							                    Register User
							                </button>
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
        /* Custom CSS for Role Badges */
        .role-badge {
            display: inline-flex; /* inline-flex */
            padding-left: 0.5rem; /* px-2 */
            padding-right: 0.5rem; /* px-2 */
            font-size: 0.75rem; /* text-xs */
            line-height: 1.25rem; /* leading-5 */
            font-weight: 600; /* font-semibold */
            border-radius: 9999px; /* rounded-full */
        }

        .role-student-badge {
            background-color: #dbeafe; /* blue-100 */
            color: #1e40af; /* blue-800 */
        }

        .role-teacher-badge {
            background-color: #d1fae5; /* green-100 */
            color: #065f46; /* green-800 */
        }

        .role-admin-badge {
            background-color: #ede9fe; /* purple-100 */
            color: #6d28d9; /* purple-800 */
        }

        /* Custom CSS for Action Buttons */
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