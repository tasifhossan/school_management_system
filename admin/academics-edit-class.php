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

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $conn->prepare("UPDATE classes SET name = ?, section = ?, teacher_id = ? WHERE id = ?");
    $stmt->bind_param("ssii", $name, $section, $teacher_id, $id);

    $name = $_POST['name'];
    $section = $_POST['section'];
    $teacher_id = $_POST['teacher_id'];
    
    if ($stmt->execute()) {
        header("Location: academics-classes.php");
    } else {
        echo "Error updating record: " . $stmt->error;
    }
    $stmt->close();
}
?>
<?php include "dashboard-top.php" ?>
<?php include "sidebar_ad.php" ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">Edit Class</h1>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form action="academics-edit-class.php?id=<?php echo $id; ?>" method="post">
                            <div class="mb-3">
                                <label for="name" class="form-label">Class Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo $row['name']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="section" class="form-label">Section</label>
                                <input type="text" class="form-control" id="section" name="section" value="<?php echo $row['section']; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="teacher_id" class="form-label">Teacher ID</label>
                                <input type="number" class="form-control" id="teacher_id" name="teacher_id" value="<?php echo $row['teacher_id']; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Update</button>
                            <a href="academics-classes.php" class="btn btn-secondary">
                                <i data-feather="arrow-left" class="me-1" style="width:16px; height:16px;"></i> Back to Classes
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php" ?>