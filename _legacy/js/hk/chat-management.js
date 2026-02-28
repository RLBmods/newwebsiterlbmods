document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const messageSearch = document.getElementById('messageSearch');
    const searchBtn = document.querySelector('.message-search button');
    const filterBtns = document.querySelectorAll('.filter-btn');
    const chatSettingsBtn = document.getElementById('chatSettingsBtn');
    const bannedWordsBtn = document.getElementById('bannedWordsBtn');
    const addBannedWordBtn = document.getElementById('addBannedWordBtn');
    const newBannedWordInput = document.getElementById('newBannedWord');
    const confirmClearChatBtn = document.getElementById('confirmClearChatBtn');
    const chatMessagesContainer = document.querySelector('.chat-messages');
    const userInfoCard = document.querySelector('.user-info-card');
    
    // Modal elements
    const modals = {
        chatSettings: document.getElementById('chatSettingsModal'),
        bannedWords: document.getElementById('bannedWordsModal'),
        banUser: document.getElementById('banUserModal'),
        clearChat: document.getElementById('clearChatModal')
    };
    
    // Modal close buttons
    const modalCloseBtns = document.querySelectorAll('.modal-close');
    const modalCancelBtns = document.querySelectorAll('.btn-cancel');
    
    // Current selected user
    let selectedUser = null;
    
    // Initialize the chat
    function initChat() {
        setupEventListeners();
        
        // Highlight current filter button based on URL
        const urlParams = new URLSearchParams(window.location.search);
        const currentFilter = urlParams.get('filter') || 'all';
        
        filterBtns.forEach(btn => {
            if (btn.dataset.filter === currentFilter) {
                btn.classList.add('active');
            }
        });
    }
    
    // Delete a message
    function deleteMessage(messageId) {
        fetch('../api/hk/chat/delete_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: messageId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const messageElement = document.querySelector(`.message[data-id="${messageId}"]`);
                if (messageElement) {
                    messageElement.classList.add('deleted');
                    messageElement.querySelector('.message-content').textContent = '[This message has been removed by moderator]';
                    
                    // Update action buttons
                    const actionsDiv = messageElement.querySelector('.message-actions');
                    actionsDiv.innerHTML = `
                        <button class="btn-admin btn-restore" title="Restore Message" data-id="${messageId}">
                            <i class="fas fa-undo"></i>
                        </button>
                    `;
                    
                    // Add event listener to restore button
                    actionsDiv.querySelector('.btn-restore').addEventListener('click', (e) => {
                        e.stopPropagation();
                        restoreMessage(messageId);
                    });
                }
            } else {
                alert('Error: ' + (data.message || 'Failed to delete message'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting message');
        });
    }
    
    // Restore a message
    function restoreMessage(messageId) {
        fetch('../api/hk/chat/restore_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: messageId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const messageElement = document.querySelector(`.message[data-id="${messageId}"]`);
                if (messageElement) {
                    messageElement.classList.remove('deleted');
                    messageElement.querySelector('.message-content').textContent = data.message.content;
                    
                    // Update action buttons
                    const actionsDiv = messageElement.querySelector('.message-actions');
                    actionsDiv.innerHTML = `
                        <button class="btn-admin btn-delete" title="Delete Message" data-id="${messageId}">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button class="btn-admin btn-ban" title="Ban User" data-username="${data.message.username}">
                            <i class="fas fa-user-slash"></i>
                        </button>
                        <button class="btn-admin btn-flag" title="${data.message.flagged ? 'Unflag Message' : 'Flag Message'}" data-id="${messageId}" data-flagged="${data.message.flagged ? '1' : '0'}">
                            <i class="fas fa-flag"></i>
                        </button>
                    `;
                    
                    // Add event listeners to new buttons
                    actionsDiv.querySelector('.btn-delete').addEventListener('click', (e) => {
                        e.stopPropagation();
                        deleteMessage(messageId);
                    });
                    
                    actionsDiv.querySelector('.btn-ban').addEventListener('click', (e) => {
                        e.stopPropagation();
                        showBanUserModal(data.message.username);
                    });
                    
                    actionsDiv.querySelector('.btn-flag').addEventListener('click', (e) => {
                        e.stopPropagation();
                        toggleMessageFlag(messageId);
                    });
                }
            } else {
                alert('Error: ' + (data.message || 'Failed to restore message'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error restoring message');
        });
    }
    
    // Toggle message flag
    function toggleMessageFlag(messageId) {
        const flagBtn = document.querySelector(`.btn-flag[data-id="${messageId}"]`);
        const isFlagged = flagBtn.getAttribute('data-flagged') === '1';
        
        fetch('../api/hk/chat/toggle_message_flag.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: messageId, flag: !isFlagged })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const messageElement = document.querySelector(`.message[data-id="${messageId}"]`);
                if (messageElement) {
                    if (data.flagged) {
                        messageElement.classList.add('flagged');
                        flagBtn.setAttribute('title', 'Unflag Message');
                        flagBtn.setAttribute('data-flagged', '1');
                    } else {
                        messageElement.classList.remove('flagged');
                        flagBtn.setAttribute('title', 'Flag Message');
                        flagBtn.setAttribute('data-flagged', '0');
                    }
                    
                    // Update flag badge if it exists
                    const flagBadge = messageElement.querySelector('.flagged-badge');
                    if (data.flagged && !flagBadge) {
                        const header = messageElement.querySelector('.message-header');
                        header.insertAdjacentHTML('beforeend', '<span class="flagged-badge">Flagged</span>');
                    } else if (!data.flagged && flagBadge) {
                        flagBadge.remove();
                    }
                }
            } else {
                alert('Error: ' + (data.message || 'Failed to toggle message flag'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error toggling message flag');
        });
    }
    
    // Select a user to show in the info card
    function selectUser(username) {
        selectedUser = username;
        
        // Fetch user details
        fetch(`../api/hk/chat/get_user.php?username=${encodeURIComponent(username)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const user = data.user;
                    
                    // Update user info card
                    const userDetails = userInfoCard.querySelector('.user-details');
                    userDetails.querySelector('.username').textContent = user.name;
                    userDetails.querySelector('.user-status').textContent = user.banned ? 'Banned' : (user.muted ? 'Muted' : 'Active');
                    
                    // Update avatar
                    const avatar = userInfoCard.querySelector('.user-avatar-large');
                    avatar.innerHTML = '';
                    if (user.profile_picture) {
                        const img = document.createElement('img');
                        img.src = user.profile_picture;
                        img.alt = `${user.name}'s avatar`;
                        img.onerror = function() {
                            this.src = '/assets/avatars/default-avatar.png';
                        };
                        avatar.appendChild(img);
                    } else {
                        const icon = document.createElement('i');
                        icon.className = 'fas fa-user';
                        avatar.appendChild(icon);
                    }
                    
                    // Update stats
                    const statValues = userInfoCard.querySelectorAll('.stat-value');
                    statValues[0].textContent = user.message_count || '0';
                    statValues[1].textContent = user.warnings_count || '0';
                    statValues[2].textContent = user.bans_count || '0';
                    
                    // Highlight messages from this user
                    document.querySelectorAll('.message').forEach(msg => {
                        if (msg.querySelector('.username').textContent === username) {
                            msg.style.backgroundColor = 'rgba(158, 2, 5, 0.2)';
                        } else {
                            msg.style.backgroundColor = '';
                        }
                    });
                } else {
                    alert('Error: ' + (data.message || 'Failed to load user details'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching user details');
            });
    }
    
    // Show ban user modal
    function showBanUserModal(username) {
        modals.banUser.style.display = 'flex';
        document.getElementById('banUsername').value = username;
    }
    
    // Clear all chat messages
    function clearChat() {
        fetch('../api/hk/chat/clear_chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the page to show cleared chat
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to clear chat'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error clearing chat');
        });
    }
    
    // Add a banned word
    function addBannedWord() {
        const word = newBannedWordInput.value.trim();
        if (!word) return;
        
        fetch('../api/hk/chat/add_banned_word.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ word })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                newBannedWordInput.value = '';
                
                // Add to the banned words list
                const listContainer = document.querySelector('.banned-words-list');
                const wordItem = document.createElement('div');
                wordItem.className = 'banned-word-item';
                wordItem.innerHTML = `
                    <span>${word}</span>
                    <button class="btn-admin btn-delete" data-word="${word}">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                listContainer.appendChild(wordItem);
                
                // Add event listener to delete button
                wordItem.querySelector('.btn-delete').addEventListener('click', function() {
                    removeBannedWord(word);
                });
            } else {
                alert('Error: ' + (data.message || 'Failed to add banned word'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error adding banned word');
        });
    }
    
    // Remove a banned word
    function removeBannedWord(word) {
        if (!confirm(`Remove "${word}" from banned words list?`)) return;
        
        fetch('../api/hk/chat/remove_banned_word.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ word })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove from the banned words list
                document.querySelectorAll('.banned-word-item').forEach(item => {
                    if (item.querySelector('span').textContent === word) {
                        item.remove();
                    }
                });
            } else {
                alert('Error: ' + (data.message || 'Failed to remove banned word'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error removing banned word');
        });
    }
    
    // Setup event listeners
    function setupEventListeners() {
        // Search functionality
        function performSearch() {
            const searchTerm = messageSearch.value.trim();
            const currentFilter = document.querySelector('.filter-btn.active').dataset.filter;
            window.location.href = `chat.php?filter=${currentFilter}&search=${encodeURIComponent(searchTerm)}`;
        }
        
        messageSearch.addEventListener('keypress', function(e) {
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
                const filter = this.dataset.filter;
                const searchTerm = messageSearch.value.trim();
                window.location.href = `chat.php?filter=${filter}&search=${encodeURIComponent(searchTerm)}`;
            });
        });
        
        // Message action buttons (event delegation)
        document.addEventListener('click', function(e) {
            // Delete button
            if (e.target.closest('.btn-delete')) {
                const btn = e.target.closest('.btn-delete');
                const messageId = btn.dataset.id;
                deleteMessage(messageId);
            }
            
            // Restore button
            else if (e.target.closest('.btn-restore')) {
                const btn = e.target.closest('.btn-restore');
                const messageId = btn.dataset.id;
                restoreMessage(messageId);
            }
            
            // Ban user button
            else if (e.target.closest('.btn-ban')) {
                const btn = e.target.closest('.btn-ban');
                const username = btn.dataset.username;
                showBanUserModal(username);
            }
            
            // Flag button
            else if (e.target.closest('.btn-flag')) {
                const btn = e.target.closest('.btn-flag');
                const messageId = btn.dataset.id;
                toggleMessageFlag(messageId);
            }
            
            // Click on message to select user (but not on action buttons)
            else if (e.target.closest('.message') && !e.target.closest('.message-actions')) {
                const messageElement = e.target.closest('.message');
                const username = messageElement.querySelector('.username').textContent;
                selectUser(username);
            }
        });
        
        // Modal buttons
        chatSettingsBtn.addEventListener('click', () => modals.chatSettings.style.display = 'flex');
        bannedWordsBtn.addEventListener('click', () => modals.bannedWords.style.display = 'flex');
        
        // Add banned word
        addBannedWordBtn.addEventListener('click', addBannedWord);
        newBannedWordInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') addBannedWord();
        });
        
        // Delete banned word buttons (event delegation)
        document.addEventListener('click', function(e) {
            if (e.target.closest('.banned-word-item .btn-delete')) {
                const btn = e.target.closest('.btn-delete');
                const word = btn.dataset.word;
                removeBannedWord(word);
            }
        });
        
        // Clear chat confirmation
        confirmClearChatBtn.addEventListener('click', clearChat);
        
        // Modal close events
        modalCloseBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal-overlay');
                modal.style.display = 'none';
            });
        });
        
        modalCancelBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal-overlay');
                modal.style.display = 'none';
            });
        });
        
        // Close modal when clicking outside
        Object.values(modals).forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
        
        // Ban user form submission
        document.getElementById('banUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const username = document.getElementById('banUsername').value;
            const reason = document.getElementById('banReason').value;
            const duration = document.getElementById('banDuration').value;
            
            fetch('../api/hk/chat/ban_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ username, reason, duration })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`User ${username} has been banned for ${duration}`);
                    modals.banUser.style.display = 'none';
                    
                    // Update the user's status in the UI
                    if (selectedUser === username) {
                        const userStatus = userInfoCard.querySelector('.user-status');
                        userStatus.textContent = 'Banned';
                        userStatus.style.color = '#e74c3c';
                    }
                    
                    // Update ban count
                    const banCount = userInfoCard.querySelector('#userBanCount');
                    banCount.textContent = parseInt(banCount.textContent) + 1;
                    
                    // Reload messages to reflect banned status
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to ban user'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error banning user');
            });
        });
        
        // Chat settings form submission
        document.getElementById('chatSettingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const status = document.getElementById('chatStatus').value;
            const slowMode = document.getElementById('slowMode').value;
            const minLevel = document.getElementById('minLevel').value;
            const linkFiltering = document.getElementById('linkFiltering').value;
            const autoMod = document.getElementById('enableAutoMod').checked;
            
            fetch('../api/hk/chat/update_chat_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status,
                    slowMode,
                    minLevel,
                    linkFiltering,
                    autoMod
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Chat settings updated successfully');
                    modals.chatSettings.style.display = 'none';
                } else {
                    alert('Error: ' + (data.message || 'Failed to update chat settings'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating chat settings');
            });
        });
        
        // Quick action buttons
        document.getElementById('clearChatBtn').addEventListener('click', () => {
            modals.clearChat.style.display = 'flex';
        });
        
        document.getElementById('toggleChatBtn').addEventListener('click', () => {
            fetch('../api/hk/chat/toggle_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Chat has been ${data.enabled ? 'enabled' : 'disabled'}`);
                } else {
                    alert('Error: ' + (data.message || 'Failed to toggle chat'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error toggling chat');
            });
        });
        
        document.getElementById('slowModeBtn').addEventListener('click', () => {
            const duration = prompt('Enter slow mode duration in seconds (0 to disable):', '5');
            if (duration !== null && !isNaN(duration)) {
                fetch('../api/hk/chat/set_slow_mode.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ duration: parseInt(duration) })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Slow mode set to ${duration} seconds`);
                    } else {
                        alert('Error: ' + (data.message || 'Failed to set slow mode'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error setting slow mode');
                });
            }
        });
        
        document.getElementById('warnUserBtn').addEventListener('click', () => {
            if (!selectedUser) {
                alert('Please select a user first');
                return;
            }
            
            const reason = prompt('Enter warning reason:', 'Violation of chat rules');
            if (reason !== null) {
                fetch('../api/hk/chat/warn_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ username: selectedUser, reason })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`User ${selectedUser} has been warned`);
                        
                        // Update warning count
                        const warningCount = userInfoCard.querySelector('#userWarningCount');
                        warningCount.textContent = parseInt(warningCount.textContent) + 1;
                    } else {
                        alert('Error: ' + (data.message || 'Failed to warn user'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error warning user');
                });
            }
        });
        
        document.getElementById('timeoutUserBtn').addEventListener('click', () => {
            if (!selectedUser) {
                alert('Please select a user first');
                return;
            }
            
            const duration = prompt('Enter timeout duration in minutes:', '5');
            if (duration !== null && !isNaN(duration)) {
                fetch('../api/hk/chat/timeout_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ username: selectedUser, duration: parseInt(duration) })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`User ${selectedUser} has been timed out for ${duration} minutes`);
                    } else {
                        alert('Error: ' + (data.message || 'Failed to timeout user'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error timing out user');
                });
            }
        });
        
        document.getElementById('banUserBtn').addEventListener('click', () => {
            if (!selectedUser) {
                alert('Please select a user first');
                return;
            }
            
            showBanUserModal(selectedUser);
        });
    }
    
    // Initialize the chat
    initChat();
});