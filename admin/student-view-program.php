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

$program_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($program_id <= 0) {
    die("Invalid program ID.");
}

// Fetch program details
$sql = "SELECT id, name, description, start_date, end_date, photo FROM school_programs WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $program_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Program not found.");
}
$program = $result->fetch_assoc();
$stmt->close();
$conn->close();

?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_student.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <div class="mb-3">
             <a href="student-school_programs.php" class="btn btn-outline-primary">&larr; Back to All Programs</a>
        </div>

        <h1 class="h3 mb-3"><?php echo htmlspecialchars($program['name']); ?></h1>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <?php if (!empty($program['photo'])): ?>
                        <img src="uploads/programs/<?php echo htmlspecialchars($program['photo']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($program['name']); ?>" style="max-height: 400px; object-fit: cover;">
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Program Dates:</strong> 
                            <?php echo date("F j, Y", strtotime($program['start_date'])); ?> to <?php echo date("F j, Y", strtotime($program['end_date'])); ?>
                        </div>
                        <hr>
                        <div class="program-description">
                            <p class="lead">
                                <?php echo nl2br(htmlspecialchars($program['description'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<style>
    .program-description {
        font-size: 1.1rem;
        line-height: 1.7;
    }
</style>

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