<?php
// login.php

// Start output buffering at the very beginning to capture any unwanted output
ob_start();

session_start(); // Start the session at the very beginning of the page

// Set error reporting for debugging (REMOVE OR SET TO 0 IN PRODUCTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database connection file
require_once 'database.php'; // Adjust path if necessary, e.g., '../config/database.php'

// Function to send JSON response and exit
// This function will also clear any buffered output before sending JSON
function sendJsonResponse($success, $message, $redirect = null, $errorDetails = null) {
    // Clear any previously buffered output to ensure only JSON is sent
    ob_clean();

    // Set header for JSON response
    header('Content-Type: application/json');

    $response = ['success' => $success, 'message' => $message];
    if ($redirect) {
        $response['redirect'] = $redirect;
    }
    if ($errorDetails && !$success) { // Only log error details if it's an error response
        error_log("Login Error: " . $message . " Details: " . (is_array($errorDetails) ? json_encode($errorDetails) : $errorDetails));
    }
    echo json_encode($response);
    exit();
}

// Initialize error message for display in HTML (if not using AJAX for initial load)
$errorMessage = "";

// --- Main execution block ---
try {
    // Check if the connection object ($link) is actually available and valid
    if (!isset($link) || $link->connect_error) {
        // If connection fails, send JSON error and exit if it's a POST request,
        // otherwise, let the HTML render with a message (though this should be caught by sendJsonResponse)
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            sendJsonResponse(false, 'Failed to connect to the database. Please check database.php configuration.', null, $link->connect_error ?? 'Connection object not set.');
        } else {
            $errorMessage = 'Failed to connect to the database. Please try again later.';
        }
    }

    // Process Login Form Submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Get and sanitize input
        $username_email = trim($_POST['username_email'] ?? '');
        $password = $_POST['password'] ?? ''; // Do not trim password before verification

        // Server-side validation
        if (empty($username_email) || empty($password)) {
            sendJsonResponse(false, 'Please enter username/email and password.');
        }

        // Prepare a SQL statement to fetch user by email or student number
        // Using $link for the database connection object as defined in database.php
        $stmt = $link->prepare("SELECT student_id, full_name, student_number, email, password_hash, is_verified FROM Students WHERE email = ? OR student_number = ?");

        if ($stmt === false) {
            sendJsonResponse(false, 'Database query preparation failed: ' . $link->error);
        }

        $stmt->bind_param("ss", $username_email, $username_email); // Bind the same value to both placeholders
        $stmt->execute();
        $result = $stmt->get_result(); // Get the result set
        $user = $result->fetch_assoc(); // Fetch the user data as an associative array
        $stmt->close(); // Close the statement

        if ($user) {
            // User found, now verify password
            if (password_verify($password, $user['password_hash'])) {
                // Password is correct
                if ($user['is_verified'] == 1) {
                    // User is verified, create session variables
                    $_SESSION['user_id'] = $user['student_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['student_number'] = $user['student_number'];
                    $_SESSION['email'] = $user['email'];

                    // Send success response
                    sendJsonResponse(true, 'Login successful! Redirecting...', 'dashboard.php');
                } else {
                    // Account not verified
                    sendJsonResponse(false, 'Your account is not verified. Please check your email.');
                }
            } else {
                // Invalid password
                sendJsonResponse(false, 'Invalid username/email or password.');
            }
        } else {
            // User not found
            sendJsonResponse(false, 'Invalid username/email or password.');
        }
        // sendJsonResponse already exits, so no need for exit() here.
    }

    // If it's a GET request, or if the POST request didn't exit (e.g., due to a logic error),
    // render the HTML form below.
    // Check for 'verified' parameter from verify-page.php redirect
    if (isset($_GET['verified']) && $_GET['verified'] === 'true') {
        $errorMessage = 'Account verified successfully! You can now log in.';
    }

} catch (Throwable $e) {
    // Catch any unexpected PHP errors/exceptions
    sendJsonResponse(false, 'An unexpected server error occurred: ' . $e->getMessage(), null, ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()]);
} finally {
    // Close the database connection if it was opened
    if (isset($link) && is_object($link) && method_exists($link, 'close')) {
        $link->close();
    }
    // End output buffering for GET requests (POST requests exit earlier via sendJsonResponse)
    ob_end_flush();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrade - Login</title>
    <!-- Boxicons CSS for icons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* General Body Styling */
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

        /* Nav Bar Styling - Same as Registration Page */
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

        /* Login Form Styling - Matches Registration Form */
        .wrapper {
            background-color: #2c2c2c; /* Dark background, same as nav bar */
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4); /* Stronger shadow */
            border: 2px solid #4a90e2; /* Blue border, same as nav bar */
            width: 100%;
            max-width: 450px; /* Slightly narrower than registration form */
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
            color: #f0f0f0; /* Light text color for form labels/text */
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
            flex-direction: column; /* Stack login fields vertically */
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
        /* For consistency, align i1 to the left of the lock icon if both are present.
            The provided HTML only has i1. */

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9em;
            margin-bottom: 20px;
        }

        .remember-forgot label input {
            accent-color: #4a90e2; /* Blue checkbox */
            margin-right: 5px;
            transform: scale(1.1); /* Slightly larger checkbox */
        }

        .remember-forgot a {
            color: #4a90e2;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .remember-forgot a:hover {
            text-decoration: underline;
            color: #3a7ace;
        }

        .btn {
            width: 100%;
            padding: 15px;
            background-color: #4a90e2; /* Blue login button */
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

        .register-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.95em;
        }

        .register-link a {
            color: #4a90e2; /* Blue for the register link */
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            text-decoration: underline;
            color: #3a7ace; /* Darker blue on hover */
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
            <a href="login.php" class="nav-link">Login</a> <!-- Link to this login page -->
            <a href="registration.php" class="nav-link">Signup</a> <!-- Link to your registration page (changed to .php) -->
        </div>
    </nav>

    <div class="wrapper">
        <!-- Form action already points to login.php -->
        <form action="login.php" method="post" id="loginForm">
            <h2>Login</h2>
            <div class="error-text" id="error-text" style="display:none;"></div>
            <div class="success-text" id="success-text" style="display:none;"></div> <!-- Added success message div -->

            <div class="input-box">
                <div class="input-field">
                    <input type="text" name="username_email" placeholder="Username or Email" required>
                    <i class='bx bx-user'></i>
                </div>
                <div class="input-field">
                    <input type="password" name="password" placeholder="Password" required id="password">
                    <i1 onclick="togglePasswordVisibility('password', this)">👁️</i1>
                </div>
            </div>

            <div class="remember-forgot">
                <label><input type="checkbox">Remember me</label>
                <a href="reset-password.php">Forgot Password?</a> <!-- Changed to .php -->
            </div>

            <button type="submit" class="btn">Login</button>

            <h3 class="register-link">Don't have an account? <a href="registration.php">Register now</a></h3> <!-- Changed to .php -->
        </form>
    </div>

    <!-- JavaScript for password toggle -->
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
    </script>
    <script>
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm'); // Added ID to form for easier selection
    const errorTextDiv = document.getElementById('error-text');
    const successTextDiv = document.getElementById('success-text'); // Get the success message div

    function displayMessage(message, type = 'error') {
        errorTextDiv.style.display = 'none'; // Hide both initially
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

    // Check for 'verified' parameter in URL on page load
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('verified') && urlParams.get('verified') === 'true') {
        displayMessage('Account verified successfully! You can now log in.', 'success');
        // Optionally remove the 'verified' parameter from the URL after displaying the message
        urlParams.delete('verified');
        const newUrl = window.location.origin + window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.replaceState({}, document.title, newUrl);
    }


    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault(); // Prevent default HTML form submission

        displayMessage('', 'none'); // Clear previous messages

        const formData = new FormData(loginForm);

        // Optional: Add loading state to button
        const loginButton = loginForm.querySelector('.btn');
        const originalButtonText = loginButton.textContent;
        loginButton.textContent = 'Logging in...';
        loginButton.disabled = true;

        try {
            const response = await fetch('login.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json(); // Assuming login.php returns JSON

            if (result.success) {
                displayMessage(result.message, 'success');
                setTimeout(() => {
                    window.location.href = result.redirect; // Use result.redirect for flexibility
                }, 1500); // Give user time to read success message
            } else {
                displayMessage(result.message, 'error');
            }

        } catch (error) {
            console.error('Error during login:', error);
            displayMessage('An unexpected error occurred. Please try again.', 'error');
        } finally {
            // Restore button state
            loginButton.textContent = originalButtonText;
            loginButton.disabled = false;
        }
    });

    window.togglePasswordVisibility = function(id, element) {
        const input = document.getElementById(id);
        if (input.type === "password") {
            input.type = "text";
            element.textContent = "🙈";
        } else {
            input.type = "password";
            element.textContent = "👁️";
        }
    };
});
</script>
</body>
</html>
