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

$allowed_sort_columns = ['id', 'name', 'section', 'teacher_name'];
$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : 'id';
$sort_direction = isset($_GET['dir']) && strtolower($_GET['dir']) == 'desc' ? 'DESC' : 'ASC';


/**
 * Retrieves all classes with filtering and sorting capabilities.
 * @param mysqli $conn The database connection object.
 * @param string $search A search term for class name or section.
 * @param string $sort_col The column to sort by.
 * @param string $sort_dir The direction to sort (ASC or DESC).
 * @return array An array of class records.
 */
function get_all_classes($conn, $search = '', $sort_col = 'id', $sort_dir = 'ASC') {
    $classes = [];
    // Map user-friendly sort columns to actual database columns to prevent SQL injection
    $column_map = [
        'id' => 'c.id',
        'name' => 'c.name',
        'section' => 'c.section',
        'teacher_name' => 'teacher_name'
    ];
    $order_by_column = $column_map[$sort_col] ?? 'c.id';

    // Base SQL query
    $sql = "SELECT c.id, c.name, c.section, u.name AS teacher_name
            FROM classes c
            LEFT JOIN users u ON c.teacher_id = u.id";

    $params = [];
    $types = '';

    // Add search condition if a search query is provided
    if (!empty($search)) {
        $sql .= " WHERE c.name LIKE ? OR c.section LIKE ?";
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
            $classes[] = $row;
        }
    } else {
        error_log("Error fetching classes: " . $conn->error);
    }
    $stmt->close();
    return $classes;
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

// Fetch all classes for the table display
$classes_data = get_all_classes($conn, $search_query, $sort_column, $sort_direction);

// Close connection
$conn->close();
?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_ad.php" ?>

<main class="content">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0">Manage Classes</h1>
            <a href="academics-add-class.php" class="btn btn-primary">Add New Class</a>
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
                        <input type="text" name="search" class="form-control" placeholder="Search by Name or Section..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="academics-classes.php" class="btn ms-1 btn-secondary">Clear Filter</a>
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
                            <th><?php sortable_header('Section', 'section', $sort_column, $sort_direction, $search_query); ?></th>
                            <th><?php sortable_header('Teacher Name', 'teacher_name', $sort_column, $sort_direction, $search_query); ?></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($classes_data)): ?>
                            <tr>
                                <td colspan="5" class="text-center p-4">
                                    <?php echo !empty($search_query) ? 'No classes found matching your search.' : 'No classes found.'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($classes_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["id"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["name"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["section"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["teacher_name"] ?? 'N/A'); ?></td>
                                    <td class="action-buttons">
                                        <a href="academics-edit-class.php?id=<?php echo htmlspecialchars($row["id"]); ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="academics-delete-class.php?id=<?php echo htmlspecialchars($row["id"]); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this class?')"><i class="fas fa-trash-alt"></i> Delete</a>
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