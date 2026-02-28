document.addEventListener('DOMContentLoaded', function() {
    // Sample giveaway data
    const giveaways = [
        {
            id: 1,
            title: "Premium Mod Package",
            description: "Win our premium mod package worth $100! Includes all current and future mods for 1 year.",
            image: "https://via.placeholder.com/400x200?text=Premium+Mod+Package",
            status: "active",
            entries: 142,
            endsIn: "2 days 5 hours",
            requirements: [
                "Must be a registered user",
                "Minimum account age: 7 days",
                "Must have at least 1 successful purchase"
            ]
        },
        {
            id: 2,
            title: "Exclusive Skin Bundle",
            description: "Get all exclusive skins for your favorite characters in this amazing bundle!",
            image: "https://via.placeholder.com/400x200?text=Exclusive+Skin+Bundle",
            status: "upcoming",
            entries: 0,
            startsIn: "3 days",
            endsIn: "5 days",
            requirements: [
                "Must be a registered user",
                "Must follow us on social media"
            ]
        },
        {
            id: 3,
            title: "VIP Membership",
            description: "1 year of VIP membership with access to all premium features and early releases!",
            image: "https://via.placeholder.com/400x200?text=VIP+Membership",
            status: "ended",
            entries: 256,
            winner: "User123",
            requirements: [
                "Must be a registered user",
                "Must have at least 5 forum posts"
            ]
        },
        {
            id: 4,
            title: "Custom Character Mod",
            description: "Win a custom character mod designed just for you!",
            image: "https://via.placeholder.com/400x200?text=Custom+Character+Mod",
            status: "active",
            entries: 87,
            endsIn: "1 day 12 hours",
            requirements: [
                "Must be a registered user",
                "Must have made at least 3 purchases"
            ]
        }
    ];

    // DOM Elements
    const giveawaysGrid = document.getElementById('giveawaysGrid');
    const filterButtons = document.querySelectorAll('.filter-btn');
    const searchInput = document.querySelector('.search-box input');
    const giveawayModal = document.getElementById('giveawayModal');
    const createGiveawayModal = document.getElementById('createGiveawayModal');
    const createGiveawayBtn = document.getElementById('createGiveawayBtn');
    const enterGiveawayBtn = document.getElementById('enterGiveawayBtn');
    const giveawayForm = document.getElementById('giveawayForm');
    const addRequirementBtn = document.getElementById('addRequirementBtn');
    const requirementsList = document.getElementById('requirementsList');

    // Current filter
    let currentFilter = 'all';
    let currentSearch = '';

    // Initialize the page
    function init() {
        displayGiveaways();
        setupEventListeners();
    }

    // Set up event listeners
    function setupEventListeners() {
        // Filter buttons
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                filterButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.getAttribute('data-filter');
                displayGiveaways();
            });
        });

        // Search input
        searchInput.addEventListener('input', function() {
            currentSearch = this.value.toLowerCase();
            displayGiveaways();
        });

        // Create giveaway button
        if (createGiveawayBtn) {
            createGiveawayBtn.addEventListener('click', function() {
                createGiveawayModal.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        }

        // Modal close buttons
        document.querySelectorAll('.modal-close').forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.modal-overlay').classList.remove('active');
                document.body.style.overflow = '';
            });
        });

        // Enter giveaway button
        if (enterGiveawayBtn) {
            enterGiveawayBtn.addEventListener('click', function() {
                alert('You have successfully entered the giveaway! Good luck!');
                giveawayModal.classList.remove('active');
                document.body.style.overflow = '';
            });
        }

        // Add requirement button
        if (addRequirementBtn) {
            addRequirementBtn.addEventListener('click', addRequirementField);
        }

        // Form submission
        if (giveawayForm) {
            giveawayForm.addEventListener('submit', function(e) {
                e.preventDefault();
                createNewGiveaway();
            });
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
    }

    // Display giveaways based on filters
    function displayGiveaways() {
        giveawaysGrid.innerHTML = '';

        const filteredGiveaways = giveaways.filter(giveaway => {
            // Filter by status
            if (currentFilter !== 'all' && giveaway.status !== currentFilter) {
                return false;
            }
            
            // Filter by search
            if (currentSearch && !giveaway.title.toLowerCase().includes(currentSearch)) {
                return false;
            }
            
            return true;
        });

        if (filteredGiveaways.length === 0) {
            giveawaysGrid.innerHTML = '<div class="no-giveaways">No giveaways found matching your criteria</div>';
            return;
        }

        filteredGiveaways.forEach(giveaway => {
            const giveawayEl = createGiveawayElement(giveaway);
            giveawaysGrid.appendChild(giveawayEl);
        });
    }

    // Create giveaway card element
    function createGiveawayElement(giveaway) {
        const giveawayEl = document.createElement('div');
        giveawayEl.className = 'giveaway-card';
        
        let statusClass = '';
        let statusText = '';
        
        switch(giveaway.status) {
            case 'active':
                statusClass = 'status-active';
                statusText = 'Active';
                break;
            case 'upcoming':
                statusClass = 'status-upcoming';
                statusText = 'Upcoming';
                break;
            case 'ended':
                statusClass = 'status-ended';
                statusText = 'Ended';
                break;
        }
        
        giveawayEl.innerHTML = `
            <div class="giveaway-image">
                <img src="${giveaway.image}" alt="${giveaway.title}">
                <span class="giveaway-status ${statusClass}">${statusText}</span>
            </div>
            <div class="giveaway-content">
                <h3 class="giveaway-title">${giveaway.title}</h3>
                <p class="giveaway-description">${giveaway.description}</p>
                <div class="giveaway-meta">
                    <span class="giveaway-entries"><i class="fas fa-users"></i> ${giveaway.entries} Entries</span>
                    <span class="giveaway-ends"><i class="fas fa-clock"></i> ${giveaway.status === 'upcoming' ? 'Starts in: ' : giveaway.status === 'ended' ? 'Ended' : 'Ends in: '}${giveaway.endsIn || giveaway.startsIn || ''}</span>
                </div>
            </div>
            <div class="giveaway-actions">
                <button class="btn-enter-giveaway" data-id="${giveaway.id}">
                    <i class="fas fa-ticket-alt"></i> ${giveaway.status === 'ended' ? 'View Results' : giveaway.status === 'upcoming' ? 'Notify Me' : 'Enter Now'}
                </button>
            </div>
        `;
        
        // Add click event to the enter button
        giveawayEl.querySelector('.btn-enter-giveaway').addEventListener('click', function() {
            openGiveawayModal(giveaway.id);
        });
        
        return giveawayEl;
    }

    // Open giveaway modal with details
    function openGiveawayModal(giveawayId) {
        const giveaway = giveaways.find(g => g.id === giveawayId);
        if (!giveaway) return;
        
        // Update modal content
        document.getElementById('giveawayImage').src = giveaway.image;
        document.getElementById('giveawayTitle').textContent = giveaway.title;
        document.getElementById('entryCount').textContent = giveaway.entries;
        document.getElementById('endsIn').textContent = giveaway.endsIn || giveaway.startsIn || 'N/A';
        document.getElementById('giveawayDescription').textContent = giveaway.description;
        
        // Update requirements
        const requirementsEl = document.getElementById('giveawayRequirements');
        requirementsEl.innerHTML = '<h4>Requirements:</h4>';
        
        if (giveaway.requirements && giveaway.requirements.length > 0) {
            const ul = document.createElement('ul');
            giveaway.requirements.forEach(req => {
                const li = document.createElement('li');
                li.textContent = req;
                ul.appendChild(li);
            });
            requirementsEl.appendChild(ul);
        } else {
            requirementsEl.innerHTML += '<p>No special requirements</p>';
        }
        
        // Update enter button text
        const enterBtn = document.getElementById('enterGiveawayBtn');
        if (giveaway.status === 'ended') {
            enterBtn.innerHTML = '<i class="fas fa-trophy"></i> View Winner';
            enterBtn.style.backgroundColor = 'var(--warning)';
        } else if (giveaway.status === 'upcoming') {
            enterBtn.innerHTML = '<i class="fas fa-bell"></i> Notify Me';
            enterBtn.style.backgroundColor = 'var(--warning)';
        } else {
            enterBtn.innerHTML = '<i class="fas fa-ticket-alt"></i> Enter Giveaway';
            enterBtn.style.backgroundColor = 'var(--game-primary)';
        }
        
        // Open modal
        giveawayModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Add requirement field
    function addRequirementField() {
        const requirementItem = document.createElement('div');
        requirementItem.className = 'requirement-item';
        requirementItem.innerHTML = `
            <input type="text" placeholder="Enter requirement">
            <button type="button" class="btn-remove-requirement"><i class="fas fa-times"></i></button>
        `;
        
        requirementItem.querySelector('.btn-remove-requirement').addEventListener('click', function() {
            requirementItem.remove();
        });
        
        requirementsList.appendChild(requirementItem);
    }

    // Create new giveaway
    function createNewGiveaway() {
        const name = document.getElementById('giveawayName').value;
        const description = document.getElementById('giveawayDescription').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const image = document.getElementById('giveawayImage').value || 'https://via.placeholder.com/400x200?text=Giveaway+Image';
        
        const requirements = [];
        document.querySelectorAll('.requirement-item input').forEach(input => {
            if (input.value) requirements.push(input.value);
        });
        
        // Create new giveaway object
        const newGiveaway = {
            id: giveaways.length + 1,
            title: name,
            description: description,
            image: image,
            status: new Date(startDate) > new Date() ? 'upcoming' : 'active',
            entries: 0,
            startsIn: formatDateDifference(new Date(startDate)),
            endsIn: formatDateDifference(new Date(endDate)),
            requirements: requirements
        };
        
        // Add to giveaways array
        giveaways.unshift(newGiveaway);
        
        // Reset form
        giveawayForm.reset();
        requirementsList.innerHTML = '<div class="requirement-item"><input type="text" placeholder="Must be a registered user"><button type="button" class="btn-remove-requirement"><i class="fas fa-times"></i></button></div>';
        
        // Close modal
        createGiveawayModal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Refresh display
        currentFilter = 'all';
        filterButtons.forEach(btn => btn.classList.remove('active'));
        document.querySelector('.filter-btn[data-filter="all"]').classList.add('active');
        displayGiveaways();
        
        alert('Giveaway created successfully!');
    }

    // Helper function to format date difference
    function formatDateDifference(date) {
        const now = new Date();
        const diff = date - now;
        
        if (diff <= 0) return 'Ended';
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        
        return `${days} days ${hours} hours`;
    }

    // Initialize the application
    init();
});