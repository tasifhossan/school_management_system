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
$allowed_sort_columns = ['id', 'student_name', 'course_code', 'marks', 'exam_date'];
$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : 'id';
$sort_direction = isset($_GET['dir']) && strtolower($_GET['dir']) == 'desc' ? 'DESC' : 'ASC';

/**
 * Retrieves all exam results with filtering and sorting capabilities for the admin view.
 * @param mysqli $conn The database connection object.
 * @param string $search A search term.
 * @param string $sort_col The column to sort by.
 * @param string $sort_dir The direction to sort (ASC or DESC).
 * @return array An array of exam result records.
 */
function get_all_exam_results($conn, $search = '', $sort_col = 'id', $sort_dir = 'ASC') {
    // Map user-friendly sort columns to actual database columns to prevent ambiguity
    $column_map = [
        'id'           => 'er.id',
        'student_name' => 'u.name',
        'course_code'  => 'c.course_code',
        'marks'        => 'er.marks',
        'exam_date'    => 'er.exam_date',
    ];
    $order_by_column = $column_map[$sort_col] ?? 'er.id';

    // Base SQL query
    $sql = "SELECT 
                er.id,
                u.name AS student_name,
                c.course_code,
                er.marks,
                er.exam_date
            FROM exam_results er
            LEFT JOIN students s ON er.student_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN course_offerings co ON er.course_offering_id = co.id
            LEFT JOIN courses c ON co.course_id = c.id";

    $params = [];
    $types = '';

    // Add search condition if a search query is provided
    if (!empty($search)) {
        $sql .= " WHERE u.name LIKE ? OR c.course_code LIKE ? OR er.marks LIKE ?";
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
    
    $exam_results = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $exam_results[] = $row;
        }
    }
    $stmt->close();
    return $exam_results;
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
    return "<a href=\"?sort={$column_name}&dir={$dir}{$search_param}\">{$display_name}{$icon}</a>";
}

// Fetch all exam result data for the table display
$exam_results_data = get_all_exam_results($conn, $search_query, $sort_column, $sort_direction);

?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
             <h1 class="h3 mb-0">Manage Exam Results</h1>
             <a href="academics-add-exam-result.php" class="btn btn-primary">Add New Exam Result</a>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Filter Results</h5>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mt-2">
                             <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search by Student Name, Course Code, or Marks..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-primary" type="submit">Search</button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="academics-exam-results.php" class="btn ms-1 btn-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover my-0">
                                <thead>
                                    <tr>
                                        <th><?php echo sortable_header('ID', 'id', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th><?php echo sortable_header('Student Name', 'student_name', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th><?php echo sortable_header('Course Code', 'course_code', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th><?php echo sortable_header('Marks', 'marks', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th><?php echo sortable_header('Exam Date', 'exam_date', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($exam_results_data)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center p-4">
                                                <?php echo !empty($search_query) ? 'No results found matching your search.' : 'No exam results have been added yet.'; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($exam_results_data as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row["id"]); ?></td>
                                                <td><?php echo htmlspecialchars($row["student_name"] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row["course_code"] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row["marks"]); ?></td>
                                                <td><?php echo date("F j, Y", strtotime($row["exam_date"])); ?></td>
                                                <td>
                                                    <a href="academics-edit-exam-result.php?id=<?php echo $row["id"]; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                                    <a href="academics-delete-exam-result.php?id=<?php echo $row["id"]; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this exam result?')"><i class="fas fa-trash-alt"></i> Delete</a>
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
include "footer.php";  ?>
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