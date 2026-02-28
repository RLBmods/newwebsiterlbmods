document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const logSearch = document.getElementById('logSearch');
    const filterButtons = document.querySelectorAll('.filter-btn');
    const logTimeRange = document.getElementById('logTimeRange');
    const logDetailsModal = document.getElementById('logDetailsModal');
    const closeModalButtons = document.querySelectorAll('.modal-close, .btn-cancel');
    const logTableBody = document.querySelector('.admin-table tbody');

    // Initialize from URL params
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('range')) {
        logTimeRange.value = urlParams.get('range');
    }

    // Filter button clicks
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            updateUrlParams({
                filter: this.getAttribute('data-filter'),
                page: null // Reset to first page
            });
        });
    });

    // Search functionality
    logSearch.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            updateUrlParams({
                search: this.value.trim() || null,
                page: null // Reset to first page
            });
        }
    });

    // Time range filter
    logTimeRange.addEventListener('change', function() {
        updateUrlParams({
            range: this.value === 'all' ? null : this.value,
            page: null // Reset to first page
        });
    });

    // View log details
    logTableBody.addEventListener('click', function(e) {
        const viewBtn = e.target.closest('.btn-view');
        if (viewBtn) {
            const logId = viewBtn.getAttribute('data-id');
            showLogDetails(logId);
        }
    });

    // Close modal
    closeModalButtons.forEach(btn => {
        btn.addEventListener('click', closeModal);
    });

    // Close modal when clicking outside
    logDetailsModal.addEventListener('click', function(e) {
        if (e.target === logDetailsModal) {
            closeModal();
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && logDetailsModal.style.display === 'flex') {
            closeModal();
        }
    });

    // Helper function to update URL parameters
    function updateUrlParams(params) {
        const url = new URL(window.location.href);
        
        Object.entries(params).forEach(([key, value]) => {
            if (value === null || value === '') {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, value);
            }
        });
        
        window.location.href = url.toString();
    }

    // Show log details
    function showLogDetails(logId) {
        fetch(`/api/hk/logs/get_log.php?id=${logId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.json();
            })
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Failed to load log');
                
                populateModal(data.log);
                logDetailsModal.style.display = 'flex';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading log details: ' + error.message);
            });
    }

    // Populate modal with log details
    function populateModal(log) {
        document.getElementById('detailTimestamp').textContent = 
            new Date(log.timestamp).toLocaleString();
        document.getElementById('detailUser').textContent = log.username || 'N/A';
        document.getElementById('detailIp').textContent = log.ip_address || 'N/A';
        document.getElementById('detailActionType').textContent = 
            log.action_type ? log.action_type.charAt(0).toUpperCase() + log.action_type.slice(1) : 'N/A';
        document.getElementById('detailAction').textContent = log.action || 'N/A';
        document.getElementById('detailStatus').textContent = 
            log.status ? log.status.charAt(0).toUpperCase() + log.status.slice(1) : 'N/A';
        document.getElementById('detailDetails').textContent = log.details || 'No details';
        
        const additionalData = log.additional_data || {};
        document.getElementById('detailAdditional').textContent = 
            Object.keys(additionalData).length 
                ? JSON.stringify(additionalData, null, 2) 
                : 'No additional data';
    }

    // Close modal
    function closeModal() {
        logDetailsModal.style.display = 'none';
    }
});