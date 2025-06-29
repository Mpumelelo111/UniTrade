<?php
// signup.php

// 1. Include the database connection file
// Make sure 'db_connect.php' (or 'config.php') contains the code from your 'db-connection' immersive.
require_once 'database.php'; // Adjust path if necessary, e.g., '../config/db_connect.php'

// Set header for JSON response - crucial for AJAX requests
header('Content-Type: application/json');

// 2. Function to generate a random verification code
/**
 * Generates a cryptographically secure random hexadecimal string.
 * This is used for email verification codes.
 *
 * @param int $length The desired length of the hex string.
 * @return string A random hexadecimal string.
 */
function generateVerificationCode($length = 32) {
    // bin2hex converts binary data to hexadecimal representation.
    // random_bytes generates cryptographically secure pseudo-random bytes.
    // We need $length / 2 bytes because each byte becomes 2 hex characters.
    return bin2hex(random_bytes($length / 2));
}

// 3. Process Form Submission only if it's a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and get input data
    // trim() removes whitespace from the beginning and end of a string.
    // For prepared statements, real_escape_string() is not needed here as bind_param handles it.
    $fullName = trim($_POST['Full-Name'] ?? ''); // Use null coalescing operator to prevent undefined index notice
    $studentNumber = trim($_POST['Student_Number'] ?? ''); // Corrected name from HTML, using null coalescing
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? ''; // Will be hashed
    $cpassword = $_POST['cpassword'] ?? '';

    $profilePicUrl = null; // Default to null for profile picture URL in database

    // Server-side validation
    // Check if any required field is empty
    if (empty($fullName) || empty($studentNumber) || empty($email) || empty($phone) || empty($password) || empty($cpassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit(); // Stop script execution
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit();
    }

    // Check if passwords match
    if ($password !== $cpassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit();
    }

    // Enforce minimum password length
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
        exit();
    }

    // Check if email or student number already exists in the database
    // Using $link as the connection object from db_connect.php
    $stmt = $link->prepare("SELECT student_id FROM Students WHERE email = ? OR student_number = ? LIMIT 1");
    if ($stmt === false) {
        // Handle prepare error
        echo json_encode(['success' => false, 'message' => 'Database prepare failed during existence check: ' . $link->error]);
        exit();
    }
    $stmt->bind_param("ss", $email, $studentNumber); // Bind parameters for security
    $stmt->execute(); // Execute the prepared statement
    $stmt->store_result(); // Store the result so num_rows can be checked

    if ($stmt->num_rows > 0) { // If any rows are returned, email or student number already exists
        echo json_encode(['success' => false, 'message' => 'Email or Student Number already registered.']);
        $stmt->close(); // Close the statement
        exit();
    }
    $stmt->close(); // Close the statement after successful check

    // Hash the password using a strong, default algorithm
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Handle profile picture upload
    // Check if a file was uploaded and if there were no errors
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
        $targetDir = "uploads/profile_pics/"; // Directory to store uploaded profile pictures
        // Create directory if it doesn't exist. Permissions 0755 are recommended (owner rwx, group rx, others rx).
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) { // Check if mkdir failed
                echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
                exit();
            }
        }

        $imageFileType = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION)); // Get file extension
        $allowedExtensions = array("jpg", "jpeg", "png", "gif"); // Define allowed image types

        // Validate file type
        if (in_array($imageFileType, $allowedExtensions)) {
            // Generate a unique filename to prevent conflicts and improve security
            $fileName = uniqid('profile_') . '.' . $imageFileType;
            $targetFilePath = $targetDir . $fileName; // Full path to save the file

            // Move the uploaded file from temporary location to its permanent destination
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetFilePath)) {
                $profilePicUrl = $targetFilePath; // Store the relative path in the database
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload profile picture.']);
                exit();
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid file type for profile picture. Only JPG, JPEG, PNG, GIF are allowed.']);
            exit();
        }
    }

    // Generate the unique verification code for the user
    $verificationCode = generateVerificationCode();

    // Prepare and execute the SQL INSERT statement to register the user
    // Using $link as the connection object
    $stmt = $link->prepare("INSERT INTO Students (full_name, student_number, email, phone_number, password_hash, profile_pic_url, is_verified, verification_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        // Handle prepare error
        echo json_encode(['success' => false, 'message' => 'Database prepare failed during registration: ' . $link->error]);
        exit();
    }
    // 'ssssssis' defines the types of the bound parameters:
    // s = string, i = integer
    $isVerified = 0; // Default: user is not verified upon registration
    $stmt->bind_param("ssssssis", $fullName, $studentNumber, $email, $phone, $passwordHash, $profilePicUrl, $isVerified, $verificationCode);

    if ($stmt->execute()) {
        // --- Placeholder for Sending Verification Email ---
        // IMPORTANT: In a real application, you MUST send a verification email here.
        // This makes sure the email address provided is valid and belongs to the user.
        // The email should contain a link that the user clicks to verify their account.
        $verificationLink = "http://yourdomain.com/verify.php?email=" . urlencode($email) . "&code=" . urlencode($verificationCode);
        
        // Example using PHP's mail() function (requires server configuration):
        // $subject = "Verify Your NWU Marketplace Account";
        // $message = "Dear " . $fullName . ",\n\nThank you for registering with NWU Marketplace. Please click the following link to verify your account:\n\n" . $verificationLink . "\n\nRegards,\nNWU Marketplace Team";
        // $headers = "From: no-reply@yourdomain.com\r\n";
        // $headers .= "Reply-To: no-reply@yourdomain.com\r\n";
        // $headers .= "X-Mailer: PHP/" . phpversion();
        // mail($email, $subject, $message, $headers);

        // For more robust email sending (e.g., with SMTP, HTML emails), consider PHPMailer:
        // require 'path/to/PHPMailer/src/PHPMailer.php';
        // require 'path/to/PHPMailer/src/SMTP.php';
        // require 'path/to/PHPMailer/src/Exception.php';
        // $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        // try {
        //     //Server settings
        //     $mail->isSMTP();
        //     $mail->Host       = 'smtp.example.com'; // Set the SMTP server to send through
        //     $mail->SMTPAuth   = true;
        //     $mail->Username   = 'your_smtp_username';
        //     $mail->Password   = 'your_smtp_password';
        //     $mail->SMTPSecure = PHPMailer\PHPMailer\SMTP::ENCRYPTION_SMTPS; // Enable implicit TLS encryption
        //     $mail->Port       = 465;

        //     //Recipients
        //     $mail->setFrom('no-reply@yourdomain.com', 'NWU Marketplace');
        //     $mail->addAddress($email, $fullName);

        //     // Content
        //     $mail->isHTML(true); // Set email format to HTML
        //     $mail->Subject = $subject;
        //     $mail->Body    = 'Please click this link to verify your account: <a href="' . $verificationLink . '">' . $verificationLink . '</a>';
        //     $mail->AltBody = 'Please click this link to verify your account: ' . $verificationLink;

        //     $mail->send();
        // } catch (Exception $e) {
        //     // Log the mail error, but don't prevent user registration success
        //     error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        // }
        // ----------------------------------------------------

        echo json_encode(['success' => true, 'message' => 'Registration successful! Please check your email to verify your account.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $stmt->error]);
    }

    $stmt->close(); // Close the statement after execution
} else {
    // If the request method is not POST, it's an invalid access
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

// Close the database connection
// Using $link as the connection object
$link->close();
?>
