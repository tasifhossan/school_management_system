<?php

session_start();
// db connection
include "../lib/connection.php";

// Check if the user is logged in and is an admin.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Redirect to login page
    exit();
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';

// --- Get Search and Sort parameters from URL ---
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$allowed_sort_columns = ['name', 'email', 'employee_id', 'department'];
$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : 'name';
$sort_direction = isset($_GET['dir']) && strtolower($_GET['dir']) == 'desc' ? 'DESC' : 'ASC';


/**
 * Retrieves all teachers with filtering and sorting capabilities.
 * @param mysqli $conn The database connection object.
 * @param string $search A search term for name or employee ID.
 * @param string $sort_col The column to sort by.
 * @param string $sort_dir The direction to sort (ASC or DESC).
 * @return array An array of teacher records.
 */
function get_all_teachers($conn, $search = '', $sort_col = 'name', $sort_dir = 'ASC') {
    $teachers = [];
    // Map user-friendly sort columns to actual database columns to prevent SQL injection
    $column_map = [
        'name' => 'u.name',
        'email' => 'u.email',
        'employee_id' => 'CAST(t.employee_id AS UNSIGNED)',
        'department' => 't.department'
    ];
    $order_by_column = $column_map[$sort_col] ?? 'u.name';

    $sql = "SELECT
                u.id AS user_id, u.name, u.email, u.phone AS user_phone,
                t.id AS teacher_id, t.employee_id, t.hire_date, t.department,
                t.qualifications, t.specialization, t.office_location, t.office_hours, t.photo
            FROM users u
            LEFT JOIN teachers t ON u.id = t.user_id
            WHERE u.role = 'teacher'";

    $params = [];
    $types = '';

    if (!empty($search)) {
        $sql .= " AND (u.name LIKE ? OR t.employee_id LIKE ?)";
        $search_param = "%" . $search . "%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ss';
    }

    $sql .= " ORDER BY {$order_by_column} {$sort_dir}, u.name ASC";
    
    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
    } else {
        error_log("Error fetching teachers: " . $conn->error);
    }
    $stmt->close();
    return $teachers;
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

// Fetch all teachers for the table display
$teachers_data = get_all_teachers($conn, $search_query, $sort_column, $sort_direction);

// Close connection
$conn->close();
?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_ad.php" ?>

<main class="content">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0">Manage Teachers</h1>
            <a href="add-teacher.php" class="btn btn-primary">Add New Teacher</a>
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
                        <input type="text" name="search" class="form-control" placeholder="Search by Name or Employee ID..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="users-teachers.php" class="btn ms-1 btn-secondary">Clear Filter</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <table class="table table-hover my-0">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th><?php sortable_header('Name', 'name', $sort_column, $sort_direction, $search_query); ?></th>
                            <th class="d-none d-md-table-cell"><?php sortable_header('Email', 'email', $sort_column, $sort_direction, $search_query); ?></th>
                            <th><?php sortable_header('Employee ID', 'employee_id', $sort_column, $sort_direction, $search_query); ?></th>
                            <th class="d-none d-md-table-cell"><?php sortable_header('Department', 'department', $sort_column, $sort_direction, $search_query); ?></th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($teachers_data)) : ?>
                            <tr>
                                <td colspan="6" class="text-center p-4">
                                    <?php echo !empty($search_query) ? 'No teachers found matching your search.' : 'No teachers registered yet.'; ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($teachers_data as $teacher) : ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($teacher['photo'] ?: 'https://placehold.co/60x60/EEE/31343C?text=No+Photo'); ?>" alt="Teacher Photo" width="40" height="40" class="rounded-circle">
                                    </td>
                                    <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($teacher['email']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['employee_id'] ?: 'N/A'); ?></td>
                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($teacher['department'] ?: 'N/A'); ?></td>
                                    <td class="action-buttons">
                                        <a href="edit-teacher.php?id=<?php echo htmlspecialchars($teacher['user_id']); ?>" class="edit-button">
                                            <?php echo $teacher['teacher_id'] ? 'Edit' : 'Complete Profile'; ?>
                                        </a>
                                        <a href="delete-teacher.php?id=<?php echo htmlspecialchars($teacher['user_id']); ?>" class="delete-button" onclick="return confirm('Are you sure you want to delete this teacher and their user account? This action cannot be undone.');">Delete</a>
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
    .action-buttons a { text-decoration: none; padding: 0.25rem 0.5rem; border-radius: 0.25rem; margin-right: 0.25rem; transition: background-color 0.15s ease-in-out; }
    .edit-button { color: #0d6efd; }
    .edit-button:hover { background-color: rgba(13, 110, 253, 0.1); }
    .delete-button { color: #dc3545; }
    .delete-button:hover { background-color: rgba(220, 53, 69, 0.1); }
    th a { text-decoration: none; color: inherit; }
    th a:hover { color: #0d6efd; }
    @media (max-width: 768px) { .d-md-table-cell { display: none; } }
</style>

</body>
</html>