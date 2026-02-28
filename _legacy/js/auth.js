document.addEventListener('DOMContentLoaded', function() {
    // Form validation for login form only
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            // Clear previous error states
            document.querySelectorAll('.input-error').forEach(el => {
                el.classList.remove('input-error', 'shake');
            });
            
            document.querySelectorAll('.input-error-message').forEach(el => el.remove());

            // Validate form
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            let isValid = true;

            if (!email.value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                showError(email, 'Please enter a valid email address');
                isValid = false;
            }

            if (!password.value) {
                showError(password, 'Please enter your password');
                isValid = false;
            } else if (password.value.length < 8) {
                showError(password, 'Password must be at least 8 characters');
                isValid = false;
            }

            if (!isValid) e.preventDefault();
        });
    }

    function showError(input, message) {
        if (!input) return;
        
        input.classList.add('input-error', 'shake');
        setTimeout(() => input.classList.remove('shake'), 400);

        const errorMessage = document.createElement('span');
        errorMessage.className = 'input-error-message';
        errorMessage.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        
        // Fixed the optional chaining syntax
        const nextSibling = input.nextElementSibling;
        const existingError = nextSibling && nextSibling.classList.contains('input-error-message');
        
        if (existingError) {
            nextSibling.innerHTML = errorMessage.innerHTML;
        } else {
            input.insertAdjacentElement('afterend', errorMessage);
        }
    }

    // Auto-hide notifications
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => alert.style.display = 'none', 300);
        });
    }, 5000);
});