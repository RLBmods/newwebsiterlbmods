document.addEventListener('DOMContentLoaded', function() {
    // Modal elements
    const newTicketBtn = document.getElementById('newTicketBtn');
    const newTicketModal = document.getElementById('newTicketModal');
    const modalClose = document.getElementById('modalClose');
    const cancelTicket = document.getElementById('cancelTicket');
    const ticketForm = document.getElementById('ticketForm');
    
    // Ticket detail elements
    const ticketDetailModal = document.getElementById('ticketDetailModal');
    const detailModalClose = document.getElementById('detailModalClose');
    const modalContent = document.getElementById('ticketModalContent');
    
    // Filter buttons
    const filterBtns = document.querySelectorAll('.filter-btn');
    
// Filter and Search functionality
function filterTickets(filter) {
    const ticketCards = document.querySelectorAll('.ticket-card');
    
    ticketCards.forEach(card => {
        const status = card.querySelector('.ticket-status').classList[1].replace('status-', '');
        
        if (filter === 'all' || 
            (filter === 'open' && status === 'open') ||
            (filter === 'answered' && status === 'answered') ||
            (filter === 'closed' && status === 'closed')) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function searchTickets(searchTerm) {
    const ticketCards = document.querySelectorAll('.ticket-card');
    const searchLower = searchTerm.toLowerCase();
    
    ticketCards.forEach(card => {
        const title = card.querySelector('.ticket-title').textContent.toLowerCase();
        const desc = card.querySelector('.ticket-desc').textContent.toLowerCase();
        
        if (title.includes(searchLower) || desc.includes(searchLower)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Initialize filter and search functionality
function initFilterAndSearch() {
    // Filter buttons
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons
            filterBtns.forEach(b => b.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            filterTickets(filter);
        });
    });
    
    // Search box
    const searchInput = document.querySelector('.search-box input');
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        if (searchTerm.length > 0) {
            searchTickets(searchTerm);
        } else {
            // If search is cleared, show tickets based on current filter
            const activeFilter = document.querySelector('.filter-btn.active').dataset.filter;
            filterTickets(activeFilter);
        }
    });
}


    // New Ticket Modal
    if (newTicketBtn && newTicketModal) {
        newTicketBtn.addEventListener('click', function() {
            newTicketModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
        
        modalClose.addEventListener('click', closeNewTicketModal);
        cancelTicket.addEventListener('click', closeNewTicketModal);
        
        function closeNewTicketModal() {
            newTicketModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }
    
    // Ticket Form Submission
// Update the ticket form submission
if (ticketForm) {
    ticketForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = ticketForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        const formData = new FormData(ticketForm);
        
        // Add any additional fields if needed
        formData.append('create_ticket', '1');
        
        fetch('support.php', {
            method: 'POST',
            body: formData,
            // Don't set Content-Type header when using FormData
            // The browser will set it automatically with the correct boundary
        })
        .then(response => {
            if (response.redirected) {
                window.location.href = response.url;
            } else {
                return response.text();
            }
        })
        .then(data => {
            if (data.includes('created=')) {
                window.location.reload();
            } else {
                showAlert('Ticket submitted successfully!', 'success');
                closeNewTicketModal();
                ticketForm.reset();
                setTimeout(() => window.location.reload(), 1500);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Failed to submit ticket. Please try again.', 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Ticket';
        });
    });
}

// Update the reply form submission in the renderTicketModal function
const replyForm = document.getElementById('ticketReplyForm');
if (replyForm) {
    replyForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        
        const formData = new FormData(this);
        
        fetch('support.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showAlert('Reply sent successfully!', 'success');
                this.querySelector('textarea').value = '';
                loadTicketModal(ticket.id);
            } else {
                showAlert(data.error || 'Failed to send reply', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            fetch(`support.php?ajax_ticket=1&ticket_id=${ticket.id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Reply sent successfully!', 'success');
                        loadTicketModal(ticket.id);
                    } else {
                        showAlert('Network error sending reply. Please check if your reply was received.', 'error');
                    }
                })
                .catch(() => {
                    showAlert('Network error sending reply. Please check if your reply was received.', 'error');
                });
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Send Reply';
        });
    });
}
    
    // View Ticket Details
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-ticket-btn') || e.target.closest('.view-ticket-btn')) {
            e.preventDefault();
            const btn = e.target.classList.contains('view-ticket-btn') ? e.target : e.target.closest('.view-ticket-btn');
            const ticketId = btn.dataset.ticketId;
            loadTicketModal(ticketId);
        }
    });
    
    // Load ticket data and show modal
    function loadTicketModal(ticketId) {
        // Show loading state
        modalContent.innerHTML = `
            <div class="loading-state">
                <i class="fas fa-spinner fa-spin"></i> Loading ticket details...
            </div>
        `;
        ticketDetailModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        
        // Fetch ticket data
        fetch(`support.php?ajax_ticket=1&ticket_id=${ticketId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderTicketModal(data.ticket, data.messages);
                } else {
                    showModalError('Failed to load ticket: ' + (data.error || 'Unknown error'), ticketId);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showModalError('Network error loading ticket', ticketId);
            });
    }
    
    // Render ticket content in modal
    function renderTicketModal(ticket, messages) {
        // Format date and time
        const formatDate = (dateString) => {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        };
        
        const formatTime = (dateString) => {
            const date = new Date(dateString);
            return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        };
        
        // Build messages HTML
        let messagesHTML = '';
        messages.forEach(msg => {
            messagesHTML += `
                <div class="message ${msg.is_support ? 'support-message' : 'user-message'}">
                    <div class="message-header">
                        <span class="message-author">
                            ${msg.is_support ? 
                            `<span class="user-role">[${msg.role ? msg.role.toUpperCase() : 'SUPPORT'}]</span> ${msg.username}` : 
                            'You'}
                        </span>
                        <span class="message-date">${formatDate(msg.created_at)} ${formatTime(msg.created_at)}</span>
                    </div>
                    <div class="message-content">
                        <p>${msg.message}</p>
                        ${msg.attachment ? `
                        <div class="message-attachment">
                            <i class="fas fa-paperclip"></i>
                            <a href="${msg.attachment}" target="_blank">${msg.attachment.split('/').pop()}</a>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        
        // Build full modal content
        modalContent.innerHTML = `
            <div class="ticket-detail-header">
                <div class="ticket-meta">
                    <span class="ticket-id">#TKT-${String(ticket.id).padStart(5, '0')}</span>
                    <span class="ticket-status status-${ticket.status.toLowerCase()}">${ticket.status}</span>
                    <span class="ticket-date"><i class="fas fa-calendar"></i> Created: ${formatDate(ticket.created_at)}</span>
                    <span class="ticket-category"><i class="fas fa-tag"></i> ${ticket.type}</span>
                </div>
                <h2 class="ticket-subject">${ticket.subject}</h2>
            </div>
            
            <div class="ticket-conversation">
                ${messagesHTML}
            </div>
            
            ${ticket.status !== 'Closed' ? `
            <div class="ticket-reply">
                <h4><i class="fas fa-reply"></i> Reply to Ticket</h4>
                <form id="ticketReplyForm">
                    <input type="hidden" name="reply_ticket" value="1">
                    <input type="hidden" name="ticket_id" value="${ticket.id}">
                    <div class="form-group">
                        <textarea id="reply-message" name="reply_message" rows="4" placeholder="Type your reply here..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="reply-attachments">Add Attachments</label>
                        <input type="file" id="reply-attachments" name="attachments" multiple>
                        <small>Max 5MB per file</small>
                    </div>
                    <button type="submit" class="btn-primary">Send Reply</button>
                </form>
            </div>
            ` : `
            <div class="ticket-closed-notice">
                <i class="fas fa-lock"></i> This ticket has been closed and cannot be replied to.
            </div>
            `}
        `;
        
        // Bind reply form handler
        const replyForm = document.getElementById('ticketReplyForm');
        if (replyForm) {
            replyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                
                const formData = new FormData(this);
                
                fetch('support.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Explicitly identify as AJAX
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showAlert('Reply sent successfully!', 'success');
                        // Clear the reply textarea
                        this.querySelector('textarea').value = '';
                        // Reload the ticket to show the new message
                        loadTicketModal(ticket.id);
                    } else {
                        showAlert(data.error || 'Failed to send reply', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Check if the error is a JSON parse error (might still be successful)
                    fetch(`support.php?ajax_ticket=1&ticket_id=${ticket.id}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // If we can load the ticket, the reply probably succeeded
                                showAlert('Reply sent successfully!', 'success');
                                loadTicketModal(ticket.id);
                            } else {
                                showAlert('Network error sending reply. Please check if your reply was received.', 'error');
                            }
                        })
                        .catch(() => {
                            showAlert('Network error sending reply. Please check if your reply was received.', 'error');
                        });
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Send Reply';
                });
            });
        }
    }
    
    // Close modals
    detailModalClose.addEventListener('click', closeTicketModal);
    ticketDetailModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeTicketModal();
        }
    });
    
    function closeTicketModal() {
        ticketDetailModal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    // Show error in modal
    function showModalError(message, ticketId) {
        modalContent.innerHTML = `
            <div class="error-state">
                <i class="fas fa-exclamation-circle"></i>
                <p>${message}</p>
                <button class="btn-secondary" onclick="loadTicketModal(${ticketId})">Try Again</button>
            </div>
        `;
    }
    
    // Show alert message
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerHTML = `
            <span>${message}</span>
            <button class="close-alert">&times;</button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Position and style the alert
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.padding = '15px';
        alertDiv.style.borderRadius = '4px';
        alertDiv.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.display = 'flex';
        alertDiv.style.justifyContent = 'space-between';
        alertDiv.style.alignItems = 'center';
        alertDiv.style.maxWidth = '400px';
        alertDiv.style.animation = 'fadeIn 0.3s ease-in-out';
        
        if (type === 'success') {
            alertDiv.style.backgroundColor = '#d4edda';
            alertDiv.style.color = '#155724';
            alertDiv.style.border = '1px solid #c3e6cb';
        } else {
            alertDiv.style.backgroundColor = '#f8d7da';
            alertDiv.style.color = '#721c24';
            alertDiv.style.border = '1px solid #f5c6cb';
        }
        
        // Close button
        const closeBtn = alertDiv.querySelector('.close-alert');
        closeBtn.addEventListener('click', () => {
            alertDiv.remove();
        });
        
        // Auto-close after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    
    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .loading-state {
            text-align: center;
            padding: 40px;
            color: #fff;
        }
        .error-state {
            text-align: center;
            padding: 20px;
            color: #ff6b6b;
        }
    `;
    document.head.appendChild(style);

    initFilterAndSearch();
});