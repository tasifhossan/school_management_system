 <?php

session_start();
// db connection
include "../lib/connection.php";

// Check if the user is logged in and is an admin.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Redirect to login page
    exit();
}

$userId = $_SESSION['user_id'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';

$message = '';
$message_type = '';
$contact_info = null;

// --- Fetch existing contact information (we assume there's only one primary record) ---
$result = $conn->query("SELECT * FROM contact_info LIMIT 1");
if ($result && $result->num_rows > 0) {
    $contact_info = $result->fetch_assoc();
}

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $position = trim($_POST['position']);
    $contact_number = trim($_POST['contact_number']);
    $email = trim($_POST['email']);
    $office_hours = trim($_POST['office_hours']);
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    // Basic validation
    if (empty($name) || empty($contact_number) || empty($email)) {
        $message = "Error: Name, Contact Number, and Email are required fields.";
        $message_type = 'danger';
    } else {
        if ($id > 0) {
            // --- UPDATE existing record ---
            $sql = "UPDATE contact_info SET name = ?, position = ?, contact_number = ?, email = ?, office_hours = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $name, $position, $contact_number, $email, $office_hours, $id);
        } else {
            // --- INSERT new record ---
            $sql = "INSERT INTO contact_info (name, position, contact_number, email, office_hours) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $name, $position, $contact_number, $email, $office_hours);
        }

        if ($stmt->execute()) {
            $message = "Contact information saved successfully!";
            $message_type = 'success';
            // Refresh data to show in the form
            $result = $conn->query("SELECT * FROM contact_info LIMIT 1");
            if ($result && $result->num_rows > 0) {
                $contact_info = $result->fetch_assoc();
            }
        } else {
            $message = "Error: Could not save the information. Please try again.";
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">

        <h1 class="h3 mb-3">Manage School Contact Information</h1>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Contact Details</h5>
                        <h6 class="card-subtitle text-muted">Update the primary contact information. This will be displayed publicly.</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                            <!-- Hidden field to store the ID for updates -->
                            <input type="hidden" name="id" value="<?php echo $contact_info['id'] ?? '0'; ?>">

                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="name" class="form-label">Contact Name / Department Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($contact_info['name'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="position" class="form-label">Position (Optional)</label>
                                    <input type="text" class="form-control" id="position" name="position" value="<?php echo htmlspecialchars($contact_info['position'] ?? ''); ?>">
                                </div>
                            </div>
                             <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="contact_number" class="form-label">Contact Number</label>
                                    <input type="tel" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($contact_info['contact_number'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($contact_info['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="office_hours" class="form-label">Office Hours / Availability (Optional)</label>
                                <textarea class="form-control" id="office_hours" name="office_hours" rows="4"><?php echo htmlspecialchars($contact_info['office_hours'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Information</button>
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
    .action-buttons a { text-decoration: none; padding: 0.25rem 0.5rem; border-radius: 0.25rem; margin-right: 0.25rem; transition: background-color 0.15s ease-in-out; }
    .edit-button { color: #0d6efd; }
    .edit-button:hover { background-color: rgba(13, 110, 253, 0.1); }
    .delete-button { color: #dc3545; }
    .delete-button:hover { background-color: rgba(220, 53, 69, 0.1); }
    th a { text-decoration: none; color: inherit; }
    th a:hover { color: #0d6efd; }
    @media (max-width: 768px) { .d-md-table-cell { display: none; } }
</style>

</body>
</html>