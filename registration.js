document.addEventListener('DOMContentLoaded', () => {
    const registrationForm = document.querySelector('.wrapper form');
    const errorTextDiv = document.getElementById('error-text');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('cpassword');
    const profilePicInput = document.getElementById('profile_pic');
    const fileUploadText = document.querySelector('.file-upload-text');

    // Function to toggle password visibility (already in your HTML, keeping here for completeness)
    window.togglePasswordVisibility = function(id, element) {
        const input = document.getElementById(id);
        if (input.type === "password") {
            input.type = "text";
            element.textContent = "🙈"; // Change to 'bx-hide' icon for better UX
            // You might want to change element.className = 'bx bx-hide'; if you use Boxicons directly
        } else {
            input.type = "password";
            element.textContent = "👁️"; // Change to 'bx-show' icon
            // You might want to change element.className = 'bx bx-show'; if you use Boxicons directly
        }
    };

    // Update file upload label text when a file is selected
    if (profilePicInput && fileUploadText) {
        profilePicInput.addEventListener('change', (event) => {
            if (event.target.files.length > 0) {
                fileUploadText.textContent = event.target.files[0].name;
            } else {
                fileUploadText.textContent = 'Upload Profile Picture (Optional)';
            }
        });
    }

    // Client-side validation before submission
    registrationForm.addEventListener('submit', async (event) => {
        event.preventDefault(); // Prevent default form submission

        errorTextDiv.style.display = 'none';
        errorTextDiv.textContent = ''; // Clear previous errors

        const password = passwordInput.value;
        const cpassword = confirmPasswordInput.value;

        if (password !== cpassword) {
            displayError("Passwords do not match!");
            return;
        }

        // Basic password length validation (already handled by maxlength, but good to double check)
        if (password.length < 8) { // Or whatever minimum length you define
            displayError("Password must be at least 8 characters long.");
            return;
        }

        // Create FormData object to send form data, including file
        const formData = new FormData(registrationForm);

        // Show a loading indicator (optional)
        const registerButton = registrationForm.querySelector('.btn');
        const originalButtonText = registerButton.textContent;
        registerButton.textContent = 'Registering...';
        registerButton.disabled = true;

        try {
            const response = await fetch('signup.php', {
                method: 'POST',
                body: formData // FormData automatically sets content-type to multipart/form-data
            });

            const result = await response.json(); // Assuming PHP returns JSON

            if (result.success) {
                displayError(result.message, 'success'); // Display success message
                registrationForm.reset(); // Clear the form
                fileUploadText.textContent = 'Upload Profile Picture (Optional)'; // Reset file input label
                // Optionally redirect to a verification page or login page
                setTimeout(() => {
                    window.location.href = 'login.html'; // Redirect to login page
                }, 2000);
            } else {
                displayError(result.message); // Display error message from server
            }
        } catch (error) {
            console.error('Error during registration:', error);
            displayError('An unexpected error occurred. Please try again.');
        } finally {
            registerButton.textContent = originalButtonText; // Restore button text
            registerButton.disabled = false; // Re-enable button
        }
    });

    function displayError(message, type = 'error') {
        errorTextDiv.textContent = message;
        if (type === 'success') {
            errorTextDiv.style.color = '#2ecc71'; // Green for success
        } else {
            errorTextDiv.style.color = '#ff6b6b'; // Red for error
        }
        errorTextDiv.style.display = 'block';
    }
});
