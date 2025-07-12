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

// --- Handle Form Submission (Update or Insert) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        // 1. Update the `users` table (for name, email, phone)
        $stmt_user = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt_user->bind_param("sssi", $_POST['name'], $_POST['email'], $_POST['phone'], $userId);
        $stmt_user->execute();
        $stmt_user->close();

        // 2. Check if a profile already exists in the `teachers` table
        $stmt_check = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmt_check->bind_param("i", $userId);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $stmt_check->close();

        if ($result_check->num_rows > 0) {
            // If it exists, UPDATE it
            $stmt_teacher = $conn->prepare("UPDATE teachers SET department = ?, qualifications = ?, specialization = ?, office_location = ?, office_hours = ? WHERE user_id = ?");
            $stmt_teacher->bind_param("sssssi", $_POST['department'], $_POST['qualifications'], $_POST['specialization'], $_POST['office_location'], $_POST['office_hours'], $userId);
        } else {
            // If it does not exist, INSERT a new one
            $stmt_teacher = $conn->prepare("INSERT INTO teachers (user_id, department, qualifications, specialization, office_location, office_hours) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_teacher->bind_param("isssss", $userId, $_POST['department'], $_POST['qualifications'], $_POST['specialization'], $_POST['office_location'], $_POST['office_hours']);
        }
        $stmt_teacher->execute();
        $stmt_teacher->close();

        // 3. Commit the transaction
        $conn->commit();
        $_SESSION['success_message'] = "Profile updated successfully!";

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error updating profile: " . $exception->getMessage();
    }
    
    header("Location: my_profile.php");
    exit();
}


// --- Fetch Data for Display ---
$teacher_info = [];
// Use LEFT JOIN to get user info even if teacher profile doesn't exist yet
$stmt_fetch = $conn->prepare("SELECT u.name, u.email, u.phone, t.employee_id, t.department, t.qualifications, t.specialization, t.office_location, t.office_hours 
                                FROM users u 
                                LEFT JOIN teachers t ON u.id = t.user_id 
                                WHERE u.id = ?");
$stmt_fetch->bind_param("i", $userId);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();
if ($result_fetch->num_rows > 0) {
    $teacher_info = $result_fetch->fetch_assoc();
}
$stmt_fetch->close();
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_teacher.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">My Profile</h1>
        
        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Your Information</h5>
            </div>
            <div class="card-body">
                <form action="my_profile.php" method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($teacher_info['name'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($teacher_info['email'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($teacher_info['phone'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="employee_id" class="form-label">Employee ID</label>
                                <input type="text" class="form-control" id="employee_id" name="employee_id" value="<?php echo htmlspecialchars($teacher_info['employee_id'] ?? ''); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($teacher_info['department'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="qualifications" class="form-label">Qualifications</label>
                                <textarea class="form-control" id="qualifications" name="qualifications" rows="3"><?php echo htmlspecialchars($teacher_info['qualifications'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="specialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control" id="specialization" name="specialization" value="<?php echo htmlspecialchars($teacher_info['specialization'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="office_location" class="form-label">Office Location</label>
                                <input type="text" class="form-control" id="office_location" name="office_location" value="<?php echo htmlspecialchars($teacher_info['office_location'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="office_hours" class="form-label">Office Hours</label>
                                <textarea class="form-control" id="office_hours" name="office_hours" rows="2"><?php echo htmlspecialchars($teacher_info['office_hours'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>