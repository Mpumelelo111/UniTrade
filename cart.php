<?php
// cart.php
session_start(); // Start the session to access the shopping cart

// Include the database connection file (if needed for future operations, e.g., fetching fresh item data)
require_once 'database.php'; // Adjust path as necessary (assuming this defines $link)

// --- User Authentication Check ---
// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Please log in to view your cart.'];
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'] ?? []; // Get the cart from the session, default to empty array
$totalAmount = 0;
$errorMessage = '';
$successMessage = '';

// --- Handle POST requests for cart actions (remove item, update quantity) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json'); // Respond with JSON for AJAX requests

    $action = $_POST['action'];
    $item_id_to_act_on = $_POST['item_id'] ?? null;

    if (empty($item_id_to_act_on) || !is_numeric($item_id_to_act_on)) {
        echo json_encode(['success' => false, 'message' => 'Invalid item ID for cart action.']);
        exit();
    }

    if ($action === 'remove_item') {
        $updated_cart = [];
        $item_removed = false;
        foreach ($cart as $index => $cart_item) {
            if ($cart_item['item_id'] == $item_id_to_act_on) {
                // This is the item to remove, skip it
                $item_removed = true;
            } else {
                $updated_cart[] = $cart_item; // Keep other items
            }
        }
        $_SESSION['cart'] = $updated_cart; // Update the session cart
        $cart = $updated_cart; // Update local cart variable

        if ($item_removed) {
            echo json_encode(['success' => true, 'message' => 'Item removed from cart.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found in cart.']);
        }
        exit();
    } elseif ($action === 'update_quantity') {
        $change_type = $_POST['change_type'] ?? null; // 'increase' or 'decrease'
        $item_found = false;

        foreach ($cart as &$cart_item) { // Use reference to modify array directly
            if ($cart_item['item_id'] == $item_id_to_act_on) {
                $item_found = true;
                if ($change_type === 'increase') {
                    $cart_item['quantity']++;
                } elseif ($change_type === 'decrease') {
                    $cart_item['quantity']--;
                    // Remove item if quantity drops to 0 or less
                    if ($cart_item['quantity'] <= 0) {
                        // Mark for removal by setting a flag, then rebuild the array later
                        $cart_item['remove'] = true;
                    }
                }
                break;
            }
        }

        if ($item_found) {
            // Rebuild the cart array to remove items marked for removal
            $final_cart = [];
            foreach ($cart as $cart_item) {
                if (!isset($cart_item['remove']) || !$cart_item['remove']) {
                    $final_cart[] = $cart_item;
                }
            }
            $_SESSION['cart'] = $final_cart; // Update the session cart
            $cart = $final_cart; // Update local cart variable

            echo json_encode(['success' => true, 'message' => 'Cart updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found in cart for quantity update.']);
        }
        exit();
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid cart action.']);
        exit();
    }
}

// Recalculate total amount for display
foreach ($cart as $cart_item) {
    $totalAmount += ($cart_item['price'] * $cart_item['quantity']);
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
    <title>Unitrade - Your Cart</title>
    <!-- Boxicons CSS for icons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* General Body Styling - Consistent with other pages */
        body {
            margin: 0;
            display: flex;
            flex-direction: column; /* Arrange content vertically */
            align-items: center; /* Center horizontally */
            min-height: 100vh; /* Full viewport height */
            background-color: #202020; /* Dark background to match nav */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #f0f0f0; /* Light text color for contrast */
            padding: 20px; /* Add some padding around the whole page */
            box-sizing: border-box; /* Include padding in element's total width and height */
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
            max-width: 800px; /* Wider for cart items */
            box-sizing: border-box;
            color: #f0f0f0;
            text-align: center;
        }

        h2 {
            text-align: center;
            color: #f0f0f0;
            margin-bottom: 30px;
            font-size: 2.2em;
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

        /* Cart Item Styles */
        .cart-items-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }

        .cart-item-card {
            display: flex;
            align-items: center;
            background-color: #3a3a3a;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #555;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .cart-item-image-container {
            width: 80px;
            height: 80px;
            overflow: hidden;
            border-radius: 8px;
            margin-right: 15px;
            flex-shrink: 0;
            background-color: #555; /* Placeholder background */
        }

        .cart-item-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cart-item-details {
            flex-grow: 1;
            text-align: left;
        }

        .cart-item-details h4 {
            margin: 0 0 5px 0;
            font-size: 1.1em;
            color: #f0f0f0;
        }

        .cart-item-details p {
            margin: 0;
            font-size: 0.9em;
            color: #ccc;
        }

        .cart-item-price-quantity {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1em;
            font-weight: bold;
            color: #2ecc71;
            flex-shrink: 0;
        }

        .cart-item-quantity {
            background-color: #555;
            color: #f0f0f0;
            padding: 5px 10px;
            border-radius: 5px;
            min-width: 30px; /* Ensure space for quantity */
            text-align: center;
        }

        /* New styles for quantity buttons */
        .qty-btn {
            background-color: #4a90e2;
            color: white;
            border: none;
            border-radius: 5px;
            width: 30px; /* Fixed width for square buttons */
            height: 30px; /* Fixed height for square buttons */
            font-size: 1.2em;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .qty-btn:hover {
            background-color: #3a7ace;
        }

        .remove-item-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            font-size: 0.9em;
            margin-left: 15px;
            flex-shrink: 0;
        }

        .remove-item-btn:hover {
            background-color: #c0392b;
        }

        /* Cart Summary & Actions */
        .cart-summary {
            background-color: #3a3a3a;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #555;
            margin-top: 20px;
            text-align: right;
        }

        .cart-summary p {
            font-size: 1.2em;
            font-weight: bold;
            margin: 0 0 15px 0;
        }

        .cart-summary span {
            color: #2ecc71;
            font-size: 1.3em;
        }

        .checkout-btn {
            width: 95%;
            padding: 15px;
            background-color: #4a90e2;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-decoration: none; /* For anchor tag */
            display: block; /* Make it take full width */
            text-align: center; /* Added to center the text */
        }

        .checkout-btn:hover {
            background-color: #3a7ace;
            transform: translateY(-2px);
        }

        .empty-cart-message {
            padding: 30px;
            background-color: #3a3a3a;
            border-radius: 10px;
            border: 1px solid #555;
            color: #ccc;
            font-size: 1.1em;
        }

        .links-container {
            margin-top: 25px;
            font-size: 0.95em;
        }
        .links-container a {
            color: #4a90e2;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
            margin: 0 10px; /* Space between links */
        }
        .links-container a:hover {
            text-decoration: underline;
            color: #3a7ace;
        }

        /* Hamburger menu icon - Hidden by default on desktop */
        .hamburger-menu {
            display: none; /* Hidden on desktop */
            color: #f0f0f0;
            font-size: 1.8em;
            cursor: pointer;
            padding: 5px;
            order: 2; /* Keep it on the right */
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .circular-nav {
                height: 50px;
                padding: 0 15px;
                flex-wrap: wrap; /* Allow content to wrap */
                justify-content: space-between; /* Keep Unitrade and hamburger at ends */
                align-items: center;
            }
            .nav-center {
                font-size: 1.3em;
                order: 1; /* Keep Unitrade first */
            }
            .nav-right {
                display: none; /* Hide desktop nav links by default on mobile */
                flex-direction: column; /* Stack links vertically when shown */
                width: 100%; /* Take full width for dropdown */
                background-color: #2c2c2c; /* Background for dropdown */
                position: absolute; /* Position below nav */
                top: 50px; /* Below the nav bar (adjust for nav height) */
                left: 0;
                border-radius: 0 0 15px 15px;
                box-shadow: 0 8px 15px rgba(0, 0, 0, 0.4);
                padding: 0; /* Initial padding 0 for collapse effect */
                z-index: 1000; /* Ensure it's above other content */
                gap: 5px; /* Smaller gap for stacked links */
                border-top: 1px solid #4a90e2; /* Separator line */
                overflow: hidden; /* Hide overflow when not active */
                max-height: 0; /* Initially hidden */
                transition: max-height 0.3s ease-out, padding 0.3s ease-out; /* Smooth transition */
            }

            .nav-right.active {
                display: flex; /* Show when active */
                max-height: 200px; /* Adjust based on number of links */
                padding: 10px 0;
            }

            .nav-link {
                font-size: 0.9em;
                padding: 8px 20px; /* Larger touch targets for mobile */
                width: calc(100% - 40px); /* Full width with padding */
                text-align: center;
            }

            .hamburger-menu {
                display: block; /* Show hamburger icon on mobile */
            }

            .wrapper {
                padding: 30px;
                width: 95%;
            }

            h2 {
                font-size: 2em;
            }

            .cart-item-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .cart-item-image-container {
                margin-right: 0;
                margin-bottom: 10px;
            }

            .cart-item-price-quantity {
                width: 100%;
                justify-content: space-between;
                margin-top: 10px;
            }

            .remove-item-btn {
                margin-left: 0;
                width: 100%;
                margin-top: 10px;
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
                top: 45px; /* Adjust dropdown position for smaller nav height */
            }

            .wrapper {
                padding: 20px;
            }

            h2 {
                font-size: 1.8em;
            }
        }

        /* Custom Modal Styles */
        .modal-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
            z-index: 2000; /* Above everything else */
        }

        .modal-content {
            background-color: #2c2c2c;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.6);
            border: 2px solid #4a90e2;
            text-align: center;
            max-width: 400px;
            width: 90%;
            color: #f0f0f0;
        }

        .modal-content h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.5em;
            color: #f0f0f0;
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 25px;
        }

        .modal-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .modal-btn.confirm {
            background-color: #e74c3c;
            color: white;
        }

        .modal-btn.confirm:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }

        .modal-btn.cancel {
            background-color: #555;
            color: white;
        }

        .modal-btn.cancel:hover {
            background-color: #777;
            transform: translateY(-2px);
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
            <div class="cart-items-container">
                <?php foreach ($cart as $index => $item): ?>
                    <div class="cart-item-card">
                        <div class="cart-item-image-container">
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="cart-item-image" onerror="this.onerror=null; this.src='assets/default_product.png';">
                        </div>
                        <div class="cart-item-details">
                            <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                            <p>Price: R <?php echo number_format($item['price'], 2); ?></p>
                        </div>
                        <div class="cart-item-price-quantity">
                            <button type="button" class="qty-btn decrease-qty-btn" data-item-id="<?php echo htmlspecialchars($item['item_id']); ?>">-</button>
                            <span class="cart-item-quantity" data-item-id="<?php echo htmlspecialchars($item['item_id']); ?>"><?php echo htmlspecialchars($item['quantity']); ?></span>
                            <button type="button" class="qty-btn increase-qty-btn" data-item-id="<?php echo htmlspecialchars($item['item_id']); ?>">+</button>
                            <span>R <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </div>
                        <button type="button" class="remove-item-btn" data-item-id="<?php echo htmlspecialchars($item['item_id']); ?>">
                            <i class='bx bx-trash'></i> Remove
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <p>Total: <span>R <?php echo number_format($totalAmount, 2); ?></span></p>
                <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
            </div>
        <?php else: ?>
            <div class="empty-cart-message">
                <p>Your cart is empty. Start shopping on the <a href="dashboard.php" style="color:#4a90e2;">dashboard</a>!</p>
            </div>
        <?php endif; ?>

        <div class="links-container">
            <a href="dashboard.php">Continue Shopping</a>
        </div>
    </div>

    <!-- Custom Confirmation Modal -->
    <div id="confirmationModal" class="modal-overlay">
        <div class="modal-content">
            <h3>Confirm Removal</h3>
            <p>Are you sure you want to remove this item from your cart?</p>
            <div class="modal-buttons">
                <button class="modal-btn confirm" id="confirmRemoveBtn">Remove</button>
                <button class="modal-btn cancel" id="cancelRemoveBtn">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const errorMessageDiv = document.getElementById('error-message');
            const successMessageDiv = document.getElementById('success-message');
            const hamburgerMenu = document.querySelector('.hamburger-menu');
            const navRight = document.querySelector('.nav-right');

            // Modal elements
            const confirmationModal = document.getElementById('confirmationModal');
            const confirmRemoveBtn = document.getElementById('confirmRemoveBtn');
            const cancelRemoveBtn = document.getElementById('cancelRemoveBtn');
            let itemIdToRemove = null; // To store the item ID when modal is opened

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

            // Function to show the custom confirmation modal
            function showConfirmationModal(itemId) {
                itemIdToRemove = itemId;
                confirmationModal.style.display = 'flex'; // Use flex to center
            }

            // Function to hide the custom confirmation modal
            function hideConfirmationModal() {
                confirmationModal.style.display = 'none';
                itemIdToRemove = null;
            }

            // Display any messages from PHP (e.g., from add_to_cart.php)
            <?php if (!empty($errorMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($errorMessage); ?>', 'error');
            <?php elseif (!empty($successMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($successMessage); ?>', 'success');
            <?php endif; ?>

            // Handle remove item buttons
            document.querySelectorAll('.remove-item-btn').forEach(button => {
                button.addEventListener('click', async (event) => {
                    const itemId = button.dataset.itemId;
                    showConfirmationModal(itemId); // Show custom modal instead of confirm()
                });
            });

            // Handle confirm removal from modal
            if (confirmRemoveBtn) {
                confirmRemoveBtn.addEventListener('click', async () => {
                    if (itemIdToRemove) {
                        hideConfirmationModal();
                        displayMessage('Removing item...', 'success'); // Optimistic message

                        try {
                            const formData = new FormData();
                            formData.append('action', 'remove_item');
                            formData.append('item_id', itemIdToRemove);

                            const response = await fetch('cart.php', { // Post back to cart.php
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();

                            if (result.success) {
                                displayMessage(result.message, 'success');
                                // Reload the page to update total and check for empty cart state
                                setTimeout(() => { location.reload(); }, 500); 
                            } else {
                                displayMessage(result.message, 'error');
                            }
                        } catch (error) {
                            console.error('Error removing item from cart:', error);
                            displayMessage('An unexpected error occurred while removing the item.', 'error');
                        }
                    }
                });
            }

            // Handle cancel removal from modal
            if (cancelRemoveBtn) {
                cancelRemoveBtn.addEventListener('click', () => {
                    hideConfirmationModal();
                });
            }


            // Handle quantity increase/decrease buttons
            document.querySelectorAll('.qty-btn').forEach(button => {
                button.addEventListener('click', async (event) => {
                    const itemId = button.dataset.itemId;
                    const changeType = button.classList.contains('increase-qty-btn') ? 'increase' : 'decrease';

                    displayMessage('Updating quantity...', 'success'); // Optimistic message

                    try {
                        const formData = new FormData();
                        formData.append('action', 'update_quantity');
                        formData.append('item_id', itemId);
                        formData.append('change_type', changeType);

                        const response = await fetch('cart.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            // Reload the page to update quantities and total
                            setTimeout(() => { location.reload(); }, 500); 
                        } else {
                            displayMessage(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error updating quantity:', error);
                        displayMessage('An unexpected error occurred while updating quantity.', 'error');
                    }
                });
            });

            // Hamburger menu toggle logic
            if (hamburgerMenu && navRight) {
                hamburgerMenu.addEventListener('click', () => {
                    navRight.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>
