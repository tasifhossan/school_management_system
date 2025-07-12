<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: sign-in.php"); 
    exit();
}

include "../lib/connection.php";
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

$teacher_name = $_SESSION['name'] ?? 'Teacher';
$teacher_id = null;

// Fetch the teacher's internal ID
$stmt_teacher_id = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt_teacher_id->bind_param("i", $userId);
$stmt_teacher_id->execute();
$result_teacher_id = $stmt_teacher_id->get_result();
if ($result_teacher_id->num_rows > 0) {
    $teacher_data = $result_teacher_id->fetch_assoc();
    $teacher_id = $teacher_data['id'];
} else {
    die("Teacher profile not found for the logged-in user.");
}
$stmt_teacher_id->close();


// --- 1. Fetch data for "At a Glance" Summary Cards ---
$stmt_students = $conn->prepare("SELECT COUNT(DISTINCT sce.student_id) as total_students FROM student_course_enrollments sce JOIN course_offerings co ON sce.course_offering_id = co.id WHERE co.teacher_id = ?");
$stmt_students->bind_param("i", $teacher_id);
$stmt_students->execute();
$total_students = $stmt_students->get_result()->fetch_assoc()['total_students'] ?? 0;
$stmt_students->close();

$stmt_courses = $conn->prepare("SELECT COUNT(*) as active_courses FROM course_offerings WHERE teacher_id = ? AND end_date >= CURDATE()");
$stmt_courses->bind_param("i", $teacher_id);
$stmt_courses->execute();
$active_courses = $stmt_courses->get_result()->fetch_assoc()['active_courses'] ?? 0;
$stmt_courses->close();

$stmt_subjects = $conn->prepare("SELECT COUNT(DISTINCT course_id) as total_subjects FROM course_offerings WHERE teacher_id = ?");
$stmt_subjects->bind_param("i", $teacher_id);
$stmt_subjects->execute();
$total_subjects = $stmt_subjects->get_result()->fetch_assoc()['total_subjects'] ?? 0;
$stmt_subjects->close();


// --- 2. Fetch data for "Today's Schedule" ---
$today_day_name = date('l'); 
$stmt_schedule = $conn->prepare("SELECT c.name, co.section, co.meeting_time, co.location FROM course_offerings co JOIN courses c ON co.course_id = c.id WHERE co.teacher_id = ? AND co.start_date <= CURDATE() AND co.end_date >= CURDATE()");
$stmt_schedule->bind_param("i", $teacher_id);
$stmt_schedule->execute();
$schedule_results = $stmt_schedule->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_schedule->close();


// --- 3. Fetch data for "Upcoming Assignment Deadlines" ---
$stmt_assignments = $conn->prepare("SELECT a.title, c.name as course_name, a.due_date FROM assignments a JOIN course_offerings co ON a.course_offering_id = co.id JOIN courses c ON co.course_id = c.id WHERE a.teacher_id = ? AND a.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY a.due_date ASC");
$stmt_assignments->bind_param("i", $teacher_id);
$stmt_assignments->execute();
$upcoming_assignments = $stmt_assignments->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_assignments->close();


// --- 4. Fetch data for "At-Risk Students" ---
$stmt_at_risk = $conn->prepare("SELECT DISTINCT u.name as student_name, s.roll_number, er.marks, c.name as course_name, er.exam_date FROM exam_results er JOIN students s ON er.student_id = s.id JOIN users u ON s.user_id = u.id JOIN course_offerings co ON er.course_offering_id = co.id JOIN courses c ON co.course_id = c.id WHERE co.teacher_id = ? AND er.marks < 50 AND er.exam_date > DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY er.exam_date DESC LIMIT 5");
$stmt_at_risk->bind_param("i", $teacher_id);
$stmt_at_risk->execute();
$at_risk_students = $stmt_at_risk->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_at_risk->close();


// --- 5. Fetch data for Course Performance Comparison chart ---
$course_perf_results = $conn->query("SELECT c.name as course_name, AVG(er.marks) as average_mark FROM exam_results er JOIN course_offerings co ON er.course_offering_id = co.id JOIN courses c ON co.course_id = c.id WHERE co.teacher_id = $teacher_id GROUP BY c.name ORDER BY average_mark DESC")->fetch_all(MYSQLI_ASSOC);
$course_perf_labels = json_encode(array_column($course_perf_results, 'course_name'));
$course_perf_values = json_encode(array_column($course_perf_results, 'average_mark'));


// --- 6. Fetch data for Attendance vs. Performance Correlation chart ---
$correlation_data = [];
$stmt_correlation = $conn->prepare("SELECT (SELECT COUNT(*) FROM attendance att WHERE att.student_id = s.id AND att.status = 'Absent' AND att.course_offering_id IN (SELECT id FROM course_offerings WHERE teacher_id = ?)) as absence_count, AVG(er.marks) as average_mark FROM students s JOIN student_course_enrollments sce ON s.id = sce.student_id JOIN course_offerings co ON sce.course_offering_id = co.id LEFT JOIN exam_results er ON s.id = er.student_id AND co.id = er.course_offering_id WHERE co.teacher_id = ? AND er.marks IS NOT NULL GROUP BY s.id");
$stmt_correlation->bind_param("ii", $teacher_id, $teacher_id);
$stmt_correlation->execute();
$correlation_results = $stmt_correlation->get_result();
while($row = $correlation_results->fetch_assoc()) {
    $correlation_data[] = ['x' => (int)$row['absence_count'], 'y' => (float)$row['average_mark']];
}
$stmt_correlation->close();
$correlation_data_json = json_encode($correlation_data);
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">

        <h1 class="h3 mb-3">Welcome back, <strong><?php echo htmlspecialchars($teacher_name); ?></strong></h1>

        <div class="row">
            <div class="col-sm-4">
                <div class="card"><div class="card-body"><div class="row"><div class="col mt-0"><h5 class="card-title">Total Students</h5></div><div class="col-auto"><div class="stat text-primary"><i class="align-middle" data-feather="users"></i></div></div></div><h1 class="mt-1 mb-3"><?php echo $total_students; ?></h1></div></div>
            </div>
             <div class="col-sm-4">
                <div class="card"><div class="card-body"><div class="row"><div class="col mt-0"><h5 class="card-title">Active Courses</h5></div><div class="col-auto"><div class="stat text-primary"><i class="align-middle" data-feather="book-open"></i></div></div></div><h1 class="mt-1 mb-3"><?php echo $active_courses; ?></h1></div></div>
            </div>
            <div class="col-sm-4">
                <div class="card"><div class="card-body"><div class="row"><div class="col mt-0"><h5 class="card-title">Unique Subjects</h5></div><div class="col-auto"><div class="stat text-primary"><i class="align-middle" data-feather="book"></i></div></div></div><h1 class="mt-1 mb-3"><?php echo $total_subjects; ?></h1></div></div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7 d-flex">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">At-Risk Students (Recent Low Marks)</h5></div>
                    <div class="table-responsive"><table class="table table-hover my-0">
                        <thead><tr><th>Student Name</th><th>Course</th><th>Exam Date</th><th class="text-end">Mark</th></tr></thead>
                        <tbody>
                            <?php if (count($at_risk_students) > 0): foreach ($at_risk_students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['exam_date']); ?></td>
                                    <td class="text-end text-danger fw-bold"><?php echo htmlspecialchars($student['marks']); ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="4">No students currently flagged as at-risk.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table></div>
                </div>
            </div>
            <div class="col-lg-5 d-flex">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">Upcoming Assignment Deadlines</h5></div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php if (count($upcoming_assignments) > 0): foreach ($upcoming_assignments as $assignment): ?>
                                <li class="list-group-item px-0">
                                    <strong><?php echo htmlspecialchars($assignment['title']); ?></strong>
                                    <div class="text-muted small"><?php echo htmlspecialchars($assignment['course_name']); ?></div>
                                    <div class="text-muted small">Due: <?php echo date("F j, Y", strtotime($assignment['due_date'])); ?></div>
                                </li>
                            <?php endforeach; else: ?>
                                <li class="list-group-item px-0">No assignments are due in the next 7 days.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="card"><div class="card-header"><h5 class="card-title">Course Performance Comparison</h5><h6 class="card-subtitle text-muted">Average student marks across different courses.</h6></div><div class="card-body"><div class="chart"><canvas id="coursePerformanceChart"></canvas></div></div></div>
            </div>
            <div class="col-lg-6">
                 <div class="card"><div class="card-header"><h5 class="card-title">Attendance vs. Performance</h5><h6 class="card-subtitle text-muted">Correlation between student absences and average marks.</h6></div><div class="card-body"><div class="chart"><canvas id="attendanceCorrelationChart"></canvas></div></div></div>
            </div>
        </div>

        <div class="row">
             <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5 class="card-title">Today's Schedule (<?php echo $today_day_name; ?>)</h5></div>
                    <div class="card-body"><div class="table-responsive"><table class="table table-hover">
                        <thead><tr><th>Time</th><th>Course</th><th>Location</th></tr></thead>
                        <tbody>
                            <?php
                            $today_schedule_count = 0;
                            foreach ($schedule_results as $item) { if (stripos($item['meeting_time'], $today_day_name) !== false) {
                                echo "<tr><td>" . htmlspecialchars($item['meeting_time']) . "</td><td>" . htmlspecialchars($item['name']) . " - " . htmlspecialchars($item['section']) . "</td><td>" . htmlspecialchars($item['location']) . "</td></tr>";
                                $today_schedule_count++;
                            }}
                            if ($today_schedule_count == 0) { echo "<tr><td colspan='3'>No classes scheduled for today.</td></tr>"; }
                            ?>
                        </tbody>
                    </table></div></div>
                </div>
            </div>
        </div>

    </div>
</main>

<?php include "footer.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Course Performance Bar Chart
    new Chart(document.getElementById("coursePerformanceChart"), {
        type: "bar", data: { labels: <?php echo $course_perf_labels; ?>, datasets: [{ label: "Average Mark", backgroundColor: "#3B7DDD", data: <?php echo $course_perf_values; ?> }] },
        options: { maintainAspectRatio: false, legend: { display: false }, scales: { yAxes: [{ ticks: { beginAtZero: true, max: 100 } }] } }
    });

    // Attendance vs. Performance Scatter Plot
    new Chart(document.getElementById("attendanceCorrelationChart"), {
        type: "scatter", data: { datasets: [{ label: "Student", backgroundColor: "rgba(220, 53, 69, 0.6)", data: <?php echo $correlation_data_json; ?> }] },
        options: { maintainAspectRatio: false, legend: { display: false }, scales: { 
            xAxes: [{ scaleLabel: { display: true, labelString: 'Number of Absences' } }], 
            yAxes: [{ scaleLabel: { display: true, labelString: 'Average Mark' }, ticks: { beginAtZero: true, max: 100 } }] 
        }}
    });
});
</script>