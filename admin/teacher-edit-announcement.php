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


$announcement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$teacher_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
$announcement = null;

if ($announcement_id <= 0) {
    header("Location: teacher-announcements.php");
    exit();
}

// --- Fetch existing announcement data, ensuring it belongs to the logged-in teacher ---
$stmt = $conn->prepare("SELECT title, content, photo_path FROM announcements WHERE id = ? AND created_by = ?");
$stmt->bind_param("ii", $announcement_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $announcement = $result->fetch_assoc();
} else {
    // No announcement found for this teacher, redirect
    $_SESSION['status_message'] = "Error: You do not have permission to edit this announcement.";
    header("Location: teacher-announcements.php");
    exit();
}
$stmt->close();

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $current_photo = $_POST['current_photo'];
    $photo_path = $current_photo;

    if (empty($title) || empty($content)) {
        $message = "Error: Title and Content fields are required.";
        $message_type = 'danger';
    } else {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/announcements/';
            if (!empty($current_photo) && file_exists($upload_dir . $current_photo)) {
                unlink($upload_dir . $current_photo);
            }
            $file_tmp_name = $_FILES['photo']['tmp_name'];
            $file_name = basename($_FILES['photo']['name']);
            $unique_file_name = uniqid() . '-' . time() . '-' . preg_replace('/[^A-Za-z0-9\.\-]/', '_', $file_name);
            $destination = $upload_dir . $unique_file_name;

            if (move_uploaded_file($file_tmp_name, $destination)) {
                $photo_path = $unique_file_name;
            } else {
                $message = "Error: Failed to move new photo.";
                $message_type = 'danger';
            }
        }

        if (empty($message)) {
            $sql = "UPDATE announcements SET title = ?, content = ?, photo_path = ? WHERE id = ? AND created_by = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $title, $content, $photo_path, $announcement_id, $teacher_id);

            if ($stmt->execute()) {
                $_SESSION['status_message'] = "Announcement updated successfully!";
                header("Location: teacher-announcements.php");
                exit();
            } else {
                $message = "Error: Could not update announcement.";
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
    $announcement['title'] = $title;
    $announcement['content'] = $content;
}
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Edit Announcement</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Modify Your Announcement</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        <form action="teacher-edit-announcement.php?id=<?php echo $announcement_id; ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="current_photo" value="<?php echo htmlspecialchars($announcement['photo_path']); ?>">
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($announcement['title']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="content" class="form-label">Content</label>
                                <textarea class="form-control" id="content" name="content" rows="8" required><?php echo htmlspecialchars($announcement['content']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="photo" class="form-label">Change Featured Photo (Optional)</label>
                                <?php if (!empty($announcement['photo_path'])): ?>
                                    <div class="mb-2">
                                        <img src="uploads/announcements/<?php echo htmlspecialchars($announcement['photo_path']); ?>" alt="Current Photo" style="max-width: 200px; max-height: 200px; border-radius: 0.25rem;">
                                        <p class="form-text mt-1">Current photo. Uploading a new file will replace this one.</p>
                                    </div>
                                <?php endif; ?>
                                <input class="form-control" type="file" id="photo" name="photo" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
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