<?php
session_start();

include "../lib/connection.php";
// Use the session check you provided
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

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

$teacher = null;
$errorMessage = '';

// Prepare the SQL query to get the teacher's complete profile.
// -- FIX: Added 't.photo' to the SELECT statement to fetch the image from the teachers table.
$sql = "SELECT 
            u.name, 
            u.email,
            u.role,
            u.phone,
            t.id AS teacher_id,
            t.department,
            t.hire_date,
            t.employee_id,
            t.qualifications,
            t.specialization,
            t.office_location,
            t.office_hours,
            t.photo
        FROM users u
        JOIN teachers t ON u.id = t.user_id
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);

// Check if the statement was prepared successfully
if ($stmt === false) {
    // This is a developer-level error, should be logged.
    die("Error preparing the statement: " . $conn->error);
}

// Bind the user ID parameter to the prepared statement
$stmt->bind_param("i", $userId);

// Execute the query
$stmt->execute();
$result = $stmt->get_result();

// Check if a profile was found
if ($result->num_rows > 0) {
    // Fetch the teacher's data as an associative array
    $teacher = $result->fetch_assoc();
} else {
    // Set an error message if no profile is found for the user ID
    $errorMessage = "Teacher profile not found. Please contact an administrator.";
}

// Close the statement and the database connection
$stmt->close();
$conn->close();
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

            <main class="content">
                <div class="container-fluid p-0">

                    <?php if (!empty($errorMessage)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($errorMessage); ?>
                        </div>
                    <?php elseif ($teacher): ?>
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
                                                    // FIX: Logic to display teacher photo from 'teachers' table or a default avatar.
                                                    $photoPath = !empty($teacher['photo']) && file_exists($teacher['photo']) ? $teacher['photo'] : 'img/avatars/avatar.jpg';
                                                ?>
                                                <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="<?php echo htmlspecialchars($teacher['name']); ?>" class="img-fluid rounded-circle mb-2" width="128" height="128" />
                                                <h5 class="card-title mt-2"><?php echo htmlspecialchars($teacher['name']); ?></h5>
                                                <div class="text-muted mb-2"><?php echo htmlspecialchars(ucfirst($teacher['role'])); ?></div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h5 class="h6 card-title mb-0">About</h5>
                                                    <a href="edit-teacher-my-profile.php?id=<?php echo htmlspecialchars($userId); ?>" class="btn btn-primary btn-sm">Edit Profile</a>
                                                </div>
                                                <ul class="list-unstyled mb-0">
                                                    <?php if (!empty($teacher['employee_id'])): ?>
                                                        <li class="mb-2"><span data-feather="hash" class="feather-sm me-2"></span> Employee ID: <strong><?php echo htmlspecialchars($teacher['employee_id']); ?></strong></li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($teacher['email'])): ?>
                                                        <li class="mb-2"><span data-feather="mail" class="feather-sm me-2"></span> Email: <strong><?php echo htmlspecialchars($teacher['email']); ?></strong></li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($teacher['phone'])): ?>
                                                        <li class="mb-2"><span data-feather="phone" class="feather-sm me-2"></span> Phone: <strong><?php echo htmlspecialchars($teacher['phone']); ?></strong></li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($teacher['department'])): ?>
                                                        <li class="mb-2"><span data-feather="book-open" class="feather-sm me-2"></span> Department: <strong><?php echo htmlspecialchars($teacher['department']); ?></strong></li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($teacher['hire_date'])): ?>
                                                        <li class="mb-2"><span data-feather="calendar" class="feather-sm me-2"></span> Hire Date: <strong><?php echo htmlspecialchars(date('F j, Y', strtotime($teacher['hire_date']))); ?></strong></li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($teacher['office_location'])): ?>
                                                        <li class="mb-2"><span data-feather="map-pin" class="feather-sm me-2"></span> Office: <strong><?php echo htmlspecialchars($teacher['office_location']); ?></strong></li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($teacher['office_hours'])): ?>
                                                        <li class="mb-2"><span data-feather="clock" class="feather-sm me-2"></span> Office Hours: <strong><?php echo htmlspecialchars($teacher['office_hours']); ?></strong></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        <hr />
                                        
                                        <!-- Qualifications and Specialization Section -->
                                        <div class="row">
                                            <div class="col-12">
                                                <h5 class="card-title mb-3">Professional Details</h5>
                                                <?php if (!empty($teacher['qualifications'])): ?>
                                                    <h6 class="h6 card-title mt-3">Qualifications</h6>
                                                    <p><?php echo nl2br(htmlspecialchars($teacher['qualifications'])); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($teacher['specialization'])): ?>
                                                    <h6 class="h6 card-title mt-3">Specialization</h6>
                                                    <p><?php echo nl2br(htmlspecialchars($teacher['specialization'])); ?></p>
                                                <?php endif; ?>
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
<?php include "footer.php"; ?>

    <style>
        /* Custom styling to match the dashboard's aesthetic */
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