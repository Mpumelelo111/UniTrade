<?php
// profile.php
session_start(); // Start the session

// Include the database connection file
require_once 'database.php'; // Adjust path as necessary

// --- User Authentication Check ---
// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$userData = null; // To store fetched user data
$errorMessage = '';
$successMessage = '';

// Set header for JSON response if it's an AJAX POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
}

// --- Fetch User Data for Display ---
$stmt = $conn->prepare("SELECT full_name, student_number, email, phone_number, profile_pic_url FROM Students WHERE student_id = ?");
if ($stmt === false) {
    $errorMessage = 'Database query preparation failed: ' . $conn->error;
} else {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $userData = $result->fetch_assoc();
    } else {
        // This case should ideally not happen if user is logged in, but handle defensively
        session_unset();
        session_destroy();
        header("Location: login.php?error=user_not_found");
        exit();
    }
    $stmt->close();
}

// --- Process POST request (Updating Profile) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newFullName = trim($_POST['full_name'] ?? '');
    $newPhoneNumber = trim($_POST['phone_number'] ?? '');
    $existingProfilePicUrl = trim($_POST['existing_profile_pic_url'] ?? ''); // Hidden field for current image

    // Server-side validation
    if (empty($newFullName) || empty($newPhoneNumber)) {
        echo json_encode(['success' => false, 'message' => 'Full Name and Phone Number are required.']);
        exit();
    }
    if (!preg_match('/^\d{10}$/', $newPhoneNumber)) { // Basic 10-digit phone number check
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format (e.g., 0123456789).']);
        exit();
    }

    // --- Handle Profile Picture Upload ---
    $profilePicToSave = $existingProfilePicUrl; // Default to existing URL
    $uploadDir = 'uploads/profiles/'; // Directory to store profile pictures
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true); // Create directory if it doesn't exist
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
                    $newFileName = uniqid('profile_', true) . '.' . $fileExt; // Generate unique filename
                    $fileDestination = $uploadDir . $newFileName;

                    if (move_uploaded_file($fileTmpName, $fileDestination)) {
                        $profilePicToSave = $fileDestination; // Update URL to new file path
                        // Optional: Delete old profile picture file if it's not the default
                        if (!empty($existingProfilePicUrl) && $existingProfilePicUrl !== 'assets/default_profile.png' && file_exists($existingProfilePicUrl)) {
                            unlink($existingProfilePicUrl);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded profile picture.']);
                        exit();
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Profile picture is too large (max 5MB).']);
                    exit();
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Error uploading profile picture. Error code: ' . $fileError]);
                exit();
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid profile picture file type. Only JPG, JPEG, PNG, GIF allowed.']);
            exit();
        }
    }

    // --- Update user data in database ---
    $updateStmt = $conn->prepare("UPDATE Students SET full_name = ?, phone_number = ?, profile_pic_url = ? WHERE student_id = ?");
    if ($updateStmt === false) {
        echo json_encode(['success' => false, 'message' => 'Database update preparation failed: ' . $conn->error]);
        exit();
    }

    $updateStmt->bind_param("sssi", $newFullName, $newPhoneNumber, $profilePicToSave, $current_user_id);

    if ($updateStmt->execute()) {
        // Update session variable if name changed
        $_SESSION['full_name'] = $newFullName;
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully!', 'profile_pic_url' => $profilePicToSave]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $updateStmt->error]);
    }
    $updateStmt->close();
    exit(); // Exit after sending JSON response
}

// Close the database connection (only for GET requests, POST requests would have exited)
$conn->close();
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

        /* Profile Content Wrapper */
        .wrapper {
            background-color: #2c2c2c;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            border: 2px solid #4a90e2;
            width: 100%;
            max-width: 600px;
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
            background-color: #3a3a3a;
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

        .info-field p,
        .info-field input {
            font-size: 1.1em;
            color: #f0f0f0;
            margin: 0;
            background: none;
            border: none;
            width: 100%;
            padding: 0;
            outline: none;
        }

        .info-field input:focus {
            box-shadow: none; /* Prevent additional shadow for read-only or subtle inputs */
        }

        .input-field {
            position: relative;
            margin-bottom: 20px;
            text-align: left; /* Align inputs left inside their container */
        }

        .input-field input[type="text"],
        .input-field input[type="tel"] {
            width: 100%;
            padding: 12px 15px;
            background-color: #3a3a3a;
            border: 1px solid #555;
            border-radius: 8px;
            font-size: 1em;
            outline: none;
            color: #f0f0f0;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
        }

        .input-field input[type="text"]::placeholder,
        .input-field input[type="tel"]::placeholder {
            color: #bbb;
        }

        .input-field input[type="text"]:focus,
        .input-field input[type="tel"]:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 8px rgba(74, 144, 226, 0.5);
        }

        /* File Upload Styles */
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
            margin-top: 10px; /* Space from previous input */
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
        <h2>My Profile</h2>

        <div class="form-message error" id="error-message" style="display:none;">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <div class="form-message success" id="success-message" style="display:none;">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>

        <?php if ($userData): ?>
            <div class="profile-pic-area">
                <img src="<?php echo htmlspecialchars($userData['profile_pic_url'] ?? 'assets/default_profile.png'); ?>"
                     alt="Profile Picture" class="profile-pic-display" id="profile-pic-display"
                     onerror="this.onerror=null; this.src='assets/default_profile.png';">
            </div>

            <form action="profile.php" method="post" enctype="multipart/form-data" id="profileForm">
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
                    <label class="sr-only" for="full_name">Full Name:</label> <!-- sr-only for accessibility -->
                    <input type="text" name="full_name" id="full_name" placeholder="Full Name" required value="<?php echo htmlspecialchars($userData['full_name']); ?>">
                </div>
                <div class="input-field">
                    <label class="sr-only" for="phone_number">Phone Number:</label> <!-- sr-only for accessibility -->
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
            const profileForm = document.getElementById('profileForm');
            const errorMessageDiv = document.getElementById('error-message');
            const successMessageDiv = document.getElementById('success-message');
            const profilePicInput = document.getElementById('profile_pic');
            const profilePicDisplay = document.getElementById('profile-pic-display');
            const fileUploadNameSpan = document.getElementById('file-upload-name');

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
                        fileUploadNameSpan.textContent = file.name; // Update label with file name
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                profilePicDisplay.src = e.target.result;
                            };
                            reader.readAsDataURL(file);
                        }
                    } else {
                        fileUploadNameSpan.textContent = 'Upload New Profile Picture (Optional)';
                        // Revert to original image if no new file selected (optional)
                        // For simplicity, we just keep the current displayed image as is
                    }
                });
            }

            // Handle form submission via AJAX
            if (profileForm) {
                profileForm.addEventListener('submit', async (event) => {
                    event.preventDefault(); // Prevent default HTML form submission

                    displayMessage('', 'none'); // Clear previous messages

                    const submitButton = profileForm.querySelector('.btn');
                    const originalButtonText = submitButton.textContent;
                    submitButton.textContent = 'Updating...';
                    submitButton.disabled = true;

                    try {
                        const formData = new FormData(profileForm);

                        const response = await fetch('profile.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json(); // Assuming PHP returns JSON

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            // Update the displayed profile picture if it changed
                            if (result.profile_pic_url) {
                                profilePicDisplay.src = result.profile_pic_url;
                                // Also update the hidden field for future submissions
                                profileForm.querySelector('input[name="existing_profile_pic_url"]').value = result.profile_pic_url;
                            }
                            // No redirect, stay on profile page with updated data
                        } else {
                            displayMessage(result.message, 'error');
                        }

                    } catch (error) {
                        console.error('Error updating profile:', error);
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
