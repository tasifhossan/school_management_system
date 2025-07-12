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


$success_message = '';
$error_message = '';

// --- Fetch All Current Settings ---
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Function to safely get a setting value
function get_setting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? htmlspecialchars($settings[$key]) : $default;
}


// --- Handle Form Submission to Update Settings ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed_keys = [
        'school_name', 'school_email', 'school_phone', 'school_address', 
        'current_session', 'timezone', 'date_format', 'default_language', 
        'footer_text', 'currency_symbol'
    ];
    
    // Begin a transaction
    $conn->begin_transaction();
    try {
        $update_stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        
        // Handle standard text inputs
        foreach ($_POST as $key => $value) {
            if (in_array($key, $allowed_keys)) {
                $trimmed_value = trim($value);
                $update_stmt->bind_param("ss", $trimmed_value, $key);
                $update_stmt->execute();
            }
        }

        // Handle switches (checkboxes)
        $setting_key_maintenance = 'maintenance_mode';
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 'on' : 'off';
        $update_stmt->bind_param("ss", $maintenance_mode, $setting_key_maintenance);
        $update_stmt->execute();

        $setting_key_registration = 'enable_registration';
        $enable_registration = isset($_POST['enable_registration']) ? 'on' : 'off';
        $update_stmt->bind_param("ss", $enable_registration, $setting_key_registration);
        $update_stmt->execute();
        
        $setting_key_messaging = 'enable_private_messaging';
        $enable_private_messaging = isset($_POST['enable_private_messaging']) ? 'on' : 'off';
        $update_stmt->bind_param("ss", $enable_private_messaging, $setting_key_messaging);
        $update_stmt->execute();

        // Handle Logo Upload
        if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
            $logo_file = $_FILES['school_logo'];
            $upload_dir = '../uploads/logo/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $allowed_types = ['image/png', 'image/jpeg', 'image/svg+xml'];
            if (in_array($logo_file['type'], $allowed_types) && $logo_file['size'] < 2000000) { // 2MB limit
                $old_logo = get_setting('school_logo');
                if (!empty($old_logo) && file_exists($upload_dir . $old_logo)) {
                    unlink($upload_dir . $old_logo);
                }
                
                $file_extension = pathinfo($logo_file['name'], PATHINFO_EXTENSION);
                $new_logo_name = 'school_logo_' . time() . '.' . $file_extension;
                $destination = $upload_dir . $new_logo_name;
                
                if (move_uploaded_file($logo_file['tmp_name'], $destination)) {
                    $setting_key_logo = 'school_logo';
                    $update_stmt->bind_param("ss", $new_logo_name, $setting_key_logo);
                    $update_stmt->execute();
                } else {
                    throw new Exception("Could not move uploaded logo.");
                }
            } else {
                 throw new Exception("Invalid file type or size for logo.");
            }
        }
        
        $update_stmt->close();
        
        $conn->commit();
        $success_message = "Settings updated successfully!";
        // Refresh settings after update
        $result = $conn->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

    } catch (Exception $exception) {
        $conn->rollback();
        $error_message = "Error updating settings: " . $exception->getMessage();
    }
}
?>
<?php include "dashboard-top.php"; ?>
<?php include "sidebar_ad.php"; ?>

<main class="content">
    <div class="container-fluid p-0">
        <h1 class="h3 mb-3">General Settings</h1>
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="settings-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" id="general-tab" data-bs-toggle="tab" href="#general" role="tab">General</a></li>
                    <li class="nav-item"><a class="nav-link" id="branding-tab" data-bs-toggle="tab" href="#branding" role="tab">Branding</a></li>
                    <li class="nav-item"><a class="nav-link" id="system-tab" data-bs-toggle="tab" href="#system" role="tab">System</a></li>
                </ul>
            </div>
            <div class="card-body">
                <?php if(!empty($success_message)): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
                <?php if(!empty($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="tab-content" id="settings-tab-content">
                        <!-- General Settings Tab -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel">
                            <h5 class="card-title mt-3">School Information</h5>
                            <div class="mb-3"><label for="school_name" class="form-label">School Name</label><input type="text" class="form-control" id="school_name" name="school_name" value="<?php echo get_setting('school_name'); ?>"></div>
                            <div class="mb-3"><label for="school_email" class="form-label">Public Email</label><input type="email" class="form-control" id="school_email" name="school_email" value="<?php echo get_setting('school_email'); ?>"></div>
                            <div class="mb-3"><label for="school_phone" class="form-label">Public Phone</label><input type="text" class="form-control" id="school_phone" name="school_phone" value="<?php echo get_setting('school_phone'); ?>"></div>
                            <div class="mb-3"><label for="school_address" class="form-label">School Address</label><textarea class="form-control" id="school_address" name="school_address" rows="3"><?php echo get_setting('school_address'); ?></textarea></div>
                            <hr>
                            <h5 class="card-title mt-3">Localization</h5>
                             <div class="mb-3">
                                <label for="currency_symbol" class="form-label">Currency Symbol</label>
                                <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" value="<?php echo get_setting('currency_symbol'); ?>" style="max-width: 100px;">
                            </div>
                             <div class="mb-3">
                                <label for="default_language" class="form-label">Default Language</label>
                                <input type="text" class="form-control" id="default_language" name="default_language" value="<?php echo get_setting('default_language'); ?>" style="max-width: 100px;">
                                <div class="form-text">e.g., 'en' for English.</div>
                            </div>
                        </div>

                        <!-- Branding Settings Tab -->
                        <div class="tab-pane fade" id="branding" role="tabpanel">
                             <h5 class="card-title mt-3">Logo & Branding</h5>
                             <div class="mb-3">
                                <label for="school_logo" class="form-label">School Logo</label>
                                <input class="form-control" type="file" id="school_logo" name="school_logo">
                                <div class="form-text">Upload a PNG, JPG, or SVG file. Max size: 2MB.</div>
                                <?php $current_logo = get_setting('school_logo'); if(!empty($current_logo)): ?>
                                    <div class="mt-2">
                                        <p>Current Logo:</p>
                                        <img src="../uploads/logo/<?php echo $current_logo; ?>" alt="School Logo" style="max-height: 80px; background: #f0f0f0; padding: 5px; border-radius: 5px;">
                                    </div>
                                <?php endif; ?>
                             </div>
                             <hr>
                             <div class="mb-3">
                                <label for="footer_text" class="form-label">Footer Text</label>
                                <input type="text" class="form-control" id="footer_text" name="footer_text" value="<?php echo get_setting('footer_text'); ?>">
                                <div class="form-text">This text will appear at the bottom of every page.</div>
                            </div>
                        </div>

                        <!-- System Settings Tab -->
                        <div class="tab-pane fade" id="system" role="tabpanel">
                            <h5 class="card-title mt-3">System Configuration</h5>
                            <div class="mb-3">
                               <label for="current_session" class="form-label">Current Academic Session</label>
                               <input type="text" class="form-control" id="current_session" name="current_session" value="<?php echo get_setting('current_session'); ?>" placeholder="e.g., 2024-2025">
                            </div>
                            <div class="mb-3">
                                <label for="timezone" class="form-label">Timezone</label>
                                <input type="text" class="form-control" id="timezone" name="timezone" value="<?php echo get_setting('timezone'); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="date_format" class="form-label">Date Format</label>
                                <input type="text" class="form-control" id="date_format" name="date_format" value="<?php echo get_setting('date_format'); ?>">
                            </div>
                            <hr>
                            <h5 class="card-title mt-3">Feature Toggles</h5>
                            <div class="form-check form-switch mb-3">
                              <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" <?php echo get_setting('maintenance_mode') === 'on' ? 'checked' : ''; ?>>
                              <label class="form-check-label" for="maintenance_mode">Enable Maintenance Mode</label>
                              <div class="form-text">When enabled, only admins can access the site.</div>
                            </div>
                            <div class="form-check form-switch mb-3">
                              <input class="form-check-input" type="checkbox" id="enable_registration" name="enable_registration" <?php echo get_setting('enable_registration') === 'on' ? 'checked' : ''; ?>>
                              <label class="form-check-label" for="enable_registration">Enable New User Registration</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                              <input class="form-check-input" type="checkbox" id="enable_private_messaging" name="enable_private_messaging" <?php echo get_setting('enable_private_messaging') === 'on' ? 'checked' : ''; ?>>
                              <label class="form-check-label" for="enable_private_messaging">Enable Private Messaging</dlabel>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                         <button type="submit" class="btn btn-primary">Save All Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>
</body>
</html>