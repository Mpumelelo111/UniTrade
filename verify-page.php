<?php
// verify.php
session_start(); // Start the session at the very beginning of the page

// Include the database connection file
require_once 'database.php'; // Adjust path if necessary, e.g., '../config/db_connect.php'

// Initialize variables for email from URL and messages
$emailFromUrl = $_GET['email'] ?? '';
$formMessage = ''; // Message to display on the form itself (e.g., "Email missing...")

// Handle POST request for code verification
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json'); // Set header for JSON response

    $verificationCode = trim($_POST['verification_code'] ?? '');
    $email = trim($_POST['email'] ?? ''); // Get email from the hidden input field

    // Server-side validation
    if (empty($verificationCode) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Both email and verification code are required.']);
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit();
    }

    // Check if the provided code matches the user's unverified account
    $stmt = $conn->prepare("SELECT student_id FROM Students WHERE email = ? AND verification_code = ? AND is_verified = 0 AND verification_code_expiry > NOW()");
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Database query preparation failed: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("ss", $email, $verificationCode);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Code is valid and not expired, activate the account
        $updateStmt = $conn->prepare("UPDATE Students SET is_verified = 1, verification_code = NULL, verification_code_expiry = NULL WHERE student_id = ?");
        if ($updateStmt === false) {
            echo json_encode(['success' => false, 'message' => 'Database update preparation failed: ' . $conn->error]);
            exit();
        }
        $updateStmt->bind_param("i", $user['student_id']);

        if ($updateStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Account verified successfully! Redirecting to login...']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Account verification failed: ' . $updateStmt->error]);
        }
        $updateStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code for this email. Please check your email or try resending the code.']);
    }
    exit(); // Exit after sending JSON response
}

// If it's a GET request and email is missing from URL, set a message
if (empty($emailFromUrl) && $_SERVER["REQUEST_METHOD"] == "GET") {
    $formMessage = 'Email missing from URL. Please go back to registration or login if you haven\'t received a code.';
}

// Close the database connection (only if it's not an AJAX POST request that exited earlier)
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrade - Verify Account</title>
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

        /* Content Wrapper Styling - Consistent with other forms/pages */
        .wrapper {
            background-color: #2c2c2c;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            border: 2px solid #4a90e2;
            width: 100%;
            max-width: 550px; /* Adjust width as needed */
            box-sizing: border-box;
            color: #f0f0f0;
            text-align: center; /* Center content within the wrapper */
        }

        h2 {
            text-align: center;
            color: #f0f0f0;
            margin-bottom: 25px;
            font-size: 2em;
        }

        p {
            margin-bottom: 20px;
            line-height: 1.6;
            color: #bbb;
        }

        .verification-icon {
            font-size: 4em;
            color: #2ecc71; /* Green checkmark */
            margin-bottom: 20px;
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

        .input-field {
            position: relative;
            width: 100%;
            margin-bottom: 20px; /* Spacing for the input field */
        }

        .input-field input {
            width: 100%;
            padding: 12px 15px; /* Adjust padding for no icon */
            background-color: #3a3a3a;
            border: 1px solid #555;
            border-radius: 8px;
            font-size: 1em;
            outline: none;
            color: #f0f0f0;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
            text-align: center; /* Center the code input */
            letter-spacing: 2px; /* For better code readability */
        }

        .input-field input:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 8px rgba(74, 144, 226, 0.5);
        }

        .btn {
            display: inline-block;
            width: auto;
            min-width: 150px;
            padding: 12px 25px;
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
            text-decoration: none;
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
            margin: 0 10px; /* Space between links */
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
            <a href="login.php" class="nav-link">Login</a> <!-- Changed to .php -->
            <a href="registration.php" class="nav-link">Signup</a> <!-- Changed to .php -->
        </div>
    </nav>

    <div class="wrapper">
        <i class='bx bx-envelope verification-icon'></i> <!-- Changed icon to envelope -->
        <h2>Verify Your Account</h2>
        <p>
            A verification code has been sent to your registered email address.
            Please enter the code below to activate your account.
        </p>

        <form id="verificationForm" action="verify.php" method="post">
            <div class="form-message" id="form-message"
                 <?php if (!empty($formMessage)): ?>
                     class="form-message error" style="display:block;"
                 <?php else: ?>
                     style="display:none;"
                 <?php endif; ?>
            >
                <?php echo htmlspecialchars($formMessage); ?>
            </div>

            <div class="input-field">
                <input type="text" name="verification_code" placeholder="Enter Verification Code" required maxlength="32" pattern="[a-fA-F0-9]{32}"> <!-- Adjusted maxlength/pattern for 32-char hex code -->
            </div>
            
            <input type="hidden" name="email" id="userEmail" value="<?php echo htmlspecialchars($emailFromUrl); ?>"> <!-- Hidden field to pass email -->

            <button type="submit" class="btn">Verify Account</button>
        </form>

        <div class="links-container">
            <a href="#" id="resendCodeLink">Resend Code?</a>
            <a href="login.php">Back to Login</a> <!-- Changed to .php -->
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const verificationForm = document.getElementById('verificationForm');
            const formMessageDiv = document.getElementById('form-message');
            const resendCodeLink = document.getElementById('resendCodeLink');
            const userEmailInput = document.getElementById('userEmail');

            // Function to display messages on the form
            function displayMessage(message, type = 'error') {
                formMessageDiv.textContent = message;
                formMessageDiv.className = `form-message ${type}`;
                formMessageDiv.style.display = 'block';
            }

            // Handle form submission
            if (verificationForm) {
                verificationForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    formMessageDiv.style.display = 'none'; // Hide previous messages

                    const formData = new FormData(verificationForm);
                    const verifyButton = verificationForm.querySelector('.btn');
                    const originalButtonText = verifyButton.textContent;
                    verifyButton.textContent = 'Verifying...';
                    verifyButton.disabled = true;

                    try {
                        const response = await fetch('verify.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            setTimeout(() => {
                                window.location.href = 'login.php?verified=true'; // Redirect to login
                            }, 2000);
                        } else {
                            displayMessage(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error during verification:', error);
                        displayMessage('An unexpected error occurred. Please try again.', 'error');
                    } finally {
                        verifyButton.textContent = originalButtonText;
                        verifyButton.disabled = false;
                    }
                });
            }

            // Handle resend code link click
            if (resendCodeLink) {
                resendCodeLink.addEventListener('click', async (event) => {
                    event.preventDefault();
                    displayMessage("Sending new code...", 'success'); // Optimistic update

                    const emailToResend = userEmailInput.value;
                    if (!emailToResend) {
                        displayMessage("Cannot resend code: Email is missing. Please ensure your email is set or return to registration.", 'error');
                        return;
                    }

                    try {
                        const response = await fetch('resend_verification_code.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ email: emailToResend })
                        });
                        const result = await response.json();

                        if (result.success) {
                            displayMessage(result.message, 'success');
                        } else {
                            displayMessage(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error resending code:', error);
                        displayMessage('An error occurred while trying to resend the code.', 'error');
                    }
                });
            }
        });
    </script>
</body>
</html>
