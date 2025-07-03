<?php
// dashboard.php

// Start output buffering at the very beginning to capture any unwanted output
ob_start();

session_start(); // Start the session at the very beginning of the page

// Set error reporting for production (errors will be logged, not displayed)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Include the database connection file
require_once 'database.php'; // Adjust path if necessary

// --- User Authentication Check ---
// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get logged-in user's details from session
$current_user_id = $_SESSION['user_id'];
$current_full_name = $_SESSION['full_name'];
$current_email = $_SESSION['email'];
$current_student_number = $_SESSION['student_number']; // Assuming this is also stored in session

// --- Fetch User Profile Picture URL from Database ---
$profilePicUrl = 'assets/default_profile.png'; // Default profile picture
// REVERTED: Changed $conn back to $link for database connection
$stmt = $link->prepare("SELECT profile_pic_url FROM Students WHERE student_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['profile_pic_url'])) {
            $profilePicUrl = htmlspecialchars($row['profile_pic_url']);
        }
    }
    $stmt->close();
} else {
    // Log database error for debugging
    // REVERTED: Changed $conn->error back to $link->error
    error_log("Error preparing profile pic query: " . $link->error);
}

// --- Handle Role Switching ---
// Default role is 'buyer' if not set
if (!isset($_SESSION['user_role'])) {
    $_SESSION['user_role'] = 'buyer';
}

// If a role switch request is made via POST (e.g., from a form)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['switch_role'])) {
    $requested_role = $_POST['switch_role'];
    if ($requested_role === 'buyer' || $requested_role === 'seller') {
        $_SESSION['user_role'] = $requested_role;
        // Redirect to self to prevent form resubmission on refresh
        header("Location: dashboard.php");
        exit();
    }
}
$current_role = $_SESSION['user_role'];

// --- Fetch Products or User Listings based on Role ---
$products = []; // For buyer view (all active products)
$userListings = []; // For seller view (current user's products)

if ($current_role === 'buyer') {
    // Fetch all active products for the buyer view, INCLUDING seller_id
    // REVERTED: Changed $conn back to $link
    $stmt = $link->prepare("SELECT item_id, title, description, price, rating, image_urls, seller_id FROM Items WHERE status = 'Available' ORDER BY posted_at DESC");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt->close();
    } else {
        // REVERTED: Changed $conn->error back to $link->error
        error_log("Error preparing products query for buyer: " . $link->error);
    }
} else { // 'seller' role
    // Fetch products listed by the current user
    // REVERTED: Changed $conn back to $link
    $stmt = $link->prepare("SELECT item_id, title, description, price, rating, image_urls, status FROM Items WHERE seller_id = ? ORDER BY posted_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $userListings[] = $row;
        }
        $stmt->close();
    } else {
        // REVERTED: Changed $conn->error back to $link->error
        error_log("Error preparing user listings query for seller: " . $link->error);
    }
}

// --- Fetch Quick Stats ---
$itemsListed = 0;
$itemsSold = 0;
$pendingOrders = 0;

// Get items listed by the current user
// REVERTED: Changed $conn back to $link
$stmt = $link->prepare("SELECT COUNT(*) AS total_listed FROM Items WHERE seller_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $itemsListed = $row['total_listed'];
    }
    $stmt->close();
}


// Get items sold by the current user (requires Transactions table)
// This counts 'Completed' transactions where the current user is the seller
// REVERTED: Changed $conn back to $link
$stmt = $link->prepare("SELECT COUNT(*) AS total_sold FROM Transactions WHERE seller_id = ? AND status = 'Completed'");
if ($stmt) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $itemsSold = $row['total_sold'];
    }
    $stmt->close();
}


// Get pending orders for the current user (if they are a seller)
// This counts 'Pending Payment' transactions where the current user is the seller
// REVERTED: Changed $conn back to $link
$stmt = $link->prepare("SELECT COUNT(*) AS total_pending FROM Transactions WHERE seller_id = ? AND status = 'Pending Payment'");
if ($stmt) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $pendingOrders = $row['total_pending'];
    }
    $stmt->close();
}

// Close the database connection
// REVERTED: Changed $conn back to $link
if (isset($link) && is_object($link) && method_exists($link, 'close')) {
    $link->close();
}

// End output buffering and send the content to the browser
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrade - Dashboard</title>
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

        /* Nav Bar Styling - Same as Login/Registration/Reset Pages */
        .circular-nav {
            width: 90%; /* Take up most of the width */
            max-width: 1000px; /* Max width for larger screens */
            height: 60px; /* Fixed height, similar to a search bar */
            background-color: #2c2c2c; /* Dark background color like the image */
            border-radius: 30px; /* Half of height for a perfect pill shape */
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            border: 2px solid #4a90e2; /* Blue border like the image */
            padding: 0 20px; /* Padding inside the nav bar */
            box-sizing: border-box; /* Include padding in width */
            margin-bottom: 40px; /* Space between nav and form */
            flex-shrink: 0; /* Prevent it from shrinking */
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

        /* Dashboard Content Styling */
        .dashboard-container {
            background-color: #2c2c2c;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            border: 2px solid #4a90e2;
            width: 100%;
            max-width: 900px; /* Wider for dashboard content */
            box-sizing: border-box;
            color: #f0f0f0;
            text-align: left; /* Align content to the left */
        }

        h2.dashboard-heading {
            text-align: center;
            color: #f0f0f0;
            margin-bottom: 30px;
            font-size: 2.5em; /* Larger heading for dashboard */
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 15px;
            background-color: #3a3a3a;
            border-radius: 10px;
            border: 1px solid #555;
        }

        .profile-pic-container {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #4a90e2;
            flex-shrink: 0;
        }

        .profile-pic {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .welcome-message {
            font-size: 1.2em;
            flex-grow: 1;
            margin: 0; /* Override default p margin */
            color: #f0f0f0;
        }

        .welcome-message span {
            color: #4a90e2; /* Highlight username */
            font-weight: bold;
        }

        .role-switcher {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            padding: 10px;
            background-color: #3a3a3a;
            border-radius: 10px;
            border: 1px solid #555;
        }

        .role-button {
            padding: 10px 20px;
            border-radius: 8px;
            border: 2px solid transparent;
            background-color: #555;
            color: #f0f0f0;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s ease, border-color 0.3s ease, transform 0.2s ease;
            text-decoration: none; /* For anchor tags */
        }

        .role-button:hover {
            background-color: #666;
            transform: translateY(-2px);
        }

        .role-button.active {
            background-color: #4a90e2;
            border-color: #4a90e2;
            color: white;
            font-weight: bold;
        }

        .dashboard-section-title {
            text-align: center;
            color: #f0f0f0;
            margin-top: 40px;
            margin-bottom: 25px;
            font-size: 2em;
        }

        /* Product Grid Styling (for Buyer View) */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .product-card {
            background-color: #3a3a3a;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            border: 1px solid #555;
            text-align: left;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.4);
        }

        .product-image-container {
            width: 100%;
            height: 180px; /* Fixed height for consistency */
            margin-bottom: 15px;
            overflow: hidden;
            border-radius: 8px;
            background-color: #555; /* Placeholder background */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-name {
            font-size: 1.3em;
            color: #f0f0f0;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-description {
            font-size: 0.9em;
            color: #ccc;
            margin-bottom: 10px;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-rating {
            color: #ffb400;
            font-size: 1.1em;
            margin-bottom: 10px;
        }

        .product-price {
            font-size: 1.4em;
            color: #2ecc71;
            font-weight: bold;
            text-align: right;
            margin-top: auto;
        }

        /* Styles for action buttons in product cards */
        .product-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 15px; /* Space from price/rating */
        }

        .product-actions .action-btn {
            flex: 1; /* Make buttons take equal width */
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-decoration: none;
            color: white;
            border: none;
            text-align: center;
        }

        .product-actions .add-to-cart-btn {
            background-color: #555; /* Darker grey */
        }
        .product-actions .add-to-cart-btn:hover {
            background-color: #666;
            transform: translateY(-2px);
        }

        .product-actions .buy-now-btn {
            background-color: #4a90e2; /* Blue */
        }
        .product-actions .buy-now-btn:hover {
            background-color: #3a7ace;
            transform: translateY(-2px);
        }

        /* New style for "Your Listing" badge */
        .your-listing-badge {
            display: block;
            width: 100%;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: bold;
            text-align: center;
            background-color: #f39c12; /* Orange color for distinction */
            color: white;
            margin-top: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }


        /* Seller View Specific Styles */
        .seller-dashboard-content {
            padding: 20px;
            background-color: #3a3a3a;
            border-radius: 10px;
            border: 1px solid #555;
            margin-top: 20px;
            text-align: center;
        }

        .seller-dashboard-content h3 {
            color: #f0f0f0;
            margin-bottom: 15px;
        }

        .seller-actions {
            margin-bottom: 30px;
            text-align: center;
        }

        .seller-actions .btn {
            display: inline-block;
            width: auto;
            min-width: 150px;
            padding: 10px 20px;
            margin: 5px;
        }

        .seller-listing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .seller-listing-card {
            background-color: #424242;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            border: 1px solid #666;
            text-align: left;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .seller-listing-card .listing-title {
            font-size: 1.2em;
            color: #f0f0f0;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .seller-listing-card .listing-price {
            font-size: 1.1em;
            color: #2ecc71;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .seller-listing-card .listing-status {
            font-size: 0.9em;
            color: #bbb;
            margin-bottom: 15px;
        }

        .seller-listing-card .listing-actions {
            margin-top: auto;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .seller-listing-card .listing-actions .action-btn {
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9em;
            cursor: pointer;
            transition: background-color 0.2s ease;
            text-decoration: none;
            color: white;
            border: none;
        }

        .seller-listing-card .listing-actions .edit-btn {
            background-color: #4a90e2;
        }
        .seller-listing-card .listing-actions .edit-btn:hover {
            background-color: #3a7ace;
        }

        .seller-listing-card .listing-actions .delete-btn {
            background-color: #e74c3c;
        }
        .seller-listing-card .listing-actions .delete-btn:hover {
            background-color: #c0392b;
        }


        /* Quick Stats Styling */
        .info-list {
            list-style: none;
            padding: 0;
            margin-top: 30px;
        }

        .info-list li {
            background-color: #3a3a3a;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #555;
        }

        .info-list li span {
            color: #4a90e2;
            font-weight: bold;
        }

        /* Responsive Adjustments */
        /* Hamburger menu icon - Hidden by default on desktop */
        .hamburger-menu {
            display: none; /* Hidden on desktop */
        }

        @media (max-width: 768px) {
            .circular-nav {
                height: 50px;
                padding: 0 15px;
                /* Allow content to wrap if needed */
                flex-wrap: wrap;
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
                top: 60px; /* Below the nav bar */
                left: 0;
                border-radius: 0 0 15px 15px;
                box-shadow: 0 8px 15px rgba(0, 0, 0, 0.4);
                padding: 10px 0;
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

            /* Hamburger menu icon */
            .hamburger-menu {
                display: block; /* Show hamburger icon on mobile */
                color: #f0f0f0;
                font-size: 1.8em;
                cursor: pointer;
                padding: 5px;
                order: 2; /* Keep it on the right */
            }

            .dashboard-container {
                padding: 30px;
                width: 95%;
            }

            h2.dashboard-heading {
                font-size: 2em;
            }

            .user-info {
                flex-direction: column;
                text-align: center;
            }

            .profile-pic-container {
                margin-bottom: 10px;
            }

            .product-grid, .seller-listing-grid { /* Apply to both grids */
                grid-template-columns: 1fr;
            }

            .seller-actions .btn {
                width: 100%;
                margin: 5px 0;
            }
        }

        @media (max-width: 480px) {
            .circular-nav {
                height: 45px; /* Slightly smaller height */
                padding: 0 10px; /* Reduced padding */
            }
            .nav-center {
                font-size: 1.2em; /* Smaller font for "Unitrade" */
                padding-left: 5px; /* Reduced padding */
            }
            .hamburger-menu {
                font-size: 1.6em;
            }
            .nav-right.active {
                top: 45px; /* Adjust dropdown position for smaller nav height */
            }
            .nav-link {
                font-size: 0.8em; /* Smaller font for nav links */
                padding: 6px 15px; /* Reduced padding for links */
            }

            .dashboard-container {
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
            <!-- Dynamic navigation links after login -->
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="profile.php" class="nav-link">Profile</a>
            <a href="cart.php" class="nav-link"><i class='bx bx-cart'></i> Cart</a>
            <a href="logout.php" class="nav-link">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <h2 class="dashboard-heading">Welcome, <?php echo htmlspecialchars($current_full_name); ?>!</h2>

        <div class="user-info">
            <div class="profile-pic-container">
                <img src="<?php echo $profilePicUrl; ?>" alt="Profile Picture" class="profile-pic" onerror="this.onerror=null; this.src='assets/default_profile.png';">
            </div>
            <p class="welcome-message">
                Hello, <span><?php echo htmlspecialchars($current_full_name); ?></span>!
                Manage your marketplace activities here.
            </p>
        </div>

        <div class="role-switcher">
            <form action="dashboard.php" method="post" style="display:inline;">
                <input type="hidden" name="switch_role" value="buyer">
                <button type="submit" class="role-button <?php echo ($current_role === 'buyer') ? 'active' : ''; ?>">
                    <i class='bx bx-shopping-bag'></i> Buy
                </button>
            </form>
            <form action="dashboard.php" method="post" style="display:inline;">
                <input type="hidden" name="switch_role" value="seller">
                <button type="submit" class="role-button <?php echo ($current_role === 'seller') ? 'active' : ''; ?>">
                    <i class='bx bx-store-alt'></i> Sell
                </button>
            </form>
        </div>

        <?php if ($current_role === 'buyer'): ?>
            <h3 class="dashboard-section-title">Items for Sale</h3>
            <?php if (!empty($products)): ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="product-image-container">
                                <?php
                                    // Assuming image_urls might be a comma-separated string, take the first one
                                    $firstImageUrl = explode(',', $product['image_urls'])[0] ?? 'assets/default_product.png';
                                    $productImageUrl = htmlspecialchars($firstImageUrl);
                                ?>
                                <img src="<?php echo $productImageUrl; ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="product-image" onerror="this.onerror=null; this.src='assets/default_product.png';">
                            </div>
                            <h4 class="product-name"><?php echo htmlspecialchars($product['title']); ?></h4>
                            <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                            <div class="product-rating">
                                <?php
                                // Display stars based on rating (assuming 1-5 scale)
                                $rating = (int) $product['rating'];
                                for ($i = 0; $i < 5; $i++) {
                                    if ($i < $rating) {
                                        echo '&#9733;'; // Filled star
                                    } else {
                                        echo '&#9734;'; // Empty star
                                    }
                                }
                                ?>
                            </div>
                            <p class="product-price">R <?php echo number_format($product['price'], 2); ?></p>
                            <div class="product-actions">
                                <?php if ($product['seller_id'] == $current_user_id): ?>
                                    <span class="your-listing-badge">Your Listing</span>
                                <?php else: ?>
                                    <a href="add_to_cart.php?item_id=<?php echo htmlspecialchars($product['item_id']); ?>" class="action-btn add-to-cart-btn">
                                        <i class='bx bx-cart-add'></i> Add to Cart
                                    </a>
                                    <a href="buy_now.php?item_id=<?php echo htmlspecialchars($product['item_id']); ?>" class="action-btn buy-now-btn">
                                        <i class='bx bx-credit-card'></i> Buy Now
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #ccc;">No items currently available for sale. Check back later!</p>
            <?php endif; ?>

        <?php elseif ($current_role === 'seller'): ?>
            <h3 class="dashboard-section-title">Your Seller Dashboard</h3>
            <div class="seller-actions">
                <a href="add_item.php" class="btn"><i class='bx bx-plus-circle'></i> Add New Item</a>
                <a href="manage_orders.php" class="btn"><i class='bx bx-notepad'></i> Manage Orders</a>
                <!-- Add more seller-specific actions here -->
            </div>

            <h4 class="dashboard-section-title" style="font-size: 1.8em;">Your Current Listings</h4>
            <?php if (!empty($userListings)): ?>
                <div class="seller-listing-grid">
                    <?php foreach ($userListings as $listing): ?>
                        <div class="seller-listing-card">
                            <div class="product-image-container" style="height: 150px; margin-bottom: 10px;">
                                <?php
                                    // Assuming image_urls might be a comma-separated string, take the first one
                                    $firstListingImageUrl = explode(',', $listing['image_urls'])[0] ?? 'assets/default_product.png';
                                    $listingImageUrl = htmlspecialchars($firstListingImageUrl);
                                ?>
                                <img src="<?php echo $listingImageUrl; ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>" class="product-image" onerror="this.onerror=null; this.src='assets/default_product.png';">
                            </div>
                            <h5 class="listing-title"><?php echo htmlspecialchars($listing['title']); ?></h5>
                            <p class="listing-price">R <?php echo number_format($listing['price'], 2); ?></p>
                            <p class="listing-status">Status: <span><?php echo htmlspecialchars($listing['status']); ?></span></p>
                            <div class="listing-actions">
                            <a href="edit_item.php?item_id=<?php echo $listing['item_id']; ?>" class="action-btn edit-btn"><i class='bx bx-edit-alt'></i> Edit</a>
                            <a href="delete_item.php?item_id=<?php echo $listing['item_id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this item?');"><i class='bx bx-trash'></i> Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #ccc;">You haven't listed any items yet. Click "Add New Item" to get started!</p>
            <?php endif; ?>

        <?php endif; ?>

        <h3 class="dashboard-section-title">Quick Stats</h3>
        <ul class="info-list">
            <li>Items Listed: <span><?php echo $itemsListed; ?></span></li>
            <li>Items Sold: <span><?php echo $itemsSold; ?></span></li>
            <li>Pending Orders: <span><?php echo $pendingOrders; ?></span></li>
        </ul>

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
