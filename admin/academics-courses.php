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

$allowed_sort_columns = ['id', 'name', 'course_code', 'credits'];
$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : 'id';
$sort_direction = isset($_GET['dir']) && strtolower($_GET['dir']) == 'desc' ? 'DESC' : 'ASC';


/**
 * Retrieves all courses with filtering and sorting capabilities.
 * @param mysqli $conn The database connection object.
 * @param string $search A search term for name or course code.
 * @param string $sort_col The column to sort by.
 * @param string $sort_dir The direction to sort (ASC or DESC).
 * @return array An array of course records.
 */
function get_all_courses($conn, $search = '', $sort_col = 'id', $sort_dir = 'ASC') {
    $courses = [];
    // Map user-friendly sort columns to actual database columns to prevent SQL injection
    $column_map = [
        'id' => 'id',
        'name' => 'name',
        'course_code' => 'course_code',
        'credits' => 'credits'
    ];
    $order_by_column = $column_map[$sort_col] ?? 'id';

    // Base SQL query
    $sql = "SELECT id, name, course_code, credits FROM courses";

    $params = [];
    $types = '';

    // Add search condition if a search query is provided
    if (!empty($search)) {
        $sql .= " WHERE name LIKE ? OR course_code LIKE ?";
        $search_param = "%" . $search . "%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ss';
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
            $courses[] = $row;
        }
    } else {
        error_log("Error fetching courses: " . $conn->error);
    }
    $stmt->close();
    return $courses;
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

// Fetch all courses for the table display
$courses_data = get_all_courses($conn, $search_query, $sort_column, $sort_direction);

// Close connection
$conn->close();
?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_ad.php" ?>

<main class="content">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0">Manage Courses</h1>
            <a href="academics-add-course.php" class="btn btn-primary">Add New Course</a>
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
                        <input type="text" name="search" class="form-control" placeholder="Search by Name or Course Code..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="academics-courses.php" class="btn ms-1 btn-secondary">Clear Filter</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <table class="table table-hover my-0">
                    <thead>
                        <tr>
                            <th><?php sortable_header('ID', 'id', $sort_column, $sort_direction, $search_query); ?></th>
                            <th><?php sortable_header('Name', 'name', $sort_column, $sort_direction, $search_query); ?></th>
                            <th><?php sortable_header('Course Code', 'course_code', $sort_column, $sort_direction, $search_query); ?></th>
                            <th><?php sortable_header('Credits', 'credits', $sort_column, $sort_direction, $search_query); ?></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($courses_data)): ?>
                            <tr>
                                <td colspan="5" class="text-center p-4">
                                    <?php echo !empty($search_query) ? 'No courses found matching your search.' : 'No courses found.'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($courses_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["id"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["name"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["course_code"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["credits"]); ?></td>
                                    <td class="action-buttons">
                                        <a href="academics-edit-course.php?id=<?php echo htmlspecialchars($row["id"]); ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="academics-delete-course.php?id=<?php echo htmlspecialchars($row["id"]); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this course?')"><i class="fas fa-trash-alt"></i> Delete</a>
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