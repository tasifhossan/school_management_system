<?php
session_start();
include "../lib/connection.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php"); 
    exit();
}
$userId = $_SESSION['user_id'];

$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';

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

// --- Get Filter Parameter ---
$filter_course_offering_id = $_GET['course_offering_id'] ?? '';

// --- Overall Statistics (for all courses of this teacher) ---
$overall_stats_stmt = $conn->prepare("SELECT marks FROM exam_results er JOIN course_offerings co ON er.course_offering_id = co.id WHERE co.teacher_id = ?");
$overall_stats_stmt->bind_param("i", $teacher_id);
$overall_stats_stmt->execute();
$overall_results = $overall_stats_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$overall_stats_stmt->close();

$overall_stats = ['highest' => null, 'lowest' => null, 'average' => null, 'count' => count($overall_results)];
if ($overall_stats['count'] > 0) {
    $marks_array = array_column($overall_results, 'marks');
    $overall_stats['highest'] = max($marks_array);
    $overall_stats['lowest'] = min($marks_array);
    $overall_stats['average'] = round(array_sum($marks_array) / $overall_stats['count'], 2);
}

// --- Main Query for Filtered Results (Added er.id for the action buttons) ---
$sql = "SELECT er.id, u.name as student_name, s.roll_number, c.name as course_name, er.marks
        FROM exam_results er
        JOIN students s ON er.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN course_offerings co ON er.course_offering_id = co.id
        JOIN courses c ON co.course_id = c.id
        WHERE co.teacher_id = ?";
$params = [$teacher_id];
$types = "i";
if (!empty($filter_course_offering_id)) {
    $sql .= " AND er.course_offering_id = ?";
    $params[] = $filter_course_offering_id;
    $types .= "i";
}
$sql .= " ORDER BY u.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$exam_results = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Calculate Grade Distribution for Filtered Results ---
$grade_distribution = ['A+' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'Fail' => 0];
$pass_count = 0;
$fail_count = 0;
$total_students = count($exam_results);

if ($total_students > 0) {
    foreach ($exam_results as $res) {
        $mark = $res['marks'];
        if ($mark >= 90) $grade_distribution['A+']++;
        elseif ($mark >= 80) $grade_distribution['A']++;
        elseif ($mark >= 70) $grade_distribution['B']++;
        elseif ($mark >= 60) $grade_distribution['C']++;
        elseif ($mark >= 50) $grade_distribution['D']++;
        else $grade_distribution['Fail']++;

        if ($mark >= 50) $pass_count++; // Assuming 50 is the pass mark
        else $fail_count++;
    }
}
$grade_chart_data = json_encode(array_values($grade_distribution));
$grade_chart_labels = json_encode(array_keys($grade_distribution));
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Exam Results Dashboard</h1>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Overall Statistics (All My Courses)</h5>
                    </div>
                    <div class="card-body">
                         <div class="row">
                            <div class="col-sm-4"><div class="card text-center"><div class="card-body"><h5 class="card-title">Highest Mark</h5><p class="h1"><?php echo htmlspecialchars($overall_stats['highest'] ?? 'N/A'); ?></p></div></div></div>
                            <div class="col-sm-4"><div class="card text-center"><div class="card-body"><h5 class="card-title">Lowest Mark</h5><p class="h1"><?php echo htmlspecialchars($overall_stats['lowest'] ?? 'N/A'); ?></p></div></div></div>
                            <div class="col-sm-4"><div class="card text-center"><div class="card-body"><h5 class="card-title">Average Mark</h5><p class="h1"><?php echo htmlspecialchars($overall_stats['average'] ?? 'N/A'); ?></p></div></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Filter Results by Course</h5>
                <form action="view_results.php" method="GET">
                    <div class="row">
                        <div class="col-md-6">
                            <select class="form-select" name="course_offering_id" id="course_offering_id">
                                <option value="">Select a course to filter...</option>
                                <?php
                                    $offerings_sql = "SELECT co.id, c.name as course_name, co.section FROM course_offerings co JOIN courses c ON co.course_id = c.id WHERE co.teacher_id = ?";
                                    $stmt_offerings = $conn->prepare($offerings_sql);
                                    $stmt_offerings->bind_param("i", $teacher_id);
                                    $stmt_offerings->execute();
                                    $offerings_result = $stmt_offerings->get_result();
                                    while ($row = $offerings_result->fetch_assoc()) {
                                        $selected = ($filter_course_offering_id == $row['id']) ? 'selected' : '';
                                        echo "<option value='" . $row['id'] . "' " . $selected . ">" . htmlspecialchars($row['course_name']) . " - " . htmlspecialchars($row['section']) . "</option>";
                                    }
                                    $stmt_offerings->close();
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                             <button type="submit" class="btn btn-primary">Filter</button>
                             <a href="view_results.php" class="btn btn-secondary">Clear Filter</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($filter_course_offering_id) && $total_students > 0): ?>
        <div class="row">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><h5 class="card-title">Grade Distribution</h5></div>
                    <div class="card-body">
                        <p><strong>Pass Percentage:</strong> <?php echo round(($pass_count / $total_students) * 100, 2); ?>%</p>
                        <p><strong>Fail Percentage:</strong> <?php echo round(($fail_count / $total_students) * 100, 2); ?>%</p>
                        <hr>
                        <ul>
                            <?php foreach ($grade_distribution as $grade => $count): ?>
                                <li><strong><?php echo $grade; ?>:</strong> <?php echo $count; ?> student(s) (<?php echo round(($count / $total_students) * 100, 2); ?>%)</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h5 class="card-title">Grades Chart</h5></div>
                    <div class="card-body">
                        <div class="chart">
                            <canvas id="chartjs-bar"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>


        <div class="card">
            <div class="card-header"><h5 class="card-title">Detailed Results</h5></div>
            <div class="card-body">
                <table id="datatablesSimple" class="table table-striped" style="width:100%">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Roll No.</th>
                            <th>Course</th>
                            <th>Marks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($exam_results as $row) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row["student_name"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["roll_number"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["course_name"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["marks"]) . "</td>";
                            // --- ACTION BUTTONS ADDED BACK ---
                            echo '<td>
                                    <a href="edit_exam_result.php?id=' . $row["id"] . '" class="btn btn-sm btn-primary">Edit</a>
                                    <a href="delete_exam_result.php?id=' . $row["id"] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure?\')">Delete</a>
                                  </td>';
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
<script>
    window.addEventListener('DOMContentLoaded', event => {
        const datatablesSimple = document.getElementById('datatablesSimple');
        if (datatablesSimple) {
            new simpleDatatables.DataTable(datatablesSimple);
        }
    });

    // Bar chart
    new Chart(document.getElementById("chartjs-bar"), {
        type: "bar",
        data: {
            labels: <?php echo $grade_chart_labels; ?>,
            datasets: [{
                label: "Number of Students",
                backgroundColor: "#3B7DDD",
                borderColor: "#3B7DDD",
                hoverBackgroundColor: "#3B7DDD",
                hoverBorderColor: "#3B7DDD",
                data: <?php echo $grade_chart_data; ?>,
                barPercentage: .75,
                categoryPercentage: .5
            }]
        },
        options: {
            maintainAspectRatio: false,
            legend: { display: false },
            scales: {
                yAxes: [{ ticks: { stepSize: 1 } }],
                xAxes: [{ gridLines: { display: false } }]
            }
        }
    });
</script>