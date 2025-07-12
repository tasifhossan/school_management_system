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

$announcement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($announcement_id <= 0) {
    die("Invalid announcement ID.");
}

// Fetch announcement details along with the creator's name
// CORRECTED: Changed 'a.photo' to 'a.photo_path' to match the database schema.
$sql = "SELECT 
            a.title, a.content, a.photo_path, a.created_at, 
            u.name AS created_by_name
        FROM announcements a
        JOIN users u ON a.created_by = u.id
        WHERE a.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $announcement_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Announcement not found.");
}
$announcement = $result->fetch_assoc();
$stmt->close();
$conn->close();

?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_student.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <div class="mb-3">
             <a href="student-announcements.php" class="btn btn-outline-primary">&larr; Back to Announcements</a>
        </div>

        <h1 class="h3 mb-3"><?php echo htmlspecialchars($announcement['title']); ?></h1>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title text-muted">
                            Published by: <?php echo htmlspecialchars($announcement['created_by_name']); ?> 
                            on <?php echo date("F j, Y, g:i a", strtotime($announcement['created_at'])); ?>
                        </h5>
                    </div>
                    <?php if (!empty($announcement['photo_path'])): ?>
                        <img src="uploads/announcements/<?php echo htmlspecialchars($announcement['photo_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($announcement['title']); ?>" style="max-height: 400px; object-fit: cover;">
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="announcement-content">
                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>


<?php include "footer.php";  ?>

<style>
    .announcement-content {
        font-size: 1.1rem;
        line-height: 1.6;
    }
    .result { padding: 1rem; border-radius: 0.5rem; border: 1px solid transparent; }
    .result-success { color: #0f5132; background-color: #d1e7dd; border-color: #badbcc; }
    .result-error { color: #842029; background-color: #f8d7da; border-color: #f5c2c7; }
    .action-buttons a { margin-right: 0.25rem; }
    th a { text-decoration: none; color: inherit; }
    th a:hover { color: #0d6efd; }
</style>
</body>

</html>