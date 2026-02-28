document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabBtns = document.querySelectorAll('.tab-btn');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons and tabs
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked button and corresponding tab
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab') + '-tab';
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Avatar upload functionality
    const avatarUpload = document.getElementById('avatar-upload');
    const avatarEditBtn = document.querySelector('.avatar-edit-btn');
    const avatarPreview = document.getElementById('avatar-preview');
    
    avatarEditBtn.addEventListener('click', function() {
        avatarUpload.click();
    });
    
    avatarUpload.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                avatarPreview.innerHTML = '';
                avatarPreview.style.backgroundImage = `url(${event.target.result})`;
                avatarPreview.style.backgroundSize = 'cover';
                avatarPreview.style.backgroundPosition = 'center';
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Password strength checker
    const newPassword = document.getElementById('new-password');
    if (newPassword) {
        newPassword.addEventListener('input', function() {
            const strengthMeter = document.querySelector('.strength-meter');
            const strengthText = document.querySelector('.strength-text');
            const password = this.value;
            
            // Reset
            strengthMeter.querySelectorAll('.strength-bar').forEach(bar => {
                bar.className = 'strength-bar';
            });
            
            if (password.length === 0) {
                strengthText.textContent = '';
                return;
            }
            
            // Very basic strength check (in real app use proper validation)
            if (password.length < 6) {
                strengthMeter.querySelector('.strength-bar:nth-child(1)').classList.add('weak');
                strengthText.textContent = 'Weak';
                strengthText.style.color = 'var(--danger)';
            } else if (password.length < 10) {
                strengthMeter.querySelectorAll('.strength-bar:nth-child(-n+2)').forEach(bar => {
                    bar.classList.add('medium');
                });
                strengthText.textContent = 'Medium';
                strengthText.style.color = 'var(--warning)';
            } else {
                strengthMeter.querySelectorAll('.strength-bar').forEach(bar => {
                    bar.classList.add('strong');
                });
                strengthText.textContent = 'Strong';
                strengthText.style.color = 'var(--success)';
            }
        });
    }
    
    // Form submission handling
    const forms = document.querySelectorAll('.settings-form');
    forms.forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('.btn-primary');
            
            try {
                // Show loading state
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                // Simulate API call delay
                await new Promise(resolve => setTimeout(resolve, 1500));
                
                if (form.parentElement.id === 'security-tab') {
                    const currentPass = document.getElementById('current-password').value;
                    const newPass = document.getElementById('new-password').value;
                    const confirmPass = document.getElementById('confirm-password').value;
                    
                    if (newPass !== confirmPass) {
                        throw new Error('New passwords do not match!');
                    }
                    
                    // Here you would typically make an API call to change the password
                    console.log('Password change requested');
                    showToast('Password changed successfully!', 'success');
                } else {
                    // Handle account info changes
                    console.log('Account info updated');
                    showToast('Profile updated successfully!', 'success');
                }
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        });
    });

    // Toast notification function
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 3000);
}

});