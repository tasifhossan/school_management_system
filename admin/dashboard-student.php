<?php  

session_start();
// db connection
include "../lib/connection.php";

// Check if the user is logged in and has the role of 'student'.
// If not, redirect them to the login page.
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

$student = null;

$errorMessage = '';
$enrolledCourses = []; // Initialize as an empty array
$announcements = []; // Initialize as an empty array

// Prepare and execute the query to get the student's complete profile
$sql_student = "SELECT  
                    u.name, u.email, u.role,
                    s.id AS student_id, s.roll_number, s.date_of_birth, s.gender, 
                    s.address, s.phone, s.guardian_name, s.enrollment_date, s.photo
                FROM users u
                JOIN students s ON u.id = s.user_id
                WHERE u.id = ?";

$stmt_student = $conn->prepare($sql_student);
if ($stmt_student === false) {
    die("Error preparing the statement: " . $conn->error);
}
$stmt_student->bind_param("i", $userId);
$stmt_student->execute();
$result_student = $stmt_student->get_result();

if ($result_student->num_rows > 0) {
    $student = $result_student->fetch_assoc();
    $student_id = $student['student_id'];
    
    // --- DYNAMICALLY FETCH ENROLLED COURSES ---
    $sql_courses = "SELECT c.name, c.course_code, u.name as teacher_name 
                    FROM courses c 
                    JOIN course_offerings co ON c.id = co.course_id 
                    JOIN student_course_enrollments sce ON co.id = sce.course_offering_id 
                    JOIN teachers t ON co.teacher_id = t.id 
                    JOIN users u ON t.user_id = u.id 
                    WHERE sce.student_id = ?";

    $stmt_courses = $conn->prepare($sql_courses);
    if($stmt_courses){
        $stmt_courses->bind_param("i", $student_id);
        $stmt_courses->execute();
        $result_courses = $stmt_courses->get_result();
        $enrolledCourses = $result_courses->fetch_all(MYSQLI_ASSOC);
        $stmt_courses->close();
    }
    
    // --- DYNAMICALLY FETCH RECENT ANNOUNCEMENTS ---
    $sql_announcements = "SELECT title, created_at FROM announcements ORDER BY created_at DESC LIMIT 3";
    $stmt_announcements = $conn->prepare($sql_announcements);
    if ($stmt_announcements) {
        $stmt_announcements->execute();
        $result_announcements = $stmt_announcements->get_result();
        $announcements = $result_announcements->fetch_all(MYSQLI_ASSOC);
        $stmt_announcements->close();
    }
    
} else {
    $errorMessage = "Student profile not found. Please contact an administrator.";
}
$stmt_student->close();


// --- Placeholder Data & Logic for Other Dashboard Sections ---
$enrolledCoursesCount = count($enrolledCourses); // This is now dynamic
$attendancePercentage = "95%"; 
$gpa = "3.8"; 
$upcomingAssignmentsCount = 3;

// UPCOMING ASSIGNMENTS (Example Query)
// SQL: "SELECT a.title, c.name AS course_name, a.due_date FROM assignments a JOIN course_offerings co ON a.course_offering_id = co.id JOIN courses c ON co.course_id = c.id JOIN student_course_enrollments sce ON sce.course_offering_id = co.id WHERE sce.student_id = ? AND a.due_date >= CURDATE() ORDER BY a.due_date ASC LIMIT 3"
$assignments = [
    ['title' => 'Calculus Problem Set 3', 'course' => 'Calculus I', 'due_date' => '2025-10-20'],
    ['title' => 'History Essay: The Roman Empire', 'course' => 'World History', 'due_date' => '2025-10-22'],
    ['title' => 'Physics Lab Report', 'course' => 'Physics 101', 'due_date' => '2025-10-25']
];

$conn->close();

?>
<?php include "dashboard-top.php" ?>

        <?php include "sidebar_student.php" ?>

        <main class="content">
            <div class="container-fluid p-0">

                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php elseif ($student): ?>
                    <div class="mb-4">
                        <h1 class="h3 d-inline align-middle">Student Dashboard</h1>
                    </div>

                    <!-- Quick Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title text-muted">Courses Enrolled</h5>
                                    <h1 class="display-5"><?php echo $enrolledCoursesCount; ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card shadow-sm" style="border-left-color: #198754;">
                                <div class="card-body">
                                    <h5 class="card-title text-muted">Attendance</h5>
                                    <h1 class="display-5"><?php echo $attendancePercentage; ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card shadow-sm" style="border-left-color: #ffc107;">
                                <div class="card-body">
                                    <h5 class="card-title text-muted">Overall GPA</h5>
                                    <h1 class="display-5"><?php echo $gpa; ?></h1>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card shadow-sm" style="border-left-color: #dc3545;">
                                <div class="card-body">
                                    <h5 class="card-title text-muted">Upcoming</h5>
                                    <h1 class="display-5"><?php echo $upcomingAssignmentsCount; ?></h1>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <!-- Left Column: Profile -->
                        <div class="col-lg-4">
                            <div class="card shadow-sm">
                                <div class="card-body text-center">
                                    <?php 
                                        $photoPath = !empty($student['photo']) && file_exists($student['photo']) ? $student['photo'] : 'https://placehold.co/128x128/E8E8E8/484848?text=No+Photo';
                                    ?>
                                    <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="<?php echo htmlspecialchars($student['name']); ?>" class="img-fluid rounded-circle mb-2" width="128" height="128" />
                                    <h5 class="card-title mt-2"><?php echo htmlspecialchars($student['name']); ?></h5>
                                    <div class="text-muted mb-3"><?php echo htmlspecialchars(ucfirst($student['role'])); ?></div>
                                    <a href="my_profile.php" class="btn btn-primary btn-sm">See Profile</a>
                                </div>
                                <hr class="my-0">
                                <div class="card-body">
                                    <h5 class="h6 card-title">About</h5>
                                    <ul class="list-unstyled mb-0">
                                        <?php if (!empty($student['address'])): ?>
                                            <li class="mb-2"><span data-feather="home" class="feather-sm me-2"></span> Lives in: <strong><?php echo htmlspecialchars($student['address']); ?></strong></li>
                                        <?php endif; ?>
                                         <?php if (!empty($student['email'])): ?>
                                            <li class="mb-2"><span data-feather="mail" class="feather-sm me-2"></span> Email: <strong><?php echo htmlspecialchars($student['email']); ?></strong></li>
                                        <?php endif; ?>
                                        <?php if (!empty($student['phone'])): ?>
                                            <li class="mb-2"><span data-feather="phone" class="feather-sm me-2"></span> Phone: <strong><?php echo htmlspecialchars($student['phone']); ?></strong></li>
                                        <?php endif; ?>
                                        <?php if (!empty($student['roll_number'])): ?>
                                            <li class="mb-2"><span data-feather="hash" class="feather-sm me-2"></span> Roll Number: <strong><?php echo htmlspecialchars($student['roll_number']); ?></strong></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Activities -->
                        <div class="col-lg-8">
                             <!-- Upcoming Deadlines -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Upcoming Deadlines</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <?php if (!empty($assignments)): ?>
                                            <?php foreach ($assignments as $assignment): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($assignment['title']); ?></strong>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($assignment['course']); ?></div>
                                                </div>
                                                <span class="badge bg-danger rounded-pill"><?php echo date('M d', strtotime($assignment['due_date'])); ?></span>
                                            </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li class="list-group-item">No upcoming deadlines.</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                            
                             <!-- Recent Announcements -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Recent Announcements</h5>
                                </div>
                                 <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <?php if (!empty($announcements)): ?>
                                            <?php foreach ($announcements as $announcement): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($announcement['title']); ?>
                                                <small class="text-muted"><?php echo date('d M, Y', strtotime($announcement['created_at'])); ?></small>
                                            </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li class="list-group-item">No recent announcements found.</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                            <!-- My Courses -->
                            <div class="card shadow-sm">
                                 <div class="card-header">
                                    <h5 class="card-title mb-0">My Courses</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Course Name</th>
                                                    <th>Code</th>
                                                    <th>Instructor</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($enrolledCourses)): ?>
                                                    <?php foreach ($enrolledCourses as $course): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($course['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                                        <td><?php echo htmlspecialchars($course['teacher_name']); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center">You are not currently enrolled in any courses.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
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

	<style>
        /* A little custom styling to match the template's look */
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
        .stat-card {
            border-left: 4px solid #0d6efd;
        }
        .list-group-item {
            border: none;
            padding-left: 0;
        }
    </style>

	<script src="js/app.js"></script>

</body>

</html>