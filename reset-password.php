<?php
// reset_password.php

// Start output buffering at the very beginning to capture any unwanted output
ob_start();

session_start(); // Start the session at the very beginning of the page

// Set error reporting for production (errors will be logged, not displayed)
ini_set('display_errors', 1); // Temporarily set to 1 for debugging - IMPORTANT!
ini_set('display_startup_errors', 1); // Temporarily set to 1 for debugging - IMPORTANT!
error_reporting(E_ALL);

// Include the database connection file
require_once 'database.php'; // Adjust path if necessary (e.g., 'db_connect.php' or 'database.php')

// Initialize messages for display
$errorMessage = '';
$successMessage = '';

// Process POST request (form submission to send reset link)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Set header for JSON response
    header('Content-Type: application/json');

    // Get and sanitize input
    $studentNumber = trim($_POST['student_number'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Server-side validation
    if (empty($studentNumber) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Please enter both student number and email.']);
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit();
    }

    // Prepare a SQL statement to find the user by student number and email
    // Using $link as per user's database connection setup
    $stmt = $link->prepare("SELECT student_id, full_name FROM Students WHERE student_number = ? AND email = ?");

    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Database query preparation failed: ' . $link->error]);
        exit();
    }

    $stmt->bind_param("ss", $studentNumber, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        // User found, generate a unique password reset token
        $resetToken = bin2hex(random_bytes(32)); // Cryptographically secure random token
        $expiryTime = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour

        // Update the user's record with the new token and its expiry
        // Using $link as per user's database connection setup
        $updateStmt = $link->prepare("UPDATE Students SET verification_code = ?, verification_code_expiry = ? WHERE student_id = ?");
        if ($updateStmt === false) {
            echo json_encode(['success' => false, 'message' => 'Database update preparation failed: ' . $link->error]);
            exit();
        }
        $updateStmt->bind_param("ssi", $resetToken, $expiryTime, $user['student_id']);

        if ($updateStmt->execute()) {
            // --- Sending Password Reset Email ---
            $resetLink = "http://localhost/UNITRADE/new-password.php?email=" . urlencode($email) . "&code=" . urlencode($resetToken);

            try {
                // Include PHPMailer if not already included
                // If you installed via Composer, use: require_once __DIR__ . '/vendor/autoload.php';
                // If you manually placed PHPMailer, adjust paths as needed, e.g.:
                require 'PHPMailer/src/Exception.php';
                require 'PHPMailer/src/PHPMailer.php';
                require 'PHPMailer/src/SMTP.php';
                
                $mail = new PHPMailer\PHPMailer\PHPMailer(true); // Enable exceptions

                // Server settings
                $mail->SMTPDebug = 0; // Disable debug output to prevent JSON corruption
                $mail->isSMTP();                                            // Send using SMTP
                $mail->Host       = 'smtp.gmail.com';                       // Set the SMTP server to send through
                $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
                $mail->Username   = '';        // SMTP username (your Gmail address)
                $mail->Password   = '';                     // SMTP password (your Gmail App Password)
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; // Enable implicit TLS encryption
                $mail->Port       = 465;                                    // TCP port to connect to; use 587 for `PHPMailer::ENCRYPTION_STARTTLS`

                // Recipients
                $mail->setFrom("mpumelelomkwanazi91@gmail.com", 'Unitrade');
                $mail->addAddress($email); // Add a recipient

                // Content
                $mail->isHTML(true);                                  // Set email format to HTML
                $mail->Subject = "Password Reset for Your Unitrade Account";
                $mail->Body    = nl2br(
                    "Dear " . htmlspecialchars($user['full_name']) . ",\n\n" .
                    "You have requested a password reset for your Unitrade account. " .
                    "Please click the following link to set a new password:\n\n" .
                    $resetLink . "\n\n" .
                    "This link will expire in 1 hour.\n\n" .
                    "If you did not request a password reset, please ignore this email.\n\n" .
                    "Regards,\nUnitrade Team"
                );
                $mail->AltBody =
                    "Dear " . htmlspecialchars($user['full_name']) . ",\n\n" .
                    "You have requested a password reset for your Unitrade account. " .
                    "Please click the following link to set a new password:\n\n" .
                    $resetLink . "\n\n" .
                    "This link will expire in 1 hour.\n\n" .
                    "If you did not request a password reset, please ignore this email.\n\n" .
                    "Regards,\nUnitrade Team";

                $mail->send();
                // Email sent successfully (from PHPMailer's perspective, doesn't guarantee delivery)
                echo json_encode(['success' => true, 'message' => 'A password reset link has been sent to your email address.']);
            } catch (Exception $e) {
                // Failed to send email (PHPMailer threw an exception)
                error_log("Failed to send password reset email to " . $email . " for user " . $user['student_id'] . ". PHPMailer Error: " . $e->getMessage()); // Use $e->getMessage() for specific error
                echo json_encode(['success' => false, 'message' => 'Failed to send password reset email. Please try again later.']);
            }
            // ----------------------------------------------------

        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to generate reset link. Please try again: ' . $updateStmt->error]);
        }
        $updateStmt->close();
    } else {
        // User not found (important not to reveal if email/student number specifically don't exist)
        echo json_encode(['success' => false, 'message' => 'If your account exists, a password reset link has been sent to your email address.']);
    }

    exit(); // Exit after sending JSON response
}

// Close the database connection (only if it's not an AJAX POST request that exited earlier)
// Using $link as per user's database connection setup
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
    <title>Unitrade - Reset Password</title>
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

        /* Nav Bar Styling - Same as Login/Registration Pages */
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
            box-shadow: 0 44px 8px rgba(0, 0, 0, 0.2);
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
        <form action="reset-password.php" method="post" id="resetPasswordForm">
            <h2>Reset Password</h2>
            <div class="error-text" id="error-text" style="display:none;"></div>
            <div class="success-text" id="success-text" style="display:none;"></div>

            <p style="margin-bottom: 25px; line-height: 1.5; color: #bbb;">
                Enter your student number and email address below and we'll send you a link to reset your password.
            </p>

            <div class="input-box">
                <div class="input-field">
                    <input type="text" name="student_number" placeholder="Enter your Student Number" required>
                    <i class='bx bx-user'></i>
                </div>
                <div class="input-field">
                    <input type="email" name="email" placeholder="Enter your Email" required>
                    <i class='bx bx-envelope'></i>
                </div>
            </div>

            <button type="submit" class="btn">Send Reset Link</button>

            <h3 class="back-link">
                Remember your password? <a href="login.php">Back to Login</a>
            </h3>
        </form>
    </div>

    <!-- JavaScript for AJAX form submission -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const resetPasswordForm = document.getElementById('resetPasswordForm');
            const errorTextDiv = document.getElementById('error-text');
            const successTextDiv = document.getElementById('success-text');

            function displayMessage(message, type = 'error') {
                errorTextDiv.style.display = 'none';
                successTextDiv.style.display = 'none';

                if (type === 'success') {
                    successTextDiv.textContent = message;
                    successTextDiv.style.color = '#2ecc71'; // Green
                    successTextDiv.style.display = 'block';
                } else {
                    errorTextDiv.textContent = message;
                    errorTextDiv.style.color = '#ff6b6b'; // Red
                    errorTextDiv.style.display = 'block';
                }
            }

            if (resetPasswordForm) {
                resetPasswordForm.addEventListener('submit', async (event) => {
                    event.preventDefault(); // Prevent default HTML form submission

                    displayMessage('', 'none'); // Clear previous messages

                    const sendButton = resetPasswordForm.querySelector('.btn');
                    const originalButtonText = sendButton.textContent;
                    sendButton.textContent = 'Sending...';
                    sendButton.disabled = true;

                    try {
                        const formData = new FormData(resetPasswordForm);

                        const response = await fetch('reset-password.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json(); // Assuming PHP returns JSON

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            // No redirect here, just confirmation
                        } else {
                            displayMessage(result.message, 'error');
                        }

                    } catch (error) {
                        console.error('Error during password reset link request:', error);
                        displayMessage('An unexpected error occurred. Please try again.', 'error');
                    } finally {
                        sendButton.textContent = originalButtonText;
                        sendButton.disabled = false;
                    }
                });
            }
        });
    </script>
</body>
</html>
