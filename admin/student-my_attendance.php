<?php  

session_start();

// db connection
include "../lib/connection.php";

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

// --- Get student's primary ID ---
$stmtStudent = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmtStudent->bind_param("i", $userId);
$stmtStudent->execute();
$resultStudent = $stmtStudent->get_result();
if ($resultStudent->num_rows === 0) {
    die("Error: Could not find student record for the logged-in user.");
}
$student = $resultStudent->fetch_assoc();
$studentId = $student['id'];
$stmtStudent->close();


// --- Get Search and Sort parameters from URL ---
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$allowed_sort_columns = ['course_name', 'attendance_date', 'status'];
$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : 'attendance_date';
$sort_direction = isset($_GET['dir']) && strtolower($_GET['dir']) == 'asc' ? 'ASC' : 'DESC';


/**
 * Retrieves all attendance records for a specific student with filtering and sorting.
 * @param mysqli $conn The database connection object.
 * @param int    $studentId The student's primary ID.
 * @param string $search A search term.
 * @param string $sort_col The column to sort by.
 * @param string $sort_dir The direction to sort (ASC or DESC).
 * @return array An array of attendance records.
 */
function get_attendance_for_student($conn, $studentId, $search = '', $sort_col = 'attendance_date', $sort_dir = 'DESC') {
    // Map user-friendly sort columns to actual database columns
    $column_map = [
        'course_name'     => 'c.name',
        'attendance_date' => 'att.attendance_date',
        'status'          => 'att.status',
    ];
    $order_by_column = $column_map[$sort_col] ?? 'att.attendance_date';

    // Base SQL query
    $sql = "SELECT 
                c.name AS course_name,
                att.attendance_date,
                att.status
            FROM attendance AS att
            JOIN course_offerings AS co ON att.course_offering_id = co.id
            JOIN courses AS c ON co.course_id = c.id
            WHERE att.student_id = ?";
    
    $params = [$studentId];
    $types = 'i';

    // Add search condition if a search query is provided
    if (!empty($search)) {
        $sql .= " AND (c.name LIKE ? OR att.status LIKE ? OR att.attendance_date LIKE ?)";
        $search_param = "%" . $search . "%";
        array_push($params, $search_param, $search_param, $search_param);
        $types .= 'sss';
    }

    // Add sorting
    $sql .= " ORDER BY {$order_by_column} {$sort_dir}";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attendance_records = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
    }
    $stmt->close();
    return $attendance_records;
}

/**
 * Generates a sortable table header link.
 */
function sortable_header($display_name, $column_name, $current_sort_col, $current_sort_dir, $search_query) {
    $dir = 'desc';
    $icon = '';
    if ($column_name == $current_sort_col) {
        $dir = (strtolower($current_sort_dir) == 'desc') ? 'asc' : 'desc';
        $icon = (strtolower($current_sort_dir) == 'desc') ? ' &#9660;' : ' &#9650;';
    }
    $search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
    return "<a href=\"?sort={$column_name}&dir={$dir}{$search_param}\">{$display_name}{$icon}</a>";
}

// Fetch all attendance data for the table display
$attendance_data = get_attendance_for_student($conn, $studentId, $search_query, $sort_column, $sort_direction);

?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_student.php"; ?>

<main class="content">
    <div class="container-fluid p-0">

        <h1 class="h3 mb-3">My Attendance</h1>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Filter Attendance Records</h5>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mt-2">
                             <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search by Course, Status (e.g., Absent), or Date (YYYY-MM-DD)..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-primary" type="submit">Search</button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="student-my_attendance.php" class="btn ms-1 btn-secondary">Clear Filter</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo sortable_header('Course Name', 'course_name', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th><?php echo sortable_header('Date', 'attendance_date', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th class="text-center"><?php echo sortable_header('Status', 'status', $sort_column, $sort_direction, $search_query); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($attendance_data)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center p-4">
                                                <?php echo !empty($search_query) ? 'No attendance records found matching your search.' : 'No attendance records found.'; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($attendance_data as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['course_name']); ?></td>
                                                <td><?php echo date("F j, Y", strtotime($record['attendance_date'])); ?></td>
                                                <td class="text-center">
                                                    <?php
                                                    $status = htmlspecialchars($record['status']);
                                                    if ($status == 'Present') {
                                                        echo '<span class="badge bg-success">Present</span>';
                                                    } elseif ($status == 'Absent') {
                                                        echo '<span class="badge bg-danger">Absent</span>';
                                                    } elseif ($status == 'Late') {
                                                        echo '<span class="badge bg-warning text-dark">Late</span>';
                                                    } else {
                                                        echo '<span class="badge bg-secondary">' . $status . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
$conn->close();
include "footer.php"; 
?>
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