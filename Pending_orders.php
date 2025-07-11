<?php
// pending_orders.php
session_start(); // Start the session

// Include the database connection file
require_once 'database.php'; // Assuming this defines $link

// --- User Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Please log in to view your orders.'];
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$orders = [];
$errorMessage = '';
$successMessage = '';

// Check for any session messages (e.g., from checkout.php)
if (isset($_SESSION['form_message'])) {
    if ($_SESSION['form_message']['type'] === 'success') {
        $successMessage = $_SESSION['form_message']['text'];
    } else {
        $errorMessage = $_SESSION['form_message']['text'];
    }
    unset($_SESSION['form_message']); // Clear the message after displaying
}

// --- Fetch User's Orders from Database ---
try {
    // Prepare statement to fetch orders for the current user
    $stmt = $link->prepare("SELECT order_id, order_date, total_amount, address_type, address_details, estimated_arrival_time, status FROM orders WHERE user_id = ? ORDER BY order_date DESC");
    if ($stmt === false) {
        throw new Exception("Prepare statement failed: " . $link->error);
    }
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($order = $result->fetch_assoc()) {
            $order_id = $order['order_id'];
            $order['items'] = []; // Initialize an array to hold items for this order

            // Fetch items for the current order
            $stmt_items = $link->prepare("SELECT item_id, quantity, price_at_purchase, item_title_at_purchase, item_image_url_at_purchase FROM order_items WHERE order_id = ?");
            if ($stmt_items === false) {
                throw new Exception("Prepare statement for order items failed: " . $link->error);
            }
            $stmt_items->bind_param("i", $order_id);
            $stmt_items->execute();
            $items_result = $stmt_items->get_result();

            while ($item = $items_result->fetch_assoc()) {
                $order['items'][] = $item;
            }
            $stmt_items->close();
            $orders[] = $order;
        }
    }
    $stmt->close();

} catch (Exception $e) {
    $errorMessage = 'Error fetching your orders: ' . $e->getMessage();
    error_log("Pending Orders Error: " . $e->getMessage() . " - SQL Error: " . $link->error);
} finally {
    // Close the database connection
    if (isset($link) && is_object($link) && method_exists($link, 'close')) {
        $link->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrade - Pending Orders</title>
    <!-- Boxicons CSS for icons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
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
        .wrapper {
            background-color: #2c2c2c;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            border: 2px solid #4a90e2;
            width: 100%;
            max-width: 800px;
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
        .orders-container {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        .order-card {
            background-color: #3a3a3a;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #555;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            text-align: left;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #555;
        }
        .order-header h3 {
            margin: 0;
            font-size: 1.4em;
            color: #f0f0f0;
        }
        .order-header .status {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9em;
        }
        .order-header .status.pending { background-color: #f39c12; color: white; }
        .order-header .status.shipped { background-color: #3498db; color: white; }
        .order-header .status.delivered { background-color: #2ecc71; color: white; }
        .order-header .status.cancelled { background-color: #e74c3c; color: white; }

        .order-details p {
            margin: 5px 0;
            font-size: 0.95em;
            color: #ccc;
        }
        .order-details strong {
            color: #f0f0f0;
        }
        .order-items-list {
            margin-top: 15px;
            border-top: 1px solid #555;
            padding-top: 15px;
        }
        .order-items-list h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #f0f0f0;
            font-size: 1.1em;
        }
        .order-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            background-color: #4a4a4a;
            padding: 10px;
            border-radius: 8px;
        }
        .order-item-image-container {
            width: 60px;
            height: 60px;
            overflow: hidden;
            border-radius: 5px;
            margin-right: 10px;
            flex-shrink: 0;
        }
        .order-item-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .order-item-details {
            flex-grow: 1;
        }
        .order-item-details p {
            margin: 0;
            font-size: 0.85em;
            color: #ddd;
        }
        .order-item-details .item-title {
            font-weight: bold;
            color: #f0f0f0;
            font-size: 1em;
        }
        .order-total {
            text-align: right;
            margin-top: 20px;
            font-size: 1.3em;
            font-weight: bold;
            color: #2ecc71;
        }
        .no-orders-message {
            padding: 30px;
            background-color: #3a3a3a;
            border-radius: 10px;
            border: 1px solid #555;
            color: #ccc;
            font-size: 1.1em;
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
                top: 50px;
                left: 0;
                border-radius: 0 0 15px 15px;
                box-shadow: 0 8px 15px rgba(0, 0, 0, 0.4);
                padding: 0;
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
            }
            .wrapper {
                padding: 30px;
                width: 95%;
            }
            h2 {
                font-size: 2em;
            }
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .order-header .status {
                align-self: flex-end; /* Push status to the right */
            }
            .order-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .order-item-image-container {
                margin-right: 0;
                margin-bottom: 10px;
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
        <h2>Your Pending Orders</h2>

        <?php if (!empty($errorMessage)): ?>
            <div class="form-message error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
        <?php if (!empty($successMessage)): ?>
            <div class="form-message success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <?php if (!empty($orders)): ?>
            <div class="orders-container">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <h3>Order #<?php echo htmlspecialchars($order['order_id']); ?></h3>
                            <span class="status <?php echo strtolower(htmlspecialchars($order['status'])); ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                        </div>
                        <div class="order-details">
                            <p><strong>Order Date:</strong> <?php echo date('Y-m-d H:i', strtotime(htmlspecialchars($order['order_date']))); ?></p>
                            <p><strong>Total Amount:</strong> R <?php echo number_format($order['total_amount'], 2); ?></p>
                            <p><strong><?php echo ucfirst(htmlspecialchars($order['address_type'])); ?> Address:</strong> <?php echo htmlspecialchars($order['address_details']); ?></p>
                            <p><strong>Estimated Arrival:</strong> <?php echo date('Y-m-d H:i', strtotime(htmlspecialchars($order['estimated_arrival_time']))); ?></p>
                        </div>
                        <div class="order-items-list">
                            <h4>Items:</h4>
                            <?php if (!empty($order['items'])): ?>
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="order-item">
                                        <div class="order-item-image-container">
                                            <img src="<?php echo htmlspecialchars($item['item_image_url_at_purchase']); ?>" alt="<?php echo htmlspecialchars($item['item_title_at_purchase']); ?>" class="order-item-image" onerror="this.onerror=null; this.src='assets/default_product.png';">
                                        </div>
                                        <div class="order-item-details">
                                            <p class="item-title"><?php echo htmlspecialchars($item['item_title_at_purchase']); ?></p>
                                            <p>Qty: <?php echo htmlspecialchars($item['quantity']); ?> | Price: R <?php echo number_format($item['price_at_purchase'], 2); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No items found for this order.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-orders-message">
                <p>You have no pending orders. Go to the <a href="dashboard.php" style="color:#4a90e2;">dashboard</a> to start shopping!</p>
            </div>
        <?php endif; ?>

        <div class="links-container" style="margin-top: 30px;">
            <a href="dashboard.php">Continue Shopping</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const hamburgerMenu = document.querySelector('.hamburger-menu');
            const navRight = document.querySelector('.nav-right');

            if (hamburgerMenu && navRight) {
                hamburgerMenu.addEventListener('click', () => {
                    navRight.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>