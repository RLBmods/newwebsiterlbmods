document.addEventListener('DOMContentLoaded', function() {
    // Section navigation
    const navItems = document.querySelectorAll('.nav-item');
    const contentSections = document.querySelectorAll('.settings-content-box');
    
    function showSection(sectionId) {
        contentSections.forEach(section => {
            section.style.display = 'none';
        });
        
        const sectionToShow = document.getElementById(`${sectionId}-section`);
        if (sectionToShow) {
            sectionToShow.style.display = 'block';
        }
    }
    
    // Set initial active section
    const initialActive = document.querySelector('.nav-item.active');
    if (initialActive) {
        const sectionId = initialActive.getAttribute('data-section');
        showSection(sectionId);
    }
    
    // Add click event listeners to nav items
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            navItems.forEach(navItem => {
                navItem.classList.remove('active');
            });
            
            this.classList.add('active');
            const sectionId = this.getAttribute('data-section');
            showSection(sectionId);
        });
    });
    
    // File upload functionality
    const uploadModal = document.getElementById('upload-modal');
    const fileInput = document.getElementById('file-input');
    let currentUploadType = '';
    
    // Handle logo upload button
    if (document.getElementById('upload-logo-btn')) {
        document.getElementById('upload-logo-btn').addEventListener('click', function() {
            currentUploadType = 'logo';
            fileInput.accept = 'image/png,image/jpeg';
            uploadModal.style.display = 'block';
        });
    }
    
    // Handle favicon upload button
    if (document.getElementById('upload-favicon-btn')) {
        document.getElementById('upload-favicon-btn').addEventListener('click', function() {
            currentUploadType = 'favicon';
            fileInput.accept = 'image/x-icon,image/png';
            uploadModal.style.display = 'block';
        });
    }
    
    // Handle remove logo button
    if (document.getElementById('remove-logo-btn')) {
        document.getElementById('remove-logo-btn').addEventListener('click', function() {
            document.getElementById('logo-input').value = '';
            document.querySelector('.logo-preview img').src = '/images/logo-placeholder.png';
        });
    }
    
    // Handle remove favicon button
    if (document.getElementById('remove-favicon-btn')) {
        document.getElementById('remove-favicon-btn').addEventListener('click', function() {
            document.getElementById('favicon-input').value = '';
            document.querySelector('.logo-preview img').src = '/images/favicon-placeholder.ico';
        });
    }
    
    // Close modal
    if (document.querySelector('.modal-close')) {
        document.querySelector('.modal-close').addEventListener('click', function() {
            uploadModal.style.display = 'none';
        });
    }
    
    if (document.querySelector('.btn-cancel')) {
        document.querySelector('.btn-cancel').addEventListener('click', function() {
            uploadModal.style.display = 'none';
        });
    }
    
    // Handle file upload
    if (document.getElementById('upload-submit')) {
        document.getElementById('upload-submit').addEventListener('click', function() {
            const file = fileInput.files[0];
            if (!file) {
                alert('Please select a file first');
                return;
            }
            
            // Here you would typically upload the file to the server
            // For now we'll just simulate a successful upload
            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', currentUploadType);
            
            // Simulate AJAX upload
            setTimeout(() => {
                const objectURL = URL.createObjectURL(file);
                
                if (currentUploadType === 'logo') {
                    document.querySelector('.logo-preview img').src = objectURL;
                    document.getElementById('logo-input').value = objectURL;
                } else {
                    document.querySelector('.favicon-preview img').src = objectURL;
                    document.getElementById('favicon-input').value = objectURL;
                }
                
                uploadModal.style.display = 'none';
                fileInput.value = '';
                
                // Show success message
                alert('File uploaded successfully!');
            }, 1000);
        });
    }
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
    
    // Color picker functionality
    document.querySelectorAll('.color-picker-btn').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            input.type = 'color';
            input.click();
            
            // Change back to text after picking a color
            setTimeout(() => {
                input.type = 'text';
            }, 100);
        });
    });
    
    // Handle form submissions
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
            
            // Simulate AJAX submission
            setTimeout(() => {
                // In a real implementation, this would be an AJAX call
                alert('Settings saved successfully!');
                
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 1500);
        });
    });
});