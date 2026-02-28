document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const transactionSearch = document.getElementById('transactionSearch');
    const searchBtn = document.getElementById('searchBtn');
    const filterBtns = document.querySelectorAll('.filter-btn');
    const transactionDetailsModal = document.getElementById('transactionDetailsModal');
    
    // Initialize the page
    function init() {
        setupEventListeners();
        highlightActiveFilter();
    }
    
    // Set up all event listeners
    function setupEventListeners() {
        // Search functionality
        transactionSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
        
        if (searchBtn) {
            searchBtn.addEventListener('click', performSearch);
        }
        
        // Filter buttons
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                applyFilter(this.dataset.filter);
            });
        });
        
        // View transaction details (event delegation)
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-view')) {
                const transactionId = e.target.closest('.btn-view').dataset.id;
                showTransactionDetails(transactionId);
            }
        });
        
        // Modal close events
        const modalClose = document.querySelector('.modal-close');
        const modalCancel = document.querySelector('.btn-cancel');
        
        if (modalClose) {
            modalClose.addEventListener('click', closeModal);
        }
        
        if (modalCancel) {
            modalCancel.addEventListener('click', closeModal);
        }
        
        // Close modal when clicking outside
        if (transactionDetailsModal) {
            transactionDetailsModal.addEventListener('click', function(e) {
                if (e.target === transactionDetailsModal) {
                    closeModal();
                }
            });
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && transactionDetailsModal.style.display === 'flex') {
                closeModal();
            }
        });
    }
    
    // Highlight the active filter based on URL
    function highlightActiveFilter() {
        const urlParams = new URLSearchParams(window.location.search);
        const currentFilter = urlParams.get('filter') || 'all';
        
        filterBtns.forEach(btn => {
            if (btn.dataset.filter === currentFilter) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }
    
    // Perform search
    function performSearch() {
        const searchTerm = transactionSearch.value.trim();
        const currentFilter = document.querySelector('.filter-btn.active').dataset.filter;
        updateUrl(currentFilter, searchTerm);
    }
    
    // Apply filter
    function applyFilter(filter) {
        const searchTerm = transactionSearch.value.trim();
        updateUrl(filter, searchTerm);
    }
    
    // Update URL with new parameters
    function updateUrl(filter, search) {
        const params = new URLSearchParams();
        params.set('filter', filter);
        
        if (search) {
            params.set('search', search);
        }
        
        window.location.href = `transactions.php?${params.toString()}`;
    }
    
    // Show transaction details modal
    function showTransactionDetails(transactionId) {
        fetch(`../api/hk/transactions/get_transaction.php?id=${transactionId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    populateModal(data.transaction);
                    openModal();
                } else {
                    showError(data.message || 'Failed to load transaction details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error fetching transaction details');
            });
    }
    
    // Populate modal with transaction data
// Populate modal with transaction data
function populateModal(transaction) {
    const fields = {
        'detail-txn-id': transaction.transaction_id || transaction.order_id,
        'detail-order-id': transaction.order_id,
        'detail-txn-user': transaction.username || transaction.user_id,
        'detail-txn-amount': '$' + parseFloat(transaction.amount).toFixed(2),
        'detail-txn-quantity': transaction.quantity,
        'detail-txn-method': formatPaymentMethod(transaction.payment_method),
        'detail-txn-gateway': transaction.gateway ? transaction.gateway.charAt(0).toUpperCase() + transaction.gateway.slice(1) : 'N/A',
        'detail-txn-date': formatDate(transaction.created_at),
        'detail-delivery-status': transaction.delivery_status || 'N/A',
        'detail-delivered-item': transaction.delivered_item || 'N/A',
        'detail-txn-note': transaction.note || 'N/A'
    };
    
    // Helper function to format payment method
    function formatPaymentMethod(method) {
        if (!method) return 'N/A';
        // Convert to lowercase first to handle cases like 'payPal' or 'PAYPAL'
        return method.toLowerCase() === 'paypal' ? 'PayPal' : method.charAt(0).toUpperCase() + method.slice(1).toLowerCase();
    }
    
    // Helper function to format date
    function formatDate(dateString) {
        const options = { 
            year: 'numeric', 
            month: 'numeric', 
            day: 'numeric',
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true
        };
        return new Date(dateString).toLocaleString('en-US', options);
    }

    // Set all field values
    Object.entries(fields).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    });
    
    // Set status badge
    const statusElement = document.getElementById('detail-txn-status');
    if (statusElement) {
        statusElement.innerHTML = `
            <span class="status-badge status-${transaction.status}">
                ${transaction.status.charAt(0).toUpperCase() + transaction.status.slice(1)}
            </span>
        `;
    }
}
    
    // Show error message
    function showError(message) {
        alert('Error: ' + message);
    }
    
    // Open modal
    function openModal() {
        if (transactionDetailsModal) {
            transactionDetailsModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }
    
    // Close modal
    function closeModal() {
        if (transactionDetailsModal) {
            transactionDetailsModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    
    // Initialize the page
    init();
});