<?php  
session_start();
// db connection
include "../lib/connection.php";

// Check if the user is logged in and is a student.
// You might have a $_SESSION['role'] check here as well if you want to be more specific.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php"); // Redirect to login page
    exit();
}


$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'student';
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

// --- Get Search and Sort parameters from URL ---
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$allowed_sort_columns = ['name', 'start_date', 'end_date'];
$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : 'start_date';
$sort_direction = isset($_GET['dir']) && strtolower($_GET['dir']) == 'asc' ? 'ASC' : 'DESC';

/**
 * Retrieves all school programs with filtering and sorting.
 * @param mysqli $conn The database connection object.
 * @param string $search A search term.
 * @param string $sort_col The column to sort by.
 * @param string $sort_dir The direction to sort (ASC or DESC).
 * @return array An array of program records.
 */
function get_all_programs($conn, $search = '', $sort_col = 'start_date', $sort_dir = 'DESC') {
    $column_map = [
        'name'       => 'name',
        'start_date' => 'start_date',
        'end_date'   => 'end_date',
    ];
    $order_by_column = $column_map[$sort_col] ?? 'start_date';

    // Base SQL query
    $sql = "SELECT id, name, description, start_date, end_date FROM school_programs";
    $params = [];
    $types = '';

    // Add search condition
    if (!empty($search)) {
        $sql .= " WHERE name LIKE ? OR description LIKE ?";
        $search_param = "%" . $search . "%";
        array_push($params, $search_param, $search_param);
        $types .= 'ss';
    }

    // Add sorting
    $sql .= " ORDER BY {$order_by_column} {$sort_dir}";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $programs = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $programs[] = $row;
        }
    }
    $stmt->close();
    return $programs;
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

$programs_data = get_all_programs($conn, $search_query, $sort_column, $sort_direction);
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_student.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">School Programs</h1>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Filter Programs</h5>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mt-2">
                             <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search by Program Name or Description..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-primary" type="submit">Search</button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="student-school_programs.php" class="btn ms-1 btn-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover my-0">
                                <thead>
                                    <tr>
                                        <th><?php echo sortable_header('Program Name', 'name', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th><?php echo sortable_header('Start Date', 'start_date', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th><?php echo sortable_header('End Date', 'end_date', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($programs_data)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center p-4">
                                                <?php echo !empty($search_query) ? 'No programs found matching your search.' : 'There are no school programs listed at this time.'; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($programs_data as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row["name"]); ?></td>
                                                <td><?php echo date("F j, Y", strtotime($row["start_date"])); ?></td>
                                                <td><?php echo date("F j, Y", strtotime($row["end_date"])); ?></td>
                                                <td>
                                                    <a href="student-view-program.php?id=<?php echo $row["id"]; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
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