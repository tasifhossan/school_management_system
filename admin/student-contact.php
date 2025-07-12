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

$contact_info = null;

// --- Fetch the primary contact information ---
// We assume there's only one main contact record, so we take the first one.
$result = $conn->query("SELECT * FROM contact_info ORDER BY id ASC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $contact_info = $result->fetch_assoc();
}

$conn->close();
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_student.php"; ?>

<main class="content">
    <div class="container-fluid p-0">

        <h1 class="h3 mb-3">Contact Information</h1>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Official School Contact Details</h5>
                        <h6 class="card-subtitle text-muted">Use the information below to get in touch with the school administration.</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($contact_info): ?>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <h5><?php echo htmlspecialchars($contact_info['name']); ?></h5>
                                    <?php if (!empty($contact_info['position'])): ?>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($contact_info['position']); ?></p>
                                    <?php endif; ?>
                                </li>
                                <li class="list-group-item">
                                    <strong><i class="align-middle me-2" data-feather="phone"></i> Phone:</strong> 
                                    <a href="tel:<?php echo htmlspecialchars($contact_info['contact_number']); ?>"><?php echo htmlspecialchars($contact_info['contact_number']); ?></a>
                                </li>
                                <li class="list-group-item">
                                    <strong><i class="align-middle me-2" data-feather="mail"></i> Email:</strong> 
                                    <a href="mailto:<?php echo htmlspecialchars($contact_info['email']); ?>"><?php echo htmlspecialchars($contact_info['email']); ?></a>
                                </li>
                                <?php if (!empty($contact_info['office_hours'])): ?>
                                <li class="list-group-item">
                                    <strong><i class="align-middle me-2" data-feather="clock"></i> Office Hours:</strong>
                                    <div class="mt-1">
                                        <?php echo nl2br(htmlspecialchars($contact_info['office_hours'])); ?>
                                    </div>
                                </li>
                                <?php endif; ?>
                            </ul>
                        <?php else: ?>
                            <div class="alert alert-warning" role="alert">
                                Contact information has not been set up by the administration yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<?php include "footer.php";  ?>

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