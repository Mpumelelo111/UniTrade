<?php
// checkout.php
session_start(); // Start the session to access the shopping cart

// Include the database connection file
require_once 'database.php'; // Adjust path as necessary (assuming this defines $link)

// --- User Authentication Check ---
// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Please log in to proceed to checkout.'];
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'] ?? []; // Get the cart from the session, default to empty array
$totalAmount = 0;
$errorMessage = '';
$successMessage = '';

// If the cart is empty, redirect back to the cart page with a message
if (empty($cart)) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Your cart is empty. Please add items before checking out.'];
    header("Location: cart.php");
    exit();
}

// Calculate total amount for display
foreach ($cart as $cart_item) {
    $totalAmount += ($cart_item['price'] * $cart_item['quantity']);
}

// --- Handle POST request for finalizing payment (placeholder for actual payment gateway) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'finalize_payment') {
    header('Content-Type: application/json'); // Respond with JSON for AJAX requests

    // Basic validation: ensure cart is not empty before processing payment
    if (empty($_SESSION['cart'])) {
        echo json_encode(['success' => false, 'message' => 'Your cart is empty. Cannot finalize payment.']);
        exit();
    }

    // In a real application, this is where you would:
    // 1. Integrate with a payment gateway (e.g., Stripe, PayPal, PayFast for SA)
    //    - Send totalAmount and item details to the payment gateway
    //    - Handle payment success/failure callbacks
    // 2. If payment is successful:
    //    - Loop through items in $_SESSION['cart']
    //    - Insert each item as a new row into the 'Transactions' table
    //    - Update item status in 'Items' table (e.g., from 'Available' to 'Sold' or 'Pending')
    //    - Clear the $_SESSION['cart']
    // 3. Redirect to a success page or display success message.
    // 4. If payment fails:
    //    - Display an error message.

    // Placeholder for payment processing logic
    // For demonstration, we'll simulate a successful payment and record transactions.

    $link->begin_transaction(); // Start a transaction for atomicity

    try {
        foreach ($cart as $item) {
            // Insert into Transactions table
            $insertStmt = $link->prepare("INSERT INTO Transactions (item_id, seller_id, buyer_id, transaction_date, amount, status, payment_method, delivery_method) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)");
            if ($insertStmt === false) {
                throw new Exception("Transaction insert prepare failed: " . $link->error);
            }

            // You'd need to fetch seller_id for each item if not already in cart data
            // For now, let's assume item['seller_id'] is available from the cart session.
            // If not, you'd need to fetch it from the 'Items' table here.
            $seller_id = $item['seller_id'] ?? null; // Make sure seller_id is passed to cart from dashboard or fetched here

            // If seller_id is not in cart, fetch it now
            if ($seller_id === null) {
                $sellerStmt = $link->prepare("SELECT seller_id FROM Items WHERE item_id = ?");
                if ($sellerStmt === false) {
                    throw new Exception("Seller ID fetch prepare failed: " . $link->error);
                }
                $sellerStmt->bind_param("i", $item['item_id']);
                $sellerStmt->execute();
                $sellerResult = $sellerStmt->get_result();
                if ($sellerRow = $sellerResult->fetch_assoc()) {
                    $seller_id = $sellerRow['seller_id'];
                } else {
                    throw new Exception("Seller ID not found for item: " . $item['item_id']);
                }
                $sellerStmt->close();
            }

            $payment_method = "Credit Card (Simulated)"; // Example
            $delivery_method = "Courier (Simulated)"; // Example
            $transaction_status = "Pending Payment"; // Initial status

            $insertStmt->bind_param("iiidsss",
                $item['item_id'],
                $seller_id, // Ensure this is correctly retrieved
                $current_user_id,
                $item['price'] * $item['quantity'],
                $transaction_status,
                $payment_method,
                $delivery_method
            );

            if (!$insertStmt->execute()) {
                throw new Exception("Transaction insert failed: " . $insertStmt->error);
            }
            $insertStmt->close();

            // Update item status in Items table (e.g., to 'Sold' or 'Pending')
            $updateItemStmt = $link->prepare("UPDATE Items SET status = 'Sold' WHERE item_id = ?");
            if ($updateItemStmt === false) {
                throw new Exception("Item status update prepare failed: " . $link->error);
            }
            $updateItemStmt->bind_param("i", $item['item_id']);
            if (!$updateItemStmt->execute()) {
                throw new Exception("Item status update failed: " . $updateItemStmt->error);
            }
            $updateItemStmt->close();
        }

        $link->commit(); // Commit the transaction if all operations succeed
        unset($_SESSION['cart']); // Clear the cart after successful transaction

        echo json_encode(['success' => true, 'message' => 'Payment finalized and order placed successfully!']);
    } catch (Exception $e) {
        $link->rollback(); // Rollback on error
        error_log("Checkout Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to finalize payment: ' . $e->getMessage()]);
    }
    exit();
}

// Check for any session messages (e.g., from cart.php)
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

        /* Checkout Summary Styles */
        .checkout-summary-section {
            margin-bottom: 30px;
            text-align: left;
            background-color: #3a3a3a;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #555;
        }

        .checkout-summary-section h3 {
            color: #4a90e2;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.5em;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #555;
            font-size: 1em;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            padding-top: 15px;
            font-size: 1.3em;
            font-weight: bold;
            color: #2ecc71;
            border-top: 2px solid #4a90e2;
            margin-top: 15px;
        }

        /* Payment Form Placeholder Styles */
        .payment-form-section {
            background-color: #3a3a3a;
            padding: 30px;
            border-radius: 10px;
            border: 1px solid #555;
            text-align: left;
            margin-top: 20px;
        }

        .payment-form-section h3 {
            color: #4a90e2;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.5em;
            text-align: center;
        }

        .input-field {
            position: relative;
            margin-bottom: 20px;
        }

        .input-field label {
            display: block;
            margin-bottom: 8px;
            color: #f0f0f0;
            font-size: 0.95em;
        }

        .input-field input[type="text"],
        .input-field input[type="email"],
        .input-field input[type="tel"] {
            width: 100%;
            padding: 12px 15px;
            background-color: #2c2c2c;
            border: 1px solid #555;
            border-radius: 8px;
            font-size: 1em;
            outline: none;
            color: #f0f0f0;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
        }

        .input-field input:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 8px rgba(74, 144, 226, 0.5);
        }

        .btn-finalize-payment {
            width: 100%;
            padding: 15px;
            background-color: #2ecc71; /* Green for payment */
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-finalize-payment:hover {
            background-color: #27ae60; /* Darker green on hover */
            transform: translateY(-2px);
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
            margin: 0 10px;
        }
        .links-container a:hover {
            text-decoration: underline;
            color: #3a7ace;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .circular-nav {
                height: 50px;
                padding: 0 15px;
            }
            .nav-center {
                font-size: 1.3em;
            }
            .nav-right {
                gap: 15px;
            }
            .nav-link {
                font-size: 0.9em;
                padding: 4px 10px;
            }

            .wrapper {
                padding: 30px;
                width: 95%;
            }

            h2 {
                font-size: 2em;
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

            .wrapper {
                padding: 20px;
            }

            h2 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <nav class="circular-nav">
        <div class="nav-center">
            Unitrade
        </div>
        <div class="nav-right">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="profile.php" class="nav-link">Profile</a>
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

        <div class="checkout-summary-section">
            <h3>Order Summary</h3>
            <?php foreach ($cart as $item): ?>
                <div class="summary-item">
                    <span><?php echo htmlspecialchars($item['title']); ?> (x<?php echo htmlspecialchars($item['quantity']); ?>)</span>
                    <span>R <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                </div>
            <?php endforeach; ?>
            <div class="summary-total">
                <span>Total:</span>
                <span>R <?php echo number_format($totalAmount, 2); ?></span>
            </div>
        </div>

        <div class="payment-form-section">
            <h3>Payment & Delivery Details</h3>
            <form id="checkoutForm">
                <!-- Placeholder for Shipping Address -->
                <div class="input-field">
                    <label for="shipping_address">Shipping Address:</label>
                    <input type="text" id="shipping_address" name="shipping_address" placeholder="Street Address, City, Postal Code" required>
                </div>
                <!-- Placeholder for Payment Method (e.g., Credit Card details) -->
                <div class="input-field">
                    <label for="card_number">Card Number:</label>
                    <input type="text" id="card_number" name="card_number" placeholder="XXXX XXXX XXXX XXXX" required>
                </div>
                <div class="input-field">
                    <label for="expiry_date">Expiry Date (MM/YY):</label>
                    <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" required>
                </div>
                <div class="input-field">
                    <label for="cvv">CVV:</label>
                    <input type="text" id="cvv" name="cvv" placeholder="XXX" required>
                </div>

                <button type="submit" class="btn-finalize-payment">Finalize Payment</button>
            </form>
        </div>

        <div class="links-container">
            <a href="cart.php">Back to Cart</a>
            <a href="dashboard.php">Continue Shopping</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const checkoutForm = document.getElementById('checkoutForm');
            const errorMessageDiv = document.getElementById('error-message');
            const successMessageDiv = document.getElementById('success-message');

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

            // Display any messages from PHP (e.g., if cart was empty)
            <?php if (!empty($errorMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($errorMessage); ?>', 'error');
            <?php elseif (!empty($successMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($successMessage); ?>', 'success');
            <?php endif; ?>

            if (checkoutForm) {
                checkoutForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    displayMessage('', 'none'); // Clear previous messages

                    const finalizeButton = checkoutForm.querySelector('.btn-finalize-payment');
                    const originalButtonText = finalizeButton.textContent;
                    finalizeButton.textContent = 'Processing...';
                    finalizeButton.disabled = true;

                    try {
                        const formData = new FormData(checkoutForm);
                        formData.append('action', 'finalize_payment'); // Add action to identify POST request

                        const response = await fetch('checkout.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            // Redirect to a success page or dashboard after a short delay
                            setTimeout(() => {
                                window.location.href = 'dashboard.php?order_placed=true';
                            }, 2000);
                        } else {
                            displayMessage(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error during checkout:', error);
                        displayMessage('An unexpected error occurred during checkout. Please try again.', 'error');
                    } finally {
                        finalizeButton.textContent = originalButtonText;
                        finalizeButton.disabled = false;
                    }
                });
            }
        });
    </script>
</body>
</html>
