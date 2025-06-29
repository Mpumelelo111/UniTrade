<?php
// verify.php

// 1. Database Connection Configuration
// IMPORTANT: Replace with your actual database credentials
$servername = "localhost";
$username = "your_db_user"; // e.g., root
$password = "your_db_password"; // e.g., root
$dbname = "unitrade_db"; // The name of your database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Set header for JSON response
header('Content-Type: application/json');

// 2. Process Code Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string(trim($_POST['email']));
    $verificationCode = $conn->real_escape_string(trim($_POST['verification_code']));

    // Server-side validation
    if (empty($email) || empty($verificationCode)) {
        echo json_encode(['success' => false, 'message' => 'Email and verification code are required.']);
        exit();
    }

    // You might add stricter validation for the code format if you have one (e.g., length, alphanumeric)
    // if (!preg_match('/^[a-zA-Z0-9]{6}$/', $verificationCode)) {
    //     echo json_encode(['success' => false, 'message' => 'Invalid verification code format.']);
    //     exit();
    // }

    // 3. Check database for matching email and code
    // IMPORTANT: In a real scenario, you should also check `verification_code_expires_at` if implemented.
    $stmt = $conn->prepare("SELECT student_id, is_verified FROM Students WHERE email = ? AND verification_code = ? LIMIT 1");
    $stmt->bind_param("ss", $email, $verificationCode);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($studentId, $isVerified);
    $stmt->fetch();

    if ($stmt->num_rows > 0) {
        if ($isVerified) {
            echo json_encode(['success' => false, 'message' => 'Your account is already verified. Please log in.']);
        } else {
            // 4. Update user's status to verified
            $updateStmt = $conn->prepare("UPDATE Students SET is_verified = 1, verification_code = NULL WHERE student_id = ?");
            $updateStmt->bind_param("i", $studentId);

            if ($updateStmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Account successfully verified! You can now log in.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Verification failed. Please try again or contact support.']);
            }
            $updateStmt->close();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid verification code or email. Please check and try again.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>
