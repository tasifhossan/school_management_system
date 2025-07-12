<?php
session_start();
// Use the session check you provided
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php"); 
    exit();
}

include "../lib/connection.php";
$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Teacher';
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

$teacher_id = $_SESSION['user_id'];

// --- Get Search and Sort parameters from URL ---
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$allowed_sort_columns = ['id', 'title', 'created_at'];
$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : 'created_at';
$sort_direction = isset($_GET['dir']) && strtolower($_GET['dir']) == 'asc' ? 'ASC' : 'DESC';

/**
 * Retrieves all announcements for a specific teacher with filtering and sorting.
 */
function get_announcements_for_teacher($conn, $teacher_id, $search = '', $sort_col = 'created_at', $sort_dir = 'DESC') {
    $column_map = [
        'id'         => 'a.id',
        'title'      => 'a.title',
        'created_at' => 'a.created_at',
    ];
    $order_by_column = $column_map[$sort_col] ?? 'a.created_at';

    // The query is filtered by the logged-in teacher's ID
    $sql = "SELECT a.id, a.title, a.created_at FROM announcements a WHERE a.created_by = ?";
    
    $params = [$teacher_id];
    $types = 'i';

    if (!empty($search)) {
        $sql .= " AND (a.title LIKE ? OR a.content LIKE ?)";
        $search_param = "%" . $search . "%";
        array_push($params, $search_param, $search_param);
        $types .= 'ss';
    }

    $sql .= " ORDER BY {$order_by_column} {$sort_dir}";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $announcements = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
    }
    $stmt->close();
    return $announcements;
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

$announcements_data = get_announcements_for_teacher($conn, $teacher_id, $search_query, $sort_column, $sort_direction);
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; // Assuming a teacher-specific sidebar ?>

<main class="content">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
             <h1 class="h3 mb-0">My Announcements</h1>
             <a href="teacher-add-announcement.php" class="btn btn-primary">Create New Announcement</a>
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
                        <h5 class="card-title">Filter My Announcements</h5>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mt-2">
                             <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search by Title or Content..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-primary" type="submit">Search</button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="teacher-announcements.php" class="btn btn-secondary">Clear</a>
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
                                        <th><?php echo sortable_header('Date Created', 'created_at', $sort_column, $sort_direction, $search_query); ?></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($announcements_data)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center p-4">
                                                <?php echo !empty($search_query) ? 'No announcements found matching your search.' : 'You have not created any announcements yet.'; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($announcements_data as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row["id"]); ?></td>
                                                <td><?php echo htmlspecialchars($row["title"]); ?></td>
                                                <td><?php echo date("F j, Y, g:i a", strtotime($row["created_at"])); ?></td>
                                                <td>
                                                    <a href="teacher-edit-announcement.php?id=<?php echo $row["id"]; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                                    <a href="teacher-delete-announcement.php?id=<?php echo $row["id"]; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?')"><i class="fas fa-trash-alt"></i> Delete</a>
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