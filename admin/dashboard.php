<?php
session_start();
include_once('../lib/connection.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Admin';

// --- Start of Data Fetching for Dashboard Widgets ---

// 1. KPI Cards Data
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$total_teachers = $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
$student_teacher_ratio = $total_teachers > 0 ? round($total_students / $total_teachers, 1) . ':1' : 'N/A';

// FIX: Handle potential null value from AVG(grade) query to prevent deprecated warning.
$school_wide_gpa_result = $conn->query("SELECT AVG(grade) as avg_gpa FROM student_course_enrollments WHERE grade IS NOT NULL AND grade > 0");
$gpa_row = $school_wide_gpa_result ? $school_wide_gpa_result->fetch_assoc() : null;
$school_wide_gpa = ($gpa_row && $gpa_row['avg_gpa'] !== null) ? number_format($gpa_row['avg_gpa'], 2) : 'N/A';


$present_today_result = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE status = 'present' AND DATE(attendance_date) = CURDATE()");
$present_today = $present_today_result ? $present_today_result->fetch_assoc()['count'] : 0;
$total_att_today_result = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM attendance WHERE DATE(attendance_date) = CURDATE()");
$total_att_today = $total_att_today_result ? $total_att_today_result->fetch_assoc()['count'] : 0;
$attendance_percentage_today = $total_att_today > 0 ? round(($present_today / $total_att_today) * 100) : 0;

$pending_payments_count = $conn->query("SELECT COUNT(*) as count FROM student_payments WHERE status = 'pending'")->fetch_assoc()['count'];


// 2. Enrollment Trend Chart Data
$enrollment_trend_result = $conn->query("SELECT DATE_FORMAT(enrollment_date, '%Y-%m') as month, COUNT(*) as count FROM students WHERE enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY month ORDER BY month ASC");
$chart_labels_enrollment = [];
$chart_data_enrollment = [];
if ($enrollment_trend_result) {
    while($row = $enrollment_trend_result->fetch_assoc()) {
        $chart_labels_enrollment[] = date("M Y", strtotime($row['month'] . "-01"));
        $chart_data_enrollment[] = $row['count'];
    }
}

// 3. Gender Distribution Chart Data
$gender_result = $conn->query("SELECT gender, COUNT(*) as count FROM students GROUP BY gender");
$chart_labels_gender = [];
$chart_data_gender = [];
if ($gender_result) {
    while($row = $gender_result->fetch_assoc()) {
        $chart_labels_gender[] = ucfirst($row['gender']);
        $chart_data_gender[] = $row['count'];
    }
}

// 4. Course Popularity Chart Data
$course_popularity_result = $conn->query("SELECT c.name, COUNT(sce.student_id) as enrollment_count FROM student_course_enrollments sce JOIN course_offerings co ON sce.course_offering_id = co.id JOIN courses c ON co.course_id = c.id GROUP BY c.id ORDER BY enrollment_count DESC LIMIT 5");
$chart_labels_courses = [];
$chart_data_courses = [];
if ($course_popularity_result) {
    while($row = $course_popularity_result->fetch_assoc()) {
        $chart_labels_courses[] = $row['name'];
        $chart_data_courses[] = $row['enrollment_count'];
    }
}

// 5. Recent Activity Feed
$recent_activity_query = "
    (SELECT 'student' as type, u.name as subject, 'registered as a new student.' as action, s.enrollment_date as activity_date FROM students s JOIN users u ON s.user_id = u.id)
    UNION ALL
    (SELECT 'assignment' as type, t.name as subject, CONCAT('created new assignment ''', a.title, ''' for ', c.name) as action, a.due_date as activity_date FROM assignments a JOIN course_offerings co ON a.course_offering_id = co.id JOIN courses c ON co.course_id = c.id JOIN teachers tech ON co.teacher_id = tech.id JOIN users t ON tech.user_id = t.id)
    UNION ALL
    (SELECT 'payment' as type, u.name as subject, CONCAT('submitted a new payment of $', sp.amount) as action, sp.payment_date as activity_date FROM student_payments sp JOIN students s ON sp.student_id = s.id JOIN users u ON s.user_id = u.id WHERE sp.status = 'pending')
    ORDER BY activity_date DESC
    LIMIT 5";
$recent_activity = $conn->query($recent_activity_query);

// 6. Top Performing Courses
$top_courses_query = "SELECT c.name, AVG(sce.grade) as avg_grade FROM student_course_enrollments sce JOIN course_offerings co ON sce.course_offering_id = co.id JOIN courses c ON co.course_id = c.id WHERE sce.grade IS NOT NULL GROUP BY c.id ORDER BY avg_grade DESC LIMIT 5";
$top_courses = $conn->query($top_courses_query);

// 7. Class Capacity Monitor
// IMPORTANT: Assumes a 'capacity' column in 'course_offerings'. Using 30 as a placeholder.
// Add `capacity` to your table for this to be accurate: ALTER TABLE course_offerings ADD capacity INT DEFAULT 30;
$class_capacity_query = "SELECT c.name, COUNT(sce.student_id) as enrolled, 30 as capacity FROM course_offerings co JOIN courses c ON co.course_id = c.id LEFT JOIN student_course_enrollments sce ON co.id = sce.course_offering_id GROUP BY co.id ORDER BY (enrolled/capacity) DESC, enrolled DESC LIMIT 5";
$class_capacities = $conn->query($class_capacity_query);

// 8. Latest Announcements
$latest_announcements_query = "SELECT title, created_at FROM announcements ORDER BY created_at DESC LIMIT 5";
$latest_announcements = $conn->query($latest_announcements_query);


$conn->close();
?>

<?php include "dashboard-top.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3">Admin Dashboard</h1>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" id="quickActions" data-bs-toggle="dropdown" aria-expanded="false">
                    Quick Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="quickActions">
                    <li><a class="dropdown-item" href="add-student.php"><i class="align-middle me-1" data-feather="user-plus"></i> Add New Student</a></li>
                    <li><a class="dropdown-item" href="add-teacher.php"><i class="align-middle me-1" data-feather="user-check"></i> Add New Teacher</a></li>
                    <li><a class="dropdown-item" href="school-operations-announcements.php"><i class="align-middle me-1" data-feather="bell"></i> Post Announcement</a></li>
                </ul>
            </div>
        </div>

        <!-- KPI Summary Cards Row 1 -->
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body"><div class="row"><div class="col mt-0"><h5 class="card-title">Total Students</h5></div><div class="col-auto"><div class="stat text-primary"><i class="align-middle" data-feather="users"></i></div></div></div><h1 class="mt-1 mb-3"><?php echo $total_students; ?></h1></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body"><div class="row"><div class="col mt-0"><h5 class="card-title">Total Teachers</h5></div><div class="col-auto"><div class="stat text-primary"><i class="align-middle" data-feather="user-check"></i></div></div></div><h1 class="mt-1 mb-3"><?php echo $total_teachers; ?></h1></div>
                </div>
            </div>
            <div class="col-md-3">
                 <div class="card">
                    <div class="card-body"><div class="row"><div class="col mt-0"><h5 class="card-title">S-T Ratio</h5></div><div class="col-auto"><div class="stat text-primary"><i class="align-middle" data-feather="git-pull-request"></i></div></div></div><h1 class="mt-1 mb-3"><?php echo $student_teacher_ratio; ?></h1></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body"><div class="row"><div class="col mt-0"><h5 class="card-title">Pending Payments</h5></div><div class="col-auto"><div class="stat text-primary"><i class="align-middle" data-feather="dollar-sign"></i></div></div></div><h1 class="mt-1 mb-3"><?php echo $pending_payments_count; ?></h1><div class="mb-0"><a href="financials-student-fees.php">View</a></div></div>
                </div>
            </div>
        </div>
        <!-- KPI Summary Cards Row 2 -->
        <div class="row">
             <div class="col-md-3">
                 <div class="card">
                    <div class="card-body"><div class="row"><div class="col mt-0"><h5 class="card-title">Today's Attendance</h5></div><div class="col-auto"><div class="stat text-primary"><i class="align-middle" data-feather="check-square"></i></div></div></div><h1 class="mt-1 mb-3"><?php echo $attendance_percentage_today; ?>%</h1></div>
                </div>
            </div>
             <div class="col-md-3">
                 <div class="card">
                    <div class="card-body"><div class="row"><div class="col mt-0"><h5 class="card-title">Average GPA</h5></div><div class="col-auto"><div class="stat text-primary"><i class="align-middle" data-feather="award"></i></div></div></div><h1 class="mt-1 mb-3"><?php echo $school_wide_gpa; ?></h1></div>
                </div>
            </div>
        </div>


        <!-- Data Visualization Row -->
        <div class="row">
            <div class="col-lg-8 d-flex">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">Student Enrollment Trend (Last 12 Months)</h5></div>
                    <div class="card-body"><canvas id="enrollmentTrendChart" height="150"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4 d-flex">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">Gender Distribution</h5></div>
                    <div class="card-body d-flex"><div class="align-self-center w-100"><canvas id="genderDistributionChart"></canvas></div></div>
                </div>
            </div>
        </div>

        <!-- Actionable Items & Resource Management Row -->
        <div class="row">
            <div class="col-lg-7 col-xl-8">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Recent Activity</h5></div>
                    <div class="card-body h-100" style="max-height: 400px; overflow-y: auto;">
                        <?php if ($recent_activity && $recent_activity->num_rows > 0): while($activity = $recent_activity->fetch_assoc()): ?>
                        <div class="d-flex align-items-start">
                            <?php
                                $icon = 'user'; $color = 'primary';
                                if ($activity['type'] == 'assignment') { $icon = 'edit-3'; $color = 'warning'; }
                                elseif ($activity['type'] == 'payment') { $icon = 'dollar-sign'; $color = 'success'; }
                            ?>
                            <div class="badge bg-<?php echo $color; ?>-light text-<?php echo $color; ?> me-3 d-flex align-items-center justify-content-center" style="height:36px; width:36px;">
                                <i class="align-middle" data-feather="<?php echo $icon; ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?php echo htmlspecialchars($activity['subject']); ?></strong> <?php echo htmlspecialchars($activity['action']); ?><br />
                                <small class="text-muted"><?php echo date('d M, Y \a\t h:i A', strtotime($activity['activity_date'])); ?></small>
                            </div>
                        </div>
                        <hr />
                        <?php endwhile; else: ?>
                            <p>No recent activity.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 col-xl-4">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Top Performing Courses</h5></div>
                    <div class="list-group list-group-flush">
                         <?php if ($top_courses && $top_courses->num_rows > 0): while($course = $top_courses->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($course['name']); ?></h6>
                                    <span class="badge bg-success">Avg. <?php echo number_format($course['avg_grade'], 2); ?></span>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <div class="list-group-item">No grade data available.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card">
                     <div class="card-header"><h5 class="card-title mb-0">Class Capacity</h5></div>
                     <div class="card-body">
                         <?php if ($class_capacities && $class_capacities->num_rows > 0): while($class = $class_capacities->fetch_assoc()):
                            $percentage = $class['capacity'] > 0 ? round(($class['enrolled'] / $class['capacity']) * 100) : 0;
                            $progress_color = $percentage > 85 ? 'bg-danger' : ($percentage > 60 ? 'bg-warning' : 'bg-success');
                         ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span><?php echo htmlspecialchars($class['name']); ?></span>
                                    <span><?php echo $class['enrolled']; ?> / <?php echo $class['capacity']; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?php echo $progress_color; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                         <?php endwhile; else: ?>
                            <p>No enrollment data to show.</p>
                         <?php endif; ?>
                     </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Enrollment Trend Chart
    if (document.getElementById("enrollmentTrendChart")) {
        new Chart(document.getElementById("enrollmentTrendChart"), {
            type: "line",
            data: {
                labels: <?php echo json_encode($chart_labels_enrollment); ?>,
                datasets: [{
                    label: "New Students",
                    data: <?php echo json_encode($chart_data_enrollment); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // Gender Distribution Chart
    if (document.getElementById("genderDistributionChart")) {
        new Chart(document.getElementById("genderDistributionChart"), {
            type: "pie",
            data: {
                labels: <?php echo json_encode($chart_labels_gender); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_data_gender); ?>,
                    backgroundColor: ["#3B7DDD", "#E3342F", "#6C757D"],
                    borderWidth: 2
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
});
</script>

<?php include "footer.php"; ?>
