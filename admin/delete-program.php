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

// Initialize message variables
$message = '';
$message_type = '';

// --- Check for a valid Program ID from the GET request ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $program_id_to_delete = (int)$_GET['id'];

    if ($program_id_to_delete > 0) {
        // We use a transaction to ensure both file and DB record are handled together
        $conn->begin_transaction();

        try {
            // 1. Get the photo filename before deleting the record
            $sql_get_photo = "SELECT photo FROM school_programs WHERE id = ? FOR UPDATE";
            $stmt_get_photo = $conn->prepare($sql_get_photo);
            $stmt_get_photo->bind_param("i", $program_id_to_delete);
            $stmt_get_photo->execute();
            $result = $stmt_get_photo->get_result();
            $photo_filename = null;
            if ($row = $result->fetch_assoc()) {
                $photo_filename = $row['photo'];
            }
            $stmt_get_photo->close();

            // 2. Delete the record from the database
            $sql_delete = "DELETE FROM school_programs WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $program_id_to_delete);

            if ($stmt_delete->execute()) {
                // 3. If DB deletion is successful, delete the photo file from the server
                if (!empty($photo_filename)) {
                    $upload_dir = 'uploads/';
                    if (file_exists($upload_dir . $photo_filename)) {
                        unlink($upload_dir . $photo_filename);
                    }
                }
                $message = "Program deleted successfully!";
                $message_type = 'success';
            } else {
                throw new Exception("Error executing the deletion query.");
            }
            $stmt_delete->close();
            
            // If all went well, commit the transaction
            $conn->commit();

        } catch (Exception $e) {
            // If anything fails, roll back the transaction
            $conn->rollback();
            $message = "Error: Could not delete the program. " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = 'Invalid Program ID.';
        $message_type = 'error';
    }
} else {
    $message = 'No Program ID provided.';
    $message_type = 'error';
}

$conn->close();

// --- Redirect back to the main programs list page with a status message ---
$redirect_url = "school-operations-programs.php?message=" . urlencode($message) . "&type=" . urlencode($message_type);
header("Location: " . $redirect_url);
exit();