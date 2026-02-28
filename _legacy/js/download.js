document.addEventListener('DOMContentLoaded', function() {
    const downloadModal = document.getElementById('downloadModal');
    const progressBar = document.querySelector('.progress');
    const progressText = document.querySelector('.download-progress-text');
    const cancelBtn = document.querySelector('.cancel-download');
    
    let downloadInProgress = false;
    let currentProductId = null;
    let progressInterval = null;
    
    // Handle download button clicks
    document.querySelectorAll('.download-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (downloadInProgress) return;
            
            currentProductId = this.getAttribute('data-product-id');
            startDownloadProcess();
        });
    });
    
    // Start the download process
    function startDownloadProcess() {
        downloadInProgress = true;
        downloadModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Reset progress
        progressBar.style.width = '0%';
        progressText.textContent = 'Preparing download...';
        
        // Simulate download progress
        let progress = 0;
        progressInterval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 100) progress = 100;
            
            progressBar.style.width = progress + '%';
            
            // Update status text
            if (progress < 30) {
                progressText.textContent = "Connecting to server...";
            } else if (progress < 60) {
                progressText.textContent = "Preparing files...";
            } else if (progress < 90) {
                progressText.textContent = "Downloading content...";
            } else {
                progressText.textContent = "Finalizing download...";
            }
            
            // When complete
            if (progress === 100) {
                clearInterval(progressInterval);
                setTimeout(() => {
                    completeDownload();
                }, 800);
            }
        }, 300);
    }
    
    // Complete the download
function completeDownload() {
    // Make an AJAX request to get the secure download URL
    fetch(`download.php?id=${currentProductId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success === false) {
            showErrorModal(data.error || 'Download failed');
        } else if (data.download_url) {
            // Create a hidden iframe to trigger the download
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = data.download_url;
            document.body.appendChild(iframe);
            
            // Remove iframe after some time
            setTimeout(() => {
                iframe.remove();
            }, 10000);
        }
    })
    .catch(error => {
        showErrorModal('Download failed: ' + error.message);
    })
    .finally(() => {
        downloadModal.style.display = 'none';
        document.body.style.overflow = 'auto';
        downloadInProgress = false;
    });
}

// Add this new function to show error modal
function showErrorModal(message) {
    const errorModal = document.createElement('div');
    errorModal.className = 'error-modal';
    errorModal.innerHTML = `
        <div class="error-modal-content">
            <div class="error-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h3 class="error-title">DOWNLOAD ERROR</h3>
            <p class="error-message">${message}</p>
            <button class="error-close-btn">OK</button>
        </div>
    `;
    
    document.body.appendChild(errorModal);
    
    // Close modal when clicking OK or outside
    errorModal.querySelector('.error-close-btn').addEventListener('click', () => {
        errorModal.remove();
    });
    
    errorModal.addEventListener('click', (e) => {
        if (e.target === errorModal) {
            errorModal.remove();
        }
    });
}
    
    // Handle cancel button
    cancelBtn.addEventListener('click', function() {
        if (downloadInProgress) {
            clearInterval(progressInterval);
            downloadModal.style.display = 'none';
            document.body.style.overflow = 'auto';
            downloadInProgress = false;
        }
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === downloadModal && downloadInProgress) {
            clearInterval(progressInterval);
            downloadModal.style.display = 'none';
            document.body.style.overflow = 'auto';
            downloadInProgress = false;
        }
    });
    
    // Add hover animations to cards
    document.querySelectorAll('.gaming-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.animation = 'cardFloat 3s ease-in-out infinite';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.animation = 'none';
        });
    });
});