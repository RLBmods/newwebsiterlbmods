document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const downloadSearch = document.getElementById('downloadSearch');
    const filterBtns = document.querySelectorAll('.filter-btn');
    const exportBtn = document.getElementById('exportBtn');
    const banFromDetailsBtn = document.getElementById('banFromDetailsBtn');
    const exportRange = document.getElementById('exportRange');
    const customDateRange = document.getElementById('customDateRange');
    
    // Modal elements
    const modals = {
        downloadDetails: document.getElementById('downloadDetailsModal'),
        banDownload: document.getElementById('banDownloadModal'),
        export: document.getElementById('exportModal')
    };
    
    // Current selected download
    let selectedDownload = null;
    
    // Show download details modal
    function showDownloadDetails(downloadId) {
        fetch(`../api/hk/downloads/get_download.php?id=${downloadId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const download = data.download;
                    
                    // Populate details
                    document.getElementById('detail-id').textContent = download.key_value;
                    document.getElementById('detail-user').innerHTML = `${download.name}`;
                    document.getElementById('detail-file').textContent = download.product_name;
                    document.getElementById('detail-version').textContent = download.product_version;
                    document.getElementById('detail-ip').textContent = download.ip_address;
                    document.getElementById('detail-agent').textContent = download.user_agent || 'Unknown';
                    document.getElementById('detail-date').textContent = new Date(download.created_at).toLocaleString();
                    document.getElementById('detail-count').textContent = download.download_count;
                    
                    // Status
                    const status = download.status === 'banned' ? 'banned' : 
                                  download.error_count > 0 ? 'flagged' : 'valid';
                    document.getElementById('detail-status').innerHTML = `
                        <span class="status-badge ${status}">
                            ${status.charAt(0).toUpperCase() + status.slice(1)}
                        </span>
                    `;
                    
                // Errors
                const errorsElement = document.getElementById('detail-errors');
                if (download.error_count > 0) {
                    errorsElement.innerHTML = `
                        <a href="#" class="view-errors-link" data-id="${downloadId}">
                            ${download.error_count} errors (click to view)
                        </a>
                    `;
                    
                    // Add click handler for viewing errors
                    errorsElement.querySelector('.view-errors-link').addEventListener('click', (e) => {
                        e.preventDefault();
                        showErrorDetails(downloadId);
                    });
                } else {
                    errorsElement.textContent = 'No errors';
                }
                
                // Store the download ID
                selectedDownload = downloadId;
                
                // Show modal
                openModal('downloadDetails');
            } else {
                showToast(data.message || 'Failed to load download details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error loading download details', 'error');
        });
}

// Show error details
// Show error details
function showErrorDetails(downloadId) {
    fetch(`../api/hk/downloads/get_download_errors.php?id=${downloadId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.errors.length > 0) {
                const errorList = document.getElementById('errorList');
                errorList.innerHTML = '';
                
                data.errors.forEach(error => {
                    try {
                        // Parse the JSON string in error_details
                        const details = JSON.parse(error.error_details);
                        const reason = details.reason || 'No reason provided';
                        
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'error-item';
                        errorDiv.innerHTML = `
                            <div class="error-reason"><strong>Reason:</strong> ${reason}</div>
                            <div class="error-date"><strong>Date:</strong> ${new Date(error.created_at).toLocaleString()}</div>
                            <hr>
                        `;
                        errorList.appendChild(errorDiv);
                    } catch (e) {
                        console.error('Error parsing error details:', e);
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'error-item';
                        errorDiv.innerHTML = `
                            <div class="error-reason"><strong>Error:</strong> Could not parse error details</div>
                            <hr>
                        `;
                        errorList.appendChild(errorDiv);
                    }
                });
                
                document.getElementById('errorDetails').style.display = 'block';
            } else {
                document.getElementById('errorList').innerHTML = '<p>No error details found</p>';
                document.getElementById('errorDetails').style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('errorList').innerHTML = '<p>Error loading error details</p>';
            document.getElementById('errorDetails').style.display = 'block';
        });
}

    // Show ban download modal
    function showBanDownloadModal(downloadId) {
        const row = document.querySelector(`tr[data-id="${downloadId}"]`);
        if (!row) return;
        
        // Populate form
        document.getElementById('banDownloadId').value = row.querySelector('td:nth-child(5)').textContent;
        document.getElementById('banDownloadUser').value = row.querySelector('.user-link').textContent;
        document.getElementById('banDownloadFile').value = row.querySelector('td:nth-child(3)').textContent;
        
        // Store the download ID
        selectedDownload = downloadId;
        
        // Show modal
        openModal('banDownload');
    }
    
    // Ban a download
    function banDownload(reason, action) {
        if (!selectedDownload) return;
        
        fetch('/hk/api/ban_download.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                download_id: selectedDownload,
                reason: reason,
                action: action
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the UI
                const row = document.querySelector(`tr[data-id="${selectedDownload}"]`);
                if (row) {
                    row.classList.remove('flagged');
                    row.classList.add('banned');
                    row.querySelector('.status-badge').className = 'status-badge banned';
                    row.querySelector('.status-badge').textContent = 'Banned';
                    
                    const avatar = row.querySelector('.user-avatar');
                    avatar.classList.add('banned');
                    avatar.innerHTML = '<i class="fas fa-user-slash"></i>';
                    
                    const actionsCell = row.querySelector('td:nth-child(10)');
                    actionsCell.innerHTML = `
                        <button class="btn-admin btn-view" title="View Details" data-id="${selectedDownload}">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-admin btn-unban" title="Unban Download" data-id="${selectedDownload}">
                            <i class="fas fa-undo"></i>
                        </button>
                    `;
                    
                    // Add event listeners to the new buttons
                    actionsCell.querySelector('.btn-view').addEventListener('click', () => {
                        showDownloadDetails(selectedDownload);
                    });
                    actionsCell.querySelector('.btn-unban').addEventListener('click', () => {
                        unbanDownload(selectedDownload);
                    });
                }
                
                closeModal('banDownload');
                showToast('Download banned successfully');
            } else {
                showToast('Error: ' + (data.message || 'Failed to ban download'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error while banning download', 'error');
        });
    }
    
    // Unban a download
    function unbanDownload(downloadId) {
        if (confirm('Are you sure you want to unban this download?')) {
            fetch('/hk/api/unban_download.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    download_id: downloadId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const row = document.querySelector(`tr[data-id="${downloadId}"]`);
                    if (row) {
                        row.classList.remove('banned');
                        row.querySelector('.status-badge').className = 'status-badge valid';
                        row.querySelector('.status-badge').textContent = 'Valid';
                        
                        const avatar = row.querySelector('.user-avatar');
                        avatar.classList.remove('banned');
                        avatar.innerHTML = '<i class="fas fa-user"></i>';
                        
                        const actionsCell = row.querySelector('td:nth-child(10)');
                        actionsCell.innerHTML = `
                            <button class="btn-admin btn-view" title="View Details" data-id="${downloadId}">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-admin btn-ban" title="Ban Download" data-id="${downloadId}">
                                <i class="fas fa-ban"></i>
                            </button>
                        `;
                        
                        // Add event listeners to new buttons
                        actionsCell.querySelector('.btn-view').addEventListener('click', () => {
                            showDownloadDetails(downloadId);
                        });
                        actionsCell.querySelector('.btn-ban').addEventListener('click', () => {
                            showBanDownloadModal(downloadId);
                        });
                    }
                    showToast('Download unbanned successfully');
                } else {
                    showToast('Error: ' + (data.message || 'Failed to unban download'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error while unbanning download', 'error');
            });
        }
    }
    
    // Open modal
    function openModal(modalName) {
        modals[modalName].style.display = 'flex';
    }
    
    // Close modal
// Close modal
function closeModal(modalName) {
    if (modals[modalName]) {
        modals[modalName].style.display = 'none';
    }
}

// Setup event listeners for close buttons
document.querySelectorAll('.modal-close, .btn-cancel').forEach(btn => {
    btn.addEventListener('click', function() {
        const modal = this.closest('.modal-overlay');
        if (modal) {
            modal.style.display = 'none';
        }
    });
});

// Close modal when clicking outside
Object.values(modals).forEach(modal => {
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
});
    
    // Show toast notification
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    }
    
    // Setup event listeners
    function setupEventListeners() {
        // Export range change
        exportRange.addEventListener('change', function() {
            customDateRange.style.display = this.value === 'custom' ? 'block' : 'none';
        });
        
        // Modal close events
        document.querySelectorAll('.modal-close, .btn-cancel').forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal-overlay');
                closeModal(modal.id.replace('Modal', '').toLowerCase());
            });
        });
        
        // Close modal when clicking outside
        Object.values(modals).forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal(modal.id.replace('Modal', '').toLowerCase());
                }
            });
        });
        
        // Ban download form submission
        document.getElementById('banDownloadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const reason = document.getElementById('banDownloadReason').value;
            const action = document.getElementById('banDownloadAction').value;
            
            banDownload(reason, action);
            this.reset();
        });
        
        
        // Add event listeners to all action buttons
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-view')) {
                const downloadId = e.target.closest('.btn-view').getAttribute('data-id');
                showDownloadDetails(downloadId);
            }
            
            if (e.target.closest('.btn-ban')) {
                const downloadId = e.target.closest('.btn-ban').getAttribute('data-id');
                showBanDownloadModal(downloadId);
            }
            
            if (e.target.closest('.btn-unban')) {
                const downloadId = e.target.closest('.btn-unban').getAttribute('data-id');
                unbanDownload(downloadId);
            }
        });
    }
    
// Filter button clicks
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const filter = this.getAttribute('data-filter');
        const url = new URL(window.location.href);
        url.searchParams.set('filter', filter);
        url.searchParams.delete('page'); // Reset to page 1 when changing filter
        window.location.href = url.toString();
    });
});

    // Initialize the download history
    function initDownloadHistory() {
        setupEventListeners();
    }
    
    // Start the application
    initDownloadHistory();
});