<?php
// banking_details.php
session_start(); // Start the session

// Include the database connection file
require_once 'database.php'; // Adjust path as necessary

// --- User Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Please log in to access payment details.'];
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null; // Get order_id from URL parameter
$errorMessage = '';
$successMessage = '';

// Validate order_id
if (empty($order_id) || !is_numeric($order_id)) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Invalid order ID provided for payment.'];
    header("Location: dashboard.php"); // Redirect if no valid order ID
    exit();
}

// Fetch order details to display (optional, but good for context)
$orderData = null;
$stmt = $link->prepare("SELECT order_id, total_amount, status FROM orders WHERE order_id = ? AND user_id = ?");
if ($stmt === false) {
    $errorMessage = 'Database query preparation failed: ' . $link->error;
} else {
    $stmt->bind_param("ii", $order_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $orderData = $result->fetch_assoc();
        // If order is already paid/processed, redirect
        if ($orderData['status'] !== 'Pending') {
            $_SESSION['form_message'] = ['type' => 'error', 'text' => 'This order has already been processed or is not pending payment.'];
            header("Location: pending_orders.php");
            exit();
        }
    } else {
        $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Order not found or you do not have access to it.'];
        header("Location: dashboard.php");
        exit();
    }
    $stmt->close();
}

// Handle POST request for banking details submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json'); // Respond with JSON for AJAX requests

    $card_number = trim($_POST['card_number'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $cvv = trim($_POST['cvv'] ?? '');
    $order_id_from_form = trim($_POST['order_id'] ?? '');

    // Server-side validation for banking details
    if (empty($card_number) || empty($expiry_date) || empty($cvv)) {
        echo json_encode(['success' => false, 'message' => 'All banking details fields are required.']);
        exit();
    }
    if (!preg_match('/^\d{16}$/', str_replace(' ', '', $card_number))) {
        echo json_encode(['success' => false, 'message' => 'Invalid card number. Must be 16 digits.']);
        exit();
    }
    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid expiry date format (MM/YY).']);
        exit();
    }
    if (!preg_match('/^\d{3,4}$/', $cvv)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CVV. Must be 3 or 4 digits.']);
        exit();
    }
    if ($order_id_from_form != $order_id) {
        echo json_encode(['success' => false, 'message' => 'Order ID mismatch. Potential security issue.']);
        exit();
    }

    // --- Simulate Payment Processing ---
    // In a real application, you would integrate with a payment gateway here.
    // This is a placeholder for successful payment.
    $payment_successful = true; // Assume success for demonstration

    if ($payment_successful) {
        // Update order status to 'Processing' or 'Paid'
        $stmt_update = $link->prepare("UPDATE orders SET status = 'Processing' WHERE order_id = ? AND user_id = ? AND status = 'Pending'");
        if ($stmt_update === false) {
            echo json_encode(['success' => false, 'message' => 'Database update preparation failed: ' . $link->error]);
            exit();
        }
        $stmt_update->bind_param("ii", $order_id, $current_user_id);
        if ($stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Payment successful! Redirecting to orders...', 'redirect' => 'pending_orders.php']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Order status could not be updated. It might have been processed already.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update order status after payment: ' . $stmt_update->error]);
        }
        $stmt_update->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment failed. Please check your details and try again.']);
    }
    exit();
}

// Check for any session messages
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
    <title>Unitrade - Banking Details</title>
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
            max-width: 500px; /* Adjusted for banking details form */
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

        .order-summary-box {
            background-color: #3a3a3a;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #555;
            margin-bottom: 25px;
            text-align: left;
        }
        .order-summary-box p {
            margin: 5px 0;
            color: #ccc;
        }
        .order-summary-box strong {
            color: #f0f0f0;
        }
        .order-summary-box .total-amount {
            font-size: 1.2em;
            color: #2ecc71;
            font-weight: bold;
            margin-top: 10px;
            border-top: 1px dashed #555;
            padding-top: 10px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #f0f0f0;
        }
        .form-group input[type="text"] {
            width: calc(100% - 20px);
            padding: 12px;
            border: 1px solid #555;
            border-radius: 8px;
            background-color: #2c2c2c;
            color: #f0f0f0;
            font-size: 1em;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        .form-group input[type="text"]:focus {
            border-color: #4a90e2;
            outline: none;
        }
        .form-group input::placeholder {
            color: #bbb;
        }

        .card-details-row {
            display: flex;
            gap: 20px;
        }
        .card-details-row .form-group {
            flex: 1;
        }

        .btn-submit-payment {
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

        .btn-submit-payment:hover {
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
            .card-details-row {
                flex-direction: column; /* Stack on small screens */
                gap: 15px;
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
        <h2>Enter Banking Details</h2>

        <div class="form-message error" id="error-message" style="display:none;">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <div class="form-message success" id="success-message" style="display:none;">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>

        <?php if ($orderData): ?>
            <div class="order-summary-box">
                <p>Order ID: <strong><?php echo htmlspecialchars($orderData['order_id']); ?></strong></p>
                <p class="total-amount">Amount Due: R <?php echo number_format($orderData['total_amount'], 2); ?></p>
            </div>
        <?php endif; ?>

        <form id="bankingDetailsForm" method="POST" action="banking_details.php">
            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">

            <div class="form-group">
                <label for="card_number">Card Number:</label>
                <input type="text" id="card_number" name="card_number" placeholder="XXXX XXXX XXXX XXXX" required maxlength="19">
            </div>

            <div class="card-details-row">
                <div class="form-group">
                    <label for="expiry_date">Expiry Date (MM/YY):</label>
                    <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" required maxlength="5">
                </div>
                <div class="form-group">
                    <label for="cvv">CVV:</label>
                    <input type="text" id="cvv" name="cvv" placeholder="CVV" required maxlength="4">
                </div>
            </div>

            <button type="submit" class="btn-submit-payment">Submit Payment</button>
        </form>

        <div class="links-container">
            <a href="cart.php">Back to Cart</a>
            <a href="dashboard.php">Continue Shopping</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const bankingDetailsForm = document.getElementById('bankingDetailsForm');
            const errorMessageDiv = document.getElementById('error-message');
            const successMessageDiv = document.getElementById('success-message');
            const cardNumberInput = document.getElementById('card_number');
            const expiryDateInput = document.getElementById('expiry_date');

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

            // Display any messages from PHP (e.g., if invalid order ID)
            <?php if (!empty($errorMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($errorMessage); ?>', 'error');
            <?php elseif (!empty($successMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($successMessage); ?>', 'success');
            <?php endif; ?>

            // Format card number input (add spaces)
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', (e) => {
                    let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                    value = value.replace(/(\d{4})(?=\d)/g, '$1 '); // Add space every 4 digits
                    e.target.value = value;
                });
            }

            // Format expiry date input (add slash)
            if (expiryDateInput) {
                expiryDateInput.addEventListener('input', (e) => {
                    let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                    if (value.length > 2) {
                        value = value.substring(0, 2) + '/' + value.substring(2, 4);
                    }
                    e.target.value = value;
                });
            }

            if (bankingDetailsForm) {
                bankingDetailsForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    displayMessage('', 'none'); // Clear previous messages

                    const finalizeButton = bankingDetailsForm.querySelector('.btn-submit-payment');
                    const originalButtonText = finalizeButton.textContent;
                    finalizeButton.textContent = 'Processing Payment...';
                    finalizeButton.disabled = true;

                    try {
                        const formData = new FormData(bankingDetailsForm);
                        // Clean card number and expiry date before sending
                        formData.set('card_number', formData.get('card_number').replace(/\s/g, ''));
                        // No need to clean expiry as PHP regex handles MM/YY format

                        const response = await fetch('banking_details.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            setTimeout(() => {
                                window.location.href = result.redirect;
                            }, 2000);
                        } else {
                            displayMessage(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error during payment submission:', error);
                        displayMessage('An unexpected error occurred during payment. Please try again.', 'error');
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