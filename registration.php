<?php
// registration.php
session_start(); // Start the session at the very beginning of the page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unitrade - User Registration</title>
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

        /* Nav Bar Styling - Adapted for the "search bar" look (remains same) */
        .circular-nav {
            width: 90%; /* Take up most of the width */
            max-width: 1000px; /* <-- CHANGED: Increased max-width here */
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

        /* Registration Form Styling - Adjusted for new color scheme */
        .wrapper {
            background-color: #2c2c2c; /* Dark background, same as nav bar */
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4); /* Stronger shadow */
            border: 2px solid #4a90e2; /* Blue border, same as nav bar */
            width: 100%;
            max-width: 500px; /* Max width for the form */
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
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .input-field {
            position: relative;
            flex: 1;
            min-width: 200px;
        }

        .input-field input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            background-color: #3a3a3a; /* Darker input background */
            border: 1px solid #555; /* Slightly lighter border for inputs */
            border-radius: 8px;
            font-size: 1em;
            outline: none;
            color: #f0f0f0; /* Light text color for input */
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
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

        /* File Upload Label Styling */
        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 12px 15px;
            background-color: #3a3a3a; /* Darker background */
            border: 1px solid #555; /* Match input border */
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: border-color 0.3s ease, background-color 0.3s ease;
            box-sizing: border-box;
        }

        .file-upload-label:hover {
            border-color: #4a90e2; /* Blue border on hover */
            background-color: #454545; /* Slightly lighter dark on hover */
        }

        .file-upload-text {
            color: #f0f0f0; /* Light text for upload text */
            flex-grow: 1;
        }

        .file-upload-label input[type="file"] {
            display: none; /* Hide the default file input button */
        }

        label {
            font-size: 0.9em;
            color: #f0f0f0; /* Light text for checkbox label */
            display: flex; /* Aligns checkbox and text */
            align-items: center;
            margin-bottom: 25px;
            cursor: pointer;
        }

        label input[type="checkbox"] {
            margin-right: 8px;
            accent-color: #4a90e2; /* Blue checkbox */
            transform: scale(1.1); /* Slightly larger checkbox */
        }

        .btn {
            width: 100%;
            padding: 15px;
            background-color: #4a90e2; /* Blue register button */
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

        .links {
            text-align: center;
            margin-top: 25px;
            font-size: 0.95em;
        }

        .links a {
            color: #4a90e2; /* Blue for the login link */
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .links a:hover {
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
                flex-direction: column;
                gap: 15px;
            }

            .input-field {
                min-width: unset;
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

        /* Styling for the password visibility toggle (i1) */
        /* It's highly recommended to use a Boxicon like 'bx bx-show' and 'bx bx-hide' */
        i1 {
            cursor: pointer;
            color: #bbb; /* Lighter color to match icons */
            position: absolute;
            right: 15px; /* Adjust based on your icon placement */
            top: 50%;
            transform: translateY(-50%);
            font-style: normal; /* To prevent italicizing */
            font-size: 1.2em;
            /* This will display the emoji if your JS doesn't add a Boxicon class */
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
        <form action="signup.php" method="post" enctype="multipart/form-data" id="registrationForm">
            <h2>User Registration</h2>
            <div class="error-text" id="error-text" style="display:none;"></div>
            <div class="success-text" id="success-text" style="display:none;"></div> <!-- Added success message div -->

            <div class="input-box">
                <div class="input-field">
                    <input type="text" name="Full-Name" placeholder="Full-Name" required>
                    <i class='bx bx-user'></i>
                </div>
                <div class="input-field">
                    <input type="text" name="Student_Number" placeholder="Student Number" required> <!-- Changed name to Student_Number -->
                    <i class='bx bx-user'></i>
                </div>
            </div>
            <div class="input-box">
                <div class="input-field">
                    <input type="email" name="email" placeholder="Email" required maxlength="40">
                    <i class='bx bx-envelope'></i>
                </div>
                <div class="input-field">
                    <input type="tel" name="phone" placeholder="Number" required maxlength="10">
                    <i class='bx bx-phone'></i>
                </div>
            </div>
            <div class="input-box">
                <div class="input-field">
                    <input type="password" name="password" placeholder="Password" required id="password"> <!-- Removed maxlength -->
                    <i1 onclick="togglePasswordVisibility('password', this)">👁️</i1>
                </div>
                <div class="input-field">
                    <input type="password" name="cpassword" placeholder="Confirm Password" required id="cpassword"> <!-- Removed maxlength -->
                    <i1 onclick="togglePasswordVisibility('cpassword', this)">👁️</i1>
                </div>
            </div>
            <div class="input-box">
                <div class="input-field">
                    <label for="profile_pic" class="file-upload-label">
                        <span class="file-upload-text" id="file-upload-name">Upload Profile Picture (Optional)</span>
                        <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
                        <i class='bx bx-image'></i>
                    </label>
                </div>
            </div>
            <label><input type="checkbox" required>I hereby declare that the above information provided is true and correct</label>
            <button type="submit" class="btn">Register</button>
            <h3 class="links"><br>
                <a href="login.php">Login?</a> <!-- Changed to .php -->
            </h3>
        </form>
    </div>

    <!-- JavaScript for password toggle and AJAX form submission -->
    <script>
        function togglePasswordVisibility(id, element) {
            const input = document.getElementById(id);
            if (input.type === "password") {
                input.type = "text";
                element.textContent = "�"; // Or change to 'bx-hide' icon
            } else {
                input.type = "password";
                element.textContent = "👁️"; // Or change to 'bx-show' icon
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const registrationForm = document.getElementById('registrationForm');
            const errorTextDiv = document.getElementById('error-text');
            const successTextDiv = document.getElementById('success-text'); // Get the success message div
            const profilePicInput = document.getElementById('profile_pic');
            const fileUploadNameSpan = document.getElementById('file-upload-name');

            // Function to display messages (reused from other forms)
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

            // Update file upload label text when a file is selected
            if (profilePicInput) {
                profilePicInput.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        fileUploadNameSpan.textContent = this.files[0].name;
                    } else {
                        fileUploadNameSpan.textContent = 'Upload Profile Picture (Optional)';
                    }
                });
            }

            // Handle form submission via AJAX
            if (registrationForm) {
                registrationForm.addEventListener('submit', async (event) => {
                    event.preventDefault(); // Prevent default HTML form submission

                    displayMessage('', 'none'); // Clear previous messages

                    const registerButton = registrationForm.querySelector('.btn');
                    const originalButtonText = registerButton.textContent;
                    registerButton.textContent = 'Registering...';
                    registerButton.disabled = true; // Disable button during submission

                    try {
                        const formData = new FormData(registrationForm);

                        const response = await fetch('signup.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json(); // Assuming signup.php returns JSON

                        if (result.success) {
                            displayMessage(result.message, 'success');
                            // Optional: Clear form fields on successful registration
                            registrationForm.reset(); // Reset the form fields
                            fileUploadNameSpan.textContent = 'Upload Profile Picture (Optional)'; // Reset file upload text
                            setTimeout(() => {
                                // Redirect to verify.php with email for seamless verification flow
                                window.location.href = 'verify-page.php?email=' + encodeURIComponent(formData.get('email'));
                            }, 2000); // Give user time to read success message
                        } else {
                            displayMessage(result.message, 'error');
                        }

                    } catch (error) {
                        console.error('Error during registration:', error);
                        displayMessage('An unexpected error occurred. Please try again.', 'error');
                    } finally {
                        // Re-enable button and restore text
                        registerButton.textContent = originalButtonText;
                        registerButton.disabled = false;
                    }
                });
            }
        });
    </script>
</body>
</html>
