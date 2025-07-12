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
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'student';
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


// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php"); // Redirect to login if not logged in or not a student
    exit();
}
// Use the actual user_id from the session
$user_id = $_SESSION['user_id'];

$courses = [];
$error_message = '';
$student_name = '';

try {
    // 1. Get the student's name from the users table (using MySQLi)
    $stmt_user = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $user_result = $result_user->fetch_assoc();
    if ($user_result) {
        $student_name = $user_result['name'];
    }
    $stmt_user->close();

    // 2. Find the student's ID from their user ID (using MySQLi)
    $stmt_student = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt_student->bind_param("i", $user_id);
    $stmt_student->execute();
    $result_student = $stmt_student->get_result();
    $student_result = $result_student->fetch_assoc();
    $stmt_student->close();


    if ($student_result) {
        $student_id = $student_result['id'];

        // 3. Fetch all courses the specific student is enrolled in (using student_course_enrollments)
        $stmt_courses = $conn->prepare("
            SELECT
                cr.course_code,
                cr.name AS course_name,
                t.name AS teacher_name,
                co.semester,
                co.meeting_time,
                co.location,
                scen.grade
            FROM student_course_enrollments scen
            JOIN course_offerings co ON scen.course_offering_id = co.id
            JOIN courses cr ON co.course_id = cr.id
            LEFT JOIN teachers tech ON co.teacher_id = tech.id
            LEFT JOIN users t ON tech.user_id = t.id
            WHERE scen.student_id = ?
        ");
        $stmt_courses->bind_param("i", $student_id);
        $stmt_courses->execute();
        $result_courses = $stmt_courses->get_result();
        $courses = $result_courses->fetch_all(MYSQLI_ASSOC);
        $stmt_courses->close();
        
        if (empty($courses)) {
             $error_message = "You are not currently enrolled in any course.";
        }

    } else {
        $error_message = "No student profile found for your user account.";
    }
} catch (Exception $e) {
    // Handle potential exceptions
    $error_message = "An error occurred: " . $e->getMessage();
}

$conn->close();
?>
<?php include "dashboard-top.php" ?>

		<?php include "sidebar_student.php" ?>
			<main class="content">
				<div class="container-fluid p-0">

					<h1 class="h3 mb-3">My Enrolled Courses</h1>

					<!-- <h5 class="card-title mb-0">We're currently putting the finishing touches on this section of our website. Please check back again shortly. 
					<br><br> In the meantime, you can head back to our <a href="dashboard.php" class="btn px-0">Dashboard</a> or visit our Blog for our latest updates. 
											
					</h5> -->

					<div class="row">
						<div class="col-12">
							<div class="card">
								<!-- <div class="card-header">
									<h5 class="card-title mb-0">
									</h5>
								</div> -->
								<div class="card-body">
						            <?php if ($error_message): ?>
						                <div class="alert alert-danger">
						                    <?php echo htmlspecialchars($error_message); ?>
						                </div>
						            <?php else: ?>
						                <!-- <h4 class="card-title">Welcome, <?php //echo htmlspecialchars($student_name); ?>!</h4> 
						                <p class="lead ">Here is the list of courses you are enrolled in for the current semester.</p>

						                 <hr> -->

						                <?php if (!empty($courses)): ?>
						                    <div class="table-responsive">
						                        <table class="table table-bordered table-hover">
						                            <thead class="table-light">
						                                <tr>
						                                    <th>Course Code</th>
						                                    <th>Course Name</th>
						                                    <th>Instructor</th>
						                                    <th>Semester</th>
						                                    <th>Class Time</th>
						                                    <th>Location</th>
						                                    <th>Grade</th>
						                                </tr>
						                            </thead>
						                            <tbody>
						                                <?php foreach ($courses as $course): ?>
						                                    <tr>
						                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
						                                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
						                                        <td><?php echo htmlspecialchars($course['teacher_name'] ?? 'TBD'); ?></td>
						                                        <td><?php echo htmlspecialchars($course['semester']); ?></td>
						                                        <td><?php echo htmlspecialchars($course['meeting_time']); ?></td>
						                                        <td><?php echo htmlspecialchars($course['location']); ?></td>
						                                        <td><?php echo htmlspecialchars($course['grade'] ?? 'N/A'); ?></td>
						                                    </tr>
						                                <?php endforeach; ?>
						                            </tbody>
						                        </table>
						                    </div>
						                <?php else: ?>
						                    <!-- This block might not be reached due to the check above, but is good for robustness -->
						                    <div class="alert alert-warning">
						                        You are not currently enrolled in any courses. Please contact your advisor.
						                    </div>
						                <?php endif; ?>

						            <?php endif; ?>
						        </div>
							</div>
						</div>
					</div>

				</div>
			</main>

			<?php include "footer.php"; ?>
<style>
    .result { padding: 1rem; border-radius: 0.5rem; border: 1px solid transparent; }
    .result-success { color: #0f5132; background-color: #d1e7dd; border-color: #badbcc; }
    .result-error { color: #842029; background-color: #f8d7da; border-color: #f5c2c7; }
    .action-buttons a { margin-right: 0.25rem; }
    th a { text-decoration: none; color: inherit; }
    th a:hover { color: #0d6efd; }
</style>			

</body>

</html>