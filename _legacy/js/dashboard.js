document.addEventListener('DOMContentLoaded', function() {
    // Read more functionality
    const readMoreButtons = document.querySelectorAll('.read-more');
    readMoreButtons.forEach(button => {
        button.addEventListener('click', function() {
            const newsItem = this.closest('.news-item');
            const excerpt = newsItem.querySelector('.news-excerpt');
            
            if (excerpt.classList.contains('expanded')) {
                excerpt.classList.remove('expanded');
                this.textContent = 'Read More';
            } else {
                excerpt.classList.add('expanded');
                this.textContent = 'Show Less';
            }
        });
    });

    // Chat functionality
    const chatInput = document.querySelector('.chat-input input');
    const sendBtn = document.querySelector('.send-btn');
    const chatMessages = document.querySelector('.chat-messages');

    function addMessage(text, isUser = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message';
        
        const now = new Date();
        const hours = now.getHours();
        const minutes = now.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const formattedHours = hours % 12 || 12;
        const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
        const timeString = `${formattedHours}:${formattedMinutes} ${ampm}`;
        
        if (isUser) {
            messageDiv.innerHTML = `
                <div class="message-avatar">U</div>
                <div class="message-content">
                    <div class="message-header">
                        <span class="username">You</span>
                        <span class="timestamp">${timeString}</span>
                    </div>
                    <p class="message-text">${text}</p>
                </div>
            `;
        } else {
            const users = ['ToxicPlayer', 'ProGamer', 'NoobSlayer', 'CheatMaster'];
            const randomUser = users[Math.floor(Math.random() * users.length)];
            const avatar = randomUser.charAt(0);
            
            messageDiv.innerHTML = `
                <div class="message-avatar">${avatar}</div>
                <div class="message-content">
                    <div class="message-header">
                        <span class="username">${randomUser}</span>
                        <span class="timestamp">${timeString}</span>
                    </div>
                    <p class="message-text">${text}</p>
                </div>
            `;
        }
        
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function sendMessage() {
        const message = chatInput.value.trim();
        if (message) {
            addMessage(message, true);
            chatInput.value = '';
            
            // Simulate response after a short delay
            setTimeout(() => {
                const responses = [
                    "Nice! How are the new features working for you?",
                    "Try adjusting your aimbot settings for better results",
                    "The devs are working on a fix for that",
                    "Make sure to use the spoofer before launching",
                    "Have you checked the #announcements channel?",
                    "That's against our rules, please stop",
                    "Our cheat is currently undetected, you're safe"
                ];
                const randomResponse = responses[Math.floor(Math.random() * responses.length)];
                addMessage(randomResponse);
            }, 1000 + Math.random() * 2000);
        }
    }

    // Send message on button click
    sendBtn.addEventListener('click', sendMessage);
    
    // Send message on Enter key
    chatInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    // Add some initial messages
    setTimeout(() => {
        addMessage("Welcome to the RLB Cheats community chat! Keep it clean");
    }, 500);
    
    setTimeout(() => {
        addMessage("New Fortnite update dropping tomorrow, prepare for patches");
    }, 2000);
    
    setTimeout(() => {
        addMessage("Anyone having issues with the latest loader update?");
    }, 3500);
    
    // Simulate random chat activity
    setInterval(() => {
        if (Math.random() > 0.7) {
            const messages = [
                "Just got a 20 kill game with the new aimbot!",
                "When is the Valorant cheat coming?",
                "My game keeps crashing after injection",
                "How do I change ESP colors?",
                "The spoofer worked perfectly, thanks devs!",
                "Is anyone else getting lag with the new update?"
            ];
            const randomMessage = messages[Math.floor(Math.random() * messages.length)];
            addMessage(randomMessage);
        }
    }, 8000);
});