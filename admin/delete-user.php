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

// Initialize default messages for the session in case of unexpected flow
$_SESSION['status_message'] = "An unexpected error occurred.";
$_SESSION['status_class'] = "result-error";

// Check if a user ID is provided in the URL query string (GET request)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int)$_GET['id'];

    // Start a database transaction to ensure atomicity (all or nothing).
    // This is vital for critical operations like deletion to maintain data integrity.
    // If any step fails, all changes within the transaction are rolled back.
    $conn->begin_transaction();

    try {
        // Prepare the SQL statement to delete the user from the 'users' table.
        // Using prepared statements prevents SQL injection vulnerabilities.
        // The 'ON DELETE CASCADE' foreign key constraint on 'students' and 'teachers' tables
        // means that deleting a user here will automatically delete related records
        // in those tables.
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");

        // Check if the statement preparation failed.
        if ($stmt === false) {
            throw new Exception("Failed to prepare delete statement: " . $conn->error);
        }

        // Bind the user ID parameter to the prepared statement.
        // 'i' specifies that the parameter is an integer.
        $stmt->bind_param("i", $user_id);

        // Execute the prepared statement.
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute delete statement: " . $stmt->error);
        }

        // Check if any rows were affected (i.e., if a user record was actually deleted).
        if ($stmt->affected_rows > 0) {
            // If deletion was successful, commit the transaction.
            $conn->commit();
            $_SESSION['status_message'] = "User and associated records deleted successfully!";
            $_SESSION['status_class'] = "result-success";
        } else {
            // If no rows were affected, it means the user ID might not have existed.
            // Rollback the transaction as no changes were made.
            $conn->rollback();
            $_SESSION['status_message'] = "Error: User with ID " . htmlspecialchars($user_id) . " not found.";
            $_SESSION['status_class'] = "result-error";
        }

        // Close the prepared statement to free up resources.
        $stmt->close();

    } catch (Exception $e) {
        // If any exception occurs during the try block, rollback the transaction.
        $conn->rollback();
        $_SESSION['status_message'] = "Error deleting user: " . $e->getMessage();
        $_SESSION['status_class'] = "result-error";
        // Log the detailed error message to the server's error log for debugging.
        error_log("Delete user failed: " . $e->getMessage());
    }
} else {
    // If no user ID was provided in the URL, set an appropriate error message.
    $_SESSION['status_message'] = "Error: No user ID provided for deletion.";
    $_SESSION['status_class'] = "result-error";
}

// Close the database connection.
$conn->close();

// Redirect back to the users-students.php page after processing the deletion.
// This is the key change for background execution - no HTML output on this page.
header("Location: users-admins.php");
exit(); // Always call exit() after header() to ensure script termination.
?>
