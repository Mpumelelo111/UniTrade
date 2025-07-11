<?php
// order_confirmation.php
session_start();

// Set error reporting for production (errors will be logged, not displayed)
ini_set('display_errors', 1); // Temporarily set to 1 for debugging
ini_set('display_startup_errors', 1); // Temporarily set to 1 for debugging
error_reporting(E_ALL);

require_once 'database.php';

// --- Database Connection Check ---
if (!isset($link) || ($link instanceof mysqli && $link->connect_error)) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Failed to connect to the database. Please try again later.'];
    header("Location: dashboard.php");
    exit();
}

// --- User Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Please log in to view order details.'];
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$orderId = $_GET['order_id'] ?? null;

$orderData = null;
$orderItems = [];
$userData = null;
$errorMessage = '';

if (!$orderId || !is_numeric($orderId)) {
    $errorMessage = "Invalid order ID provided.";
} else {
    // Fetch main order details
    $stmt = $link->prepare("SELECT order_id, user_id, order_date, total_amount, address_details, status FROM Orders WHERE order_id = ? AND user_id = ?");
    if ($stmt === false) {
        $errorMessage = 'Database query preparation failed for order details: ' . $link->error;
    } else {
        $stmt->bind_param("ii", $orderId, $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $orderData = $result->fetch_assoc();
            // Decode address_details JSON
            $orderData['address_details'] = json_decode($orderData['address_details'], true);
        } else {
            $errorMessage = "Order not found or you do not have permission to view it.";
        }
        $stmt->close();
    }

    // Fetch order items if order data was found
    if ($orderData) {
        $stmt = $link->prepare("SELECT item_id, quantity, price_at_purchase, item_title_at_purchase, item_image_url_at_purchase FROM order_items WHERE order_id = ?");
        if ($stmt === false) {
            $errorMessage .= ' Database query preparation failed for order items: ' . $link->error;
        } else {
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $orderItems[] = $row;
            }
            $stmt->close();
        }

        // Fetch user details (again, for redundancy and completeness on this page)
        $stmt = $link->prepare("SELECT full_name, email, phone_number FROM Students WHERE student_id = ?");
        if ($stmt === false) {
            $errorMessage .= ' Database query preparation failed for user details: ' . $link->error;
        } else {
            $stmt->bind_param("i", $current_user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $userData = $result->fetch_assoc();
            }
            $stmt->close();
        }
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
    <title>Unitrade - Order #<?php echo htmlspecialchars($orderId); ?></title>
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
            max-width: 800px; /* Wider for order details */
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

        /* Order Details Sections */
        .order-section {
            background-color: #3a3a3a;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #555;
            text-align: left;
        }

        .order-section h3 {
            color: #4a90e2;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.4em;
            border-bottom: 1px solid #555;
            padding-bottom: 10px;
        }

        .order-info p {
            margin: 8px 0;
            color: #f0f0f0;
            font-size: 1em;
        }

        .order-info p strong {
            color: #bbb; /* Slightly dim for labels */
            display: inline-block;
            width: 150px; /* Align labels */
        }

        .order-items-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .order-items-list li {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px dashed #444;
        }

        .order-items-list li:last-child {
            border-bottom: none;
        }

        .item-thumbnail {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #666;
            flex-shrink: 0;
        }

        .item-details {
            flex-grow: 1;
        }

        .item-details .title {
            font-weight: bold;
            color: #f0f0f0;
            font-size: 1.1em;
        }

        .item-details .quantity-price {
            color: #ccc;
            font-size: 0.9em;
        }

        .item-subtotal {
            font-weight: bold;
            color: #2ecc71;
            font-size: 1.2em;
            white-space: nowrap; /* Prevent wrapping */
        }

        .total-summary {
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

        .total-summary .amount {
            color: #2ecc71;
        }

        .back-link {
            display: block;
            margin-top: 30px;
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
            .order-section {
                padding: 20px;
            }
            .order-section h3 {
                font-size: 1.2em;
            }
            .order-info p strong {
                width: 100px; /* Adjust label width */
            }
            .order-items-list li {
                flex-direction: column; /* Stack items vertically */
                align-items: flex-start;
                gap: 5px;
            }
            .item-details {
                width: 100%;
            }
            .item-subtotal {
                width: 100%;
                text-align: right;
            }
            .total-summary {
                font-size: 1.3em;
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
            .order-section {
                padding: 15px;
            }
            .order-section h3 {
                font-size: 1.1em;
            }
            .total-summary {
                font-size: 1.1em;
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
        <?php if (!empty($errorMessage)): ?>
            <div class="form-message error">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
            <a href="dashboard.php" class="back-link">Back to Dashboard</a>
        <?php elseif ($orderData): ?>
            <h2>Order #<?php echo htmlspecialchars($orderData['order_id']); ?> Details</h2>

            <div class="form-message success">
                Thank you for your order! Your purchase has been confirmed.
            </div>

            <div class="order-section">
                <h3>Order Information</h3>
                <div class="order-info">
                    <p><strong>Order ID:</strong> <?php echo htmlspecialchars($orderData['order_id']); ?></p>
                    <p><strong>Order Date:</strong> <?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($orderData['order_date']))); ?></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($orderData['status']); ?></p>
                    <p><strong>Total Amount:</strong> R <?php echo number_format($orderData['total_amount'], 2); ?></p>
                </div>
            </div>

            <div class="order-section">
                <h3>Delivery/Collection Details</h3>
                <div class="order-info">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($userData['full_name'] ?? 'N/A'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($userData['email'] ?? 'N/A'); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($userData['phone_number'] ?? 'N/A'); ?></p>
                    <p><strong>Method:</strong> <?php echo htmlspecialchars(ucfirst($orderData['address_details']['type'] ?? 'N/A')); ?></p>
                    <?php if (($orderData['address_details']['type'] ?? '') === 'delivery'): ?>
                        <p><strong>Address:</strong>
                            <?php echo htmlspecialchars($orderData['address_details']['street'] ?? ''); ?>
                            <?php echo !empty($orderData['address_details']['complex']) ? ', ' . htmlspecialchars($orderData['address_details']['complex']) : ''; ?>,
                            <?php echo htmlspecialchars($orderData['address_details']['suburb'] ?? ''); ?>,
                            <?php echo htmlspecialchars($orderData['address_details']['city'] ?? ''); ?>,
                            <?php echo htmlspecialchars($orderData['address_details']['province'] ?? ''); ?>,
                            <?php echo htmlspecialchars($orderData['address_details']['postal_code'] ?? ''); ?>
                        </p>
                    <?php else: ?>
                        <p><strong>Collection Point:</strong> <?php echo htmlspecialchars($orderData['address_details']['collection_point'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="order-section">
                <h3>Items in Order</h3>
                <ul class="order-items-list">
                    <?php if (!empty($orderItems)): ?>
                        <?php foreach ($orderItems as $item): ?>
                            <li>
                                <img src="<?php echo htmlspecialchars($item['item_image_url_at_purchase']); ?>" alt="<?php echo htmlspecialchars($item['item_title_at_purchase']); ?>" class="item-thumbnail" onerror="this.onerror=null; this.src='assets/default_product.png';">
                                <div class="item-details">
                                    <div class="title"><?php echo htmlspecialchars($item['item_title_at_purchase']); ?></div>
                                    <div class="quantity-price">Quantity: <?php echo htmlspecialchars($item['quantity']); ?> @ R <?php echo number_format($item['price_at_purchase'], 2); ?> each</div>
                                </div>
                                <span class="item-subtotal">R <?php echo number_format($item['quantity'] * $item['price_at_purchase'], 2); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #ccc;">No items found for this order.</p>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="order-section">
                <h3>Payment Information</h3>
                <div class="order-info">
                    <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($orderData['payment_method'] ?? 'N/A'); ?></p>
                    <!-- Add more payment details if stored in the 'orders' table (e.g., masked card number) -->
                    <!-- For now, we don't store sensitive payment details directly in 'orders' table,
                         only the method used. A real system would integrate with a payment gateway. -->
                </div>
            </div>

            <a href="dashboard.php" class="back-link">Back to Dashboard</a>

        <?php else: ?>
            <div class="form-message error">
                Unable to load order details. Please ensure you have a valid order ID.
            </div>
            <a href="dashboard.php" class="back-link">Back to Dashboard</a>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const hamburgerMenu = document.querySelector('.hamburger-menu');
            const navRight = document.querySelector('.nav-right');

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
