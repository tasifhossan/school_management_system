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

// Check for a valid Announcement ID from the GET request
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $announcement_id_to_delete = (int)$_GET['id'];

    if ($announcement_id_to_delete > 0) {
        // Start a transaction
        $conn->begin_transaction();
        
        try {
            // Prepare the DELETE statement for the announcements table
            $sql = "DELETE FROM announcements WHERE id = ?";
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                throw new Exception("Failed to prepare the SQL statement.");
            }
            
            $stmt->bind_param("i", $announcement_id_to_delete);

            if ($stmt->execute()) {
                // Check if a row was actually deleted
                if ($stmt->affected_rows > 0) {
                    $message = "Announcement deleted successfully!";
                    $message_type = 'success';
                } else {
                    throw new Exception("No announcement found with that ID.");
                }
            } else {
                throw new Exception("Error executing the deletion query.");
            }
            $stmt->close();

            // If everything was successful, commit the transaction
            $conn->commit();

        } catch (Exception $e) {
            // If any error occurred, roll back the transaction
            $conn->rollback();
            $message = "Error: Could not delete the announcement. " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = 'Invalid Announcement ID provided.';
        $message_type = 'error';
    }
} else {
    $message = 'No Announcement ID provided.';
    $message_type = 'error';
}

$conn->close();

header("Location: school-operations-announcements.php");
exit();
?>