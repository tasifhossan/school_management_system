<?php  

session_start();
// db connection
include "../lib/connection.php";
// Check if the user is logged in and is a student.
// You might have a $_SESSION['role'] check here as well if you want to be more specific.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php"); // Redirect to login page
    exit();
}


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

// $userId = 5;
$student = null;

// Prepare and execute the query to get the student's complete profile
$sql = "SELECT 
            u.name, 
            u.email,
            u.role,
            s.id AS student_id,
            s.roll_number,
            s.date_of_birth,
            s.gender,
            s.address,
            s.phone,
            s.guardian_name,
            s.enrollment_date,
            s.photo
        FROM users u
        JOIN students s ON u.id = s.user_id
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Error preparing the statement: " . $conn->error);
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
} else {
    $errorMessage = "Student profile not found. Please contact an administrator.";
}

$stmt->close();
$conn->close();

?>
<?php include "dashboard-top.php" ?>

		<?php include "sidebar_student.php" ?>

			<main class="content">
		        <div class="container-fluid p-0">

		            <?php if (isset($errorMessage)): ?>
		                <div class="alert alert-danger">
		                    <?php echo htmlspecialchars($errorMessage); ?>
		                </div>
		            <?php elseif ($student): ?>
		                <div class="mb-3">
		                    <h1 class="h3 d-inline align-middle">My Profile</h1>
		                </div>
		                <div class="row">
		                    <div class="col-12">
		                        <div class="card">
		                            <div class="card-body">
		                                <div class="row">
		                                    <div class="col-md-4 text-center">
		                                        <?php 
		                                            $photoPath = !empty($student['photo']) && file_exists($student['photo']) ? $student['photo'] : 'https://placehold.co/128x128/E8E8E8/484848?text=No+Photo';
		                                        ?>
		                                        <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="<?php echo htmlspecialchars($student['name']); ?>" class="img-fluid rounded-circle mb-2" width="128" height="128" />
		                                        <h5 class="card-title mt-2"><?php echo htmlspecialchars($student['name']); ?></h5>
		                                        <div class="text-muted mb-2"><?php echo htmlspecialchars(ucfirst($student['role'])); ?></div>
		                                    </div>
		                                    <div class="col-md-8">
		                                        <div class="d-flex justify-content-between align-items-center mb-2">
		                                            <h5 class="h6 card-title mb-0">About</h5>
		                                            <a href="edit-my-profile.php?id=<?php echo htmlspecialchars($userId); ?>" class="btn btn-primary btn-sm">Edit Profile</a>
		                                        </div>
		                                        <ul class="list-unstyled mb-0">
		                                            <?php if (!empty($student['address'])): ?>
		                                                <li class="mb-2"><span data-feather="home" class="feather-sm me-2"></span> Lives in: <strong><?php echo htmlspecialchars($student['address']); ?></strong></li>
		                                            <?php endif; ?>

		                                            <?php if (!empty($student['phone'])): ?>
		                                                <li class="mb-2"><span data-feather="phone" class="feather-sm me-2"></span> Phone: <strong><?php echo htmlspecialchars($student['phone']); ?></strong></li>
		                                            <?php endif; ?>
		                                            
		                                            <?php if (!empty($student['email'])): ?>
		                                                <li class="mb-2"><span data-feather="mail" class="feather-sm me-2"></span> Email: <strong><?php echo htmlspecialchars($student['email']); ?></strong></li>
		                                            <?php endif; ?>

		                                            <?php if (!empty($student['roll_number'])): ?>
		                                                <li class="mb-2"><span data-feather="hash" class="feather-sm me-2"></span> Roll Number: <strong><?php echo htmlspecialchars($student['roll_number']); ?></strong></li>
		                                            <?php endif; ?>

		                                            <?php if (!empty($student['date_of_birth'])): ?>
		                                                <li class="mb-2"><span data-feather="calendar" class="feather-sm me-2"></span> Date of Birth: <strong><?php echo htmlspecialchars(date('F j, Y', strtotime($student['date_of_birth']))); ?></strong></li>
		                                            <?php endif; ?>
		                                        </ul>
		                                    </div>
		                                </div>
		                                <hr />
		                                <div class="row">
		                                     <div class="col-12">
		                                        <h5 class="card-title mb-3">Enrolled Courses & Activities</h5>
		                                        <p>This section is currently under development.</p>
		                                        <p>Information about your enrolled courses, recent grades, and attendance will be displayed here soon.</p>
		                                    </div>
		                                </div>
		                            </div>
		                        </div>
		                    </div>
		                </div>
		            <?php else: ?>
		                <div class="alert alert-warning">Could not load profile data.</div>
		            <?php endif; ?>
		        </div>
		    </main>

			<?php include "footer.php" ?>

	<style>
        /* A little custom styling to match the template's look */
        body {
            background-color: #f5f7fb;
        }
        .content {
            padding: 2rem;
        }
        .card-title {
            font-weight: 600;
        }
        .feather-sm {
            width: 1rem;
            height: 1rem;
        }
    </style>

	<script src="js/app.js"></script>

</body>

</html>