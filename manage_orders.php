<?php
// manage_orders.php
session_start(); // Start the session

// Include the database connection file
require_once 'database.php'; // Adjust path as necessary

// --- User Authentication Check ---
// If the user is not logged in or not acting as a seller, redirect them
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header("Location: " . (isset($_SESSION['user_id']) ? "dashboard.php" : "login.php"));
    exit();
}

$current_user_id = $_SESSION['user_id'];
$orders = []; // Array to store fetched orders
$errorMessage = '';
$successMessage = '';

// --- Handle potential POST requests for order actions (e.g., update status) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json'); // Respond with JSON for AJAX requests

    $action = $_POST['action'];
    $transaction_id = $_POST['transaction_id'] ?? null;
    $new_status = $_POST['new_status'] ?? null;

    if (empty($transaction_id) || !is_numeric($transaction_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid transaction ID.']);
        exit();
    }

    // Basic validation of transaction ownership (crucial security step)
    $checkStmt = $conn->prepare("SELECT seller_id FROM Transactions WHERE transaction_id = ?");
    if ($checkStmt === false) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
        exit();
    }
    $checkStmt->bind_param("i", $transaction_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $transaction = $checkResult->fetch_assoc();
    $checkStmt->close();

    if (!$transaction || $transaction['seller_id'] != $current_user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
        exit();
    }

    // --- Process specific actions ---
    if ($action === 'update_status' && !empty($new_status)) {
        $allowedStatuses = ['Pending Payment', 'Processing', 'Shipped', 'Completed', 'Canceled', 'Disputed']; // Define allowed statuses
        if (!in_array($new_status, $allowedStatuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status provided.']);
            exit();
        }

        $updateStmt = $conn->prepare("UPDATE Transactions SET status = ? WHERE transaction_id = ? AND seller_id = ?");
        if ($updateStmt === false) {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
            exit();
        }
        $updateStmt->bind_param("sii", $new_status, $transaction_id, $current_user_id);
        if ($updateStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Order status updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update order status: ' . $updateStmt->error]);
        }
        $updateStmt->close();
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action or missing parameters.']);
        exit();
    }
}

// --- Fetch orders for the current seller (GET request) ---
// Join Items and Students tables to get item title and buyer name
$stmt = $conn->prepare("
    SELECT
        t.transaction_id,
        t.item_id,
        t.transaction_date,
        t.amount,
        t.status,
        t.payment_method,
        t.delivery_method,
        i.title AS item_title,
        s.full_name AS buyer_name,
        s.email AS buyer_email,
        s.phone_number AS buyer_phone
    FROM
        Transactions t
    JOIN
        Items i ON t.item_id = i.item_id
    JOIN
        Students s ON t.buyer_id = s.student_id
    WHERE
        t.seller_id = ?
    ORDER BY
        t.transaction_date DESC
");

if ($stmt === false) {
    $errorMessage = 'Database query preparation failed: ' . $conn->error;
} else {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
}

// Check for any session messages (e.g., from a redirect)
if (isset($_SESSION['form_message'])) {
    if ($_SESSION['form_message']['type'] === 'success') {
        $successMessage = $_SESSION['form_message']['text'];
    } else {
        $errorMessage = $_SESSION['form_message']['text'];
    }
    unset($_SESSION['form_message']); // Clear the message after displaying
}

// Close the database connection (only for GET requests, POST requests would have exited)
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrade - Manage Orders</title>
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
            max-width: 1000px; /* Wider for order list */
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

        /* Order List Styles */
        .order-table-container {
            width: 100%;
            overflow-x: auto; /* Enable horizontal scrolling for small screens */
            margin-top: 20px;
            border-radius: 10px;
            border: 1px solid #444;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px; /* Ensure table doesn't get too squished */
        }

        .order-table th, .order-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #444;
            color: #f0f0f0;
        }

        .order-table th {
            background-color: #3a3a3a;
            font-weight: bold;
            color: #4a90e2; /* Blue header text */
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .order-table tr:hover {
            background-color: #333;
        }

        .order-table td {
            background-color: #2c2c2c;
            font-size: 0.9em;
        }

        .order-table .status-pending { color: #ffc107; } /* Orange */
        .order-table .status-processing { color: #007bff; } /* Blue */
        .order-table .status-shipped { color: #17a2b8; } /* Light Blue */
        .order-table .status-completed { color: #2ecc71; } /* Green */
        .order-table .status-canceled { color: #e74c3c; } /* Red */
        .order-table .status-disputed { color: #ff6b6b; } /* Error Red */


        .action-select {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #555;
            background-color: #3a3a3a;
            color: #f0f0f0;
            cursor: pointer;
            outline: none;
            margin-right: 5px;
        }

        .action-select option {
            background-color: #3a3a3a; /* Match dropdown background */
            color: #f0f0f0;
        }

        .update-btn {
            padding: 8px 12px;
            border-radius: 5px;
            border: none;
            background-color: #4a90e2;
            color: white;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .update-btn:hover {
            background-color: #3a7ace;
        }

        .back-link {
            text-align: center;
            margin-top: 30px;
            font-size: 0.95em;
        }

        .back-link a {
            color: #4a90e2;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
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
            .order-table-container {
                min-width: unset; /* Allow table to shrink on very small screens */
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
        <h2>Manage Your Sales Orders</h2>

        <div class="form-message error" id="error-message" style="display:none;">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <div class="form-message success" id="success-message" style="display:none;">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>

        <?php if (!empty($orders)): ?>
            <div class="order-table-container">
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Item</th>
                            <th>Buyer</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Payment/Delivery</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['transaction_id']); ?></td>
                                <td><a href="view_item.php?item_id=<?php echo htmlspecialchars($order['item_id']); ?>" style="color:#4a90e2; text-decoration:underline;"><?php echo htmlspecialchars($order['item_title']); ?></a></td>
                                <td>
                                    <?php echo htmlspecialchars($order['buyer_name']); ?><br>
                                    <small><?php echo htmlspecialchars($order['buyer_email']); ?></small><br>
                                    <small><?php echo htmlspecialchars($order['buyer_phone']); ?></small>
                                </td>
                                <td>R <?php echo number_format($order['amount'], 2); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($order['transaction_date'])); ?></td>
                                <td class="status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?><br>
                                    <?php echo htmlspecialchars($order['delivery_method'] ?? 'N/A'); ?>
                                </td>
                                <td>
                                    <select class="action-select" data-transaction-id="<?php echo htmlspecialchars($order['transaction_id']); ?>">
                                        <option value="">Update Status</option>
                                        <option value="Processing" <?php echo ($order['status'] == 'Processing') ? 'selected' : ''; ?>>Processing</option>
                                        <option value="Shipped" <?php echo ($order['status'] == 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="Completed" <?php echo ($order['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="Canceled" <?php echo ($order['status'] == 'Canceled') ? 'selected' : ''; ?>>Cancel</option>
                                    </select>
                                    <button type="button" class="update-btn" data-transaction-id="<?php echo htmlspecialchars($order['transaction_id']); ?>">Update</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #ccc;">You have no sales orders to manage yet.</p>
        <?php endif; ?>

        <h3 class="back-link">
            <a href="dashboard.php">Back to Dashboard</a>
        </h3>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
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

            // Display any messages from PHP (e.g., from a redirect after an action)
            <?php if (!empty($errorMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($errorMessage); ?>', 'error');
            <?php elseif (!empty($successMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($successMessage); ?>', 'success');
            <?php endif; ?>

            // Handle status update buttons
            document.querySelectorAll('.update-btn').forEach(button => {
                button.addEventListener('click', async (event) => {
                    const transactionId = button.dataset.transactionId;
                    const selectElement = button.closest('td').querySelector('.action-select');
                    const newStatus = selectElement.value;

                    if (!newStatus) {
                        displayMessage('Please select a status to update.', 'error');
                        return;
                    }

                    displayMessage('Updating status...', 'success'); // Optimistic message

                    // Disable button and select during update
                    button.disabled = true;
                    selectElement.disabled = true;
                    const originalButtonText = button.textContent;
                    button.textContent = 'Updating...';


                    try {
                        const formData = new FormData();
                        formData.append('action', 'update_status');
                        formData.append('transaction_id', transactionId);
                        formData.append('new_status', newStatus);

                        const response = await fetch('manage_orders.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            // Visually update the status in the table without full reload
                            const statusCell = button.closest('tr').querySelector('.status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>'); // This needs to be more dynamic
                            // A better way would be to re-fetch the row data or update the class dynamically
                            statusCell.textContent = newStatus;
                            statusCell.className = `status-${newStatus.toLowerCase().replace(' ', '-')}`; // Update class for styling
                        } else {
                            displayMessage(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error updating order status:', error);
                        displayMessage('An unexpected error occurred while updating status.', 'error');
                    } finally {
                        button.textContent = originalButtonText;
                        button.disabled = false;
                        selectElement.disabled = false;
                    }
                });
            });
        });
    </script>
</body>
</html>
