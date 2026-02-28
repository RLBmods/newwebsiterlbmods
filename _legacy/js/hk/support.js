document.addEventListener('DOMContentLoaded', function() {
    // Modal elements
    const ticketDetailModal = document.getElementById('ticketDetailModal');
    const detailModalClose = document.getElementById('detailModalClose');
    const modalContent = document.getElementById('ticketModalContent');
    
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
        const modalContent = document.getElementById('ticketModalContent');
        modalContent.innerHTML = `
            <div class="loading-state">
                <i class="fas fa-spinner fa-spin"></i> Loading ticket details...
            </div>
        `;
        
        // Keep modal open
        const ticketDetailModal = document.getElementById('ticketDetailModal');
        ticketDetailModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        
        fetch(`support.php?ajax_ticket=1&ticket_id=${ticketId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response failed');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    renderTicketModal(data.ticket, data.messages, data.assignedStaff, data.staffMembers);
                } else {
                    throw new Error(data.error || 'Failed to load ticket');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalContent.innerHTML = `
                    <div class="error-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>${error.message}</p>
                        <button class="btn-secondary" onclick="loadTicketModal(${ticketId})">Try Again</button>
                    </div>
                `;
            });
    }
    
    // Render ticket content in modal
    function renderTicketModal(ticket, messages, assignedStaff, staffMembers) {
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
                            `<i class="fas fa-user"></i> ${msg.username}`}
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
        
        // Build assigned staff HTML
        // Update the staff assignment section in renderTicketModal()
        let assignedStaffHTML = '';
        if (assignedStaff.length > 0) {
            assignedStaff.forEach(staff => {
                assignedStaffHTML += `
                    <div class="assigned-staff">
                        <span class="staff-name">${staff.name}</span>
                        <span class="staff-role">${staff.role}</span>
                        <button class="btn-small remove-assignment" data-staff-id="${staff.id}">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                `;
            });
        } else {
            assignedStaffHTML = '<div class="no-assignments">No staff assigned</div>';
        }

        // Build staff selection dropdown with all eligible roles
        let staffOptions = '<option value="">Assign to staff...</option>';
        staffMembers.forEach(staff => {
            // Skip already assigned staff
            if (!assignedStaff.some(as => as.id === staff.id)) {
                staffOptions += `<option value="${staff.id}">${staff.name} (${staff.role})</option>`;
            }
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
                <div class="ticket-customer">
                    <i class="fas fa-user"></i> ${ticket.customer_name || 'Unknown customer'}
                </div>
                
                <div class="ticket-actions">
                    <div class="status-selector">
                        <select id="ticketStatus" data-ticket-id="${ticket.id}">
                            <option value="Open" ${ticket.status === 'Open' ? 'selected' : ''}>Open</option>
                            <option value="Pending" ${ticket.status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Resolved" ${ticket.status === 'Resolved' ? 'selected' : ''}>Resolved</option>
                            <option value="Closed" ${ticket.status === 'Closed' ? 'selected' : ''}>Closed</option>
                        </select>
                    </div>
                    
                    <div class="staff-assignment">
                        <div class="current-assignments">
                            <h4><i class="fas fa-users"></i> Assigned Staff</h4>
                            ${assignedStaffHTML}
                        </div>
                        <div class="assign-staff">
                            <select id="assignStaffSelect" data-ticket-id="${ticket.id}">
                                ${staffOptions}
                            </select>
                            <button class="btn-small" id="assignStaffBtn">
                                <i class="fas fa-plus"></i> Assign
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="ticket-conversation">
                ${messagesHTML}
            </div>
            
            <div class="ticket-reply">
                <h4><i class="fas fa-reply"></i> Reply to Ticket</h4>
                <form id="ticketReplyForm">
                    <input type="hidden" name="reply_ticket" value="1">
                    <input type="hidden" name="ticket_id" value="${ticket.id}">
                    
                    <div class="form-group">
                        <select name="reply_status" id="replyStatus">
                            <option value="">Keep current status</option>
                            <option value="Open">Set to Open</option>
                            <option value="Pending">Set to Pending</option>
                            <option value="Resolved">Set to Resolved</option>
                            <option value="Closed">Set to Closed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <textarea id="reply-message" name="reply_message" rows="4" placeholder="Type your reply here..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="reply-attachments">Add Attachments</label>
                        <input type="file" id="reply-attachments" name="reply-attachments[]" multiple>
                        <small>Max 5MB per file</small>
                    </div>
                    <button type="submit" class="btn-primary">Send Reply</button>
                </form>
            </div>
        `;
        
        // Bind status change handler
        const statusSelect = document.getElementById('ticketStatus');
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                const newStatus = this.value;
                const ticketId = this.dataset.ticketId;
                
                fetch('support.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `update_ticket_status=1&ticket_id=${ticketId}&status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Ticket status updated successfully!', 'success');
                        // Update the status display
                        document.querySelector('.ticket-status').textContent = newStatus;
                        document.querySelector('.ticket-status').className = `ticket-status status-${newStatus.toLowerCase()}`;
                    } else {
                        showAlert(data.error || 'Failed to update status', 'error');
                        // Revert the select to previous value
                        this.value = ticket.status;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Network error updating status', 'error');
                    this.value = ticket.status;
                });
            });
        }
        
// Update the staff assignment handler
const assignStaffBtn = document.getElementById('assignStaffBtn');
if (assignStaffBtn) {
    assignStaffBtn.addEventListener('click', function() {
        const staffSelect = document.getElementById('assignStaffSelect');
        const staffId = staffSelect.value;
        const ticketId = staffSelect.dataset.ticketId;
        
        if (!staffId) {
            showAlert('Please select a staff member', 'error');
            return;
        }
        
        const submitBtn = this;
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
        
        fetch('support.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `assign_ticket=1&ticket_id=${ticketId}&staff_id=${staffId}`
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.error || 'Network response was not ok');
                }).catch(() => {
                    throw new Error('Network response was not ok');
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data) throw new Error('Empty response from server');
            
            if (data.success) {
                showAlert('Staff assigned successfully!', 'success');
                staffSelect.value = '';
                // Reload the ticket to show the new assignment
                loadTicketModal(ticketId);
            } else {
                throw new Error(data.error || 'Failed to assign staff');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Verify if the assignment succeeded despite the error
            fetch(`support.php?ajax_ticket=1&ticket_id=${ticketId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Staff assigned successfully!', 'success');
                        loadTicketModal(ticketId);
                    } else {
                        showAlert(error.message || 'Error assigning staff. Please check if the assignment was successful.', 'error');
                    }
                })
                .catch(() => {
                    showAlert(error.message || 'Error assigning staff. Please check if the assignment was successful.', 'error');
                });
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
}

// Update the remove assignment handler
document.querySelectorAll('.remove-assignment').forEach(btn => {
    btn.addEventListener('click', function() {
        const staffId = this.dataset.staffId;
        const ticketId = document.getElementById('ticketStatus').dataset.ticketId;
        
        if (!confirm('Are you sure you want to remove this assignment?')) return;
        
        const submitBtn = this;
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        fetch('support.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `remove_assignment=1&ticket_id=${ticketId}&staff_id=${staffId}`
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.error || 'Network response was not ok');
                }).catch(() => {
                    throw new Error('Network response was not ok');
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data) throw new Error('Empty response from server');
            
            if (data.success) {
                showAlert('Assignment removed successfully!', 'success');
                // Reload the ticket to update assignments
                loadTicketModal(ticketId);
            } else {
                throw new Error(data.error || 'Failed to remove assignment');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Verify if the removal succeeded despite the error
            fetch(`support.php?ajax_ticket=1&ticket_id=${ticketId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Assignment removed successfully!', 'success');
                        loadTicketModal(ticketId);
                    } else {
                        showAlert(error.message || 'Error removing assignment. Please check if it was successful.', 'error');
                    }
                })
                .catch(() => {
                    showAlert(error.message || 'Error removing assignment. Please check if it was successful.', 'error');
                });
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
});
        
        // Bind reply form handler
        const replyForm = document.getElementById('ticketReplyForm');
        if (replyForm) {
            replyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
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
                    // First check if the response is OK (status 200-299)
                    if (!response.ok) {
                        // Try to parse the error response as JSON
                        return response.json().then(err => {
                            throw new Error(err.error || 'Network response was not ok');
                        }).catch(() => {
                            // If JSON parsing fails, throw a generic error
                            throw new Error('Network response was not ok');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data) {
                        throw new Error('Empty response from server');
                    }
                    
                    if (data.success) {
                        showAlert('Reply sent successfully!', 'success');
                        // Clear the form
                        this.querySelector('textarea').value = '';
                        // Reload the ticket to show the new message
                        loadTicketModal(ticket.id);
                    } else {
                        throw new Error(data.error || 'Failed to send reply');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Check if the reply actually succeeded despite the error
                    fetch(`support.php?ajax_ticket=1&ticket_id=${ticket.id}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // If we can load the ticket with the new reply, consider it a success
                                showAlert('Reply sent successfully!', 'success');
                                loadTicketModal(ticket.id);
                            } else {
                                showAlert(error.message || 'Error sending reply. Please check if your reply was received.', 'error');
                            }
                        })
                        .catch(() => {
                            showAlert(error.message || 'Error sending reply. Please check if your reply was received.', 'error');
                        });
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
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
            color: #666;
        }
        .error-state {
            text-align: center;
            padding: 20px;
            color: #ff6b6b;
        }
        .ticket-actions {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        .status-selector select {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .staff-assignment {
            flex-grow: 1;
        }
        .current-assignments {
            margin-bottom: 10px;
        }
        .assigned-staff {
            display: inline-flex;
            align-items: center;
            background: #f0f0f0;
            padding: 5px 10px;
            border-radius: 4px;
            margin-right: 8px;
            margin-bottom: 8px;
        }
        .assigned-staff button {
            margin-left: 8px;
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
        }
        .assigned-staff button:hover {
            color: #ff6b6b;
        }
        .assign-staff {
            display: flex;
            gap: 8px;
        }
        .assign-staff select {
            flex-grow: 1;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .no-assignments {
            color: #999;
            font-style: italic;
        }
        .ticket-customer {
            margin-top: 5px;
            color: #666;
        }

        .assigned-staff {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f0f0f0;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 8px;
        }
        
        .staff-name {
            font-weight: 500;
        }
        
        .staff-role {
            font-size: 0.8em;
            color: #666;
            text-transform: capitalize;
        }
        
        .assigned-staff button {
            margin-left: auto;
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 4px;
        }
        
        .assigned-staff button:hover {
            color: #ff6b6b;
        }
        
        .assign-staff {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        
        .assign-staff select {
            flex-grow: 1;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .current-assignments {
            margin-bottom: 15px;
        }
        
        .current-assignments h4 {
            margin-bottom: 10px;
            font-size: 1em;
            color: #555;
        }
    `;
    document.head.appendChild(style);
});