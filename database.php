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
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // Return a JSON error response if the database connection fails
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit(); // Stop script execution on connection failure
}

// Set character set to UTF-8 for proper handling of various characters.
// This is important to prevent character encoding issues.
// FIX: Changed '$link' to '$conn' to match your connection variable.
$conn->set_charset("utf8mb4");

// At this point, the $conn variable holds the active database connection.
// This script should be saved as a file (e.g., 'db_connect.php')
// and included using 'require_once' in any other PHP files that need database access.

?>