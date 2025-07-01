<?php
// logout.php

// Start the session to access session variables
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session.
// This will delete the session file on the server.
session_destroy();

// Redirect to the login page.
// Ensure no output is sent before this header, which is handled by ob_start() if used.
header("Location: login.php");
exit(); // Always exit after a header redirect to prevent further script execution

// Note: Output buffering (ob_start() and ob_end_flush()) is not strictly necessary
// for a simple logout script that immediately redirects, but it's good practice
// if there might be any preceding output or included files that could produce output.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out...</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #202020;
            color: #f0f0f0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
        }
        .message-container {
            text-align: center;
            background-color: #2c2c2c;
            padding: 30px 50px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            border: 2px solid #4a90e2;
        }
        h2 {
            color: #f0f0f0;
            margin-bottom: 20px;
        }
        p {
            color: #bbb;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="message-container">
        <h2>Logging you out...</h2>
        <p>You will be redirected to the login page shortly.</p>
    </div>
</body>
</html>
