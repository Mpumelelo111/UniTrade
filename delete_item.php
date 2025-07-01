<?php
// delete_item.php

// Start output buffering at the very beginning to capture any unwanted output
ob_start();

session_start(); // Start the session

// Set error reporting for production (errors will be logged, not displayed)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Include the database connection file
require_once 'database.php'; // Adjust path if necessary

// --- User Authentication Check ---
// If the user is not logged in or not acting as a seller, redirect them
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header("Location: " . (isset($_SESSION['user_id']) ? "dashboard.php" : "login.php"));
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Check if item_id is provided in the URL
$item_id = $_GET['item_id'] ?? null;

if (empty($item_id) || !is_numeric($item_id)) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Invalid item ID provided for deletion.'];
    header("Location: dashboard.php");
    exit();
}

// --- Verify item ownership before attempting to delete/update status ---
$stmt = $link->prepare("SELECT seller_id FROM Items WHERE item_id = ?");
if ($stmt === false) {
    // FIX: Changed $conn->error to $link->error
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Database query preparation failed during ownership check: ' . $link->error];
    header("Location: dashboard.php");
    exit();
}

$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item || $item['seller_id'] != $current_user_id) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Unauthorized: Item not found or you do not own it.'];
    header("Location: dashboard.php");
    exit();
}

// --- "Soft Delete" the item by updating its status ---
// It's generally better practice not to hard-delete records, especially if there might be linked transactions or a need for historical data.
// We'll set the status to 'Removed'. You can define other statuses like 'Archived' etc.
$new_status = 'Removed';
$stmt = $link->prepare("UPDATE Items SET status = ? WHERE item_id = ? AND seller_id = ?"); // Added seller_id again for extra security
if ($stmt === false) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Database update preparation failed for deletion: ' . $link->error];
    header("Location: dashboard.php");
    exit();
}

$stmt->bind_param("sii", $new_status, $item_id, $current_user_id);

if ($stmt->execute()) {
    $_SESSION['form_message'] = ['type' => 'success', 'text' => 'Item successfully removed from listings.'];
} else {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Failed to remove item: ' . $stmt->error];
}
$stmt->close();

// Close the database connection
if (isset($link) && is_object($link) && method_exists($link, 'close')) {
    $link->close();
}

// End output buffering and send the content to the browser
ob_end_flush();

// Redirect back to the dashboard after processing
header("Location: dashboard.php");
exit();

?>
