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
$allowed_sort_columns = ['id', 'title', 'activity_date', 'created_by_name'];
$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : 'activity_date';
$sort_direction = isset($_GET['dir']) && strtolower($_GET['dir']) == 'asc' ? 'ASC' : 'DESC';

/**
 * Retrieves all school activities for the admin view with filtering and sorting.
 */
function get_all_activities($conn, $search = '', $sort_col = 'activity_date', $sort_dir = 'DESC') {
    $column_map = [
        'id'              => 'sa.id',
        'title'           => 'sa.title',
        'activity_date'   => 'sa.activity_date',
        'created_by_name' => 'u.name',
    ];
    $order_by_column = $column_map[$sort_col] ?? 'sa.activity_date';

    $sql = "SELECT sa.id, sa.title, sa.activity_date, u.name AS created_by_name
            FROM school_activities sa
            JOIN users u ON sa.created_by = u.id";
    
    $params = [];
    $types = '';

    if (!empty($search)) {
        $sql .= " WHERE sa.title LIKE ? OR sa.description LIKE ? OR u.name LIKE ?";
        $search_param = "%" . $search . "%";
        array_push($params, $search_param, $search_param, $search_param);
        $types .= 'sss';
    }

    $sql .= " ORDER BY {$order_by_column} {$sort_dir}";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
    }
    $stmt->close();
    return $activities;
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

$status_message = '';
if (isset($_SESSION['status_message'])) {
    $status_message = $_SESSION['status_message'];
    unset($_SESSION['status_message']);
}

$activities_data = get_all_activities($conn, $search_query, $sort_column, $sort_direction);
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
             <h1 class="h3 mb-0">Manage School Activities</h1>
             <a href="school-operations-add-activity.php" class="btn btn-primary">Add New Activity</a>
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
                        <h5 class="card-title">Filter Activities</h5>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mt-2">
                             <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search by Title, Description, or Organizer..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-primary" type="submit">Search</button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="school-operations-activities.php" class="btn btn-secondary">Clear</a>
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
                                        <th><?php echo sortable_header('Title', 'title', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th><?php echo sortable_header('Activity Date', 'activity_date', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th><?php echo sortable_header('Organized By', 'created_by_name', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($activities_data)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center p-4">
                                                <?php echo !empty($search_query) ? 'No activities found matching your search.' : 'No school activities have been added yet.'; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($activities_data as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row["id"]); ?></td>
                                                <td><?php echo htmlspecialchars($row["title"]); ?></td>
                                                <td><?php echo date("F j, Y", strtotime($row["activity_date"])); ?></td>
                                                <td><?php echo htmlspecialchars($row["created_by_name"]); ?></td>
                                                <td>
                                                    <a href="school-operations-edit-activity.php?id=<?php echo $row["id"]; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                                    <a href="school-operations-delete-activity.php?id=<?php echo $row["id"]; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this activity?')"><i class="fas fa-trash-alt"></i> Delete</a>
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
include "footer.php";   ?>

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