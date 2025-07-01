<?php
/**
 * PHP MySQLi Database Connection Script
 *
 * This script provides a simple way to connect to a MySQL database
 * using the MySQLi extension. It's designed to be included in other PHP files
 * where database interaction is needed.
 */

// 1. Database Connection Configuration
// IMPORTANT: Replace with your actual database credentials
$servername = "localhost";
$username = "root"; // e.g., root
$password = ""; // e.g., your_db_password (remember to use a strong password!)
$dbname = "unitrade"; // The name of your database

// Create connection
$link = new mysqli($servername, $username, $password, $dbname); // Changed $conn to $link for consistency

// Check connection
if ($link->connect_error) { // Changed $conn to $link
    // On connection failure, simply die or throw an exception.
    // The calling script (e.g., signup.php) will catch this and handle the JSON response.
    die("Connection failed: " . $link->connect_error);
    // Alternatively, for more controlled error handling in production:
    // error_log("Database connection failed: " . $link->connect_error);
    // exit(); // Or throw new Exception("Database connection failed.");
}

// Set character set to UTF-8 for proper handling of various characters.
// This is important to prevent character encoding issues.
$link->set_charset("utf8mb4"); // Changed $conn to $link

// At this point, the $link variable holds the active database connection.
// This script should be saved as a file (e.g., 'database.php')
// and included using 'require_once' in any other PHP files that need database access.

?>
