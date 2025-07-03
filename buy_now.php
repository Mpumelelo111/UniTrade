<?php
// buy_now.php
session_start(); // Start the session to manage user and cart information

// Include the database connection file
require_once 'database.php'; // Adjust path as necessary (assuming this defines $link)

// --- User Authentication Check ---
// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Please log in to buy items.'];
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$item_id = $_GET['item_id'] ?? null; // Get item_id from the URL
$item = null; // To store fetched item data
$errorMessage = '';
$successMessage = '';

// Check for any session messages (e.g., from a redirect)
if (isset($_SESSION['form_message'])) {
    if ($_SESSION['form_message']['type'] === 'success') {
        $successMessage = $_SESSION['form_message']['text'];
    } else {
        $errorMessage = $_SESSION['form_message']['text'];
    }
    unset($_SESSION['form_message']); // Clear the message after displaying
}

// Validate item_id from URL
if (empty($item_id) || !is_numeric($item_id)) {
    $errorMessage = 'Invalid item selected for direct purchase.';
    // No redirect immediately, display error on page
} else {
    // --- Fetch Item Details from Database ---
    $stmt = $link->prepare("SELECT item_id, title, description, price, image_urls, seller_id FROM Items WHERE item_id = ? AND status = 'Available'");
    if ($stmt === false) {
        $errorMessage = 'Database query preparation failed: ' . $link->error;
    } else {
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $item = $result->fetch_assoc();
            // Prevent buying your own item
            if ($item['seller_id'] == $current_user_id) {
                $errorMessage = 'You cannot buy your own listing.';
                $item = null; // Clear item data to prevent display
            }
        } else {
            $errorMessage = 'Item not found or not available for purchase.';
        }
        $stmt->close();
    }
}

// --- Handle POST request for finalizing payment (placeholder for actual payment gateway) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'finalize_buy_now') {
    header('Content-Type: application/json'); // Respond with JSON for AJAX requests

    $posted_item_id = $_POST['item_id'] ?? null;

    // Re-fetch item details to ensure it's still available and valid
    $recheck_item = null;
    if (!empty($posted_item_id) && is_numeric($posted_item_id)) {
        $recheckStmt = $link->prepare("SELECT item_id, title, price, seller_id FROM Items WHERE item_id = ? AND status = 'Available'");
        if ($recheckStmt) {
            $recheckStmt->bind_param("i", $posted_item_id);
            $recheckStmt->execute();
            $recheckResult = $recheckStmt->get_result();
            if ($recheckRow = $recheckResult->fetch_assoc()) {
                $recheck_item = $recheckRow;
            }
            $recheckStmt->close();
        }
    }

    if (!$recheck_item || $recheck_item['seller_id'] == $current_user_id) {
        echo json_encode(['success' => false, 'message' => 'Item is no longer available or you cannot purchase your own listing.']);
        exit();
    }

    $link->begin_transaction(); // Start a transaction for atomicity

    try {
        // Insert into Transactions table for this single item
        $insertStmt = $link->prepare("INSERT INTO Transactions (item_id, seller_id, buyer_id, transaction_date, amount, status, payment_method, delivery_method) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)");
        if ($insertStmt === false) {
            throw new Exception("Transaction insert prepare failed: " . $link->error);
        }

        $payment_method = "Credit Card (Simulated)"; // Example
        $delivery_method = "Courier (Simulated)"; // Example
        $transaction_status = "Pending Payment"; // Initial status

        $insertStmt->bind_param("iiidsss",
            $recheck_item['item_id'],
            $recheck_item['seller_id'],
            $current_user_id,
            $recheck_item['price'], // Amount is just the item's price for Buy Now
            $transaction_status,
            $payment_method,
            $delivery_method
        );

        if (!$insertStmt->execute()) {
            throw new Exception("Transaction insert failed: " . $insertStmt->error);
        }
        $insertStmt->close();

        // Update item status in Items table
        $updateItemStmt = $link->prepare("UPDATE Items SET status = 'Sold' WHERE item_id = ?");
        if ($updateItemStmt === false) {
            throw new Exception("Item status update prepare failed: " . $link->error);
        }
        $updateItemStmt->bind_param("i", $recheck_item['item_id']);
        if (!$updateItemStmt->execute()) {
            throw new Exception("Item status update failed: " . $updateItemStmt->error);
        }
        $updateItemStmt->close();

        $link->commit(); // Commit the transaction if all operations succeed

        echo json_encode(['success' => true, 'message' => 'Purchase successful! Redirecting to dashboard...']);
    } catch (Exception $e) {
        $link->rollback(); // Rollback on error
        error_log("Buy Now Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to finalize purchase: ' . $e->getMessage()]);
    }
    exit();
}

// Close the database connection (for GET requests, POST requests would have exited)
if (isset($link) && is_object($link) && method_exists($link, 'close')) {
    $link->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrade - Buy Now</title>
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
            max-width: 600px; /* Adjusted width for single item */
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

        /* Item Summary Styles */
        .item-summary-section {
            margin-bottom: 30px;
            text-align: left;
            background-color: #3a3a3a;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #555;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .item-summary-image-container {
            width: 100px;
            height: 100px;
            overflow: hidden;
            border-radius: 8px;
            flex-shrink: 0;
            background-color: #555; /* Placeholder background */
        }

        .item-summary-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-summary-details {
            flex-grow: 1;
        }

        .item-summary-details h3 {
            color: #f0f0f0;
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 1.3em;
        }

        .item-summary-details p {
            margin: 0 0 5px 0;
            font-size: 0.95em;
            color: #ccc;
        }

        .item-summary-price {
            font-size: 1.4em;
            font-weight: bold;
            color: #2ecc71;
            text-align: right;
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

        .btn-finalize-purchase {
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

        .btn-finalize-purchase:hover {
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
            .item-summary-section {
                flex-direction: column;
                align-items: flex-start;
            }
            .item-summary-image-container {
                margin-bottom: 15px;
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
        <h2>Buy Now</h2>

        <div class="form-message error" id="error-message" style="display:none;">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <div class="form-message success" id="success-message" style="display:none;">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>

        <?php if ($item): ?>
            <div class="item-summary-section">
                <div class="item-summary-image-container">
                    <?php
                        $firstImageUrl = explode(',', $item['image_urls'])[0] ?? 'assets/default_product.png';
                        $displayImageUrl = htmlspecialchars($firstImageUrl);
                    ?>
                    <img src="<?php echo $displayImageUrl; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="item-summary-image" onerror="this.onerror=null; this.src='assets/default_product.png';">
                </div>
                <div class="item-summary-details">
                    <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                    <p><?php echo htmlspecialchars($item['description']); ?></p>
                    <p class="item-summary-price">R <?php echo number_format($item['price'], 2); ?></p>
                </div>
            </div>

            <div class="payment-form-section">
                <h3>Payment & Delivery Details</h3>
                <form id="buyNowForm">
                    <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item['item_id']); ?>">
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

                    <button type="submit" class="btn-finalize-purchase">Finalize Purchase</button>
                </form>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #ff6b6b;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
        <?php endif; ?>

        <div class="links-container">
            <a href="dashboard.php">Back to Dashboard</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const buyNowForm = document.getElementById('buyNowForm');
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

            // Display any messages from PHP
            <?php if (!empty($errorMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($errorMessage); ?>', 'error');
            <?php elseif (!empty($successMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($successMessage); ?>', 'success');
            <?php endif; ?>

            if (buyNowForm) {
                buyNowForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    displayMessage('', 'none'); // Clear previous messages

                    const finalizeButton = buyNowForm.querySelector('.btn-finalize-purchase');
                    const originalButtonText = finalizeButton.textContent;
                    finalizeButton.textContent = 'Processing...';
                    finalizeButton.disabled = true;

                    try {
                        const formData = new FormData(buyNowForm);
                        formData.append('action', 'finalize_buy_now'); // Action for this specific purchase

                        const response = await fetch('buy_now.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            // Redirect to a success page or dashboard after a short delay
                            setTimeout(() => {
                                window.location.href = 'dashboard.php?purchase_successful=true';
                            }, 2000);
                        } else {
                            displayMessage(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error during Buy Now purchase:', error);
                        displayMessage('An unexpected error occurred during purchase. Please try again.', 'error');
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
