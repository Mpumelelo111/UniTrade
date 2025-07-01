<?php
// set_new_password.php
// Start output buffering at the very beginning to capture any unwanted output
ob_start();

session_start(); // Start the session (though not strictly necessary for this specific page, good habit)

// Set error reporting for debugging
ini_set('display_errors', 1); // Temporarily set to 1 for debugging - IMPORTANT!
ini_set('display_startup_errors', 1); // Temporarily set to 1 for debugging - IMPORTANT!
error_reporting(E_ALL);

// Include the database connection file
require_once 'database.php'; // Adjust path if necessary (e.g., 'db_connect.php' or 'database.php')

// Initialize variables for email and verification code from GET request
$email = $_GET['email'] ?? '';
$verificationCode = $_GET['code'] ?? '';
$isValidRequest = false;
$errorMessage = '';
$successMessage = '';

// Check if it's a GET request (initial page load via verification link)
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Validate email and verification code from URL
    if (empty($email) || empty($verificationCode)) {
        $errorMessage = 'Invalid password reset link. Missing email or verification code.';
    } else {
        // Prepare statement to check if the email and code exist and are valid/not expired
        // Using $link for database connection
        $stmt = $link->prepare("SELECT student_id FROM Students WHERE email = ? AND verification_code = ? AND verification_code_expiry > NOW()");
        if ($stmt === false) {
            $errorMessage = 'Database query preparation failed: ' . $link->error;
        } else {
            $stmt->bind_param("ss", $email, $verificationCode);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $isValidRequest = true; // Request is valid, allow user to set new password
            } else {
                $errorMessage = 'Invalid or expired password reset link. Please request a new one.';
            }
            $stmt->close();
        }
    }
}

// Process POST request (form submission to set new password)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json'); // Set header for AJAX response

    $newPassword = $_POST['new_password'] ?? '';
    $confirmNewPassword = $_POST['confirm_new_password'] ?? '';
    $emailFromForm = $_POST['email_from_form'] ?? ''; // Hidden field to pass email
    $codeFromForm = $_POST['code_from_form'] ?? ''; // Hidden field to pass code

    // Re-validate the link parameters, just in case
    // Using $link for database connection
    $stmt = $link->prepare("SELECT student_id FROM Students WHERE email = ? AND verification_code = ? AND verification_code_expiry > NOW()");
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Database query preparation failed.']);
        exit();
    }
    $stmt->bind_param("ss", $emailFromForm, $codeFromForm);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired password reset link. Please request a new one.']);
        exit();
    }

    // Server-side validation for new passwords
    if (empty($newPassword) || empty($confirmNewPassword)) {
        echo json_encode(['success' => false, 'message' => 'Please enter and confirm your new password.']);
        exit();
    }
    if ($newPassword !== $confirmNewPassword) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
        exit();
    }
    if (strlen($newPassword) < 8) { // Minimum password length
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
        exit();
    }

    // Hash the new password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update the user's password and clear the verification code/expiry
    // Using $link for database connection
    $stmt = $link->prepare("UPDATE Students SET password_hash = ?, verification_code = NULL, verification_code_expiry = NULL WHERE student_id = ?");
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Database update preparation failed: ' . $link->error]);
        exit();
    }
    $studentId = $user['student_id'];
    $stmt->bind_param("si", $passwordHash, $studentId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Your password has been reset successfully. You can now log in.', 'redirect' => 'login.php']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to set new password: ' . $stmt->error]);
    }
    $stmt->close();
    exit(); // Exit after sending JSON response
}

// Close the database connection (only if it's not an AJAX POST request that exited earlier)
// Using $link for database connection
if (isset($link) && is_object($link) && method_exists($link, 'close')) {
    $link->close();
}

// End output buffering and send the content to the browser for GET requests
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrade - Set New Password</title>
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
            justify-content: space-between; /* Distribute items with space between */
            align-items: center; /* Center vertically */
            position: relative; /* For positioning internal elements */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            border: 2px solid #4a90e2; /* Blue border like the image */
            padding: 0 20px; /* Padding inside the nav bar */
            box-sizing: border-box; /* Include padding in width */
            margin-bottom: 40px; /* Space between nav and form */
            flex-shrink: 0; /* Prevent it from shrinking */
        }

        .nav-center {
            color: #f0f0f0; /* Light color for text */
            font-size: 1.5em; /* Adjust font size */
            font-weight: bold;
            text-align: left; /* Align to the left side */
            padding-left: 10px; /* Space from the left edge, mimicking search icon position */
            white-space: nowrap; /* Prevent "Unitrade" from wrapping */
        }

        .nav-right {
            display: flex;
            flex-direction: row; /* Keep login and signup side-by-side */
            align-items: center;
            gap: 20px; /* Space between Login and Signup */
            padding-right: 10px; /* Space from the right edge, mimicking star icon position */
        }

        .nav-link {
            color: #f0f0f0; /* Light color for links */
            text-decoration: none;
            font-size: 1em;
            padding: 5px 15px; /* Smaller padding for a more compact look */
            border-radius: 20px; /* Slightly rounded if you want a subtle button look */
            transition: color 0.3s ease, background-color 0.3s ease;
            white-space: nowrap; /* Prevent text from wrapping */
        }

        .nav-link:hover {
            color: #ffffff; /* Brighter white on hover */
            background-color: rgba(255, 255, 255, 0.1); /* Subtle highlight on hover */
        }

        /* Form Wrapper Styling - Consistent with other pages */
        .wrapper {
            background-color: #2c2c2c; /* Dark background, same as nav bar */
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4); /* Stronger shadow */
            border: 2px solid #4a90e2; /* Blue border, same as nav bar */
            width: 100%;
            max-width: 450px; /* Consistent width for forms */
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
            color: #f0f0f0; /* Light text color for form labels/text */
            text-align: center; /* Center content within the wrapper */
        }

        h2 {
            text-align: center;
            color: #f0f0f0; /* Light color for heading */
            margin-bottom: 30px;
            font-size: 2em;
        }

        .error-text {
            color: #ff6b6b; /* Slightly brighter red for errors on dark background */
            text-align: center;
            margin-bottom: 15px;
            font-size: 0.9em;
        }

        .success-text { /* Added for success messages */
            color: #2ecc71; /* Green for success */
            text-align: center;
            margin-bottom: 15px;
            font-size: 0.9em;
        }


        .input-box {
            display: flex;
            flex-direction: column; /* Stack fields vertically */
            gap: 20px;
            margin-bottom: 20px;
        }

        .input-field {
            position: relative;
            width: 100%; /* Full width for input fields */
        }

        .input-field input {
            width: 100%;
            padding: 12px 40px 12px 15px; /* Adjust padding for icon */
            background-color: #3a3a3a; /* Darker input background */
            border: 1px solid #555; /* Slightly lighter border for inputs */
            border-radius: 8px;
            font-size: 1em;
            outline: none;
            color: #f0f0f0; /* Light text color for input */
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box; /* Crucial for consistent width */
        }

        .input-field input::placeholder { /* Style placeholder text */
            color: #bbb;
        }

        .input-field input:focus {
            border-color: #4a90e2; /* Blue border on focus */
            box-shadow: 0 0 8px rgba(74, 144, 226, 0.5); /* Blue shadow on focus */
        }

        .input-field i.bx { /* Styling for Boxicons */
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #bbb; /* Lighter icon color */
            font-size: 1.2em;
        }

        /* Styling for the password visibility toggle (i1) */
        i1 {
            cursor: pointer;
            color: #bbb; /* Lighter color to match icons */
            position: absolute;
            right: 15px; /* Adjust based on your icon placement */
            top: 50%;
            transform: translateY(-50%);
            font-style: normal; /* To prevent italicizing */
            font-size: 1.2em;
        }

        .btn {
            width: 100%;
            padding: 15px;
            background-color: #4a90e2; /* Blue button */
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
            background-color: #3a7ace; /* Darker blue on hover */
            transform: translateY(-2px);
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.95em;
        }

        .back-link a {
            color: #4a90e2; /* Blue for the link */
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            text-decoration: underline;
            color: #3a7ace; /* Darker blue on hover */
        }

        /* Responsive Adjustments - Consistent with other pages */
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
            <a href="login.php" class="nav-link">Login</a>
            <a href="registration.php" class="nav-link">Signup</a>
        </div>
    </nav>

    <div class="wrapper">
        <form action="new-password.php" method="post" id="setPasswordForm">
            <h2>Set New Password</h2>
            <div class="error-text" id="error-text" style="display:none;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
            <div class="success-text" id="success-text" style="display:none;">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>

            <?php if (!$isValidRequest && $_SERVER["REQUEST_METHOD"] == "GET"): ?>
                <p style="margin-bottom: 25px; line-height: 1.5; color: #ff6b6b;">
                    The password reset link is invalid or has expired. Please return to the <a href="reset-password.php" style="color:#4a90e2; text-decoration:underline;">Forgot Password</a> page to request a new link.
                </p>
            <?php else: ?>
                <p style="margin-bottom: 25px; line-height: 1.5; color: #bbb;">
                    Please enter and confirm your new password.
                </p>

                <!-- Hidden fields to pass email and code securely to POST request -->
                <input type="hidden" name="email_from_form" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="code_from_form" value="<?php echo htmlspecialchars($verificationCode); ?>">

                <div class="input-box">
                    <div class="input-field">
                        <input type="password" name="new_password" placeholder="New Password" required id="new_password">
                        <i1 onclick="togglePasswordVisibility('new_password', this)">👁️</i1>
                    </div>
                    <div class="input-field">
                        <input type="password" name="confirm_new_password" placeholder="Confirm New Password" required id="confirm_new_password">
                        <i1 onclick="togglePasswordVisibility('confirm_new_password', this)">👁️</i1>
                    </div>
                </div>

                <button type="submit" class="btn">Set New Password</button>
            <?php endif; ?>

            <h3 class="back-link">
                <a href="login.php">Back to Login</a>
            </h3>
        </form>
    </div>

    <!-- JavaScript for password toggle and AJAX form submission -->
    <script>
        function togglePasswordVisibility(id, element) {
            const input = document.getElementById(id);
            if (input.type === "password") {
                input.type = "text";
                element.textContent = "🙈"; // Or change to 'bx-hide' icon
            } else {
                input.type = "password";
                element.textContent = "👁️"; // Or change to 'bx-show' icon
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const setPasswordForm = document.getElementById('setPasswordForm');
            const errorTextDiv = document.getElementById('error-text');
            const successTextDiv = document.getElementById('success-text'); // Added success message div

            function displayMessage(message, type = 'error') {
                errorTextDiv.style.display = 'none';
                successTextDiv.style.display = 'none';

                if (type === 'success') {
                    successTextDiv.textContent = message;
                    successTextDiv.style.color = '#2ecc71'; // Green for success
                    successTextDiv.style.display = 'block';
                } else {
                    errorTextDiv.textContent = message;
                    errorTextDiv.style.color = '#ff6b6b'; // Red for error
                    errorTextDiv.style.display = 'block';
                }
            }

            if (setPasswordForm) { // Ensure form exists before attaching listener
                setPasswordForm.addEventListener('submit', async (event) => {
                    event.preventDefault(); // Prevent default HTML form submission

                    displayMessage('', 'none'); // Clear previous messages

                    const submitButton = setPasswordForm.querySelector('.btn');
                    const originalButtonText = submitButton.textContent;
                    submitButton.textContent = 'Setting Password...';
                    submitButton.disabled = true;

                    try {
                        const formData = new FormData(setPasswordForm);

                        const response = await fetch('new-password.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json(); // Assuming PHP returns JSON

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            setTimeout(() => {
                                window.location.href = result.redirect; // Redirect on success
                            }, 2000); // Give user time to read success message
                        } else {
                            displayMessage(result.message, 'error');
                        }

                    } catch (error) {
                        console.error('Error during password reset:', error);
                        displayMessage('An unexpected error occurred. Please try again.', 'error');
                    } finally {
                        submitButton.textContent = originalButtonText;
                        submitButton.disabled = false;
                    }
                });
            }

            // Initial display of messages from PHP on GET request
            if (errorTextDiv.textContent.trim() !== '') {
                errorTextDiv.style.display = 'block';
            }
            if (successTextDiv.textContent.trim() !== '') {
                successTextDiv.style.display = 'block';
            }
        });
    </script>
</body>
</html>
