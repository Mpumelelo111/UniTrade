<?php
// cart.php
session_start(); // Start the session to access cart data

// Set error reporting for production (errors will be logged, not displayed)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Include the database connection file (if needed for future operations, e.g., fetching full item details from DB)
require_once 'database.php'; // Adjust path as necessary

// --- User Authentication Check ---
// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Please log in to view your cart.'];
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'] ?? []; // Get the cart array from the session, default to empty array if not set
$totalCartAmount = 0;
$errorMessage = '';
$successMessage = '';

// --- Handle POST requests for cart actions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Set header for JSON response as these will be AJAX requests
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';
    $itemId = $_POST['item_id'] ?? null;

    if (empty($itemId) || !is_numeric($itemId)) {
        echo json_encode(['success' => false, 'message' => 'Invalid item ID.']);
        exit();
    }

    if ($action === 'remove_item') {
        $found = false;
        foreach ($cart as $key => $cartItem) {
            if ($cartItem['item_id'] == $itemId) {
                unset($cart[$key]); // Remove the item from the cart array
                $found = true;
                break;
            }
        }
        // Re-index the array after removal
        $_SESSION['cart'] = array_values($cart);

        if ($found) {
            // Recalculate total after removal
            $newTotal = 0;
            foreach ($_SESSION['cart'] as $item) {
                $newTotal += $item['price'] * $item['quantity'];
            }
            echo json_encode(['success' => true, 'message' => 'Item removed from cart.', 'new_total' => number_format($newTotal, 2)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found in cart.']);
        }
        exit();
    } elseif ($action === 'update_quantity') {
        $newQuantity = $_POST['new_quantity'] ?? null;

        if (!is_numeric($newQuantity) || $newQuantity < 1) {
            echo json_encode(['success' => false, 'message' => 'Quantity must be a positive number.']);
            exit();
        }

        $found = false;
        $updatedSubtotal = 0;
        foreach ($cart as $key => &$cartItem) { // Use & for reference to modify original array
            if ($cartItem['item_id'] == $itemId) {
                $cartItem['quantity'] = (int)$newQuantity; // Update quantity
                $updatedSubtotal = $cartItem['price'] * $cartItem['quantity'];
                $found = true;
                break;
            }
        }
        unset($cartItem); // Break the reference

        $_SESSION['cart'] = $cart; // Save updated cart back to session

        if ($found) {
            // Recalculate total cart amount after quantity update
            $newTotal = 0;
            foreach ($_SESSION['cart'] as $item) {
                $newTotal += $item['price'] * $item['quantity'];
            }
            echo json_encode([
                'success' => true,
                'message' => 'Quantity updated successfully!',
                'item_id' => $itemId,
                'new_quantity' => (int)$newQuantity,
                'new_subtotal' => number_format($updatedSubtotal, 2),
                'new_total' => number_format($newTotal, 2)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found in cart.']);
        }
        exit();
    }
}

// Calculate total cart amount for display (for initial page load)
foreach ($cart as $item) {
    $totalCartAmount += $item['price'] * $item['quantity'];
}

// Check for any session messages (e.g., from add_to_cart.php)
if (isset($_SESSION['form_message'])) {
    if ($_SESSION['form_message']['type'] === 'success') {
        $successMessage = $_SESSION['form_message']['text'];
    } else {
        $errorMessage = $_SESSION['form_message']['text'];
    }
    unset($_SESSION['form_message']); // Clear the message after displaying
}

// Close the database connection (if it was opened)
if (isset($link) && is_object($link) && method_exists($link, 'close')) {
    $link->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrade - My Cart</title>
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
            max-width: 900px; /* Wider for cart items */
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

        /* Cart Item Card Styles (New Design) */
        .cart-item-card {
            background-color: #3a3a3a;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid #555;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .cart-item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #666;
            flex-shrink: 0; /* Prevent image from shrinking */
        }

        .cart-item-details {
            flex-grow: 1; /* Allow details to take up available space */
            text-align: left;
        }

        .cart-item-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #f0f0f0;
            margin-bottom: 5px;
        }

        .cart-item-price-per-unit {
            font-size: 0.95em;
            color: #ccc;
            margin-bottom: 10px;
        }

        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap; /* Allow buttons to wrap on smaller screens */
            justify-content: flex-end; /* Align actions to the right */
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            background-color: #2c2c2c;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #555;
        }

        .quantity-btn {
            background-color: #4a90e2;
            color: white;
            border: none;
            padding: 8px 12px;
            font-size: 1.2em;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px; /* Fixed width for buttons */
            height: 40px; /* Fixed height for buttons */
        }

        .quantity-btn:hover {
            background-color: #3a7ace;
        }

        .quantity-input {
            width: 50px; /* Fixed width for input */
            padding: 8px 0;
            border: none;
            background-color: #3a3a3a;
            color: #f0f0f0;
            text-align: center;
            font-size: 1.1em;
            outline: none;
            -moz-appearance: textfield; /* Hide arrows for Firefox */
        }
        /* Hide arrows for Chrome, Safari, Edge, Opera */
        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .cart-item-subtotal {
            font-weight: bold;
            color: #2ecc71;
            font-size: 1.6em; /* Larger subtotal */
            margin-left: 15px; /* Space from quantity controls */
            min-width: 120px; /* Ensure space for amount */
            text-align: right;
        }

        .remove-btn {
            background-color: #e74c3c; /* Red for remove */
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            font-size: 1em;
            display: flex;
            align-items: center;
            gap: 5px;
            
        }

        .remove-btn:hover {
            background-color: #c0392b;
        }

        .cart-summary {
            background-color: #3a3a3a;
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
            border: 1px solid #555;
            text-align: right;
            width: 100%;
            max-width: 900px;
            box-sizing: border-box;
        }

        .cart-summary .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #555;
            padding-top: 15px;
            margin-top: 15px;
        }

        .cart-summary .total-label {
            font-size: 1.4em;
            font-weight: bold;
            color: #f0f0f0;
        }

        .cart-summary .total-amount {
            font-size: 1.8em;
            font-weight: bold;
            color: #2ecc71;
        }

        .checkout-btn {
            width: 97%;
            padding: 15px;
            background-color: #4a90e2;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-decoration: none; /* For anchor tags if used as button */
            display: block; /* Make it a block element for full width */
            justify-content: center; /* Center text horizontally */
            text-align: center;      /* Center text inside the button */
        }
        .checkout-btn:hover {
            background-color: #3a7ace;
            transform: translateY(-2px);
        }

        .continue-shopping-link {
            display: block;
            margin-top: 20px;
            color: #4a90e2;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        .continue-shopping-link:hover {
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
            .cart-item-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .cart-item-details {
                width: 100%; /* Take full width when stacked */
                text-align: center;
            }
            .cart-item-image {
                margin: 0 auto; /* Center image */
            }
            .cart-item-actions {
                width: 100%;
                justify-content: center; /* Center buttons when stacked */
            }
            .cart-item-subtotal {
                margin-left: 0; /* Remove left margin when stacked */
                margin-top: 10px; /* Add top margin for spacing */
                width: 100%;
                text-align: center;
            }
            .quantity-controls, .remove-btn {
                flex-grow: 1; /* Allow buttons to grow */
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
            .cart-item-image {
                width: 80px;
                height: 80px;
            }
            .cart-item-title {
                font-size: 1.1em;
            }
            .cart-item-price-per-unit {
                font-size: 0.85em;
            }
            .quantity-btn {
                width: 35px;
                height: 35px;
                font-size: 1em;
            }
            .quantity-input {
                width: 40px;
                font-size: 1em;
            }
            .cart-item-subtotal {
                font-size: 1.4em;
            }
            .remove-btn {
                padding: 8px 12px;
                font-size: 0.9em;
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
        <h2>Your Shopping Cart</h2>

        <div class="form-message error" id="error-message" style="display:none;">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <div class="form-message success" id="success-message" style="display:none;">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>

        <?php if (!empty($cart)): ?>
            <div class="cart-items-list">
                <?php foreach ($cart as $item): ?>
                    <div class="cart-item-card" id="cart-item-<?php echo htmlspecialchars($item['item_id']); ?>">
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="cart-item-image" onerror="this.onerror=null; this.src='assets/default_product.png';">
                        <div class="cart-item-details">
                            <div class="cart-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div class="cart-item-price-per-unit">Price: R <?php echo number_format($item['price'], 2); ?></div>
                        </div>
                        <div class="cart-item-actions">
                            <div class="quantity-controls">
                                <button type="button" class="quantity-btn minus-btn" data-item-id="<?php echo htmlspecialchars($item['item_id']); ?>">-</button>
                                <input type="number" class="quantity-input" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="1" data-item-id="<?php echo htmlspecialchars($item['item_id']); ?>">
                                <button type="button" class="quantity-btn plus-btn" data-item-id="<?php echo htmlspecialchars($item['item_id']); ?>">+</button>
                            </div>
                            <div class="cart-item-subtotal" id="subtotal-<?php echo htmlspecialchars($item['item_id']); ?>">R <?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                            <button type="button" class="remove-btn" data-item-id="<?php echo htmlspecialchars($item['item_id']); ?>">
                                <i class='bx bx-trash'></i> Remove
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <div class="total-row">
                    <span class="total-label">Total:</span>
                    <span class="total-amount" id="cart-total-amount">R <?php echo number_format($totalCartAmount, 2); ?></span>
                </div>
                <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
            </div>

        <?php else: ?>
            <p style="text-align: center; color: #ccc;">Your cart is empty. Start adding some items!</p>
        <?php endif; ?>

        <a href="dashboard.php" class="continue-shopping-link">Continue Shopping</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const hamburgerMenu = document.querySelector('.hamburger-menu');
            const navRight = document.querySelector('.nav-right');
            const errorMessageDiv = document.getElementById('error-message');
            const successMessageDiv = document.getElementById('success-message');
            const cartTotalAmountSpan = document.getElementById('cart-total-amount');
            const cartItemsList = document.querySelector('.cart-items-list');
            const cartSummary = document.querySelector('.cart-summary');


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

            // Handle "Remove" button clicks
            document.querySelectorAll('.remove-btn').forEach(button => {
                button.addEventListener('click', async (event) => {
                    const itemId = button.dataset.itemId;
                    if (!confirm('Are you sure you want to remove this item from your cart?')) {
                        return; // User cancelled
                    }

                    displayMessage('Removing item...', 'success');

                    try {
                        const formData = new FormData();
                        formData.append('action', 'remove_item');
                        formData.append('item_id', itemId);

                        const response = await fetch('cart.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            const itemCard = document.getElementById(`cart-item-${itemId}`);
                            if (itemCard) {
                                itemCard.remove();
                                cartTotalAmountSpan.textContent = `R ${result.new_total}`;
                                // If cart becomes empty, show the empty cart message
                                if (document.querySelectorAll('.cart-item-card').length === 0) {
                                    cartItemsList.innerHTML = '<p style="text-align: center; color: #ccc;">Your cart is empty. Start adding some items!</p>';
                                    cartSummary.style.display = 'none';
                                }
                            }
                        } else {
                            displayMessage(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error removing item:', error);
                        displayMessage('An unexpected error occurred while removing the item.', 'error');
                    }
                });
            });

            // Handle quantity changes (plus/minus buttons and direct input)
            document.querySelectorAll('.quantity-controls .quantity-btn').forEach(button => {
                button.addEventListener('click', async (event) => {
                    const itemId = button.dataset.itemId;
                    const quantityInput = document.querySelector(`.quantity-input[data-item-id="${itemId}"]`);
                    let newQuantity = parseInt(quantityInput.value, 10);

                    if (button.classList.contains('plus-btn')) {
                        newQuantity++;
                    } else if (button.classList.contains('minus-btn')) {
                        newQuantity--;
                    }

                    // Client-side validation
                    if (newQuantity < 1) {
                        displayMessage('Quantity cannot be less than 1.', 'error');
                        return; // Do not send request if invalid
                    }

                    // Update input field immediately for responsiveness
                    quantityInput.value = newQuantity;
                    displayMessage('Updating quantity...', 'success');

                    try {
                        const formData = new FormData();
                        formData.append('action', 'update_quantity');
                        formData.append('item_id', itemId);
                        formData.append('new_quantity', newQuantity);

                        const response = await fetch('cart.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            document.getElementById(`subtotal-${itemId}`).textContent = `R ${result.new_subtotal}`;
                            cartTotalAmountSpan.textContent = `R ${result.new_total}`;
                        } else {
                            displayMessage(result.message, 'error');
                            // Revert quantity on error
                            quantityInput.value = parseInt(quantityInput.dataset.originalValue, 10);
                        }
                    } catch (error) {
                        console.error('Error updating quantity:', error);
                        displayMessage('An unexpected error occurred while updating the quantity.', 'error');
                        // Revert quantity on error
                        quantityInput.value = parseInt(quantityInput.dataset.originalValue, 10);
                    }
                });
            });

            // Handle direct input changes in quantity field
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', async (event) => {
                    const itemId = event.target.dataset.itemId;
                    let newQuantity = parseInt(event.target.value, 10);
                    const originalQuantity = parseInt(event.target.dataset.originalValue, 10);

                    // Client-side validation
                    if (isNaN(newQuantity) || newQuantity < 1) {
                        displayMessage('Quantity must be a positive number.', 'error');
                        event.target.value = originalQuantity; // Revert to original
                        return;
                    }

                    displayMessage('Updating quantity...', 'success');

                    try {
                        const formData = new FormData();
                        formData.append('action', 'update_quantity');
                        formData.append('item_id', itemId);
                        formData.append('new_quantity', newQuantity);

                        const response = await fetch('cart.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            document.getElementById(`subtotal-${itemId}`).textContent = `R ${result.new_subtotal}`;
                            cartTotalAmountSpan.textContent = `R ${result.new_total}`;
                            event.target.dataset.originalValue = newQuantity; // Update original value
                        } else {
                            displayMessage(result.message, 'error');
                            event.target.value = originalQuantity; // Revert on server error
                        }
                    } catch (error) {
                        console.error('Error updating quantity:', error);
                        displayMessage('An unexpected error occurred while updating the quantity.', 'error');
                        event.target.value = originalQuantity; // Revert on network error
                    }
                });
            });
        });
    </script>
</body>
</html>
