<?php
session_start();
include "../lib/connection.php";

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

$teacher_id = null;

$stmt_teacher_id = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt_teacher_id->bind_param("i", $userId);
$stmt_teacher_id->execute();
$result_teacher_id = $stmt_teacher_id->get_result();
if ($result_teacher_id->num_rows > 0) {
    $teacher_data = $result_teacher_id->fetch_assoc();
    $teacher_id = $teacher_data['id'];
} else {
    die("Teacher profile not found.");
}
$stmt_teacher_id->close();

$filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$filter_status = $_GET['filter_status'] ?? '';

$sql = "SELECT att.id, u.name as student_name, s.roll_number, s.photo, c.name as course_name, att.status 
        FROM attendance att
        JOIN students s ON att.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN course_offerings co ON att.course_offering_id = co.id
        JOIN courses c ON co.course_id = c.id
        WHERE co.teacher_id = ? AND att.attendance_date = ?";

$params = [$teacher_id, $filter_date];
$types = "is";

if (!empty($filter_status)) {
    $sql .= " AND att.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$sql .= " ORDER BY u.name";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// --- Fetch data for the chart ---
$chart_data = ['Present' => 0, 'Absent' => 0, 'Late' => 0];
$stmt_chart = $conn->prepare("SELECT status, COUNT(*) as count FROM attendance att JOIN course_offerings co ON att.course_offering_id = co.id WHERE co.teacher_id = ? AND att.attendance_date = ? GROUP BY status");
$stmt_chart->bind_param("is", $teacher_id, $filter_date);
$stmt_chart->execute();
$result_chart = $stmt_chart->get_result();
while($row_chart = $result_chart->fetch_assoc()){
    if(isset($chart_data[$row_chart['status']])) {
        $chart_data[$row_chart['status']] = $row_chart['count'];
    }
}
$stmt_chart->close();
$chart_data_json = json_encode(array_values($chart_data));
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">View Attendance History</h1>
        <div class="row">
            <div class="col-lg-8">
                 <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Filter Records</h5>
                        <form action="view_attendance.php" method="GET" class="row row-cols-lg-auto g-3 align-items-center">
                            <div class="col-12">
                                <label for="filter_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="filter_date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                            </div>
                            <div class="col-12">
                                <label for="filter_status" class="form-label">Status</label>
                                <select class="form-select" name="filter_status" id="filter_status">
                                    <option value="" <?php if(empty($filter_status)) echo 'selected'; ?>>All Statuses</option>
                                    <option value="Present" <?php if($filter_status == 'Present') echo 'selected'; ?>>Present</option>
                                    <option value="Absent" <?php if($filter_status == 'Absent') echo 'selected'; ?>>Absent</option>
                                    <option value="Late" <?php if($filter_status == 'Late') echo 'selected'; ?>>Late</option>
                                </select>
                            </div>
                            <div class="col-12 align-self-end">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover my-0">
                                <thead>
                                    <tr>
                                        <th>Avatar</th>
                                        <th>Student Name</th>
                                        <th>Roll No.</th>
                                        <th>Course</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $photo_path = !empty($row["photo"]) ? '/uploads/student_photos/' . htmlspecialchars($row["photo"]) : 'img/avatars/avatar.jpg';
                                            $status_badge = '';
                                            switch($row['status']) {
                                                case 'Present': $status_badge = '<span class="badge bg-success">Present</span>'; break;
                                                case 'Absent': $status_badge = '<span class="badge bg-danger">Absent</span>'; break;
                                                case 'Late': $status_badge = '<span class="badge bg-warning">Late</span>'; break;
                                            }
                                            echo "<tr>";
                                            echo '<td><img src="' . $photo_path . '" class="avatar img-fluid rounded-circle" alt="' . htmlspecialchars($row["student_name"]) . '"></td>';
                                            echo "<td>" . htmlspecialchars($row["student_name"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["roll_number"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["course_name"]) . "</td>";
                                            echo "<td>" . $status_badge . "</td>";
                                            echo "<td><a href='edit_attendance.php?id=" . $row['id'] . "' class='btn btn-sm btn-primary'>Edit</a></td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6'>No attendance records found for the selected filters.</td></tr>";
                                    }
                                    $result->close();
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
             <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Summary for <?php echo htmlspecialchars($filter_date); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height:300px">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    new Chart(document.getElementById("attendanceChart"), {
        type: "pie",
        data: {
            labels: ["Present", "Absent", "Late"],
            datasets: [{
                data: <?php echo $chart_data_json; ?>,
                backgroundColor: ["#1cbb8c", "#dc3545", "#fcb92c"],
                borderColor: "transparent"
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { 
                display: true,
                position: 'bottom'
            }
        }
    });
});
</script>