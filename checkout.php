<?php
// checkout.php
session_start(); // Start the session to access cart and user data

// Set error reporting for production (errors will be logged, not displayed)
ini_set('display_errors', 1); // Temporarily set to 1 for debugging
ini_set('display_startup_errors', 1); // Temporarily set to 1 for debugging
error_reporting(E_ALL);

// Include the database connection file
require_once 'database.php'; // Adjust path as necessary

// --- Database Connection Check ---
if (!isset($link) || ($link instanceof mysqli && $link->connect_error)) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Failed to connect to the database. Please try again later.'];
    header("Location: cart.php"); // Redirect back to cart if DB connection fails
    exit();
}

// --- User Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Please log in to proceed to checkout.'];
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'] ?? [];
$totalCartAmount = 0;
$errorMessage = '';
$successMessage = '';

// Calculate total cart amount
if (empty($cart)) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Your cart is empty. Please add items before checking out.'];
    header("Location: cart.php");
    exit();
}

foreach ($cart as $item) {
    $totalCartAmount += $item['price'] * $item['quantity'];
}

// Fetch user details from database for Delivery/Collection Details section
$userData = [];
$stmt = $link->prepare("SELECT full_name, email, phone_number, default_address_type, default_street_address, default_complex_building, default_suburb, default_city_town, default_province, default_postal_code FROM Students WHERE student_id = ?");
if ($stmt === false) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Database query preparation failed for user data: ' . $link->error];
    header("Location: cart.php");
    exit();
}
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $userData = $result->fetch_assoc();
} else {
    // This should ideally not happen if user_id is in session, but as a fallback
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'User data not found. Please log in again.'];
    header("Location: login.php");
    exit();
}
$stmt->close();

// Default collection point text
$collectionPointText = 'Campus A2 building, main entrance';


// --- Handle POST request for placing the order ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json'); // Respond with JSON for AJAX requests

    $paymentMethod = $_POST['payment_method'] ?? '';
    // Delivery method is now derived from user's default settings
    $deliveryMethod = $userData['default_address_type'] ?? 'collection'; 

    // Card details (only if Credit Card is selected)
    $cardNumber = trim(str_replace(' ', '', $_POST['card_number'] ?? '')); // Remove spaces
    $cardExpiry = trim($_POST['expiry_date'] ?? ''); // Name changed to expiry_date as per user's snippet
    $cardCVC = trim($_POST['cvv'] ?? ''); // Name changed to cvv as per user's snippet
    $cardHolderName = trim($_POST['card_holder_name'] ?? ''); // Added card_holder_name

    // Server-side validation for payment methods
    $allowedPaymentMethods = ['Credit Card', 'EFT', 'Instant EFT'];

    if (!in_array($paymentMethod, $allowedPaymentMethods)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment method selected.']);
        exit();
    }

    // Validate card details if payment method is 'Credit Card'
    if ($paymentMethod === 'Credit Card') {
        if (empty($cardNumber) || empty($cardExpiry) || empty($cardCVC) || empty($cardHolderName)) {
            echo json_encode(['success' => false, 'message' => 'All credit card details are required for Credit Card payment.']);
            exit();
        }
        // Basic card number validation (e.g., length, numeric)
        if (!preg_match('/^\d{13,19}$/', $cardNumber)) {
            echo json_encode(['success' => false, 'message' => 'Invalid card number format.']);
            exit();
        }
        // Basic expiry date validation (MM/YY)
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $cardExpiry)) {
            echo json_encode(['success' => false, 'message' => 'Invalid expiry date format (MM/YY).']);
            exit();
        }
        // Basic CVC validation (3 or 4 digits)
        if (!preg_match('/^\d{3,4}$/', $cardCVC)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CVC format.']);
            exit();
        }
    }


    // Start a transaction for atomicity
    $link->begin_transaction();
    $orderPlacedSuccessfully = true;

    try {
        foreach ($cart as $item) {
            // Fetch item details again to ensure it's still available and to get seller_id
            $stmt = $link->prepare("SELECT seller_id, status FROM Items WHERE item_id = ? FOR UPDATE"); // Lock row
            if ($stmt === false) {
                throw new Exception('Database query preparation failed for item check: ' . $link->error);
            }
            $stmt->bind_param("i", $item['item_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $dbItem = $result->fetch_assoc();
            $stmt->close();

            if (!$dbItem || $dbItem['status'] !== 'Available') {
                throw new Exception('Item "' . htmlspecialchars($item['title']) . '" is no longer available.');
            }

            $seller_id = $dbItem['seller_id'];

            // Insert into Transactions table
            // Note: The 'Transactions' table schema in database-schema-sql does not currently
            // support storing full address or card details directly. For a real application,
            // you would need to update your database schema to include these fields,
            // or create separate tables for order details and payment info.
            // For this example, we will just pass them as variables in PHP.
            $insertStmt = $link->prepare("INSERT INTO Transactions (item_id, seller_id, buyer_id, amount, payment_method, delivery_method, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending Payment')");
            if ($insertStmt === false) {
                throw new Exception('Database insert preparation failed for transaction: ' . $link->error);
            }
            $itemAmount = $item['price'] * $item['quantity'];
            $insertStmt->bind_param("iiidss", $item['item_id'], $seller_id, $current_user_id, $itemAmount, $paymentMethod, $deliveryMethod);
            
            if (!$insertStmt->execute()) {
                throw new Exception('Failed to record transaction for item "' . htmlspecialchars($item['title']) . '": ' . $insertStmt->error);
            }
            $insertStmt->close();

            // Optionally, update item status to 'Pending' or 'Sold' immediately
            // For simplicity, we'll mark it as 'Pending' in this example.
            $updateItemStmt = $link->prepare("UPDATE Items SET status = 'Pending' WHERE item_id = ?");
            if ($updateItemStmt === false) {
                throw new Exception('Database update preparation failed for item status: ' . $link->error);
            }
            $updateItemStmt->bind_param("i", $item['item_id']);
            if (!$updateItemStmt->execute()) {
                throw new Exception('Failed to update item status for "' . htmlspecialchars($item['title']) . '": ' . $updateItemStmt->error);
            }
            $updateItemStmt->close();
        }

        // If all items processed successfully, commit the transaction
        $link->commit();
        $_SESSION['cart'] = []; // Clear the cart after successful order
        echo json_encode(['success' => true, 'message' => 'Your order has been placed successfully!', 'redirect' => 'dashboard.php']);

    } catch (Exception $e) {
        // Rollback transaction on any error
        $link->rollback();
        echo json_encode(['success' => false, 'message' => 'Order placement failed: ' . $e->getMessage()]);
        $orderPlacedSuccessfully = false;
    } finally {
        // Close the database connection
        if (isset($link) && is_object($link) && method_exists($link, 'close')) {
            $link->close();
        }
        exit(); // Exit after sending JSON response
    }
}

// Check for any session messages (e.g., from cart.php redirect)
if (isset($_SESSION['form_message'])) {
    if ($_SESSION['form_message']['type'] === 'success') {
        $successMessage = $_SESSION['form_message']['text'];
    } else {
        $errorMessage = $_SESSION['form_message']['text'];
    }
    unset($_SESSION['form_message']); // Clear the message after displaying
}

// Close the database connection (for GET requests)
if (isset($link) && is_object($link) && method_exists($link, 'close')) {
    $link->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrade - Checkout</title>
    <!-- Boxicons CSS for icons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* General Body Styling - Consistent with other pages */
        body {
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            background-color: #202020;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #f0f0f0;
            padding: 20px;
            box-sizing: border-box;
        }

        /* Nav Bar Styling - Consistent with other pages */
        .circular-nav {
            width: 90%;
            max-width: 1000px;
            height: 60px;
            background-color: #2c2c2c;
            border-radius: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            border: 2px solid #4a90e2;
            padding: 0 20px;
            box-sizing: border-box;
            margin-bottom: 40px;
            flex-shrink: 0;
        }

        .nav-center {
            color: #f0f0f0;
            font-size: 1.5em;
            font-weight: bold;
            text-align: left;
            padding-left: 10px;
            white-space: nowrap;
        }

        .nav-right {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 20px;
            padding-right: 10px;
        }

        .nav-link {
            color: #f0f0f0;
            text-decoration: none;
            font-size: 1em;
            padding: 5px 15px;
            border-radius: 20px;
            transition: color 0.3s ease, background-color 0.3s ease;
            white-space: nowrap;
        }

        .nav-link:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* Main Content Wrapper */
        .wrapper {
            background-color: #2c2c2c;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            border: 2px solid #4a90e2;
            width: 100%;
            max-width: 700px; /* Adjusted width for checkout */
            box-sizing: border-box;
            color: #f0f0f0;
            text-align: center;
        }

        h2 {
            text-align: center;
            color: #f0f0f0;
            margin-bottom: 30px;
            font-size: 2em;
        }

        .form-message {
            margin-bottom: 20px;
            font-weight: bold;
        }
        .form-message.error {
            color: #ff6b6b;
        }
        .form-message.success {
            color: #2ecc71;
        }

        /* Checkout Sections */
        .order-summary-section,
        .checkout-section { /* Combined styling for consistency */
            background-color: #3a3a3a;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #555;
            text-align: left;
        }

        .order-summary-section h3,
        .checkout-section h3 {
            color: #4a90e2;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.4em;
            border-bottom: 1px solid #555;
            padding-bottom: 10px;
        }

        .address-details p {
            margin: 8px 0;
            color: #f0f0f0;
            font-size: 1em;
        }

        .address-details p strong {
            color: #bbb; /* Slightly dim for labels */
            display: inline-block;
            width: 120px; /* Align labels */
        }

        .edit-address-link {
            text-align: right;
            display: block;
            color: #4a90e2;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
            margin-top: 15px; /* Space from address details */
        }

        .edit-address-link:hover {
            text-decoration: underline;
            color: #3a7ace;
        }

        .item-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .item-list li {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #444;
        }

        .item-list li:last-child {
            border-bottom: none;
        }

        .item-list .item-name {
            flex-grow: 1;
        }

        .item-list .item-price {
            font-weight: bold;
            color: #2ecc71;
        }

        .total-section { /* Renamed from .total-summary for consistency with user's snippet */
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            margin-top: 15px;
            border-top: 1px solid #555;
            font-size: 1.5em;
            font-weight: bold;
            color: #f0f0f0;
        }

        .total-section .amount {
            color: #2ecc71;
        }

        .input-group { /* Renamed from .input-field for consistency with user's snippet */
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #f0f0f0;
        }

        .input-group input[type="text"],
        .input-group input[type="email"],
        .input-group input[type="tel"],
        .input-group input[type="password"],
        .input-group input[type="number"],
        .input-group select {
            width: 100%;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #555;
            background-color: #2c2c2c;
            color: #f0f0f0;
            font-size: 1em;
            outline: none;
            box-sizing: border-box; /* Include padding in width */
        }

        .input-group input:focus,
        .input-group select:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 8px rgba(74, 144, 226, 0.5);
        }

        /* Specific style for select dropdown arrow */
        .input-group select {
            cursor: pointer;
            appearance: none; /* Remove default arrow */
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23f0f0f0%22%20d%3D%22M287%2C197.9c-3.2%2C3.2-8.3%2C3.2-11.6%2C0L146.2%2C70.7L16.9%2C197.9c-3.2%2C3.2-8.3%2C3.2-11.6%2C0c-3.2-3.2-3.2-8.3%2C0-11.6l135.9-135.9c3.2-3.2%2C8.3-3.2%2C11.6%2C0l135.9%2C135.9C290.2%2C189.6%2C290.2%2C194.7%2C287%2C197.9z%22%2F%3E%3C%2Fsvg%3E'); /* Custom arrow */
            background-repeat: no-repeat;
            background-position: right 10px top 50%;
            background-size: 12px auto;
        }

        .input-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }

        .input-row .input-group {
            flex: 1; /* Distribute space evenly */
            min-width: 150px; /* Minimum width before breaking */
            margin-bottom: 0; /* Remove default margin-bottom from .input-group */
        }

        .btn { /* Renamed from .place-order-btn to .btn as per user's snippet */
            width: 100%;
            padding: 15px;
            background-color: #2ecc71; /* Green for place order */
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-decoration: none;
            display: block;
            text-align: center;
        }
        .btn:hover {
            background-color: #27ae60; /* Darker green on hover */
            transform: translateY(-2px);
        }

        .back-link { /* Renamed from .back-to-cart-link for consistency with user's snippet */
            display: block;
            margin-top: 20px;
            color: #4a90e2;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        .back-link:hover {
            text-decoration: underline;
            color: #3a7ace;
        }

        /* Hamburger menu icon - Hidden by default on desktop */
        .hamburger-menu {
            display: none;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .circular-nav {
                height: 50px;
                padding: 0 15px;
                flex-wrap: wrap;
                justify-content: space-between;
                align-items: center;
            }
            .nav-center {
                font-size: 1.3em;
                order: 1;
            }
            .nav-right {
                display: none;
                flex-direction: column;
                width: 100%;
                background-color: #2c2c2c;
                position: absolute;
                top: 60px;
                left: 0;
                border-radius: 0 0 15px 15px;
                box-shadow: 0 8px 15px rgba(0, 0, 0, 0.4);
                padding: 10px 0;
                z-index: 1000;
                gap: 5px;
                border-top: 1px solid #4a90e2;
                overflow: hidden;
                max-height: 0;
                transition: max-height 0.3s ease-out, padding 0.3s ease-out;
            }
            .nav-right.active {
                display: flex;
                max-height: 200px;
                padding: 10px 0;
            }
            .nav-link {
                font-size: 0.9em;
                padding: 8px 20px;
                width: calc(100% - 40px);
                text-align: center;
            }
            .hamburger-menu {
                display: block;
                color: #f0f0f0;
                font-size: 1.8em;
                cursor: pointer;
                padding: 5px;
                order: 2;
            }
            .wrapper {
                padding: 30px;
                width: 95%;
            }
            h2 {
                font-size: 1.8em;
            }
            .order-summary-section,
            .checkout-section {
                padding: 20px;
            }
            .order-summary-section h3,
            .checkout-section h3 {
                font-size: 1.2em;
            }
            .total-section {
                font-size: 1.3em;
            }
            .input-row {
                flex-direction: column; /* Stack inputs vertically on small screens */
                gap: 10px;
            }
            .input-row .input-group {
                min-width: unset; /* Remove min-width */
                width: 100%; /* Take full width */
            }
            .edit-address-link {
                position: static; /* Remove absolute positioning on small screens */
                display: block;
                text-align: center;
                margin-top: 15px;
            }
        }

        @media (max-width: 480px) {
            .circular-nav {
                height: 45px;
                padding: 0 10px;
            }
            .nav-center {
                font-size: 1.2em;
            }
            .nav-right {
                gap: 10px;
            }
            .nav-link {
                font-size: 0.8em;
                padding: 3px 8px;
            }
            .hamburger-menu {
                font-size: 1.6em;
            }
            .nav-right.active {
                top: 45px;
            }
            .wrapper {
                padding: 20px;
            }
            h2 {
                font-size: 1.6em;
            }
            .order-summary-section,
            .checkout-section {
                padding: 15px;
            }
            .order-summary-section h3,
            .checkout-section h3 {
                font-size: 1.1em;
            }
            .total-section {
                font-size: 1.1em;
            }
            .btn {
                font-size: 1em;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <nav class="circular-nav">
        <div class="nav-center">
            Unitrade
        </div>
        <!-- Hamburger menu icon for mobile -->
        <div class="hamburger-menu">
            <i class='bx bx-menu'></i>
        </div>
        <div class="nav-right mobile-nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="profile.php" class="nav-link">Profile</a>
            <a href="cart.php" class="nav-link"><i class='bx bx-cart'></i> Cart</a>
            <a href="logout.php" class="nav-link">Logout</a>
        </div>
    </nav>

    <div class="wrapper">
        <h2>Checkout</h2>

        <div class="form-message error" id="error-message" style="display:none;">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <div class="form-message success" id="success-message" style="display:none;">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>

        <form id="checkoutForm" action="checkout.php" method="post">
            <div class="order-summary-section">
                <h3>Order Summary</h3>
                <ul class="item-list">
                    <?php foreach ($cart as $item): ?>
                        <li>
                            <span class="item-name"><?php echo htmlspecialchars($item['title']); ?> (x<?php echo htmlspecialchars($item['quantity']); ?>)</span>
                            <span class="item-price">R <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="order-summary-section">
                <h3>Delivery/Collection Details</h3>
                <div class="address-details">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($userData['full_name'] ?? 'N/A'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($userData['email'] ?? 'N/A'); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($userData['phone_number'] ?? 'N/A'); ?></p>
                    <p><strong>Method:</strong> <?php echo htmlspecialchars(ucfirst($userData['default_address_type'] ?? 'N/A')); ?></p>
                    <?php if (($userData['default_address_type'] ?? '') === 'delivery'): ?>
                        <p><strong>Address:</strong>
                            <?php echo htmlspecialchars($userData['default_street_address'] ?? ''); ?>
                            <?php echo !empty($userData['default_complex_building']) ? ', ' . htmlspecialchars($userData['default_complex_building']) : ''; ?>,
                            <?php echo htmlspecialchars($userData['default_suburb'] ?? ''); ?>,
                            <?php echo htmlspecialchars($userData['default_city_town'] ?? ''); ?>,
                            <?php echo htmlspecialchars($userData['default_province'] ?? ''); ?>,
                            <?php echo htmlspecialchars($userData['default_postal_code'] ?? ''); ?>
                        </p>
                    <?php else: ?>
                        <p><strong>Collection Point:</strong> <?php echo htmlspecialchars($collectionPointText); ?></p>
                    <?php endif; ?>
                </div>
                <a href="profile.php?tab=address_book" class="edit-address-link">Edit Address</a>
            </div>

            <div class="checkout-section">
                <h3>Payment Information</h3>
                <div class="input-group">
                    <label for="payment_method">Select Payment Method:</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">-- Select --</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="EFT">EFT (Electronic Funds Transfer)</option>
                        <option value="Instant EFT">Instant EFT</option>
                    </select>
                </div>
                <div id="payment-details-fields">
                    <!-- Dynamic fields will be loaded here by JavaScript -->
                </div>
            </div>

            <div class="total-section">
                <span class="label">Total:</span>
                <span class="amount">R <?php echo number_format($totalCartAmount, 2); ?></span>
            </div>

            <button type="submit" class="btn">Proceed to Payment</button>
        </form>

        <a href="dashboard.php" class="back-link">Cancel and Go Back</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const hamburgerMenu = document.querySelector('.hamburger-menu');
            const navRight = document.querySelector('.nav-right');
            const errorMessageDiv = document.getElementById('error-message');
            const successMessageDiv = document.getElementById('success-message');
            const checkoutForm = document.getElementById('checkoutForm');
            const paymentMethodSelect = document.getElementById('payment_method');
            const paymentDetailsFields = document.getElementById('payment-details-fields');

            // Hamburger menu toggle logic
            if (hamburgerMenu && navRight) {
                hamburgerMenu.addEventListener('click', () => {
                    navRight.classList.toggle('active');
                });
            }

            function displayMessage(message, type = 'error') {
                errorMessageDiv.style.display = 'none';
                successMessageDiv.style.display = 'none';
                if (type === 'success') {
                    successMessageDiv.textContent = message;
                    successMessageDiv.style.display = 'block';
                } else {
                    errorMessageDiv.textContent = message;
                    errorMessageDiv.style.display = 'block';
                }
            }

            // Display any initial messages from PHP
            <?php if (!empty($errorMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($errorMessage); ?>', 'error');
            <?php elseif (!empty($successMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($successMessage); ?>', 'success');
            <?php endif; ?>

            // Function to dynamically load payment input fields
            function loadPaymentFields() {
                const selectedMethod = paymentMethodSelect.value;
                paymentDetailsFields.innerHTML = ''; // Clear previous fields

                if (selectedMethod === 'Credit Card') {
                    paymentDetailsFields.innerHTML = `
                        <div class="input-group">
                            <label for="card_number">Card Number:</label>
                            <input type="text" id="card_number" name="card_number" placeholder="XXXX XXXX XXXX XXXX" required pattern="[0-9]{13,19}" title="Enter a valid credit card number (13-19 digits)" maxlength="19">
                        </div>
                        <div class="input-row">
                            <div class="input-group">
                                <label for="expiry_date">Expiry Date (MM/YY):</label>
                                <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" required pattern="(0[1-9]|1[0-2])\\/[0-9]{2}" title="Enter expiry date in MM/YY format" maxlength="5">
                            </div>
                            <div class="input-group">
                                <label for="cvv">CVV:</label>
                                <input type="text" id="cvv" name="cvv" placeholder="XXX" required pattern="[0-9]{3,4}" title="Enter 3 or 4 digit CVV" maxlength="4">
                            </div>
                        </div>
                        <div class="input-group">
                            <label for="card_holder_name">Cardholder Name:</label>
                            <input type="text" name="card_holder_name" id="card_holder_name" placeholder="Name on Card" required>
                        </div>
                    `;
                    // Attach formatting listeners for the newly created inputs
                    const cardNumberInput = document.getElementById('card_number');
                    const cardExpiryInput = document.getElementById('expiry_date');

                    if (cardNumberInput) {
                        cardNumberInput.addEventListener('input', (event) => {
                            let value = event.target.value.replace(/\D/g, ''); // Remove non-digits
                            value = value.replace(/(\d{4})(?=\d)/g, '$1 '); // Add space after every 4 digits
                            event.target.value = value.trim(); // Trim trailing space
                        });
                    }

                    if (cardExpiryInput) {
                        cardExpiryInput.addEventListener('input', (event) => {
                            let value = event.target.value.replace(/\D/g, ''); // Remove non-digits
                            if (value.length > 2) {
                                value = value.substring(0, 2) + '/' + value.substring(2, 4);
                            }
                            event.target.value = value;
                        });
                    }

                } else if (selectedMethod === 'EFT') {
                    paymentDetailsFields.innerHTML = `
                        <p style="text-align: center; color: #bbb; font-style: italic;">
                            You will be redirected to your bank's portal to complete the EFT payment.
                        </p>
                    `;
                } else if (selectedMethod === 'Instant EFT') {
                    paymentDetailsFields.innerHTML = `
                        <p style="text-align: center; color: #bbb; font-style: italic;">
                            You will be redirected to the Instant EFT provider to complete your payment securely.
                        </p>
                    `;
                }
            }

            // Event listener for payment method selection change
            if (paymentMethodSelect) {
                paymentMethodSelect.addEventListener('change', loadPaymentFields);
            }

            // Initial load of payment fields
            loadPaymentFields();

            // Handle checkout form submission
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', async (event) => {
                    event.preventDefault(); // Prevent default HTML form submission

                    displayMessage('', 'none'); // Clear previous messages

                    const placeOrderBtn = checkoutForm.querySelector('.btn'); // Changed to .btn
                    const originalBtnText = placeOrderBtn.textContent;
                    placeOrderBtn.textContent = 'Placing Order...';
                    placeOrderBtn.disabled = true;

                    try {
                        const formData = new FormData(checkoutForm);

                        const response = await fetch('checkout.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            // Clear cart in session (handled by PHP, but client-side visual update if needed)
                            setTimeout(() => {
                                window.location.href = result.redirect; // Redirect to dashboard
                            }, 2000);
                        } else {
                            displayMessage(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error during checkout:', error);
                        displayMessage('An unexpected error occurred during checkout. Please try again.', 'error');
                    } finally {
                        placeOrderBtn.textContent = originalBtnText;
                        placeOrderBtn.disabled = false;
                    }
                });
            }
        });
    </script>
</body>
</html>


