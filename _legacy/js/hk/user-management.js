document.addEventListener('DOMContentLoaded', function() {
    // Modal elements
    const modals = {
        edit: document.getElementById('editUserModal'),
        add: document.getElementById('addUserModal'),
        ban: document.getElementById('banUserModal'),
        unban: document.getElementById('unbanUserModal'),
        delete: document.getElementById('deleteUserModal')
    };

    // Close modals when clicking X or cancel button
    document.querySelectorAll('.modal-close, .btn-cancel').forEach(btn => {
        btn.addEventListener('click', function() {
            Object.values(modals).forEach(modal => {
                if (modal) modal.style.display = 'none';
            });
        });
    });

    // Close modals when clicking outside
    Object.values(modals).forEach(modal => {
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    });

// Edit user button
document.querySelectorAll('.btn-edit[data-user-id]').forEach(btn => {
    btn.addEventListener('click', function() {
        const userId = this.getAttribute('data-user-id');
        const modal = modals.edit;
        
        // Show loading state
        modal.querySelector('.modal-body').innerHTML = '<div class="loading">Loading user data...</div>';
        modal.style.display = 'flex';

        fetch(`/api/hk/get_user_details.php?user_id=${userId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Failed to load user data');
            }

            // Create form with the received data
            const formHtml = `
                <form id="editUserForm" method="POST" action="users.php">
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="user_id" value="${userId}">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editUsername">Username</label>
                            <input type="text" id="editUsername" name="username" value="${escapeHtml(data.data.username)}" required>
                        </div>
                        <div class="form-group">
                            <label for="editEmail">Email</label>
                            <input type="email" id="editEmail" name="email" value="${escapeHtml(data.data.email)}" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editDiscordId">Discord ID</label>
                            <input type="text" id="editDiscordId" name="discord_id" value="${data.data.discordid || '0'}">
                        </div>
                        <div class="form-group">
                            <label for="editBalance">Balance</label>
                            <input type="text" id="editBalance" name="balance" value="${data.data.balance || 0}">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editRole">Role</label>
                            <select id="editRole" name="role" required onchange="toggleDiscountField()">
                                ${['member', 'customer', 'media', 'reseller', 'support', 'developer', 'manager', 'founder']
                                    .map(role => `<option value="${role}" ${role === data.data.role ? 'selected' : ''}>${role.charAt(0).toUpperCase() + role.slice(1)}</option>`)
                                    .join('')}
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editStatus">Status</label>
                            <select id="editStatus" name="status" required>
                                <option value="active" ${!data.data.banned ? 'selected' : ''}>Active</option>
                                <option value="banned" ${data.data.banned ? 'selected' : ''}>Banned</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Discount field for resellers -->
                    <div class="form-group" id="discountField" style="${data.data.role === 'reseller' ? '' : 'display: none;'}">
                        <label for="editDiscount">Discount Percentage</label>
                        <input type="number" step="0.01" min="0" max="100" id="editDiscount" name="discount" 
                               value="${data.data.discount || 0}" placeholder="0-100%">
                        <small>Enter a discount percentage for this reseller (0-100)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Product Access</label>
                        <div class="checkbox-group" id="productAccessContainer">
                            <!-- Products will be loaded separately -->
                            Loading products...
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-admin btn-cancel">Cancel</button>
                        <button type="submit" class="btn-admin btn-submit">Save Changes</button>
                    </div>
                </form>
            `;
            
            modal.querySelector('.modal-body').innerHTML = formHtml;
            
            // Now load products separately
            fetchProductsForEditModal(userId, data.data.products);
            
            // Re-attach form submission handler
            document.getElementById('editUserForm').addEventListener('submit', handleFormSubmit);
        })
        .catch(error => {
            console.error('Error:', error);
            modal.querySelector('.modal-body').innerHTML = `
                <div class="alert alert-error">
                    Failed to load user data: ${error.message}
                    <button onclick="location.reload()" class="btn-admin">Try Again</button>
                </div>
            `;
        });
    });
});


// Function to load products for the edit modal
function fetchProductsForEditModal(userId, userProducts) {
    fetch('/api/hk/get_products.php')  // You'll need to create this endpoint
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('productAccessContainer');
                container.innerHTML = data.data.map(product => `
                    <label class="checkbox-label">
                        <input type="checkbox" name="productAccess[]" value="${product.name}" 
                            ${userProducts.includes(product.name) ? 'checked' : ''}>
                        ${product.name}
                    </label>
                `).join('');
            } else {
                throw new Error(data.error || 'Failed to load products');
            }
        })
        .catch(error => {
            console.error('Failed to load products:', error);
            document.getElementById('productAccessContainer').innerHTML = `
                <div class="alert alert-warning">Failed to load products: ${error.message}</div>
            `;
        });
}

    // Add user button
    document.getElementById('addUserBtn').addEventListener('click', function() {
        modals.add.style.display = 'flex';
    });

    // Ban user button
    document.querySelectorAll('.btn-ban[data-user-id]').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.closest('tr').querySelector('td:nth-child(2)').textContent.trim();
            
            document.getElementById('banUserId').value = userId;
            document.getElementById('banReason').value = '';
            document.getElementById('banDuration').value = 'permanent';
            document.getElementById('customBanDateGroup').style.display = 'none';
            
            modals.ban.querySelector('h3').innerHTML = `<i class="fas fa-ban"></i> Ban ${username}`;
            modals.ban.style.display = 'flex';
        });
    });

    // Unban user button
    document.querySelectorAll('.btn-unban[data-user-id]').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.closest('tr').querySelector('td:nth-child(2)').textContent.trim();
            
            document.getElementById('unbanUserId').value = userId;
            modals.unban.querySelector('p').textContent = `Are you sure you want to unban ${username}?`;
            modals.unban.style.display = 'flex';
        });
    });

    // Delete user button
    document.querySelectorAll('.btn-delete[data-user-id]').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.closest('tr').querySelector('td:nth-child(2)').textContent.trim();
            
            document.getElementById('deleteUserId').value = userId;
            modals.delete.querySelector('p').textContent = 
                `Are you sure you want to permanently delete ${username}? This action cannot be undone.`;
            modals.delete.style.display = 'flex';
        });
    });

    // Ban duration selection
    document.getElementById('banDuration').addEventListener('change', function() {
        document.getElementById('customBanDateGroup').style.display = 
            this.value === 'custom' ? 'block' : 'none';
    });

    // Form submission handler
    function handleFormSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                if (data.password) {
                    alert(`Generated password: ${data.password}\nSend this to the user securely!`);
                }
                location.reload();
            } else {
                throw new Error(data.error || 'Failed to update user');
            }
        })
        .catch(error => {
            alert(error.message);
        });
    }

    // Attach form submission handlers
    if (document.getElementById('editUserForm')) {
        document.getElementById('editUserForm').addEventListener('submit', handleFormSubmit);
    }
    if (document.getElementById('addUserForm')) {
        document.getElementById('addUserForm').addEventListener('submit', handleFormSubmit);
    }
    if (document.getElementById('banUserForm')) {
        document.getElementById('banUserForm').addEventListener('submit', handleFormSubmit);
    }
    if (document.getElementById('unbanUserForm')) {
        document.getElementById('unbanUserForm').addEventListener('submit', handleFormSubmit);
    }
    if (document.getElementById('deleteUserForm')) {
        document.getElementById('deleteUserForm').addEventListener('submit', handleFormSubmit);
    }

    // Helper function to escape HTML
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            window.location.href = `users.php?filter=${filter}`;
        });
    });

    // Search form submission
    document.querySelector('.search-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const search = document.getElementById('userSearch').value;
        const filter = document.querySelector('.filter-btn.active').getAttribute('data-filter');
        window.location.href = `users.php?filter=${filter}&search=${encodeURIComponent(search)}`;
    });
});