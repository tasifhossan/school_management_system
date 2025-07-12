<?php 

$host = "localhost";
$user = "root";
$pass = "";
$db   = "school";

$conn = new mysqli($host, $user, $pass, $db);


// Fetch maintenance mode setting
$maintenance_setting = 'off'; // Default to off if not found
$result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $maintenance_setting = $row['setting_value'];
}

// Get the name of the current script
$current_page = basename($_SERVER['PHP_SELF']);

// Check if maintenance mode is on
if ($maintenance_setting === 'on' && $current_page !== 'maintenance.php' && $current_page !== 'login.php') {
    // Allow admins to bypass maintenance mode
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        // Redirect all other users to the maintenance page
        header('Location: ../admin/maintenance.php'); // Adjust path if needed
        exit();
    }
}

// The rest of your existing connection.php code would continue here...
// For example: if(!isset... etc)

// if($conn -> connect_error ){
// 	die($conn -> error );
// }else{
// 	// echo  "database connected successfully";
// }



?>