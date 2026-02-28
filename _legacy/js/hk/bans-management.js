document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const banSearch = document.getElementById('banSearch');
    const filterBtns = document.querySelectorAll('.filter-btn');
    const addBanBtn = document.getElementById('addBanBtn');
    const bansTableBody = document.getElementById('bans-table-body');
    const prevPageBtn = document.getElementById('prevPage');
    const nextPageBtn = document.getElementById('nextPage');
    const pageNumbers = document.getElementById('pageNumbers');
    
    // State
    let currentPage = 1;
    let currentFilter = 'all';
    let currentSearch = '';
    let totalPages = 1;
    
    // Initialize
    loadBans();
    setupEventListeners();
    
    function setupEventListeners() {
        // Search functionality
        banSearch.addEventListener('input', function() {
            currentSearch = this.value.trim();
            currentPage = 1;
            loadBans();
        });
        
        // Filter buttons
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                currentPage = 1;
                loadBans();
            });
        });
        
        // Pagination
        prevPageBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                loadBans();
            }
        });
        
        nextPageBtn.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                loadBans();
            }
        });
        
        // Add ban button
        addBanBtn.addEventListener('click', () => {
            resetNewBanForm();
            document.getElementById('newBanModal').style.display = 'flex';
        });
        
        // Ban duration selection
        document.getElementById('banDuration').addEventListener('change', function() {
            document.getElementById('customBanDateGroup').style.display = 
                this.value === 'custom' ? 'block' : 'none';
        });
        
        // Extend duration selection
        document.getElementById('extendDuration').addEventListener('change', function() {
            document.getElementById('customExtendDateGroup').style.display = 
                this.value === 'custom' ? 'block' : 'none';
        });
        
        // Modal close handlers
        document.querySelectorAll('.modal-close, .btn-cancel').forEach(btn => {
            btn.addEventListener('click', closeAllModals);
        });
        
        // Form submissions
        document.getElementById('newBanForm').addEventListener('submit', handleNewBan);
        document.getElementById('extendBanForm').addEventListener('submit', handleExtendBan);
        document.getElementById('confirmUnbanBtn').addEventListener('click', confirmUnban);
    }
    
    function loadBans() {
        showLoading(true);
        
        const params = new URLSearchParams({
            page: currentPage,
            filter: currentFilter,
            search: currentSearch
        });
        
        fetch(`/api/hk/bans/list.php?${params.toString()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    renderBansTable(data.bans);
                    updatePagination(data.pagination);
                } else {
                    throw new Error(data.error || 'Failed to load bans');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', error.message);
                bansTableBody.innerHTML = '<tr><td colspan="8" class="text-center">Error loading bans</td></tr>';
            })
            .finally(() => showLoading(false));
    }
    
    function renderBansTable(bans) {
        bansTableBody.innerHTML = bans.length ? '' : 
            '<tr><td colspan="8" class="text-center">No bans found</td></tr>';
    
        bans.forEach(ban => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${ban.id}</td>
                <td>
                    <span class="user-avatar"><i class="fas fa-user"></i></span>
                    ${ban.username}
                </td>
                <td>${ban.reason}</td>
                <td>${ban.banned_by_username}</td>
                <td>${ban.banned_at}</td>
                <td>${ban.is_permanent ? 'Never' : (ban.expires_at || 'N/A')}</td>
                <td>
                <span class="status-badge ${
                    ban.status === 'Active' ? 'status-active' : 
                    ban.status === 'Expired' ? 'status-expired' : 
                    ban.status === 'Unbanned' ? 'status-inactive' : 'status-permanent'
                }">${ban.status}</span>
                 </td>
                <td>
                <div class="admin-actions">
                    ${ban.status === 'Active' || ban.status === 'Permanent' ? `
                        ${ban.status === 'Permanent' ? '' : `
                            <button class="btn-admin btn-extend" data-id="${ban.id}" data-username="${ban.username}" title="Extend Ban">
                                <i class="fas fa-clock"></i>
                            </button>
                        `}
                        <button class="btn-admin btn-unban" data-id="${ban.id}" data-username="${ban.username}" title="Unban">
                            <i class="fas fa-unlock"></i>
                        </button>
                    ` : `
                        <button class="btn-admin btn-ban" data-id="${ban.id}" data-username="${ban.username}" title="Ban Again">
                            <i class="fas fa-ban"></i>
                        </button>
                    `}
                </div>
            </td>
            `;
            
            // Add event listeners after the element is in the DOM
            setTimeout(() => {
                const extendBtn = tr.querySelector('.btn-extend');
                if (extendBtn) {
                    extendBtn.addEventListener('click', (e) => {
                        const { id, username } = e.currentTarget.dataset;
                        openExtendModal(id, username);
                    });
                }
                
                const unbanBtn = tr.querySelector('.btn-unban');
                if (unbanBtn) {
                    unbanBtn.addEventListener('click', (e) => {
                        const { id, username } = e.currentTarget.dataset;
                        openUnbanModal(id, username);
                    });
                }
                
                const banBtn = tr.querySelector('.btn-ban');
                if (banBtn) {
                    banBtn.addEventListener('click', (e) => {
                        const { username } = e.currentTarget.dataset;
                        resetNewBanForm(username);
                        document.getElementById('newBanModal').style.display = 'flex';
                    });
                }
            }, 0);
            
            bansTableBody.appendChild(tr);
        });
    }
    
    function openExtendModal(banId, username) {
        document.getElementById('extendBanId').value = banId;
        document.getElementById('extendUsernameDisplay').textContent = username;
        document.getElementById('extendDuration').value = '7d';
        document.getElementById('customExtendDateGroup').style.display = 'none';
        document.getElementById('extendBanModal').style.display = 'flex';
    }
    
    function openUnbanModal(banId, username) {
        document.getElementById('unbanId').value = banId;
        document.getElementById('unbanMessage').textContent = `Are you sure you want to unban ${username}?`;
        document.getElementById('unbanModal').style.display = 'flex';
    }
    
    async function handleNewBan(e) {
        e.preventDefault();
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        const form = document.getElementById('newBanForm');
        
        try {
            // Clear previous errors
            const existingError = form.querySelector('.error-message');
            if (existingError) existingError.remove();
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            const username = document.getElementById('banUsername').value.trim();
            const reason = document.getElementById('banReason').value.trim();
            const duration = document.getElementById('banDuration').value;
            const customDate = duration === 'custom' 
                ? document.getElementById('customBanDate').value 
                : null;
            
            if (!username || !reason) {
                throw new Error('Username and reason are required');
            }
            
            if (duration === 'custom' && !customDate) {
                throw new Error('Please select a custom date');
            }
            
            const response = await fetch('/api/hk/bans/create.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    username,
                    reason,
                    duration,
                    custom_date: customDate
                }),
                credentials: 'include'
            });
            
            const contentType = response.headers.get('content-type');
            let responseData;
            
            if (contentType && contentType.includes('application/json')) {
                responseData = await response.json();
            } else {
                responseData = await response.text();
            }
            
            if (!response.ok) {
                throw new Error(responseData.error || 'Failed to create ban');
            }
            
            showNotification('success', responseData.message || 'Ban created successfully');
            closeAllModals();
            loadBans();
        } catch (error) {
            console.error('Ban creation error:', error);
            
            // Display error in the form
            const errorDisplay = document.createElement('div');
            errorDisplay.className = 'error-message';
            errorDisplay.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${error.message}`;
            form.insertBefore(errorDisplay, form.firstChild);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }
    
    async function handleExtendBan(e) {
        e.preventDefault();
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        const form = document.getElementById('extendBanForm');
        
        try {
            // Clear previous errors
            const existingError = form.querySelector('.error-message');
            if (existingError) existingError.remove();
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            const banId = document.getElementById('extendBanId').value;
            const duration = document.getElementById('extendDuration').value;
            const customDate = duration === 'custom' 
                ? document.getElementById('customExtendDate').value 
                : null;
            
            if (duration === 'custom' && !customDate) {
                throw new Error('Please select a custom date');
            }
            
            const response = await fetch('/api/hk/bans/extend.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    id: banId,
                    duration,
                    custom_date: customDate
                }),
                credentials: 'include'
            });
            
            const contentType = response.headers.get('content-type');
            let responseData;
            
            if (contentType && contentType.includes('application/json')) {
                responseData = await response.json();
            } else {
                responseData = await response.text();
            }
            
            if (!response.ok) {
                throw new Error(responseData.error || 'Failed to extend ban');
            }
            
            showNotification('success', responseData.message || 'Ban extended successfully');
            closeAllModals();
            loadBans();
        } catch (error) {
            console.error('Extend ban error:', error);
            
            // Display error in the form
            const errorDisplay = document.createElement('div');
            errorDisplay.className = 'error-message';
            errorDisplay.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${error.message}`;
            form.insertBefore(errorDisplay, form.firstChild);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }
    
    async function confirmUnban() {
        const confirmBtn = document.getElementById('confirmUnbanBtn');
        const originalBtnText = confirmBtn.innerHTML;
        const banId = document.getElementById('unbanId').value;
        
        try {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            const response = await fetch('/api/hk/bans/deactivate.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ id: banId }),
                credentials: 'include'
            });
    
            // First get the response text
            const responseText = await response.text();
            
            // Try to parse it as JSON
            let responseData;
            try {
                responseData = JSON.parse(responseText);
            } catch (e) {
                // If parsing fails but response was OK, assume success
                if (response.ok) {
                    responseData = { success: true, message: 'User unbanned successfully' };
                } else {
                    throw new Error(responseText || 'Failed to parse server response');
                }
            }
    
            if (!response.ok || !responseData.success) {
                throw new Error(responseData.error || 'Failed to unban user');
            }
            
            showNotification('success', responseData.message || 'User unbanned successfully');
            loadBans();
        } catch (error) {
            console.error('Unban error:', error);
            showNotification('error', error.message || 'Failed to unban user');
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = originalBtnText;
            closeAllModals(); // Ensure modal closes in all cases
        }
    }
    
    function resetNewBanForm(username = '') {
        document.getElementById('banUsername').value = username;
        document.getElementById('banReason').value = '';
        document.getElementById('banDuration').value = 'permanent';
        document.getElementById('customBanDateGroup').style.display = 'none';
        
        // Clear any errors
        const errorDisplay = document.querySelector('#newBanForm .error-message');
        if (errorDisplay) errorDisplay.remove();
    }
    
    function closeAllModals() {
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.style.display = 'none';
        });
    }
    
    function updatePagination(pagination) {
        totalPages = pagination.last_page;
        
        // Update pagination buttons
        prevPageBtn.disabled = currentPage <= 1;
        nextPageBtn.disabled = currentPage >= totalPages;
        
        // Update page numbers
        pageNumbers.innerHTML = '';
        
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        // First page
        if (startPage > 1) {
            const pageBtn = createPageButton(1);
            pageNumbers.appendChild(pageBtn);
            
            if (startPage > 2) {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'pagination-ellipsis';
                ellipsis.textContent = '...';
                pageNumbers.appendChild(ellipsis);
            }
        }
        
        // Middle pages
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = createPageButton(i);
            if (i === currentPage) {
                pageBtn.classList.add('active');
            }
            pageNumbers.appendChild(pageBtn);
        }
        
        // Last page
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'pagination-ellipsis';
                ellipsis.textContent = '...';
                pageNumbers.appendChild(ellipsis);
            }
            
            const pageBtn = createPageButton(totalPages);
            pageNumbers.appendChild(pageBtn);
        }
    }
    
    function createPageButton(page) {
        const btn = document.createElement('button');
        btn.className = 'pagination-btn';
        btn.textContent = page;
        btn.addEventListener('click', () => {
            currentPage = page;
            loadBans();
        });
        return btn;
    }
    
    function showLoading(show) {
        const loader = document.getElementById('loading-indicator') || createLoadingIndicator();
        loader.style.display = show ? 'flex' : 'none';
    }
    
    function createLoadingIndicator() {
        const loader = document.createElement('div');
        loader.id = 'loading-indicator';
        loader.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        `;
        loader.innerHTML = '<div class="spinner"></div>';
        document.body.appendChild(loader);
        return loader;
    }
    
    function showNotification(type, message) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 5000);
    }
});