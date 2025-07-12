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
$allowed_sort_columns = ['id', 'name', 'start_date', 'end_date'];
$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : 'start_date';
$sort_direction = isset($_GET['dir']) && strtolower($_GET['dir']) == 'asc' ? 'ASC' : 'DESC';

/**
 * Retrieves all school programs with filtering and sorting capabilities.
 * @param mysqli $conn The database connection object.
 * @param string $search A search term.
 * @param string $sort_col The column to sort by.
 * @param string $sort_dir The direction to sort (ASC or DESC).
 * @return array An array of program records.
 */
function get_all_programs($conn, $search = '', $sort_col = 'start_date', $sort_dir = 'DESC') {
    $column_map = [
        'id'         => 'id',
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

// Check for and display status messages from session
$status_message = '';
if (isset($_SESSION['status_message'])) {
    $status_message = $_SESSION['status_message'];
    unset($_SESSION['status_message']);
}

$programs_data = get_all_programs($conn, $search_query, $sort_column, $sort_direction);

?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
             <h1 class="h3 mb-0">Manage School Programs</h1>
             <a href="school-operations-add-program.php" class="btn btn-primary">Add New Program</a>
        </div>

        <?php if (!empty($status_message)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($status_message); ?>
            </div>
        <?php endif; ?>

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
                                    <a href="school-operations-programs.php" class="btn btn-secondary">Clear</a>
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
                                        <th>Description</th>
                                        <th><?php echo sortable_header('Start Date', 'start_date', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th><?php echo sortable_header('End Date', 'end_date', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($programs_data)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center p-4">
                                                <?php echo !empty($search_query) ? 'No programs found matching your search.' : 'No programs have been added yet.'; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($programs_data as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row["name"]); ?></td>
                                                <td style="max-width: 400px;"><?php echo htmlspecialchars($row["description"]); ?></td>
                                                <td><?php echo date("F j, Y", strtotime($row["start_date"])); ?></td>
                                                <td><?php echo date("F j, Y", strtotime($row["end_date"])); ?></td>
                                                <td>
                                                    <a href="school-operations-edit-program.php?id=<?php echo $row["id"]; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                                    <a href="school-operations-delete-program.php?id=<?php echo $row["id"]; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this program?')"><i class="fas fa-trash-alt"></i> Delete</a>
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
        /* Custom styles for result messages */
        .result {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
        }
        .result-success {
            color: #047857;
            background-color: #d1fae5;
        }
        .result-error {
            color: #b91c1c;
            background-color: #fee2e2;
        }
        /* Custom styles for table actions */
        .action-buttons a {
            margin-right: 0.5rem;
            text-decoration: none;
            padding: 0.375rem 0.75rem 0.375rem 0;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out;
        }
        .edit-button {
            color: #4f46e5; /* indigo-600 */
            margin-right: 0.5rem; /* mr-2 */
            padding: 0.5rem 0 0.5rem 0; /* p-2 */
            border-radius: 0.375rem; /* rounded-md */
            transition: all 150ms ease-in-out; /* transition duration-150 ease-in-out */
        }

        .edit-button:hover {
            color: #4338ca; /* indigo-700 or a darker shade for hover */
            background-color: #eef2ff; /* indigo-50 */
        }

        .delete-button {
            color: #ef4444; /* red-600 */
            padding: 0.5rem; /* p-2 */
            border-radius: 0.375rem; /* rounded-md */
            transition: all 150ms ease-in-out; /* transition duration-150 ease-in-out */
        }

        .delete-button:hover {
            color: #dc2626; /* red-700 or a darker shade for hover */
            background-color: #fef2f2; /* red-50 */
        }
	</style>

</body>

</html>