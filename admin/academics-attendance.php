<?php

session_start();
// db connection
include "../lib/connection.php";

// Check if the user is logged in and is a student.
// You might have a $_SESSION['role'] check here as well if you want to be more specific.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Redirect to login page
    exit();
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';

// --- Get Search and Sort parameters from URL ---
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$allowed_sort_columns = ['id', 'student_name', 'roll_number', 'course_code', 'attendance_date', 'status'];
$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : 'id';
$sort_direction = isset($_GET['dir']) && strtolower($_GET['dir']) == 'desc' ? 'DESC' : 'ASC';


/**
 * Retrieves all attendance records with filtering and sorting capabilities.
 * @param mysqli $conn The database connection object.
 * @param string $search A search term.
 * @param string $sort_col The column to sort by.
 * @param string $sort_dir The direction to sort (ASC or DESC).
 * @return array An array of attendance records.
 */
function get_all_attendance($conn, $search = '', $sort_col = 'id', $sort_dir = 'ASC') {
    $attendance_records = [];
    // Map user-friendly sort columns to actual database columns to prevent SQL injection
    $column_map = [
        'id' => 'a.id',
        'student_name' => 'student_name',
        'roll_number' => 's.roll_number',
        'course_code' => 'c.course_code',
        'attendance_date' => 'a.attendance_date',
        'status' => 'a.status'
    ];
    $order_by_column = $column_map[$sort_col] ?? 'a.id';

    // Base SQL query
    $sql = "SELECT 
                a.id,
                u.name AS student_name,
                s.roll_number,
                c.course_code,
                a.attendance_date,
                a.status
            FROM attendance a
            LEFT JOIN students s ON a.student_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN course_offerings co ON a.course_offering_id = co.id
            LEFT JOIN courses c ON co.course_id = c.id";

    $params = [];
    $types = '';

    // Add search condition if a search query is provided
    if (!empty($search)) {
        $sql .= " WHERE u.name LIKE ? OR s.roll_number LIKE ? OR c.course_code LIKE ?";
        $search_param = "%" . $search . "%";
        array_push($params, $search_param, $search_param, $search_param);
        $types .= 'sss';
    }

    // Add sorting
    $sql .= " ORDER BY {$order_by_column} {$sort_dir}";
    
    $stmt = $conn->prepare($sql);

    // Bind parameters if they exist
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
    } else {
        error_log("Error fetching attendance records: " . $conn->error);
    }
    $stmt->close();
    return $attendance_records;
}

/**
 * Generates a sortable table header link.
 */
function sortable_header($display_name, $column_name, $current_sort_col, $current_sort_dir, $search_query) {
    $dir = 'asc';
    $icon = '';
    if ($column_name == $current_sort_col) {
        $dir = (strtolower($current_sort_dir) == 'asc') ? 'desc' : 'asc';
        $icon = (strtolower($current_sort_dir) == 'asc') ? ' &#9650;' : ' &#9660;';
    }
    $search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
    echo "<a href=\"?sort={$column_name}&dir={$dir}{$search_param}\">{$display_name}{$icon}</a>";
}

// Check for and display status messages from session
$result_message = '';
$result_class = '';
if (isset($_SESSION['status_message'])) {
    $result_message = $_SESSION['status_message'];
    $result_class = $_SESSION['status_class'] ?? 'result-success';
    unset($_SESSION['status_message'], $_SESSION['status_class']);
}

// Fetch all attendance data for the table display
$attendance_data = get_all_attendance($conn, $search_query, $sort_column, $sort_direction);

// Close connection
$conn->close();
?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_ad.php" ?>

<main class="content">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0">Manage Attendance</h1>
            <a href="academics-add-attendance.php" class="btn btn-primary">Add Attendance Record</a>
        </div>

        <!-- Result Message Area -->
        <?php
        if (!empty($result_message)) {
            echo "<div class=\"result {$result_class} mb-3\">" . htmlspecialchars($result_message) . "</div>";
        }
        ?>
        
        <div class="card">
             <div class="card-header">
                <h5 class="card-title">Search & Filter</h5>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search by Student Name, Roll, or Course Code..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="academics-attendance.php" class="btn ms-1 btn-secondary">Clear Filter</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <table class="table table-hover my-0">
                    <thead>
                        <tr>
                            <th><?php sortable_header('ID', 'id', $sort_column, $sort_direction, $search_query); ?></th>
                            <th><?php sortable_header('Student Name', 'student_name', $sort_column, $sort_direction, $search_query); ?></th>
                            <th><?php sortable_header('Roll Number', 'roll_number', $sort_column, $sort_direction, $search_query); ?></th>
                            <th><?php sortable_header('Course Code', 'course_code', $sort_column, $sort_direction, $search_query); ?></th>
                            <th><?php sortable_header('Date', 'attendance_date', $sort_column, $sort_direction, $search_query); ?></th>
                            <th><?php sortable_header('Status', 'status', $sort_column, $sort_direction, $search_query); ?></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendance_data)): ?>
                            <tr>
                                <td colspan="7" class="text-center p-4">
                                    <?php echo !empty($search_query) ? 'No attendance records found matching your search.' : 'No attendance records found.'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attendance_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["id"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["student_name"] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row["roll_number"] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row["course_code"] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row["attendance_date"]); ?></td>
                                    <td>
                                        <span class="badge <?php echo $row['status'] == 'Present' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo htmlspecialchars($row["status"]); ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="academics-edit-attendance.php?id=<?php echo htmlspecialchars($row["id"]); ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="academics-delete-attendance.php?id=<?php echo htmlspecialchars($row["id"]); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this attendance record?')"><i class="fas fa-trash-alt"></i> Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php" ?>

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