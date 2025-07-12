<?php
// ALWAYS start the session at the very beginning of the file
session_start();

// If a user is already logged in, they shouldn't see the login page again.
// Redirect them straight to the main dashboard.
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    // We can also add role-based redirection here for already logged-in users
    $redirect_url = 'index.php'; // Default page
    if (isset($_SESSION['role'])) {
        switch ($_SESSION['role']) {
            case 'admin':
                $redirect_url = 'dashboard.php';
                break;
            case 'teacher':
                $redirect_url = 'dashboard-teacher.php';
                break;
            case 'student':
                $redirect_url = 'dashboard-student.php';
                break;
        }
    }
    header("Location: " . $redirect_url);
    exit; // Stop script execution after redirection
}

// Include the database connection file
// Make sure the path is correct relative to your file structure
include "../lib/connection.php";

// Initialize a variable to hold notification messages (e.g., for errors)
$notify = '';

// --- FORM SUBMISSION LOGIC ---
// Check if the form was submitted by checking for the 'login' POST variable
if (isset($_POST['login'])) {
    
    // Get the email and password from the form submission
    $email = $_POST['i_email'];
    $password = $_POST['i_pass'];

    // --- Basic Input Validation ---
    if (empty($email) || empty($password)) {
        // If either field is empty, prepare an error message
        $notify = '<div class="alert alert-danger" role="alert">Email and password are required.</div>';
    } else {
        // --- Database Interaction ---
        // Prepare a SQL statement with placeholders (?) to prevent SQL injection attacks
        $sql = "SELECT id, name, password, role FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        
        // Check if the statement was prepared successfully
        if ($stmt) {
            // Bind the user's email to the placeholder in the SQL statement
            $stmt->bind_param("s", $email);
            
            // Execute the prepared statement
            $stmt->execute();
            
            // Get the result of the query
            $result = $stmt->get_result();

            // Check if a user with that email exists (should be exactly one)
            if ($result->num_rows === 1) {
                // Fetch the user's data as an associative array
                $user = $result->fetch_assoc();

                // --- Password Verification ---
                // Use password_verify() to securely check the submitted password against the stored hash.
                if (password_verify($password, $user['password'])) {

                    // --- Login Success: Set Session ---
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];

                    // --- NEW: ROLE-BASED REDIRECTION ---
                    $redirect_url = 'index.php'; // Default page
                    switch ($user['role']) {
                        case 'admin':
                            $redirect_url = 'dashboard.php';
                            break;
                        case 'teacher':
                            $redirect_url = 'dashboard-teacher.php';
                            break;
                        case 'student':
                            $redirect_url = 'dashboard-student.php';
                            break;
                        // The default case handles any other roles or unexpected values
                    }
                    header("Location: " . $redirect_url);
                    exit; // IMPORTANT: Stop script after redirect

                } else {
                    // --- Login Failure: Incorrect Password ---
                    $notify = '<div class="alert alert-danger" role="alert">Invalid email or password.</div>';
                }
            } else {
                // --- Login Failure: No User Found ---
                $notify = '<div class="alert alert-danger" role="alert">Invalid email or password.</div>';
            }
            // Close the statement
            $stmt->close();
        } else {
            // Error if the statement couldn't be prepared
            $notify = '<div class="alert alert-danger" role="alert">Database query failed. Please try again later.</div>';
        }
        // Close the database connection
        $conn->close();
    }
}
?>
<!-- The HTML part of your page starts here -->
<?php include "dashboard-top.php" ?>
<body>
	<main class="d-flex w-100">
		<div class="container d-flex flex-column">
			<div class="row vh-100">
				<div class="col-sm-10 col-md-8 col-lg-6 col-xl-5 mx-auto d-table h-100">
					<div class="d-table-cell align-middle">

						<div class="text-center mt-4">
							<h1 class="h2">Welcome back!</h1>
							<p class="lead">
								Sign in to your account to continue
							</p>
						</div>

						<div class="card">
							<div class="card-body">
								<div class="m-sm-3">
                                    <!-- The form submits to the current page itself.
                                         htmlspecialchars() is used to prevent XSS attacks. -->
									<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
										<div class="text-center text-danger mb-3">
                                            <!-- This is where error/success messages will be displayed -->
                                            <?php echo $notify; ?>
                                            	
                                        </div>
										<div class="mb-3">
											<label class="form-label" for="inputEmail">Email</label>
											<input class="form-control form-control-lg" type="email" name="i_email" id="inputEmail" placeholder="Enter your email" required />
										</div>
										<div class="mb-3">
											<label class="form-label" for="inputPassword">Password</label>
											<input class="form-control form-control-lg" type="password" name="i_pass" id="inputPassword" placeholder="Enter your password" required />
										</div>
										<div>
											<div class="form-check align-items-center">
												<input id="inputRememberPassword" type="checkbox" class="form-check-input" value="remember-me" name="r_pass" checked>
												<label class="form-check-label text-small" for="inputRememberPassword">Remember me</label>
											</div>
										</div>
										<div class="d-grid gap-2 mt-3">
											<input name="login" class="btn btn-lg btn-primary" type="submit" value="Sign in">
										</div>
									</form>
								</div>
							</div>
						</div>
						<div class="text-center mb-3">
							Don't have an account? <a href="sign-up.php">Sign up</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>

	<script src="js/app.js"></script>

</body>

</html>
