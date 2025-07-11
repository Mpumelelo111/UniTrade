<?php
// profile.php
session_start(); // Start the session

// Set error reporting for debugging - IMPORTANT!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database connection file
require_once 'database.php'; // Adjust path as necessary (assuming this defines $link)

// --- User Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$userData = null; // To store fetched user data
$errorMessage = '';
$successMessage = '';
$likedItems = []; // To store liked items for the 'Likes' tab

// Determine active tab from GET parameter or default to 'profile'
$activeTab = $_GET['tab'] ?? 'profile';

// --- Process POST request (Updating Profile or Address) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    ob_start(); // Start output buffering
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? ''; // 'update_profile' or 'update_address'

    if ($action === 'update_profile') {
        $newFullName = trim($_POST['full_name'] ?? '');
        $newPhoneNumber = trim($_POST['phone_number'] ?? '');
        $existingProfilePicUrl = trim($_POST['existing_profile_pic_url'] ?? '');

        // Server-side validation for profile details
        if (empty($newFullName) || empty($newPhoneNumber)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Full Name and Phone Number are required.']);
            exit();
        }
        if (!preg_match('/^\d{10}$/', $newPhoneNumber)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid phone number format (e.g., 0123456789).']);
            exit();
        }

        // --- Handle Profile Picture Upload ---
        $profilePicToSave = $existingProfilePicUrl;
        $uploadDir = 'uploads/profiles/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to create upload directory. Check server permissions.']);
                exit();
            }
        }

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_pic'];
            $fileName = $file['name'];
            $fileTmpName = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileError = $file['error'];
            $fileType = $file['type'];

            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($fileExt, $allowedExtensions)) {
                if ($fileError === 0) {
                    if ($fileSize < 5000000) { // Max file size: 5MB
                        $newFileName = uniqid('profile_', true) . '.' . $fileExt;
                        $fileDestination = $uploadDir . $newFileName;

                        if (move_uploaded_file($fileTmpName, $fileDestination)) {
                            $profilePicToSave = $fileDestination;
                            if (!empty($existingProfilePicUrl) && $existingProfilePicUrl !== 'assets/default_profile.png' && file_exists($existingProfilePicUrl)) {
                                if (strpos($existingProfilePicUrl, $uploadDir) === 0) {
                                    unlink($existingProfilePicUrl);
                                } else {
                                    error_log("Security warning: Attempted to delete file outside of upload directory: " . $existingProfilePicUrl);
                                }
                            }
                        } else {
                            error_log("Failed to move uploaded file: " . $fileTmpName . " to " . $fileDestination . " (Error: " . error_get_last()['message'] . ")");
                            ob_clean();
                            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded profile picture. Check server logs for details.']);
                            exit();
                        }
                    } else {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'Profile picture is too large (max 5MB).']);
                        exit();
                    }
                } else {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Error uploading profile picture. Error code: ' . $fileError]);
                    exit();
                }
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid profile picture file type. Only JPG, JPEG, PNG, GIF allowed.']);
                exit();
            }
        }

        // Update user data in database
        $updateStmt = $link->prepare("UPDATE Students SET full_name = ?, phone_number = ?, profile_pic_url = ? WHERE student_id = ?");
        if ($updateStmt === false) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Database update preparation failed: ' . $link->error]);
            exit();
        }

        $updateStmt->bind_param("sssi", $newFullName, $newPhoneNumber, $profilePicToSave, $current_user_id);

        if ($updateStmt->execute()) {
            $_SESSION['full_name'] = $newFullName;
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully!', 'profile_pic_url' => $profilePicToSave]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $updateStmt->error]);
        }
        $updateStmt->close();
        exit();

    } elseif ($action === 'update_address') {
        $newAddressType = trim($_POST['default_address_type'] ?? '');
        $newStreetAddress = trim($_POST['default_street_address'] ?? '');
        $newComplexBuilding = trim($_POST['default_complex_building'] ?? '');
        $newSuburb = trim($_POST['default_suburb'] ?? '');
        $newCityTown = trim($_POST['default_city_town'] ?? '');
        $newProvince = trim($_POST['default_province'] ?? '');
        $newPostalCode = trim($_POST['default_postal_code'] ?? '');

        // Server-side validation for address details
        if (empty($newAddressType)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Address Type is required.']);
            exit();
        }
        $allowedAddressTypes = ['delivery', 'collection'];
        if (!in_array($newAddressType, $allowedAddressTypes)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid address type selected.']);
            exit();
        }

        if ($newAddressType === 'delivery') {
            if (empty($newStreetAddress) || empty($newSuburb) || empty($newCityTown) || empty($newProvince) || empty($newPostalCode)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'All delivery address fields except Complex/Building are required.']);
                exit();
            }
            // Basic validation for postal code (e.g., 4-6 digits)
            if (!preg_match('/^\d{4,6}$/', $newPostalCode)) {
                 ob_clean();
                 echo json_encode(['success' => false, 'message' => 'Invalid postal code format.']);
                 exit();
            }
        } else { // It's 'collection'
            // For collection, clear out delivery-specific address fields
            $newStreetAddress = null;
            $newComplexBuilding = null;
            $newSuburb = null;
            $newCityTown = null;
            $newProvince = null;
            $newPostalCode = null;
        }

        // Update address data in database
        $updateStmt = $link->prepare("UPDATE Students SET default_address_type = ?, default_street_address = ?, default_complex_building = ?, default_suburb = ?, default_city_town = ?, default_province = ?, default_postal_code = ? WHERE student_id = ?");
        if ($updateStmt === false) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Database update preparation failed: ' . $link->error]);
            exit();
        }

        $updateStmt->bind_param("sssssssi", $newAddressType, $newStreetAddress, $newComplexBuilding, $newSuburb, $newCityTown, $newProvince, $newPostalCode, $current_user_id);

        if ($updateStmt->execute()) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Address updated successfully!']);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to update address: ' . $updateStmt->error]);
        }
        $updateStmt->close();
        exit();
    }
}

// --- Fetch User Data for Display (GET request) ---
// Fetch all necessary user data including address details
$stmt = $link->prepare("SELECT full_name, student_number, email, phone_number, profile_pic_url, default_address_type, default_street_address, default_complex_building, default_suburb, default_city_town, default_province, default_postal_code FROM Students WHERE student_id = ?");
if ($stmt === false) {
    $errorMessage = 'Database query preparation failed: ' . $link->error;
} else {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $userData = $result->fetch_assoc();
    } else {
        session_unset();
        session_destroy();
        header("Location: login.php?error=user_not_found");
        exit();
    }
    $stmt->close();
}

// --- Fetch Liked Items for the 'Likes' tab ---
// Only fetch if the 'likes' tab might be active or if we want to pre-load
$stmtLiked = $link->prepare("
    SELECT i.item_id, i.title, i.description, i.price, i.rating, i.image_urls, i.seller_id
    FROM LikedItems li
    JOIN Items i ON li.item_id = i.item_id
    WHERE li.student_id = ?
    ORDER BY li.liked_at DESC
");
if ($stmtLiked) {
    $stmtLiked->bind_param("i", $current_user_id);
    $stmtLiked->execute();
    $resultLiked = $stmtLiked->get_result();
    while ($row = $resultLiked->fetch_assoc()) {
        $likedItems[] = $row;
    }
    $stmtLiked->close();
} else {
    error_log("Error preparing liked items query: " . $link->error);
}


// Check for any session messages (e.g., from checkout.php)
if (isset($_SESSION['form_message'])) {
    if ($_SESSION['form_message']['type'] === 'success') {
        $successMessage = $_SESSION['form_message']['text'];
    } else {
        $errorMessage = $_SESSION['form_message']['text'];
    }
    unset($_SESSION['form_message']); // Clear the message after displaying
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
    <title>Unitrade - My Profile</title>
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

        /* Profile Content Wrapper */
        .wrapper {
            background-color: #2c2c2c;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            border: 2px solid #4a90e2;
            width: 100%;
            max-width: 800px; /* Increased max-width for multiple sections */
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

        /* Profile Navigation Tabs */
        .profile-nav-tabs {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #4a90e2;
            padding-bottom: 10px;
        }

        .profile-nav-tab {
            background-color: #3a3a3a;
            color: #f0f0f0;
            padding: 10px 20px;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            transition: background-color 0.3s ease, color 0.3s ease;
            font-weight: bold;
            border: 1px solid #555;
            border-bottom: none; /* Hide bottom border for tab effect */
        }

        .profile-nav-tab:hover {
            background-color: #4a4a4a;
        }

        .profile-nav-tab.active {
            background-color: #4a90e2;
            color: white;
            border-color: #4a90e2;
            box-shadow: 0 -3px 10px rgba(74, 144, 226, 0.5);
        }

        /* Content Sections */
        .profile-content-section {
            display: none; /* Hidden by default */
            padding: 20px;
            background-color: #3a3a3a;
            border-radius: 0 0 15px 15px; /* Rounded bottom corners */
            border: 1px solid #555;
            border-top: none; /* Connect to tabs */
            text-align: left;
        }

        .profile-content-section.active {
            display: block; /* Show active section */
        }

        /* Existing Profile Details Styles */
        .profile-pic-area {
            margin-bottom: 30px;
            text-align: center;
        }
        .profile-pic-display {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4a90e2;
            margin-bottom: 15px;
            box-shadow: 0 0 10px rgba(74, 144, 226, 0.5);
        }
        .profile-info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 30px;
            text-align: left;
        }
        .info-field {
            background-color: #2c2c2c; /* Darker for info fields */
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #555;
        }
        .info-field label {
            display: block;
            font-size: 0.9em;
            color: #bbb;
            margin-bottom: 5px;
        }
        .info-field p {
            font-size: 1.1em;
            color: #f0f0f0;
            margin: 0;
        }
        .input-field {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }
        .input-field input[type="text"],
        .input-field input[type="tel"],
        .input-field textarea,
        .input-field select { /* Added select for address fields */
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
        .input-field textarea {
            resize: vertical;
            min-height: 80px;
        }
        .input-field input::placeholder,
        .input-field textarea::placeholder {
            color: #bbb;
        }
        .input-field input:focus,
        .input-field textarea:focus,
        .input-field select:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 8px rgba(74, 144, 226, 0.5);
        }
        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 12px 15px;
            background-color: #2c2c2c;
            border: 1px solid #555;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: border-color 0.3s ease, background-color 0.3s ease;
            box-sizing: border-box;
            margin-top: 10px;
        }
        .file-upload-label:hover {
            border-color: #4a90e2;
            background-color: #353535;
        }
        .file-upload-text {
            color: #f0f0f0;
            flex-grow: 1;
        }
        .file-upload-label input[type="file"] {
            display: none;
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
        }
        .btn:hover {
            background-color: #3a7ace;
            transform: translateY(-2px);
        }

        /* Address Book Specific Styles */
        .address-form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .address-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #f0f0f0;
        }
        .address-radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            margin-bottom: 20px;
            justify-content: flex-start;
        }
        .address-radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 1em;
            color: #f0f0f0;
        }
        .address-radio-group input[type="radio"] {
            appearance: none;
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid #4a90e2;
            border-radius: 50%;
            outline: none;
            cursor: pointer;
            position: relative;
            flex-shrink: 0;
        }
        .address-radio-group input[type="radio"]:checked {
            background-color: #4a90e2;
            border-color: #4a90e2;
        }
        .address-radio-group input[type="radio"]:checked::after {
            content: '';
            width: 10px;
            height: 10px;
            background-color: #f0f0f0;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .collection-message {
            background-color: #2c2c2c;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #4a90e2;
            color: #f0f0f0;
            text-align: center;
            margin-top: 20px;
        }
        .collection-message p {
            margin: 5px 0;
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
            margin: 0 10px;
        }
        .links-container a:hover {
            text-decoration: underline;
            color: #3a7ace;
        }

        /* Hamburger menu icon - Hidden by default on desktop */
        .hamburger-menu {
            display: none;
        }

        /* Product Grid Styling (for Liked Items) - Reusing dashboard styles */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .product-card {
            background-color: #2c2c2c; /* Slightly darker for profile liked items */
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            border: 1px solid #555;
            text-align: left;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
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
            .profile-nav-tabs {
                flex-direction: column;
                align-items: stretch;
            }
            .profile-nav-tab {
                border-radius: 8px; /* Full rounded corners for stacked tabs */
                border-bottom: 1px solid #555; /* Restore bottom border for separation */
            }
            .profile-nav-tab.active {
                border-radius: 8px;
                border-bottom: 1px solid #4a90e2;
            }
            .profile-content-section {
                border-radius: 15px; /* Full rounded corners for content */
                border-top: 1px solid #555; /* Restore top border */
            }
            .product-grid {
                grid-template-columns: 1fr; /* Stack products on small screens */
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
        <h2>My Profile</h2>

        <div class="form-message error" id="error-message" style="display:none;">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <div class="form-message success" id="success-message" style="display:none;">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>

        <?php if ($userData): ?>
            <div class="profile-nav-tabs">
                <div class="profile-nav-tab <?php echo ($activeTab === 'profile') ? 'active' : ''; ?>" data-tab="profile">Profile Details</div>
                <div class="profile-nav-tab <?php echo ($activeTab === 'orders') ? 'active' : ''; ?>" data-tab="orders">Orders</div>
                <div class="profile-nav-tab <?php echo ($activeTab === 'returns') ? 'active' : ''; ?>" data-tab="returns">Returns</div>
                <div class="profile-nav-tab <?php echo ($activeTab === 'invoice') ? 'active' : ''; ?>" data-tab="invoice">Invoice</div>
                <div class="profile-nav-tab <?php echo ($activeTab === 'likes') ? 'active' : ''; ?>" data-tab="likes">Likes</div>
                <div class="profile-nav-tab <?php echo ($activeTab === 'address_book') ? 'active' : ''; ?>" data-tab="address_book">Address Book</div>
            </div>

            <!-- Profile Details Section -->
            <div id="profile-content-profile" class="profile-content-section <?php echo ($activeTab === 'profile') ? 'active' : ''; ?>">
                <div class="profile-pic-area">
                    <img src="<?php
                        $default = 'assets/default_profile.png';
                        $pic = !empty($userData['profile_pic_url']) ? $userData['profile_pic_url'] : $default;
                        
                        if (filter_var($pic, FILTER_VALIDATE_URL)) {
                            echo htmlspecialchars($pic, ENT_QUOTES, 'UTF-8');
                        } elseif (strpos($pic, 'uploads/profiles/') === 0 && file_exists($pic)) {
                            echo htmlspecialchars($pic, ENT_QUOTES, 'UTF-8');
                        } else {
                            echo $default;
                        }
                    ?>"
                    alt="Profile Picture" class="profile-pic-display" id="profile-pic-display"
                    onerror="this.onerror=null; this.src='assets/default_profile.png';">
                </div>

                <form action="profile.php" method="post" enctype="multipart/form-data" id="profileDetailsForm">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="existing_profile_pic_url" value="<?php echo htmlspecialchars($userData['profile_pic_url'] ?? ''); ?>">

                    <div class="profile-info-grid">
                        <div class="info-field">
                            <label>Student Number:</label>
                            <p><?php echo htmlspecialchars($userData['student_number']); ?></p>
                        </div>
                        <div class="info-field">
                            <label>Email:</label>
                            <p><?php echo htmlspecialchars($userData['email']); ?></p>
                        </div>
                    </div>

                    <div class="input-field">
                        <label class="sr-only" for="full_name">Full Name:</label>
                        <input type="text" name="full_name" id="full_name" placeholder="Full Name" required value="<?php echo htmlspecialchars($userData['full_name']); ?>">
                    </div>
                    <div class="input-field">
                        <label class="sr-only" for="phone_number">Phone Number:</label>
                        <input type="tel" name="phone_number" id="phone_number" placeholder="Phone Number" required value="<?php echo htmlspecialchars($userData['phone_number'] ?? ''); ?>">
                    </div>

                    <div class="file-upload-container">
                        <label for="profile_pic" class="file-upload-label">
                            <span class="file-upload-text" id="file-upload-name">Upload New Profile Picture (Optional)</span>
                            <i class='bx bx-upload'></i>
                            <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
                        </label>
                    </div>

                    <button type="submit" class="btn">Update Profile</button>
                </form>

                <div class="links-container">
                    <a href="change_password.php">Change Password</a>
                </div>
            </div>

            <!-- Orders Section -->
            <div id="profile-content-orders" class="profile-content-section <?php echo ($activeTab === 'orders') ? 'active' : ''; ?>">
                <h3>Your Orders</h3>
                <p>View all your past and pending orders here.</p>
                <a href="pending_orders.php" class="btn" style="width: auto; padding: 10px 20px;">View All Orders</a>
            </div>

            <!-- Returns Section -->
            <div id="profile-content-returns" class="profile-content-section <?php echo ($activeTab === 'returns') ? 'active' : ''; ?>">
                <h3>Returns</h3>
                <p>Manage your returns here. (Feature coming soon!)</p>
            </div>

            <!-- Invoice Section -->
            <div id="profile-content-invoice" class="profile-content-section <?php echo ($activeTab === 'invoice') ? 'active' : ''; ?>">
                <h3>Invoice History</h3>
                <p>Access your invoices for past purchases. (Feature coming soon!)</p>
            </div>

            <!-- Likes Section -->
            <div id="profile-content-likes" class="profile-content-section <?php echo ($activeTab === 'likes') ? 'active' : ''; ?>">
                <h3>Your Liked Items</h3>
                <?php if (!empty($likedItems)): ?>
                    <div class="product-grid">
                        <?php foreach ($likedItems as $product): ?>
                            <div class="product-card">
                                <div class="product-image-container">
                                    <?php
                                        $firstImageUrl = explode(',', $product['image_urls'])[0] ?? 'assets/default_product.png';
                                        $productImageUrl = htmlspecialchars($firstImageUrl);
                                    ?>
                                    <img src="<?php echo $productImageUrl; ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="product-image" onerror="this.onerror=null; this.src='assets/default_product.png';">
                                </div>
                                <h4 class="product-name"><?php echo htmlspecialchars($product['title']); ?></h4>
                                <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                                <div class="product-rating">
                                    <?php
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
                                        <span class="your-listing-badge" style="background-color: #f39c12;">Your Listing</span>
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
                    <p style="text-align: center; color: #ccc;">You haven't liked any items yet. Start browsing the dashboard!</p>
                <?php endif; ?>
            </div>

            <!-- Address Book Section -->
            <div id="profile-content-address_book" class="profile-content-section <?php echo ($activeTab === 'address_book') ? 'active' : ''; ?>">
                <h3>Address Book</h3>
                <p>Set your default delivery or collection address.</p>
                <form action="profile.php" method="post" id="addressBookForm">
                    <input type="hidden" name="action" value="update_address">

                    <div class="address-form-group">
                        <label>Default Address Type:</label>
                        <div class="address-radio-group">
                            <label>
                                <input type="radio" name="default_address_type" value="delivery" <?php echo ($userData['default_address_type'] === 'delivery') ? 'checked' : ''; ?> required> Delivery
                            </label>
                            <label>
                                <input type="radio" name="default_address_type" value="collection" <?php echo ($userData['default_address_type'] === 'collection') ? 'checked' : ''; ?>> Collection
                            </label>
                        </div>
                    </div>

                    <div id="delivery-address-fields" class="delivery-address-fields">
                        <div class="input-field">
                            <label for="default_street_address">Street Address:</label>
                            <input type="text" id="default_street_address" name="default_street_address" placeholder="e.g., 123 Main St" required value="<?php echo htmlspecialchars($userData['default_street_address'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label for="default_complex_building">Complex/Building (Optional):</label>
                            <input type="text" id="default_complex_building" name="default_complex_building" placeholder="e.g., Apt 4B, The Terraces" value="<?php echo htmlspecialchars($userData['default_complex_building'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label for="default_suburb">Suburb:</label>
                            <input type="text" id="default_suburb" name="default_suburb" placeholder="e.g., Hatfield" required value="<?php echo htmlspecialchars($userData['default_suburb'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label for="default_city_town">City/Town:</label>
                            <input type="text" id="default_city_town" name="default_city_town" placeholder="e.g., Pretoria" required value="<?php echo htmlspecialchars($userData['default_city_town'] ?? ''); ?>">
                        </div>
                        <div class="input-field">
                            <label for="default_province">Province:</label>
                            <select id="default_province" name="default_province" required>
                                <option value="">Select Province</option>
                                <?php
                                    $provinces = [
                                        'Eastern Cape', 'Free State', 'Gauteng', 'KwaZulu-Natal',
                                        'Limpopo', 'Mpumalanga', 'North West', 'Northern Cape', 'Western Cape'
                                    ];
                                    $defaultProvince = $userData['default_province'] ?? 'North West'; // Set default from DB or 'North West'
                                    foreach ($provinces as $province) {
                                        $selected = ($defaultProvince === $province) ? 'selected' : '';
                                        echo "<option value=\"{$province}\" {$selected}>{$province}</option>";
                                    }
                                ?>
                            </select>
                        </div>
                        <div class="input-field">
                            <label for="default_postal_code">Postal Code:</label>
                            <input type="text" id="default_postal_code" name="default_postal_code" placeholder="e.g., 0083" required value="<?php echo htmlspecialchars($userData['default_postal_code'] ?? ''); ?>">
                        </div>
                    </div>

                    <div id="collection-message" class="collection-message" style="display:none;">
                        <p><strong>Campus A2 building, main entrance, for collection.</strong></p>
                        <p>This is the designated collection point for all collection orders.</p>
                    </div>

                    <button type="submit" class="btn">Save Address</button>
                </form>
            </div>

            <div class="links-container">
                <a href="dashboard.php">Back to Dashboard</a>
            </div>

        <?php else: ?>
            <p style="text-align: center; color: #ff6b6b;">Could not load user profile. Please try logging in again.</p>
            <div class="links-container">
                <a href="login.php">Go to Login</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const profileDetailsForm = document.getElementById('profileDetailsForm');
            const addressBookForm = document.getElementById('addressBookForm');
            const errorMessageDiv = document.getElementById('error-message');
            const successMessageDiv = document.getElementById('success-message');
            const profilePicInput = document.getElementById('profile_pic');
            const profilePicDisplay = document.getElementById('profile-pic-display');
            const fileUploadNameSpan = document.getElementById('file-upload-name');
            const hamburgerMenu = document.querySelector('.hamburger-menu');
            const navRight = document.querySelector('.nav-right');

            const tabButtons = document.querySelectorAll('.profile-nav-tab');
            const contentSections = document.querySelectorAll('.profile-content-section');

            // Address book specific elements
            const addressTypeRadios = document.querySelectorAll('input[name="default_address_type"]');
            const deliveryAddressFields = document.getElementById('delivery-address-fields');
            const collectionMessage = document.getElementById('collection-message');
            const deliveryInputs = deliveryAddressFields.querySelectorAll('input, select, textarea');


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

            // Function to switch tabs
            function switchTab(tabId) {
                tabButtons.forEach(button => {
                    button.classList.remove('active');
                });
                contentSections.forEach(section => {
                    section.classList.remove('active');
                });

                document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
                document.getElementById(`profile-content-${tabId}`).classList.add('active');

                // Update URL without reloading
                const url = new URL(window.location);
                url.searchParams.set('tab', tabId);
                window.history.pushState({}, '', url);

                // If switching to address book, ensure correct fields are shown/hidden
                if (tabId === 'address_book') {
                    toggleAddressFields();
                }
            }

            // Event listeners for tab buttons
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.dataset.tab;
                    switchTab(tabId);
                });
            });

            // Set initial active tab based on URL or default
            const initialTab = new URLSearchParams(window.location.search).get('tab') || 'profile';
            switchTab(initialTab);


            // Display any initial messages from PHP
            <?php if (!empty($errorMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($errorMessage); ?>', 'error');
            <?php elseif (!empty($successMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($successMessage); ?>', 'success');
            <?php endif; ?>

            // Handle profile picture preview
            if (profilePicInput) {
                profilePicInput.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        const file = this.files[0];
                        fileUploadNameSpan.textContent = file.name;
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                profilePicDisplay.src = e.target.result;
                            };
                            reader.readAsDataURL(file);
                        }
                    } else {
                        fileUploadNameSpan.textContent = 'Upload New Profile Picture (Optional)';
                    }
                });
            }

            // Handle Profile Details Form submission via AJAX
            if (profileDetailsForm) {
                profileDetailsForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    displayMessage('', 'none');

                    const submitButton = profileDetailsForm.querySelector('.btn');
                    const originalButtonText = submitButton.textContent;
                    submitButton.textContent = 'Updating...';
                    submitButton.disabled = true;

                    try {
                        const formData = new FormData(profileDetailsForm);
                        formData.append('action', 'update_profile'); // Explicit action for PHP

                        const response = await fetch('profile.php', {
                            method: 'POST',
                            body: formData
                        });

                        const contentType = response.headers.get("content-type");
                        if (contentType && contentType.indexOf("application/json") !== -1) {
                            const result = await response.json();

                            if (result.success) {
                                displayMessage(result.message, 'success');
                                if (result.profile_pic_url) {
                                    profilePicDisplay.src = result.profile_pic_url;
                                    profileDetailsForm.querySelector('input[name="existing_profile_pic_url"]').value = result.profile_pic_url;
                                }
                            } else {
                                displayMessage(result.message, 'error');
                            }
                        } else {
                            const errorText = await response.text();
                            console.error('Server response was not JSON:', errorText);
                            displayMessage('An unexpected server response was received. Please check server logs.', 'error');
                        }

                    } catch (error) {
                        console.error('Error updating profile:', error.name, error.message, error);
                        displayMessage('An unexpected error occurred. Please try again.', 'error');
                    } finally {
                        submitButton.textContent = originalButtonText;
                        submitButton.disabled = false;
                    }
                });
            }

            // Function to toggle address fields visibility and required/disabled attributes
            function toggleAddressFields() {
                const selectedType = document.querySelector('input[name="default_address_type"]:checked').value;

                if (selectedType === 'collection') {
                    deliveryAddressFields.style.display = 'none';
                    collectionMessage.style.display = 'block';
                    deliveryInputs.forEach(input => {
                        input.setAttribute('disabled', 'disabled');
                        input.removeAttribute('required'); // Remove required for disabled fields
                    });
                } else { // 'delivery'
                    deliveryAddressFields.style.display = 'block';
                    collectionMessage.style.display = 'none';
                    deliveryInputs.forEach(input => {
                        input.removeAttribute('disabled');
                        // Re-add required for delivery fields, except for complex/building
                        if (input.id !== 'default_complex_building') {
                            input.setAttribute('required', 'required');
                        }
                    });
                }
            }

            // Initial call to set state based on PHP pre-selection when address_book tab is active
            // This is now handled by the switchTab function, but good to have it here too for robustness.
            if (document.getElementById('profile-content-address_book').classList.contains('active')) {
                toggleAddressFields();
            }

            // Add event listeners to radio buttons for immediate UI update
            addressTypeRadios.forEach(radio => {
                radio.addEventListener('change', toggleAddressFields);
            });

            // Handle Address Book Form submission via AJAX
            if (addressBookForm) {
                addressBookForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    displayMessage('', 'none');

                    const submitButton = addressBookForm.querySelector('.btn');
                    const originalButtonText = submitButton.textContent;
                    submitButton.textContent = 'Saving Address...';
                    submitButton.disabled = true;

                    try {
                        const formData = new FormData(addressBookForm);
                        formData.append('action', 'update_address'); // Explicit action for PHP

                        const response = await fetch('profile.php', {
                            method: 'POST',
                            body: formData
                        });

                        const contentType = response.headers.get("content-type");
                        if (contentType && contentType.indexOf("application/json") !== -1) {
                            const result = await response.json();

                            if (result.success) {
                                displayMessage(result.message, 'success');
                                // No need to redirect, just update UI if needed
                            } else {
                                displayMessage(result.message, 'error');
                            }
                        } else {
                            const errorText = await response.text();
                            console.error('Server response was not JSON:', errorText);
                            displayMessage('An unexpected server response was received. Please check server logs.', 'error');
                        }

                    } catch (error) {
                        console.error('Error updating address:', error.name, error.message, error);
                        displayMessage('An unexpected error occurred. Please try again.', 'error');
                    } finally {
                        submitButton.textContent = originalButtonText;
                        submitButton.disabled = false;
                    }
                });
            }

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



