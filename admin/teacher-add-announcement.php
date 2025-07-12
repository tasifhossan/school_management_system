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


$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $created_by = $_SESSION['user_id']; // The creator is the logged-in teacher
    $photo_path = null;

    if (empty($title) || empty($content)) {
        $message = "Error: Title and Content fields cannot be empty.";
        $message_type = 'danger';
    } else {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/announcements/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

            $file_tmp_name = $_FILES['photo']['tmp_name'];
            $file_name = basename($_FILES['photo']['name']);
            $unique_file_name = uniqid() . '-' . time() . '-' . preg_replace('/[^A-Za-z0-9\.\-]/', '_', $file_name);
            $destination = $upload_dir . $unique_file_name;

            if (move_uploaded_file($file_tmp_name, $destination)) {
                $photo_path = $unique_file_name;
            } else {
                $message = "Error: Failed to move uploaded photo.";
                $message_type = 'danger';
            }
        }

        if (empty($message)) {
            $sql = "INSERT INTO announcements (title, photo_path, content, created_by) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $title, $photo_path, $content, $created_by);

            if ($stmt->execute()) {
                $_SESSION['status_message'] = "Announcement published successfully!";
                header("Location: teacher-announcements.php");
                exit();
            } else {
                $message = "Error: Could not publish announcement.";
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Create New Announcement</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Announcement Details</h5>
                        <h6 class="card-subtitle text-muted">Fill out the form below to create a new announcement.</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="content" class="form-label">Content</label>
                                <textarea class="form-control" id="content" name="content" rows="8" required></textarea>
                            </div>
                             <div class="mb-3">
                                <label for="photo" class="form-label">Featured Photo (Optional)</label>
                                <input class="form-control" type="file" id="photo" name="photo" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-primary">Publish Announcement</button>
                            <a href="teacher-announcements.php" class="btn btn-secondary">Cancel</a>
                        </form>
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