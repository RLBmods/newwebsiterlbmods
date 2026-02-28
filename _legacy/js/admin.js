document.addEventListener('DOMContentLoaded', function() {
    // Edit User Overlay
    const editButtons = document.querySelectorAll('.btn-icon.edit');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const userId = row.cells[0].textContent;
            const username = row.cells[1].querySelector('span').textContent;
            const email = row.cells[2].textContent;
            const role = row.cells[3].textContent;
            const status = row.cells[4].textContent.trim();
            
            openEditUserOverlay(userId, username, email, role, status);
        });
    });

    // Delete User Confirmation
    const deleteButtons = document.querySelectorAll('.btn-icon.delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const userId = row.cells[0].textContent;
            const username = row.cells[1].querySelector('span').textContent;
            
            openDeleteConfirmation(userId, username);
        });
    });

    // Add User Button
    const addUserBtn = document.querySelector('.user-actions .btn-primary');
    if (addUserBtn) {
        addUserBtn.addEventListener('click', openAddUserOverlay);
    }

    // Close Overlay Buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('close-overlay') || 
            e.target.classList.contains('overlay-backdrop')) {
            closeAllOverlays();
        }
    });
});

// Edit User Overlay
function openEditUserOverlay(userId, username, email, role, status) {
    const overlay = createOverlay(`
        <div class="overlay-content">
            <div class="overlay-header">
                <h3>Edit User: ${username}</h3>
                <button class="close-overlay">&times;</button>
            </div>
            <div class="overlay-body">
                <form id="editUserForm">
                    <input type="hidden" name="userId" value="${userId}">
                    
                    <div class="form-group">
                        <label for="editUsername">Username</label>
                        <input type="text" id="editUsername" name="username" value="${username}" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editEmail">Email</label>
                        <input type="email" id="editEmail" name="email" value="${email}" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editRole">Role</label>
                        <select id="editRole" name="role" required>
                            <option value="Admin" ${role === 'Admin' ? 'selected' : ''}>Admin</option>
                            <option value="Moderator" ${role === 'Moderator' ? 'selected' : ''}>Moderator</option>
                            <option value="User" ${role === 'User' ? 'selected' : ''}>User</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="editStatus">Status</label>
                        <select id="editStatus" name="status" required>
                            <option value="Active" ${status === 'Active' ? 'selected' : ''}>Active</option>
                            <option value="Pending" ${status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Banned" ${status === 'Banned' ? 'selected' : ''}>Banned</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="overlay-footer">
                <button class="btn btn-secondary close-overlay">Cancel</button>
                <button class="btn btn-primary" id="saveUserChanges">Save Changes</button>
            </div>
        </div>
    `);

    document.getElementById('saveUserChanges').addEventListener('click', function() {
        const form = document.getElementById('editUserForm');
        const formData = new FormData(form);
        const updatedUser = Object.fromEntries(formData.entries());
        
        // Here you would typically send the data to your backend
        console.log('Updated user data:', updatedUser);
        
        // Simulate success and update the UI
        alert(`User ${updatedUser.username} updated successfully!`);
        closeAllOverlays();
        
        // In a real app, you would update the table row here
        // updateUserInTable(updatedUser);
    });
}

// Delete Confirmation Overlay
function openDeleteConfirmation(userId, username) {
    const overlay = createOverlay(`
        <div class="overlay-content overlay-warning">
            <div class="overlay-header">
                <h3>Delete User</h3>
                <button class="close-overlay">&times;</button>
            </div>
            <div class="overlay-body">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <p>Are you sure you want to delete user <strong>${username}</strong> (ID: ${userId})?</p>
                <p class="text-warning">This action cannot be undone!</p>
            </div>
            <div class="overlay-footer">
                <button class="btn btn-secondary close-overlay">Cancel</button>
                <button class="btn btn-danger" id="confirmDelete">Delete User</button>
            </div>
        </div>
    `);

    document.getElementById('confirmDelete').addEventListener('click', function() {
        // Here you would typically send a delete request to your backend
        console.log(`Deleting user ${userId}`);
        
        // Simulate success
        alert(`User ${username} deleted successfully!`);
        closeAllOverlays();
        
        // In a real app, you would remove the row from the table here
        // removeUserFromTable(userId);
    });
}

// Add User Overlay
function openAddUserOverlay() {
    const overlay = createOverlay(`
        <div class="overlay-content">
            <div class="overlay-header">
                <h3>Add New User</h3>
                <button class="close-overlay">&times;</button>
            </div>
            <div class="overlay-body">
                <form id="addUserForm">
                    <div class="form-group">
                        <label for="newUsername">Username</label>
                        <input type="text" id="newUsername" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="newEmail">Email</label>
                        <input type="email" id="newEmail" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="newPassword">Password</label>
                        <input type="password" id="newPassword" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="newRole">Role</label>
                        <select id="newRole" name="role" required>
                            <option value="Admin">Admin</option>
                            <option value="Moderator">Moderator</option>
                            <option value="User" selected>User</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="overlay-footer">
                <button class="btn btn-secondary close-overlay">Cancel</button>
                <button class="btn btn-primary" id="createUser">Create User</button>
            </div>
        </div>
    `);

    document.getElementById('createUser').addEventListener('click', function() {
        const form = document.getElementById('addUserForm');
        const formData = new FormData(form);
        const newUser = Object.fromEntries(formData.entries());
        
        // Here you would typically send the data to your backend
        console.log('New user data:', newUser);
        
        // Simulate success
        alert(`User ${newUser.username} created successfully!`);
        closeAllOverlays();
        
        // In a real app, you would add the new user to the table
        // addUserToTable(newUser);
    });
}

// Helper functions
function createOverlay(content) {
    closeAllOverlays();
    
    const overlay = document.createElement('div');
    overlay.className = 'overlay-backdrop';
    overlay.innerHTML = content;
    
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
    
    return overlay;
}

function closeAllOverlays() {
    const overlays = document.querySelectorAll('.overlay-backdrop');
    overlays.forEach(overlay => overlay.remove());
    document.body.style.overflow = '';
}