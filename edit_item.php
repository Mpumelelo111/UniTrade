<?php
// edit_item.php

// Start output buffering at the very beginning to capture any unwanted output
ob_start();

session_start(); // Start the session

// Set error reporting for debugging (show errors directly in browser)
ini_set('display_errors', 1); // Set to 0 for production
ini_set('display_startup_errors', 1); // Set to 0 for production
error_reporting(E_ALL);

// Include the database connection file
require_once 'database.php'; // Adjust path if necessary

// Use $link instead of $conn for database connection
// Function to send JSON response and exit
// This function will also clear any buffered output before sending JSON
function sendJsonResponse($success, $message, $redirect = null, $errorDetails = null) {
    // Clear any previously buffered output to ensure only JSON is sent
    if (ob_get_length()) {
        ob_clean();
    }

    // Set header for JSON response
    header('Content-Type: application/json');

    $response = ['success' => $success, 'message' => $message];
    if ($redirect) {
        $response['redirect'] = $redirect;
    }
    if ($errorDetails && !$success) { // Only log error details if it's an error response
        error_log("Edit Item Error: " . $message . " Details: " . (is_array($errorDetails) ? json_encode($errorDetails) : $errorDetails));
    }
    echo json_encode($response);
    exit();
}

// --- User Authentication Check ---
// If the user is not logged in or not acting as a seller, redirect them
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header("Location: " . (isset($_SESSION['user_id']) ? "dashboard.php" : "login.php"));
    exit();
}

$current_user_id = $_SESSION['user_id'];
$itemData = null; // To store existing item data

// --- Handle GET request (Displaying the form with existing data) ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $item_id = $_GET['item_id'] ?? '';

    if (empty($item_id) || !is_numeric($item_id)) {
        $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Invalid item ID provided.'];
        header("Location: dashboard.php");
        exit();
    }

    // Fetch existing item data from the database
    // Ensure $link is the correct database connection variable from database.php
    $stmt = $link->prepare("SELECT item_id, seller_id, title, description, category, price, `condition`, image_urls, status FROM Items WHERE item_id = ? AND seller_id = ?");
    if ($stmt === false) {
        $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Database query preparation failed: ' . $link->error];
        header("Location: dashboard.php");
        exit();
    }
    
    $stmt->bind_param("ii", $item_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $itemData = $result->fetch_assoc();
    } else {
        $_SESSION['form_message'] = ['type' => 'error', 'text' => 'Item not found or you do not have permission to edit it.'];
        header("Location: dashboard.php");
        exit();
    }
    $stmt->close();
}

// --- Process POST request (Updating the item) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if the database connection is available
    if (!isset($link) || $link->connect_error) {
        sendJsonResponse(false, 'Failed to connect to the database. Please check database.php configuration.', null, $link->connect_error ?? 'Connection object not set.');
    }

    $item_id = trim($_POST['item_id'] ?? ''); // Get item_id from hidden field
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $condition = trim($_POST['condition'] ?? '');
    // We will fetch the current image URLs from the DB for server-side processing
    $existingImageUrlsFromDb = '';

    // Re-verify that the item belongs to the current user (security check)
    $stmt = $link->prepare("SELECT seller_id, image_urls FROM Items WHERE item_id = ?");
    if ($stmt === false) {
        sendJsonResponse(false, 'Database query preparation failed: ' . $link->error);
    }
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $dbItem = $result->fetch_assoc();
    $stmt->close();

    if (!$dbItem || $dbItem['seller_id'] != $current_user_id) {
        sendJsonResponse(false, 'Unauthorized action: Item not found or you do not own it.');
    }

    $existingImageUrlsFromDb = $dbItem['image_urls']; // Get current image URLs from DB

    // --- Server-side validation for updated fields ---
    if (empty($title) || empty($description) || empty($category) || empty($price) || empty($condition)) {
        sendJsonResponse(false, 'All fields except images are required.');
    }
    if (!is_numeric($price) || $price <= 0) {
        sendJsonResponse(false, 'Price must be a positive number.');
    }
    $price = number_format($price, 2, '.', ''); // Format price to 2 decimal places

    $allowedCategories = ['Books', 'Electronics', 'Clothing', 'Furniture', 'Stationery', 'Other'];
    if (!in_array($category, $allowedCategories)) {
        sendJsonResponse(false, 'Invalid category selected.');
    }

    $allowedConditions = ['New', 'Used - Like New', 'Used - Good', 'Used - Fair'];
    if (!in_array($condition, $allowedConditions)) {
        sendJsonResponse(false, 'Invalid condition selected.');
    }

    // --- Handle image updates ---
    $imageUrls = explode(',', $existingImageUrlsFromDb); // Start with existing images from DB
    $uploadDir = 'uploads/items/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            sendJsonResponse(false, 'Failed to create upload directory. Check folder permissions.');
        }
    }

    // Corrected condition: Check if new files are actually uploaded (UPLOAD_ERR_NO_FILE means no file was selected)
    if (isset($_FILES['item_images']) && $_FILES['item_images']['error'][0] !== UPLOAD_ERR_NO_FILE) {
        // If new files are uploaded, clear old images and process new ones
        $imageUrls = []; // Clear existing images if new ones are uploaded
        $totalFiles = count($_FILES['item_images']['name']);

        for ($i = 0; $i < $totalFiles; $i++) {
            // Only process if a file was actually uploaded for this index and there's no error
            if ($_FILES['item_images']['error'][$i] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['item_images']['name'][$i];
                $fileTmpName = $_FILES['item_images']['tmp_name'][$i];
                $fileSize = $_FILES['item_images']['size'][$i];
                $fileType = $_FILES['item_images']['type'][$i];

                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($fileExt, $allowedExtensions)) {
                    if ($fileSize < 5000000) { // Max file size: 5MB
                        $newFileName = uniqid('', true) . '.' . $fileExt;
                        $fileDestination = $uploadDir . $newFileName;

                        if (move_uploaded_file($fileTmpName, $fileDestination)) {
                            $imageUrls[] = $fileDestination;
                        } else {
                            sendJsonResponse(false, 'Failed to move uploaded file ' . htmlspecialchars($fileName) . '. Check folder permissions.');
                        }
                    } else {
                        sendJsonResponse(false, 'File ' . htmlspecialchars($fileName) . ' is too large (max 5MB).');
                    }
                } else {
                    sendJsonResponse(false, 'Invalid file type for ' . htmlspecialchars($fileName) . '. Only JPG, JPEG, PNG, GIF allowed.');
                }
            } elseif ($_FILES['item_images']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                // Handle other upload errors for this specific file, but ignore UPLOAD_ERR_NO_FILE
                sendJsonResponse(false, 'Error uploading file ' . htmlspecialchars($_FILES['item_images']['name'][$i]) . '. Error code: ' . $_FILES['item_images']['error'][$i]);
            }
        }
    } else if (empty($imageUrls)) {
        // If no new images are uploaded AND there were no existing images, and images are mandatory
        // Uncomment the line below if at least one image is always required
        // sendJsonResponse(false, 'At least one image is required.');
    }

    $imageUrlsString = implode(',', $imageUrls); // Update the string for database

    // --- Update item in database ---
    $stmt = $link->prepare("UPDATE Items SET title = ?, description = ?, category = ?, price = ?, `condition` = ?, image_urls = ? WHERE item_id = ? AND seller_id = ?");
    if ($stmt === false) {
        sendJsonResponse(false, 'Database update preparation failed: ' . $link->error);
    }

    $stmt->bind_param("sssdssii", $title, $description, $category, $price, $condition, $imageUrlsString, $item_id, $current_user_id);

    if ($stmt->execute()) {
        sendJsonResponse(true, 'Item updated successfully!', 'dashboard.php');
    } else {
        sendJsonResponse(false, 'Failed to update item: ' . $stmt->error);
    }
    $stmt->close();
    // sendJsonResponse already exits, so no need for exit() here.
}

// Close the database connection (only if it's not an AJAX POST request that exited earlier)
if (isset($link) && is_object($link) && method_exists($link, 'close')) {
    $link->close();
}

// Retrieve any messages set in the session (e.g., from GET redirect)
$displayMessage = $_SESSION['form_message'] ?? null;
unset($_SESSION['form_message']); // Clear the message after displaying

// End output buffering and send the content to the browser for GET requests
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrade - Edit Item</title>
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

        /* Form Wrapper Styling - Consistent with other forms/pages */
        .wrapper {
            background-color: #2c2c2c;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            border: 2px solid #4a90e2;
            width: 100%;
            max-width: 600px; /* Adjusted width for more fields */
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

        .input-box {
            display: flex;
            flex-wrap: wrap; /* Allow wrapping for responsiveness */
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
        }

        .input-field {
            position: relative;
            flex: 1; /* Allow fields to grow */
            min-width: 250px; /* Minimum width before wrapping */
        }

        .input-field input,
        .input-field textarea,
        .input-field select {
            width: 100%;
            padding: 12px 15px; /* Adjust padding for no icon by default */
            background-color: #3a3a3a;
            border: 1px solid #555;
            border-radius: 8px;
            font-size: 1em;
            outline: none;
            color: #f0f0f0;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
        }

        .input-field textarea {
            resize: vertical; /* Allow vertical resizing */
            min-height: 80px;
            padding-right: 15px; /* Ensure no icon space if not used */
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

        /* Specific padding for inputs with icons */
        .input-field.has-icon input {
            padding-right: 40px;
        }

        .input-field i.bx { /* Styling for Boxicons */
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #bbb;
            font-size: 1.2em;
            pointer-events: none; /* Make icon unclickable */
        }

        /* File Upload Styles */
        .file-upload-container {
            width: 100%;
            margin-bottom: 20px;
            text-align: left; /* Align text within container */
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 12px 15px;
            background-color: #3a3a3a;
            border: 1px solid #555;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: border-color 0.3s ease, background-color 0.3s ease;
            box-sizing: border-box;
        }

        .file-upload-label:hover {
            border-color: #4a90e2;
            background-color: #454545;
        }

        .file-upload-text {
            color: #f0f0f0;
            flex-grow: 1;
        }

        .file-upload-label input[type="file"] {
            display: none; /* Hide the default file input button */
        }

        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
            justify-content: center; /* Center previews */
        }

        .image-preview {
            width: 100px;
            height: 100px;
            border: 1px solid #555;
            border-radius: 5px;
            object-fit: cover;
            background-color: #444;
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
            margin-top: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn:hover {
            background-color: #3a7ace;
            transform: translateY(-2px);
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
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

            .input-box {
                flex-direction: column;
                gap: 15px;
            }

            .input-field {
                min-width: unset; /* Remove min-width on small screens */
            }

            h2 {
                font-size: 1.8em;
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
        <div class="nav-right">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="profile.php" class="nav-link">Profile</a>
            <a href="logout.php" class="nav-link">Logout</a>
        </div>
    </nav>

    <div class="wrapper">
        <form action="edit_item.php" method="post" enctype="multipart/form-data" id="editItemForm">
            <h2>Edit Item Listing</h2>
            <div class="form-message error" id="error-message" style="display:none;"></div>
            <div class="form-message success" id="success-message" style="display:none;"></div>

            <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($itemData['item_id'] ?? ''); ?>">
            <input type="hidden" name="existing_image_urls" value="<?php echo htmlspecialchars($itemData['image_urls'] ?? ''); ?>">


            <div class="input-box">
                <div class="input-field has-icon">
                    <input type="text" name="title" placeholder="Item Title" required value="<?php echo htmlspecialchars($itemData['title'] ?? ''); ?>">
                    <i class='bx bx-tag'></i>
                </div>
                <div class="input-field has-icon">
                    <input type="number" name="price" placeholder="Price (e.g., 150.00)" step="0.01" min="0.01" required value="<?php echo htmlspecialchars($itemData['price'] ?? ''); ?>">
                    <i class='bx bx-purchase-tag'></i>
                </div>
            </div>

            <div class="input-box">
                <div class="input-field">
                    <textarea name="description" placeholder="Item Description" required><?php echo htmlspecialchars($itemData['description'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="input-box">
                <div class="input-field has-icon">
                    <select name="category" required>
                        <option value="">Select Category</option>
                        <?php
                            $categories = ['Books', 'Electronics', 'Clothing', 'Furniture', 'Stationery', 'Other'];
                            foreach ($categories as $cat) {
                                $selected = (isset($itemData['category']) && $itemData['category'] === $cat) ? 'selected' : '';
                                echo "<option value=\"{$cat}\" {$selected}>{$cat}</option>";
                            }
                        ?>
                    </select>
                    <i class='bx bx-category'></i>
                </div>
                <div class="input-field has-icon">
                    <select name="condition" required>
                        <option value="">Select Condition</option>
                        <?php
                            $conditions = ['New', 'Used - Like New', 'Used - Good', 'Used - Fair'];
                            foreach ($conditions as $cond) {
                                $selected = (isset($itemData['condition']) && $itemData['condition'] === $cond) ? 'selected' : '';
                                echo "<option value=\"{$cond}\" {$selected}>{$cond}</option>";
                            }
                        ?>
                    </select>
                    <i class='bx bx-check-shield'></i>
                </div>
            </div>

            <div class="file-upload-container">
                <label for="item_images" class="file-upload-label">
                    <span class="file-upload-text">Upload New Images (replaces existing)</span>
                    <i class='bx bx-upload'></i>
                    <input type="file" name="item_images[]" id="item_images" accept="image/*" multiple>
                </label>
                <div class="image-preview-container" id="image-preview-container">
                    <?php
                        // Display existing image previews
                        if (isset($itemData['image_urls']) && !empty($itemData['image_urls'])) {
                            $existingImages = explode(',', $itemData['image_urls']);
                            foreach ($existingImages as $imgUrl) {
                                if (!empty($imgUrl)) {
                                    echo '<img src="' . htmlspecialchars($imgUrl) . '" alt="Item Image" class="image-preview">';
                                }
                            }
                        }
                    ?>
                </div>
            </div>

            <button type="submit" class="btn">Update Listing</button>

            <h3 class="back-link">
                <a href="dashboard.php">Back to Dashboard</a>
            </h3>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const editItemForm = document.getElementById('editItemForm');
            const errorMessageDiv = document.getElementById('error-message');
            const successMessageDiv = document.getElementById('success-message');
            const itemImagesInput = document.getElementById('item_images');
            const imagePreviewContainer = document.getElementById('image-preview-container');

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

            // Display initial messages from PHP (e.g., if redirected from dashboard with an error)
            <?php if (isset($displayMessage)): ?>
                displayMessage('<?php echo htmlspecialchars($displayMessage['text']); ?>', '<?php echo htmlspecialchars($displayMessage['type']); ?>');
            <?php endif; ?>

            // Handle new image file selection for preview
            if (itemImagesInput) {
                itemImagesInput.addEventListener('change', function() {
                    imagePreviewContainer.innerHTML = ''; // Clear existing previews when new files are selected
                    if (this.files) {
                        Array.from(this.files).forEach(file => {
                            if (file.type.startsWith('image/')) {
                                const reader = new FileReader();
                                reader.onload = (e) => {
                                    const img = document.createElement('img');
                                    img.src = e.target.result;
                                    img.classList.add('image-preview');
                                    imagePreviewContainer.appendChild(img);
                                };
                                reader.readAsDataURL(file);
                            }
                        });
                    }
                });
            }

            // Handle form submission via AJAX
            if (editItemForm) {
                editItemForm.addEventListener('submit', async (event) => {
                    event.preventDefault(); // Prevent default HTML form submission

                    displayMessage('', 'none'); // Clear previous messages

                    const submitButton = editItemForm.querySelector('.btn');
                    const originalButtonText = submitButton.textContent;
                    submitButton.textContent = 'Updating Listing...';
                    submitButton.disabled = true;

                    try {
                        const formData = new FormData(editItemForm);

                        const response = await fetch('edit_item.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json(); // Assuming PHP returns JSON

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            setTimeout(() => {
                                window.location.href = result.redirect; // Redirect to dashboard
                            }, 2000); // Give user time to read success message
                        } else {
                            displayMessage(result.message, 'error');
                        }

                    } catch (error) {
                        console.error('Error updating item:', error);
                        displayMessage('An unexpected error occurred. Please try again.', 'error');
                    } finally {
                        submitButton.textContent = originalButtonText;
                        submitButton.disabled = false;
                    }
                });
            }
        });
    </script>
</body>
</html>