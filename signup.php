<?php
// signup.php

// Start output buffering at the very beginning to capture any unwanted output
ob_start();

// Start session
session_start();

// PHPMailer namespace imports
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Error reporting for debugging (REMOVE OR SET TO 0 IN PRODUCTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Generate a random verification code.
 *
 * @param int $length Length of the resulting hex string (must be even).
 * @return string Hexadecimal string of length $length.
 */
function generateVerificationCode($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Function to send JSON response and exit
// This function will also clear any buffered output before sending JSON
function sendJsonResponse($success, $message, $errorDetails = null) {
    // Clear any previously buffered output to ensure only JSON is sent
    ob_clean();

    // Set header for JSON response
    header('Content-Type: application/json');

    $response = ['success' => $success, 'message' => $message];
    if ($errorDetails && !$success) { // Only log error details if it's an error response
        error_log("Signup Error: " . $message . " Details: " . (is_array($errorDetails) ? json_encode($errorDetails) : $errorDetails));
    }
    echo json_encode($response);
    exit();
}

// --- Main execution block ---
try {
    // 1. Include the database connection file
    if (!file_exists('database.php')) {
        sendJsonResponse(false, 'Database connection file not found. Please ensure "database.php" exists.');
    }
    require_once 'database.php'; // Adjust path if necessary, e.g., '../config/database.php'

    // Check if the connection object ($link) is actually available and valid
    if (!isset($link) || $link->connect_error) {
        sendJsonResponse(false, 'Failed to connect to the database. Please check database.php configuration.', $link->connect_error ?? 'Connection object not set.');
    }

    // Include PHPMailer library files
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    // 3. Process Form Submission only if it's a POST request
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Sanitize and get input data
        $fullName = trim($_POST['Full-Name'] ?? '');
        $studentNumber = trim($_POST['Student_Number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $cpassword = $_POST['cpassword'] ?? '';

        $profilePicUrl = null; // Default to null for profile picture URL in database

        // Server-side validation
        if (empty($fullName) || empty($studentNumber) || empty($email) || empty($phone) || empty($password) || empty($cpassword)) {
            sendJsonResponse(false, 'All fields are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse(false, 'Invalid email format.');
        }

        if ($password !== $cpassword) {
            sendJsonResponse(false, 'Passwords do not match.');
        }

        if (strlen($password) < 8) {
            sendJsonResponse(false, 'Password must be at least 8 characters long.');
        }

        // Check if email or student number already exists in the database
        $stmt = $link->prepare("SELECT student_id FROM Students WHERE email = ? OR student_number = ? LIMIT 1");
        if ($stmt === false) {
            sendJsonResponse(false, 'Database prepare failed during existence check: ' . $link->error);
        }
        $stmt->bind_param("ss", $email, $studentNumber);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            sendJsonResponse(false, 'Email or Student Number already registered.');
        }
        $stmt->close();

        // Hash the password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Handle profile picture upload
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
            $targetDir = "uploads/profiles/"; // Consistent with profile.php
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true)) {
                    sendJsonResponse(false, 'Failed to create upload directory. Check folder permissions.');
                }
            }

            $imageFileType = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = array("jpg", "jpeg", "png", "gif");

            if (in_array($imageFileType, $allowedExtensions)) {
                $fileName = uniqid('profile_') . '.' . $imageFileType;
                $targetFilePath = $targetDir . $fileName;

                if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetFilePath)) {
                    sendJsonResponse(false, 'Failed to upload profile picture. Check folder permissions or file size.');
                }
                $profilePicUrl = $targetFilePath;
            } else {
                sendJsonResponse(false, 'Invalid file type for profile picture. Only JPG, JPEG, PNG, GIF are allowed.');
            }
        }

        // Generate verification code and expiry
        $verificationCode = generateVerificationCode();
        $verificationCodeExpiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour

        // Prepare and execute the SQL INSERT statement
        $stmt = $link->prepare("INSERT INTO Students (full_name, student_number, email, phone_number, password_hash, profile_pic_url, is_verified, verification_code, verification_code_expiry) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            sendJsonResponse(false, 'Database prepare failed during registration: ' . $link->error);
        }
        $isVerified = 0;
        $stmt->bind_param("sssssisss", $fullName, $studentNumber, $email, $phone, $passwordHash, $profilePicUrl, $isVerified, $verificationCode, $verificationCodeExpiry);

        if ($stmt->execute()) {
            // --- Sending Verification Email ---
            $mail = new PHPMailer(true);
            try {
                $mail->SMTPDebug = 0; // Set to 0 (or SMTP::DEBUG_OFF) to disable verbose debug output for production
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'mpumelelomkwanazi91@gmail.com'; // Your Gmail address
                $mail->Password   = 'vusczsavaqyacfuy'; // Your Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use ENCRYPTION_SMTPS for port 465
                $mail->Port       = 465; // TCP port to connect to

                $mail->setFrom('no-reply@yourdomain.com', 'Unitrade'); // Your "from" address
                $mail->addAddress($email, $fullName); // Add a recipient

                $mail->isHTML(true);
                $mail->Subject = "Verify Your Unitrade Account";
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'];
                $verificationLink = $protocol . $host . "/verify-page.php?email=" . urlencode($email) . "&code=" . urlencode($verificationCode);

                // MODIFIED: Explicitly display the verification code in the email body
                $mail->Body    = '
                    <p>Dear ' . htmlspecialchars($fullName) . ',</p>
                    <p>Thank you for registering with Unitrade. To activate your account, please use the verification code below:</p>
                    <p style="font-size: 1.5em; font-weight: bold; color: #4a90e2; text-align: center; letter-spacing: 2px;">' . htmlspecialchars($verificationCode) . '</p>
                    <p>Alternatively, you can click the link below to verify your account:</p>
                    <p><a href="' . htmlspecialchars($verificationLink) . '">' . htmlspecialchars($verificationLink) . '</a></p>
                    <p>If you did not register for an account, please ignore this email.</p>
                    <p>Regards,<br>The Unitrade Team</p>
                ';
                // Also update the AltBody for plain text email clients
                $mail->AltBody = 'Dear ' . htmlspecialchars($fullName) . ",\n\n"
                               . 'Thank you for registering with Unitrade. To activate your account, please use the verification code below:' . "\n\n"
                               . 'Verification Code: ' . htmlspecialchars($verificationCode) . "\n\n"
                               . 'Alternatively, you can click the link below to verify your account: ' . htmlspecialchars($verificationLink) . "\n\n"
                               . 'If you did not register for an account, please ignore this email.' . "\n\n"
                               . 'Regards,\nThe Unitrade Team';


                if (!$mail->send()) {
                    error_log("Verification email could not be sent. Mailer Error: {$mail->ErrorInfo}");
                }
            } catch (Exception $e) {
                error_log("Verification email could not be sent. Exception: {$e->getMessage()}");
            }
            // --- End Email Sending ---

            sendJsonResponse(true, 'Registration successful! Please check your email to verify your account.');
        } else {
            sendJsonResponse(false, 'Registration failed: ' . $stmt->error);
        }

        $stmt->close();
    } else {
        sendJsonResponse(false, 'Invalid request method. Only POST requests are allowed.');
    }

} catch (Throwable $e) {
    // Catch any unexpected PHP errors/exceptions
    sendJsonResponse(false, 'An unexpected server error occurred: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()]);
} finally {
    // Close the database connection if it was opened
    if (isset($link) && is_object($link) && method_exists($link, 'close')) {
        $link->close();
    }
}
?>
