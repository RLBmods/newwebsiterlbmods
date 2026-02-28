document.addEventListener('DOMContentLoaded', function() {
    console.log("Profile JS loaded successfully");
    
    // Initialize all components
    initTooltips();
    initLazyLoading();
    initAwardTooltips();
    
    // Handle reply functionality
    setupReplySystem();
    
    // Handle form submissions
    setupFormValidation();
    
    // Handle file upload previews
    setupFileUploadPreviews();
    
    // Initialize profile tabs if they exist
    if (document.querySelectorAll('.profile-tab').length > 0) {
        initProfileTabs();
    }
    
    // Handle profile not found modal
    setupProfileNotFoundModal();
});

// ==================== REPLY SYSTEM ====================
function setupReplySystem() {
    // Event delegation for reply buttons
    document.addEventListener('click', function(e) {
        // Handle reply button clicks
        if (e.target.closest('.reply-button')) {
            handleReplyButtonClick(e);
        }
        
        // Handle cancel reply buttons
        if (e.target.closest('.cancel-reply')) {
            handleCancelReplyClick(e);
        }
    });
    
    // Toggle replies visibility
    document.querySelectorAll('.reply-count').forEach(count => {
        count.addEventListener('click', toggleRepliesVisibility);
    });
}

function handleReplyButtonClick(e) {
    const button = e.target.closest('.reply-button');
    const post = button.closest('.post');
    const replyForm = post.querySelector('.reply-form');
    
    if (!replyForm) {
        console.error("Reply form not found for post:", post);
        return;
    }
    
    // Hide all other reply forms first
    document.querySelectorAll('.reply-form').forEach(form => {
        if (form !== replyForm) {
            form.style.display = 'none';
        }
    });
    
    // Toggle this reply form
    replyForm.style.display = replyForm.style.display === 'none' ? 'block' : 'none';
    
    // Focus textarea when showing
    if (replyForm.style.display === 'block') {
        setTimeout(() => {
            replyForm.querySelector('textarea').focus();
            replyForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 10);
    }
    
    e.preventDefault();
}

function handleCancelReplyClick(e) {
    const cancelBtn = e.target.closest('.cancel-reply');
    const replyForm = cancelBtn.closest('.reply-form');
    
    // Save reply content to session storage if needed
    const postIdInput = replyForm.querySelector('input[name="post_id"]');
    const textarea = replyForm.querySelector('textarea');
    
    if (postIdInput && textarea && textarea.value.trim()) {
        sessionStorage.setItem('reply_' + postIdInput.value, textarea.value);
    }
    
    // Hide the form
    replyForm.style.display = 'none';
    e.preventDefault();
}

function toggleRepliesVisibility(e) {
    const container = e.target.closest('.post').querySelector('.replies-container');
    if (!container) return;
    
    const isHidden = container.style.display === 'none';
    
    container.style.display = isHidden ? 'block' : 'none';
    container.style.opacity = isHidden ? '1' : '0';
    container.style.maxHeight = isHidden ? '1000px' : '0';
}

// ==================== FORM VALIDATION ====================
function setupFormValidation() {
    // Post form validation
    const postForm = document.querySelector('.post-form form');
    if (postForm) {
        postForm.addEventListener('submit', function(e) {
            const content = this.querySelector('textarea').value.trim();
            if (!content) {
                e.preventDefault();
                showToast('Please enter some content for your post.', 'error');
                this.querySelector('textarea').focus();
            } else {
                // Add loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';
                }
            }
        });
        
        // Restore saved content if exists
        const savedPost = sessionStorage.getItem('post_content');
        if (savedPost) {
            const textarea = postForm.querySelector('textarea');
            if (textarea) {
                textarea.value = savedPost;
                sessionStorage.removeItem('post_content');
            }
        }
    }
    
    // Reply form validation
    document.addEventListener('submit', function(e) {
        if (e.target.closest('.reply-form form')) {
            const form = e.target.closest('.reply-form form');
            const content = form.querySelector('textarea').value.trim();
            
            if (!content) {
                e.preventDefault();
                showToast('Please enter some content for your reply.', 'error');
                form.querySelector('textarea').focus();
            }
        }
    });
}

// ==================== FILE UPLOADS ====================
function setupFileUploadPreviews() {
    // Avatar upload preview
    const avatarInput = document.querySelector('#avatar-upload-form input[type="file"]');
    if (avatarInput) {
        avatarInput.addEventListener('change', function() {
            validateAndPreviewImage(this, '.avatar-large img', 2);
        });
    }
    
    // Banner upload preview
    const bannerInput = document.querySelector('#banner-upload-form input[type="file"]');
    if (bannerInput) {
        bannerInput.addEventListener('change', function() {
            validateAndPreviewImage(this, '.banner-image', 5);
        });
    }
}

// ==================== MODAL & UPLOAD FORMS ====================
function setupProfileNotFoundModal() {
    const modal = document.getElementById('profile-not-found-modal');
    if (!modal) return;
    
    // Focus search input when modal opens
    const searchInput = modal.querySelector('input[name="u"]');
    if (searchInput) searchInput.focus();
    
    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === modal) modal.style.display = 'none';
    });
}

// Show upload form with overlay
function showUploadForm(type) {
    const form = document.getElementById(`${type}-upload-form`);
    const overlay = document.getElementById('form-overlay');
    
    if (form && overlay) {
        form.style.display = 'block';
        overlay.style.display = 'block';
        
        setTimeout(() => {
            form.style.opacity = '1';
            form.style.transform = 'translate(-50%, -50%) scale(1)';
        }, 10);
    }
}

// Hide upload form with animation
function hideUploadForm(type) {
    const form = document.getElementById(`${type}-upload-form`);
    const overlay = document.getElementById('form-overlay');
    
    if (form) {
        form.style.opacity = '0';
        form.style.transform = 'translate(-50%, -50%) scale(0.9)';
        setTimeout(() => form.style.display = 'none', 300);
    }
    
    if (overlay) overlay.style.display = 'none';
}

// Hide all upload forms
function hideAllUploadForms() {
    document.querySelectorAll('.upload-form').forEach(form => {
        form.style.opacity = '0';
        form.style.transform = 'translate(-50%, -50%) scale(0.9)';
        setTimeout(() => form.style.display = 'none', 300);
    });
    
    const overlay = document.getElementById('form-overlay');
    if (overlay) overlay.style.display = 'none';
}

// ==================== UTILITY FUNCTIONS ====================
function validateAndPreviewImage(input, previewSelector, maxSizeMB) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const maxSize = maxSizeMB * 1024 * 1024;
    
    if (file.size > maxSize) {
        showToast(`File is too large. Maximum size is ${maxSizeMB}MB.`, 'error');
        input.value = '';
        return;
    }
    
    if (!file.type.match('image.*')) {
        showToast('Please select an image file.', 'error');
        input.value = '';
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.querySelector(previewSelector);
        if (preview) preview.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    }, 10);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(el => {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = el.dataset.tooltip;
        document.body.appendChild(tooltip);
        
        el.addEventListener('mouseenter', (e) => {
            const rect = el.getBoundingClientRect();
            tooltip.style.left = `${rect.left + rect.width / 2 - tooltip.offsetWidth / 2}px`;
            tooltip.style.top = `${rect.top - tooltip.offsetHeight - 5}px`;
            tooltip.style.opacity = '1';
        });
        
        el.addEventListener('mouseleave', () => {
            tooltip.style.opacity = '0';
        });
    });
}

function initLazyLoading() {
    const lazyImages = document.querySelectorAll('img.lazy');
    if (!lazyImages.length) return;
    
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        });
        
        lazyImages.forEach(img => observer.observe(img));
    } else {
        // Fallback for older browsers
        lazyImages.forEach(img => {
            img.src = img.dataset.src;
            img.classList.remove('lazy');
        });
    }
}

function initAwardTooltips() {
    document.querySelectorAll('.award-item').forEach(item => {
        const tooltip = document.createElement('div');
        tooltip.className = 'award-tooltip';
        tooltip.textContent = item.dataset.tooltip;
        document.body.appendChild(tooltip);
        
        item.addEventListener('mouseenter', () => {
            const rect = item.getBoundingClientRect();
            tooltip.style.left = `${rect.left + rect.width / 2 - tooltip.offsetWidth / 2}px`;
            tooltip.style.top = `${rect.top - tooltip.offsetHeight - 5}px`;
            tooltip.style.opacity = '1';
        });
        
        item.addEventListener('mouseleave', () => {
            tooltip.style.opacity = '0';
        });
    });
}

function initProfileTabs() {
    const tabs = document.querySelectorAll('.profile-tab');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active classes
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            const contentId = this.dataset.tab;
            const content = document.getElementById(contentId);
            if (content) content.classList.add('active');
            
            // Update URL
            history.pushState(null, null, `#${contentId}`);
        });
    });
    
    // Check hash on load
    const hash = window.location.hash.substring(1);
    if (hash) {
        const tab = document.querySelector(`.profile-tab[data-tab="${hash}"]`);
        if (tab) tab.click();
    }
}