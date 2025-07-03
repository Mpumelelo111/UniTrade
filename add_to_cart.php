<?php
// add_to_cart.php
session_start(); // Start the session to manage the shopping cart

// Include the database connection file
require_once 'database.php'; // Adjust path as necessary (assuming this defines $link)

// --- User Authentication Check ---
// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Please log in to add items to your cart.'];
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$item_id = $_GET['item_id'] ?? null; // Get item_id from the URL

if (empty($item_id) || !is_numeric($item_id)) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Invalid item selected.'];
    header("Location: dashboard.php"); // Redirect back to dashboard if item_id is missing/invalid
    exit();
}

// --- Fetch Item Details from Database ---
$item = null;
$stmt = $link->prepare("SELECT item_id, title, price, image_urls, seller_id FROM Items WHERE item_id = ? AND status = 'Available'");
if ($stmt === false) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Database query preparation failed: ' . $link->error];
    header("Location: dashboard.php");
    exit();
}

$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $item = $result->fetch_assoc();
}
$stmt->close();

// Close the database connection
if (isset($link) && is_object($link) && method_exists($link, 'close')) {
    $link->close();
}

if (!$item) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Item not found or not available for purchase.'];
    header("Location: dashboard.php");
    exit();
}

// Prevent adding your own item to your cart
if ($item['seller_id'] == $current_user_id) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'You cannot add your own listing to your cart.'];
    header("Location: dashboard.php");
    exit();
}

// --- Add Item to Cart (Session) ---
// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$item_already_in_cart = false;
foreach ($_SESSION['cart'] as &$cart_item) {
    if ($cart_item['item_id'] == $item_id) {
        // Item already in cart, increment quantity
        $cart_item['quantity']++;
        $item_already_in_cart = true;
        break;
    }
}

if (!$item_already_in_cart) {
    // Item not in cart, add it
    $firstImageUrl = explode(',', $item['image_urls'])[0] ?? 'assets/default_product.png';
    $_SESSION['cart'][] = [
        'item_id' => $item['item_id'],
        'title' => $item['title'],
        'price' => $item['price'],
        'image_url' => $firstImageUrl,
        'quantity' => 1
    ];
}

$_SESSION['form_message'] = ['type' => 'success', 'text' => htmlspecialchars($item['title']) . ' added to cart!'];
header("Location: dashboard.php"); // Redirect back to dashboard with a success message
exit();

?>
