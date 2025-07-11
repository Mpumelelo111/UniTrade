<?php
// buy_now.php
session_start();

// Set error reporting for production (errors will be logged, not displayed)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once 'database.php'; // Include your database connection

// --- User Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$itemId = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);

$item = null;
$userData = null;
$errorMessage = '';

// Fetch item details
if ($itemId) {
    $stmt = $link->prepare("SELECT item_id, title, description, price, image_urls, seller_id FROM Items WHERE item_id = ? AND status = 'Available'");
    if ($stmt) {
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $item = $result->fetch_assoc();
            // Ensure user cannot buy their own item
            if ($item['seller_id'] == $current_user_id) {
                $errorMessage = "You cannot purchase your own listing.";
                $item = null; // Invalidate item to prevent purchase
            }
        } else {
            $errorMessage = "Item not found or not available.";
        }
        $stmt->close();
    } else {
        $errorMessage = "Database error fetching item: " . $link->error;
    }
} else {
    $errorMessage = "No item ID provided.";
}

// Fetch user's default address details
if ($item) { // Only fetch user data if item is valid
    $stmt = $link->prepare("SELECT full_name, email, phone_number, default_address_type, default_street_address, default_complex_building, default_suburb, default_city_town, default_province, default_postal_code FROM Students WHERE student_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $userData = $result->fetch_assoc();
            // If no address type is set, default to 'delivery' or prompt user
            if (empty($userData['default_address_type'])) {
                $userData['default_address_type'] = 'delivery'; // Default if not set
                // You might want to add a message here prompting the user to set their address
                // $_SESSION['form_message'] = ['type' => 'warning', 'text' => 'Please set your default address in your profile.'];
            }
        } else {
            $errorMessage = "User profile not found.";
        }
        $stmt->close();
    } else {
        $errorMessage = "Database error fetching user data: " . $link->error;
    }
}

// Close the database connection
if (isset($link) && is_object($link) && method_exists($link, 'close')) {
    $link->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrade - Confirm Order</title>
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
            max-width: 700px; /* Adjusted max-width for order summary */
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

        .error-message {
            color: #ff6b6b;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .order-summary-section {
            background-color: #3a3a3a;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #555;
            text-align: left;
        }

        .order-summary-section h3 {
            color: #4a90e2;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.5em;
            border-bottom: 1px solid #555;
            padding-bottom: 10px;
        }

        .item-details {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .item-image-container {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid #666;
        }

        .item-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-info {
            flex-grow: 1;
        }

        .item-info h4 {
            margin: 0 0 5px 0;
            font-size: 1.2em;
            color: #f0f0f0;
        }

        .item-info p {
            margin: 0;
            font-size: 0.9em;
            color: #ccc;
        }

        .item-price {
            font-size: 1.5em;
            color: #2ecc71;
            font-weight: bold;
            text-align: right;
            margin-left: auto; /* Push price to the right */
        }

        .address-details p {
            margin: 5px 0;
            font-size: 1em;
            color: #f0f0f0;
        }

        .address-details strong {
            color: #4a90e2;
        }

        /* Payment Method Styles */
        .input-field {
            position: relative;
            margin-bottom: 20px;
        }

        .input-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #f0f0f0;
        }

        .input-field select,
        .input-field input[type="text"] {
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

        .input-field select:focus,
        .input-field input[type="text"]:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 8px rgba(74, 144, 226, 0.5);
        }

        .total-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid #555;
            margin-top: 20px;
        }

        .total-section .label {
            font-size: 1.3em;
            font-weight: bold;
            color: #f0f0f0;
        }

        .total-section .amount {
            font-size: 1.8em;
            font-weight: bold;
            color: #2ecc71;
        }

        .btn {
            width: 100%;
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
        }
        .btn:hover {
            background-color: #3a7ace;
            transform: translateY(-2px);
        }

        .back-link {
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
            .item-details {
                flex-direction: column;
                text-align: center;
            }
            .item-image-container {
                margin-bottom: 10px;
            }
            .item-price {
                margin-left: 0; /* Center price on small screens */
                text-align: center;
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
        <h2>Confirm Your Order</h2>

        <?php if (!empty($errorMessage)): ?>
            <p class="error-message"><?php echo htmlspecialchars($errorMessage); ?></p>
            <a href="dashboard.php" class="back-link">Back to Dashboard</a>
        <?php elseif ($item && $userData): ?>
            <div class="order-summary-section">
                <h3>Item Details</h3>
                <div class="item-details">
                    <div class="item-image-container">
                        <?php
                            $firstImageUrl = explode(',', $item['image_urls'])[0] ?? 'assets/default_product.png';
                            $itemImageUrl = htmlspecialchars($firstImageUrl);
                        ?>
                        <img src="<?php echo $itemImageUrl; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="item-image" onerror="this.onerror=null; this.src='assets/default_product.png';">
                    </div>
                    <div class="item-info">
                        <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                        <p><?php echo htmlspecialchars($item['description']); ?></p>
                    </div>
                    <span class="item-price">R <?php echo number_format($item['price'], 2); ?></span>
                </div>
            </div>

            <div class="order-summary-section">
                <h3>Delivery/Collection Details</h3>
                <div class="address-details">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($userData['full_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($userData['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($userData['phone_number']); ?></p>
                    <p><strong>Method:</strong> <?php echo htmlspecialchars(ucfirst($userData['default_address_type'])); ?></p>
                    <?php if ($userData['default_address_type'] === 'delivery'): ?>
                        <p><strong>Address:</strong>
                            <?php echo htmlspecialchars($userData['default_street_address']); ?>
                            <?php echo !empty($userData['default_complex_building']) ? ', ' . htmlspecialchars($userData['default_complex_building']) : ''; ?>,
                            <?php echo htmlspecialchars($userData['default_suburb']); ?>,
                            <?php htmlspecialchars($userData['default_city_town']); ?>,
                            <?php echo htmlspecialchars($userData['default_province']); ?>,
                            <?php echo htmlspecialchars($userData['default_postal_code']); ?>
                        </p>
                    <?php else: ?>
                        <p><strong>Collection Point:</strong> Campus A2 building, main entrance</p>
                    <?php endif; ?>
                </div>
                <a href="profile.php?tab=address_book" class="back-link" style="text-align: right; display: block;">Edit Address</a>
            </div>

            <div class="order-summary-section">
                <h3>Payment Information</h3>
                <div class="input-field">
                    <label for="payment_method">Select Payment Method:</label>
                    <select id="payment_method" name="payment_method" form="paymentForm" required>
                        <option value="">-- Select --</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="EFT">EFT (Electronic Funds Transfer)</option>
                        <!-- Add other methods as needed -->
                    </select>
                </div>
                <div id="payment-details-fields">
                    <!-- Dynamic fields will be loaded here by JavaScript -->
                </div>
            </div>

            <div class="total-section">
                <span class="label">Total:</span>
                <span class="amount">R <?php echo number_format($item['price'], 2); ?></span>
            </div>

            <form action="process_payment.php" method="post" id="paymentForm">
                <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item['item_id']); ?>">
                <input type="hidden" name="item_price" value="<?php echo htmlspecialchars($item['price']); ?>">
                <input type="hidden" name="seller_id" value="<?php echo htmlspecialchars($item['seller_id']); ?>">
                <input type="hidden" name="delivery_method" value="<?php echo htmlspecialchars($userData['default_address_type']); ?>">
                <button type="submit" class="btn">Proceed to Payment</button>
            </form>

            <a href="dashboard.php" class="back-link">Cancel and Go Back</a>

        <?php else: ?>
            <p class="error-message">Unable to display order details. Please try again.</p>
            <a href="dashboard.php" class="back-link">Back to Dashboard</a>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const hamburgerMenu = document.querySelector('.hamburger-menu');
            const navRight = document.querySelector('.nav-right');
            const paymentMethodSelect = document.getElementById('payment_method');
            const paymentDetailsFields = document.getElementById('payment-details-fields');
            const paymentForm = document.getElementById('paymentForm'); // Get the form by its new ID

            // Hamburger menu toggle logic
            if (hamburgerMenu && navRight) {
                hamburgerMenu.addEventListener('click', () => {
                    navRight.classList.toggle('active');
                });
            }

            // Function to dynamically load payment input fields
            function loadPaymentFields() {
                const selectedMethod = paymentMethodSelect.value;
                paymentDetailsFields.innerHTML = ''; // Clear previous fields

                if (selectedMethod === 'Credit Card') {
                    paymentDetailsFields.innerHTML = `
                        <div class="input-field">
                            <label for="card_number">Card Number:</label>
                            <input type="text" id="card_number" name="card_number" placeholder="XXXX XXXX XXXX XXXX" required pattern="[0-9]{13,16}" title="Enter a valid credit card number (13-16 digits)">
                        </div>
                        <div class="input-field">
                            <label for="expiry_date">Expiry Date (MM/YY):</label>
                            <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" required pattern="(0[1-9]|1[0-2])\\/[0-9]{2}" title="Enter expiry date in MM/YY format">
                        </div>
                        <div class="input-field">
                            <label for="cvv">CVV:</label>
                            <input type="text" id="cvv" name="cvv" placeholder="XXX" required pattern="[0-9]{3,4}" title="Enter 3 or 4 digit CVV">
                        </div>
                    `;
                } else if (selectedMethod === 'EFT') {
                    paymentDetailsFields.innerHTML = `
                        <div class="input-field">
                            <label for="bank_name">Bank Name:</label>
                            <input type="text" id="bank_name" name="bank_name" placeholder="e.g., FNB, Absa" required>
                        </div>
                        <div class="input-field">
                            <label for="account_number">Account Number:</label>
                            <input type="text" id="account_number" name="account_number" placeholder="e.g., 123456789" required pattern="[0-9]+" title="Enter a valid account number">
                        </div>
                    `;
                }
                // Instead of appending to the form, the fields are now inside paymentDetailsFields div.
                // The form attribute 'form="paymentForm"' on the select element ensures it's submitted with the form.
                // For the dynamically added inputs, they are already within the form context due to being inside #payment-details-fields,
                // which is itself inside the <form id="paymentForm">.
                // No explicit appending to paymentForm is needed here.
            }

            // Event listener for payment method selection change
            if (paymentMethodSelect) {
                paymentMethodSelect.addEventListener('change', loadPaymentFields);
            }

            // Initial load of payment fields if a method is pre-selected (unlikely in this flow but good practice)
            loadPaymentFields();
        });
    </script>
</body>
</html>


