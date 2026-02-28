document.addEventListener('DOMContentLoaded', function() {
    // Follow Button Toggle
    const followBtn = document.querySelector('.btn-follow');
    if (followBtn) {
        followBtn.addEventListener('click', function() {
            const isFollowing = this.classList.contains('following');
            
            if (isFollowing) {
                this.classList.remove('following');
                this.innerHTML = '<i class="fas fa-user-plus"></i> Follow';
                this.style.backgroundColor = 'var(--primary)';
                updateFollowerCount(-1);
            } else {
                this.classList.add('following');
                this.innerHTML = '<i class="fas fa-user-check"></i> Following';
                this.style.backgroundColor = 'var(--success)';
                updateFollowerCount(1);
            }
        });
    }

    // Update follower count
    function updateFollowerCount(change) {
        const followerCountElement = document.querySelector('.stat-item:nth-child(2) .stat-number');
        if (followerCountElement) {
            let currentCount = parseInt(followerCountElement.textContent.replace(/,/g, ''));
            currentCount += change;
            followerCountElement.textContent = currentCount.toLocaleString();
        }
    }

    // Post Like Buttons
    document.querySelectorAll('.post-actions button:first-child').forEach(btn => {
        btn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            const isLiked = icon.classList.contains('fas');
            
            if (isLiked) {
                icon.classList.remove('fas');
                icon.classList.add('far');
                updateLikeCount(this, -1);
            } else {
                icon.classList.remove('far');
                icon.classList.add('fas');
                icon.style.color = 'var(--danger)';
                updateLikeCount(this, 1);
            }
        });
    });

    // Update like count
    function updateLikeCount(button, change) {
        const likeText = button.innerHTML;
        const currentCount = parseInt(likeText.match(/\d+/)[0]);
        button.innerHTML = likeText.replace(/\d+/, currentCount + change);
    }

    // Photo Gallery Lightbox
    const photoItems = document.querySelectorAll('.photo-item');
    photoItems.forEach(photo => {
        photo.addEventListener('click', function() {
            const imgUrl = this.style.backgroundImage.slice(5, -2);
            openLightbox(imgUrl);
        });
    });

    // Lightbox functionality
    function openLightbox(imgUrl) {
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.innerHTML = `
            <div class="lightbox-content">
                <img src="${imgUrl}" alt="Enlarged photo">
                <button class="close-lightbox">&times;</button>
            </div>
        `;
        
        document.body.appendChild(lightbox);
        
        lightbox.querySelector('.close-lightbox').addEventListener('click', function() {
            lightbox.remove();
        });
        
        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) {
                lightbox.remove();
            }
        });
    }

    // Friend item hover effect
    const friendItems = document.querySelectorAll('.friend-item');
    friendItems.forEach(friend => {
        friend.addEventListener('mouseenter', function() {
            const avatar = this.querySelector('.friend-avatar');
            avatar.style.transform = 'scale(1.05)';
        });
        
        friend.addEventListener('mouseleave', function() {
            const avatar = this.querySelector('.friend-avatar');
            avatar.style.transform = 'scale(1)';
        });
    });

    // Profile navigation smooth scroll
    const navLinks = document.querySelectorAll('.profile-nav a');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all links
            navLinks.forEach(navLink => {
                navLink.parentNode.classList.remove('active');
            });
            
            // Add active class to clicked link
            this.parentNode.classList.add('active');
            
            // In a real app, you would load content here
            console.log(`Loading content for: ${this.textContent.trim()}`);
        });
    });

    // Search functionality
    const searchInput = document.querySelector('.search-container input');
    const searchBtn = document.querySelector('.search-container button');
    
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            performSearch();
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    }
    
    function performSearch() {
        const query = searchInput.value.trim();
        if (query) {
            alert(`Searching for: ${query}`);
            // In a real app, you would make an API call here
        }
    }

    // Responsive adjustments
    function handleResponsive() {
        const profileContent = document.querySelector('.profile-content');
        if (!profileContent) return;
        
        if (window.innerWidth < 768) {
            // Mobile adjustments
        } else if (window.innerWidth < 1200) {
            // Tablet adjustments
        } else {
            // Desktop adjustments
        }
    }
    
    window.addEventListener('resize', handleResponsive);
    handleResponsive();
});

// Add lightbox styles dynamically
const lightboxStyles = document.createElement('style');
lightboxStyles.textContent = `
    .lightbox {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        opacity: 0;
        animation: fadeIn 0.3s forwards;
    }
    
    .lightbox-content {
        position: relative;
        max-width: 90%;
        max-height: 90%;
    }
    
    .lightbox img {
        max-height: 80vh;
        max-width: 100%;
        border-radius: var(--border-radius);
    }
    
    .close-lightbox {
        position: absolute;
        top: -40px;
        right: 0;
        background: none;
        border: none;
        color: white;
        font-size: 30px;
        cursor: pointer;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
`;
document.head.appendChild(lightboxStyles);