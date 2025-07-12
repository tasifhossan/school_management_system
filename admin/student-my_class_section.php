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



$class_info = null;
$courses = [];
$error_message = '';
$student_name = '';

// Check if the connection object exists and there are no connection errors
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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

        // 3. Find the student's class enrollment (using MySQLi)
        $stmt_enrollment = $conn->prepare("
            SELECT c.id, c.name, c.section
            FROM student_class_enrollments sce
            JOIN classes c ON sce.class_id = c.id
            WHERE sce.student_id = ?
        ");
        $stmt_enrollment->bind_param("i", $student_id);
        $stmt_enrollment->execute();
        $result_enrollment = $stmt_enrollment->get_result();
        $class_info = $result_enrollment->fetch_assoc();
        $stmt_enrollment->close();


        if ($class_info) {
            $class_id = $class_info['id'];

            // 4. Fetch all course offerings for that class (using MySQLi)
            $stmt_courses = $conn->prepare("
                SELECT
                    co.id,
                    cr.course_code,
                    cr.name AS course_name,
                    t.name AS teacher_name,
                    co.semester,
                    co.meeting_time,
                    co.location
                FROM class_course_offerings cco
                JOIN course_offerings co ON cco.course_offering_id = co.id
                JOIN courses cr ON co.course_id = cr.id
                LEFT JOIN teachers tech ON co.teacher_id = tech.id
                LEFT JOIN users t ON tech.user_id = t.id
                WHERE cco.class_id = ?
            ");
            $stmt_courses->bind_param("i", $class_id);
            $stmt_courses->execute();
            $result_courses = $stmt_courses->get_result();
            $courses = $result_courses->fetch_all(MYSQLI_ASSOC);
            $stmt_courses->close();

        } else {
            $error_message = "You are not currently enrolled in any class.";
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

					<h1 class="h3 mb-3">My Class and Courses</h1>

					<!-- <h5 class="card-title mb-0">We're currently putting the finishing touches on this section of our website. Please check back again shortly. 
					<br><br> In the meantime, you can head back to our <a href="dashboard.php" class="btn px-0">Dashboard</a> or visit our Blog for our latest updates. 
											
					</h5> -->

					<div class="row">
						<div class="col-12">
							<div class="card">
								<div class="card-header">
									<h5 class="card-title mb-0">Card Header
									</h5>
								</div>
								<div class="card-body">
						            <?php if ($error_message): ?>
						                <div class="alert alert-danger">
						                    <?php echo htmlspecialchars($error_message); ?>
						                </div>
						            <?php elseif ($class_info): ?>
						                <h4 class="card-title">Welcome, <?php echo htmlspecialchars($student_name); ?>!</h4>
						                <p class="lead">Here is the information about your assigned class and courses for the current semester.</p>

						                <div class="alert alert-info">
						                    <h5>
						                        <strong>Class:</strong> <?php echo htmlspecialchars($class_info['name']); ?>
						                        <br>
						                        <strong>Section:</strong> <?php echo htmlspecialchars($class_info['section']); ?>
						                    </h5>
						                </div>

						                <hr>

						                <h5>Your Courses:</h5>
						                <?php if (!empty($courses)): ?>
						                    <div class="table-responsive">
						                        <table class="table table-bordered table-hover">
						                            <thead class="table-light">
						                                <tr>
						                                    <th>Course Code</th>
						                                    <th>Course Name</th>
						                                    <th>Instructor</th>
						                                    <th>Semester</th>
						                                    <th>Meeting Time</th>
						                                    <th>Location</th>
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
						                                    </tr>
						                                <?php endforeach; ?>
						                            </tbody>
						                        </table>
						                    </div>
						                <?php else: ?>
						                    <div class="alert alert-warning">
						                        No courses are currently assigned to your class. Please check back later.
						                    </div>
						                <?php endif; ?>

						            <?php else: ?>
						                 <div class="alert alert-warning">
						                    No class information found. Please contact administration.
						                </div>
						            <?php endif; ?>
						        </div>
							</div>
						</div>
					</div>

				</div>
			</main>

			<?php include "footer.php" ?>

</body>

</html>