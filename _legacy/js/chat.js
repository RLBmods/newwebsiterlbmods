class ChatSystem {
    constructor() {
        this.chatContainer = document.getElementById('chat-messages');
        this.messageInput = document.getElementById('chat-message');
        this.chatForm = document.getElementById('chat-form');
        this.emojiBtn = document.querySelector('.emoji-picker-btn');
        this.mentionDropdown = document.createElement('div');
        
        if (!this.chatContainer || !this.messageInput || !this.chatForm) {
            console.error('Required chat elements not found');
            return;
        }
        
        this.lastMessageId = 0;
        this.isSending = false;
        this.pollInterval = 3000;
        this.mentionCheckInterval = 10000;
        this.isAtBottom = true;
        this.currentUsername = document.body.dataset.username;
        this.onlineUsers = [];

        // Audio handling
        this.audioElement = new Audio('https://s.compilecrew.xyz/assets/sounds/mention.mp3');
        this.audioElement.preload = 'auto';
        this.audioUnlocked = false;
        
        this.init();
        this.setupAudioUnlock();
    }


    
    setupAudioUnlock() {
        const unlock = () => {
            if (this.audioUnlocked) return;
            
            // "Warm up" the audio system by playing silent audio
            this.audioElement.volume = 0.001; // Very low volume instead of 0
            this.audioElement.play()
                .then(() => {
                    this.audioElement.pause();
                    this.audioElement.currentTime = 0;
                    this.audioElement.volume = 1;
                    this.audioUnlocked = true;
                    console.log("Audio unlocked successfully");
                })
                .catch(e => {
                    console.log("Audio warmup failed, will try again on user interaction:", e);
                    // Don't mark as unlocked, let it try again on next interaction
                });
        };
    
        // Listen for user interactions
        const events = ['click', 'keydown', 'mousedown', 'touchstart'];
        events.forEach(event => {
            document.addEventListener(event, unlock, { once: true });
        });
    }

    playMentionSound() {
        if (!this.audioUnlocked) {
            console.log("Audio not unlocked yet - waiting for user interaction");
            return;
        }
        
        try {
            this.audioElement.currentTime = 0; // Rewind to start
            this.audioElement.play()
                .then(() => console.log("Mention sound played"))
                .catch(e => console.error("Sound play failed:", e));
        } catch (e) {
            console.error("Audio playback error:", e);
        }
    }

    init() {
        this.fetchInitialMessages();
        this.setupEventListeners();
        this.initPolling();
        this.initMentionDropdown();
        this.requestNotificationPermission();
        this.setupAvatarFallbacks();
        
        // Wait for DOM to be fully ready before checking mute status
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(() => this.checkMuteStatus(), 500);
            });
        } else {
            setTimeout(() => this.checkMuteStatus(), 500);
        }
        
        // Check mute status every minute
        this.muteCheckInterval = setInterval(() => this.checkMuteStatus(), 60000);
    }
    
    setupEventListeners() {
        this.chatForm.addEventListener('submit', (e) => this.handleSubmit(e));
        this.chatContainer.addEventListener('scroll', this.handleScroll.bind(this));
        this.messageInput.addEventListener('input', this.handleInput.bind(this));
        this.emojiBtn.addEventListener('click', () => this.toggleEmojiPicker());
    }
    
    initPolling() {
        this.messagePoll = setInterval(() => this.checkForNewMessages(), this.pollInterval);
        this.mentionPoll = setInterval(() => this.checkForMentions(), this.mentionCheckInterval);
    }
    
    initMentionDropdown() {
        this.mentionDropdown.className = 'mention-dropdown';
        document.body.appendChild(this.mentionDropdown);
        this.fetchOnlineUsers();
    }
    
    async fetchInitialMessages() {
        try {
            const response = await fetch('blades/chat/list_chat.php');
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.text();
            if (data) {
                this.chatContainer.innerHTML = data;
                this.scrollToBottom();
                this.updateLastMessageId();
                this.highlightOwnMentions();
            }
        } catch (error) {
            console.error('Error fetching initial messages:', error);
        }
    }
    
    async checkForNewMessages() {
        if (this.lastMessageId === 0) return;
        
        try {
            const response = await fetch(`blades/chat/get_new_messages.php?last_id=${this.lastMessageId}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.text();
            if (data) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data;
                
                while (tempDiv.firstChild) {
                    this.chatContainer.appendChild(tempDiv.firstChild);
                }
                
                this.updateLastMessageId();
                if (this.isAtBottom) this.scrollToBottom();
                this.highlightOwnMentions();
            }
        } catch (error) {
            console.error('Error checking for new messages:', error);
        }
    }
    
    // Add this method to your ChatSystem class
    async checkMuteStatus() {
        try {
            const response = await fetch('blades/chat/check_mute_status.php');
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Mute status check returned non-JSON:', text.substring(0, 100));
                return;
            }
            
            const data = await response.json();
            
            if (data.error) {
                console.error('Mute status error:', data.error);
                return;
            }
            
            if (data.muted) {
                //this.showMuteStatus(data);
            } else {
                //this.hideMuteStatus();
            }
        } catch (error) {
            console.error('Error checking mute status:', error);
        }
    }

// Add to your init method
init() {
    this.fetchInitialMessages();
    this.setupEventListeners();
    this.initPolling();
    this.initMentionDropdown();
    this.requestNotificationPermission();
    this.checkMuteStatus(); // Check mute status on init
    
    // Check mute status every minute
    this.muteCheckInterval = setInterval(() => this.checkMuteStatus(), 60000);
}

// Add to your destroy method
destroy() {
    clearInterval(this.messagePoll);
    clearInterval(this.mentionPoll);
    clearInterval(this.muteCheckInterval);
    this.chatForm.removeEventListener('submit', this.handleSubmit);
    document.body.removeChild(this.mentionDropdown);
    this.hideMuteStatus();
}

    async checkForMentions() {
        if (!this.currentUsername) return;
        
        try {
            const response = await fetch('blades/chat/check_mentions.php');
            const data = await response.json();
            
            if (data.mentions && data.mentions.length > 0) {
                this.playMentionSound();
                this.showMentionNotification(data.mentions);
            }
        } catch (error) {
            console.error('Error checking mentions:', error);
        }
    }
    
    highlightOwnMentions() {
        if (!this.currentUsername) return;
        
        document.querySelectorAll('.mention').forEach(mention => {
            if (mention.textContent === `@${this.currentUsername}`) {
                const message = mention.closest('.message');
                if (message) {
                    message.classList.add('mentioned');
                    setTimeout(() => message.classList.remove('mentioned'), 5000);
                }
            }
        });
    }
    
    showMentionNotification(mentions) {
        // Highlight messages in chat
        mentions.forEach(mention => {
            const message = document.querySelector(`.message[data-id="${mention.id}"]`);
            if (message) {
                message.classList.add('mentioned');
                setTimeout(() => message.classList.remove('mentioned'), 5000);
                if (!this.isAtBottom) message.scrollIntoView({ behavior: 'smooth' });
            }
        });
        
        // Desktop notification
        if (Notification.permission === "granted") {
            const notification = new Notification(`You were mentioned (${mentions.length}x)`, {
                body: `${mentions[0].sender}: ${mentions[0].message.substring(0, 100)}...`,
                icon: '/assets/icons/notification.png'
            });
            
            notification.onclick = () => {
                window.focus();
                const lastMention = mentions[mentions.length - 1];
                const message = document.querySelector(`.message[data-id="${lastMention.id}"]`);
                if (message) message.scrollIntoView({ behavior: 'smooth' });
            };
        }
    }

    setupAvatarFallbacks() {
        document.addEventListener('error', function(e) {
            if (e.target.tagName === 'IMG' && e.target.src.includes('/assets/avatars/')) {
                e.target.src = '/assets/avatars/default-avatar.png';
            }
        }, true);
    }
    
    async fetchOnlineUsers() {
        try {
            const response = await fetch('blades/chat/get_online_users.php');
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Online users returned non-JSON:', text.substring(0, 100));
                return;
            }
            
            const data = await response.json();
            
            if (data.error) {
                console.error('Online users error:', data.error);
                return;
            }
            
            this.onlineUsers = data.users || [];
            this.updateMentionDropdown();
        } catch (error) {
            console.error('Error fetching online users:', error);
        }
    }
    
    updateMentionDropdown() {
        this.mentionDropdown.innerHTML = this.onlineUsers.map(user => {
            // Fix avatar path - ensure it's just the filename
            let avatarPath = user.avatar || 'default-avatar.png';
            
            // Remove any leading slashes or duplicate paths
            avatarPath = avatarPath.replace(/^\/+/, '')
                                  .replace(/^assets\/avatars\//, '')
                                  .replace(/^\/assets\/avatars\//, '');
            
            return `<div class="mention-option" data-username="${user.username}">
                <img src="/assets/avatars/${avatarPath}" 
                     alt="${user.username}" 
                     onerror="this.onerror=null;this.src='/assets/avatars/default-avatar.png'">
                <span>${user.username}</span>
                ${user.role ? `<span class="mention-role ${user.role}">${user.role}</span>` : ''}
            </div>`;
        }).join('');
    }
    
    handleScroll() {
        const { scrollTop, scrollHeight, clientHeight } = this.chatContainer;
        this.isAtBottom = scrollHeight - scrollTop <= clientHeight + 5;
    }
    
    handleInput(e) {
        const text = e.target.value;
        const cursorPos = e.target.selectionStart;
        
        // Show mention dropdown when @ is typed
        if (text[cursorPos - 1] === '@') {
            const rect = this.messageInput.getBoundingClientRect();
            this.mentionDropdown.style.display = 'block';
            this.mentionDropdown.style.top = `${rect.top - this.mentionDropdown.offsetHeight - 10}px`;
            this.mentionDropdown.style.left = `${rect.left + cursorPos * 8}px`;
            
            // Add click handler for mention options
            this.mentionDropdown.querySelectorAll('.mention-option').forEach(option => {
                option.addEventListener('click', () => {
                    const username = option.dataset.username;
                    const currentValue = this.messageInput.value;
                    const cursorPos = this.messageInput.selectionStart;
                    
                    // Replace @ with @username
                    const before = currentValue.substring(0, cursorPos - 1);
                    const after = currentValue.substring(cursorPos);
                    this.messageInput.value = before + '@' + username + ' ' + after;
                    
                    // Focus back on input
                    this.messageInput.focus();
                    this.messageInput.selectionStart = cursorPos + username.length;
                    this.messageInput.selectionEnd = cursorPos + username.length;
                    
                    this.mentionDropdown.style.display = 'none';
                });
            });
        } else {
            this.mentionDropdown.style.display = 'none';
        }
    }
    
    async handleSubmit(e) {
        e.preventDefault();
        if (this.isSending || !this.messageInput.value.trim()) return;
        
        this.isSending = true;
        const message = this.messageInput.value.trim();
        
        try {
            // First verify mentions
            const mentions = message.match(/@(\w+)/g) || [];
            let invalidMentions = [];
            
            if (mentions.length > 0) {
                try {
                    const response = await fetch('blades/chat/verify_mentions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            mentions: mentions.map(m => m.substring(1)) // Remove @
                        })
                    });
                    
                    // Check if response is JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        throw new Error(`Server returned: ${text.substring(0, 100)}`);
                    }
                    
                    const data = await response.json();
                    
                    if (!response.ok) {
                        throw new Error(data.error || 'Failed to verify mentions');
                    }
                    
                    invalidMentions = data.invalid || [];
                } catch (error) {
                    console.error('Mention verification error:', error);
                    throw new Error('Failed to verify mentions');
                }
            }
            
            if (invalidMentions.length > 0) {
                this.chatForm.classList.add('shake');
                setTimeout(() => {
                    this.chatForm.classList.remove('shake');
                }, 500);
                
                let newMessage = message;
                invalidMentions.forEach(user => {
                    newMessage = newMessage.replace(new RegExp(`@${user}\\b`, 'g'), '@unknown');
                });
                this.messageInput.value = newMessage;
                
                throw new Error(`Cannot mention non-existent users: ${invalidMentions.join(', ')}`);
            }
            
            // Send the message
            const response = await fetch('blades/chat/send_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message })
            });
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(`Server returned: ${text.substring(0, 100)}`);
            }
            
            const data = await response.json();
            
            if (!response.ok) {
                // Handle mute errors specifically
                if (data.error && data.error.includes('muted')) {
                    this.showMuteError(data.error);
                    throw new Error(data.error);
                }
                throw new Error(data.error || 'Failed to send message');
            }
            
            this.messageInput.value = '';
            this.mentionDropdown.style.display = 'none';
            await this.checkForNewMessages();
        } catch (error) {
            console.error('Error sending message:', error);
            
            // Don't show alert for mute errors (they're handled by showMuteError)
            if (!error.message.includes('muted') && !error.message.includes('non-existent users')) {
                this.showError(error.message);
            }
        } finally {
            this.isSending = false;
        }
    }
        
    showMuteError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'chat-error mute-error';
        errorDiv.innerHTML = `
            <i class="fas fa-microphone-slash"></i>
            <span>${message}</span>
        `;
        
        // Try multiple locations to insert the error
        const chatContainer = document.querySelector('.chat-messages');
        const chatPanel = document.querySelector('.chat-panel');
        
        if (chatContainer) {
            chatContainer.insertBefore(errorDiv, chatContainer.firstChild);
        } else if (chatPanel) {
            chatPanel.insertBefore(errorDiv, chatPanel.firstChild);
        } else {
            console.error('Could not find location to insert mute error');
            return;
        }
        
        // Auto-remove after 8 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 8000);
    }
    
    showError(message, className = 'error') {
        const errorDiv = document.createElement('div');
        errorDiv.className = `chat-error ${className}`;
        errorDiv.textContent = message;
        
        // Insert error message at the top of chat
        const chatContainer = document.querySelector('.chat-container');
        if (chatContainer) {
            chatContainer.insertBefore(errorDiv, chatContainer.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.remove();
                }
            }, 5000);
        }
    }
    
    requestNotificationPermission() {
        if (!("Notification" in window)) return;
        if (Notification.permission !== "granted" && Notification.permission !== "denied") {
            Notification.requestPermission().then(permission => {
                console.log('Notification permission:', permission);
            });
        }
    }
    
    scrollToBottom() {
        this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
    }
    
    updateLastMessageId() {
        const messages = this.chatContainer.querySelectorAll('.message');
        if (messages.length > 0) {
            this.lastMessageId = parseInt(messages[messages.length - 1].dataset.id) || 0;
        }
    }
    
    destroy() {
        clearInterval(this.messagePoll);
        clearInterval(this.mentionPoll);
        this.chatForm.removeEventListener('submit', this.handleSubmit);
        document.body.removeChild(this.mentionDropdown);
    }
}

// Every minute, update activity status
setInterval(() => {
    fetch('/blades/chat/update_activity.php');
}, 60000);

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => new ChatSystem());