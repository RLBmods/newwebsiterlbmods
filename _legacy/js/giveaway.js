document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const giveawaysGrid = document.getElementById('giveawaysGrid');
    const filterButtons = document.querySelectorAll('.filter-btn');
    const searchInput = document.getElementById('searchInput');
    const giveawayModal = document.getElementById('giveawayModal');
    const createGiveawayModal = document.getElementById('createGiveawayModal');
    const createGiveawayBtn = document.getElementById('createGiveawayBtn');
    const enterGiveawayBtn = document.getElementById('enterGiveawayBtn');
    const giveawayForm = document.getElementById('giveawayForm');
    const addRequirementBtn = document.getElementById('addRequirementBtn');
    const createRequirementsList = document.getElementById('createRequirementsList');
    const cancelGiveawayBtn = document.getElementById('cancelGiveawayBtn');
    const winnerSection = document.getElementById('winnerSection');

    // Current filter and search
    let currentFilter = 'all';
    let currentSearch = '';
    let currentGiveawayId = null;
    let giveaways = [];

    // Initialize the page
    function init() {
        loadGiveaways();
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
                loadGiveaways();
            });
        });

        // Search input
        searchInput.addEventListener('input', function() {
            currentSearch = this.value;
            debounceSearch();
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
            button.addEventListener('click', closeAllModals);
        });

        // Cancel giveaway button
        cancelGiveawayBtn.addEventListener('click', closeAllModals);

        // Enter giveaway button
        enterGiveawayBtn.addEventListener('click', function() {
            if (currentGiveawayId) {
                enterGiveaway(currentGiveawayId);
            }
        });

        // Add requirement button
        addRequirementBtn.addEventListener('click', addRequirementField);

        // Form submission
        giveawayForm.addEventListener('submit', function(e) {
            e.preventDefault();
            createNewGiveaway();
        });

        // Close modals when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeAllModals();
                }
            });
        });

        // Open giveaway modal when clicking enter button
        giveawaysGrid.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-enter-giveaway');
            if (btn) {
                const giveawayId = parseInt(btn.getAttribute('data-id'));
                openGiveawayModal(giveawayId);
            }
        });
    }

    // Load giveaways from server
    function loadGiveaways() {
        giveawaysGrid.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Loading giveaways...</p>
            </div>
        `;
        
        fetch('giveaway.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_giveaways&filter=${currentFilter}&search=${encodeURIComponent(currentSearch)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                giveaways = data.giveaways;
                displayGiveaways();
            } else {
                showError(data.error || 'Failed to load giveaways');
            }
        })
        .catch(error => {
            showError('Network error. Please try again.');
        });
    }

    // Display giveaways
    function displayGiveaways() {
        giveawaysGrid.innerHTML = '';

        if (giveaways.length === 0) {
            giveawaysGrid.innerHTML = '<div class="no-giveaways">No giveaways found matching your criteria</div>';
            return;
        }

        giveaways.forEach(giveaway => {
            const giveawayEl = createGiveawayElement(giveaway);
            giveawaysGrid.appendChild(giveawayEl);
        });
    }

    // Create giveaway card element
    function createGiveawayElement(giveaway) {
        const giveawayEl = document.createElement('div');
        giveawayEl.className = 'giveaway-card';
        
        // Determine status
        const now = new Date();
        const startDate = new Date(giveaway.start_date);
        const endDate = new Date(giveaway.end_date);
        
        let status, statusText, timeRemaining;
        
        if (now < startDate) {
            status = 'upcoming';
            statusText = 'Upcoming';
            timeRemaining = formatTimeDifference(now, startDate);
        } else if (now <= endDate) {
            status = 'active';
            statusText = 'Active';
            timeRemaining = formatTimeDifference(now, endDate);
        } else {
            status = 'ended';
            statusText = 'Ended';
            timeRemaining = 'Ended on ' + endDate.toLocaleDateString();
        }
        
        giveawayEl.innerHTML = `
            <div class="giveaway-image">
                <img src="${giveaway.image_url || 'https://placehold.co/800x800'}" alt="${giveaway.title}">
                <span class="giveaway-status status-${status}">${statusText}</span>
            </div>
            <div class="giveaway-content">
                <h3 class="giveaway-title">${giveaway.title}</h3>
                <p class="giveaway-description">${giveaway.description}</p>
                <div class="giveaway-meta">
                    <span class="giveaway-entries"><i class="fas fa-users"></i> ${giveaway.entries} Entries</span>
                    <span class="giveaway-ends"><i class="fas fa-clock"></i> ${status === 'upcoming' ? 'Starts in: ' : status === 'ended' ? 'Ended' : 'Ends in: '}${timeRemaining}</span>
                </div>
            </div>
            <div class="giveaway-actions">
                <button class="btn-enter-giveaway" data-id="${giveaway.id}">
                    <i class="fas ${status === 'ended' ? 'fa-trophy' : status === 'upcoming' ? 'fa-bell' : 'fa-ticket-alt'}"></i> 
                    ${status === 'ended' ? 'View Winner' : status === 'upcoming' ? 'Notify Me' : (giveaway.has_entered ? 'Entered' : 'Enter Now')}
                </button>
            </div>
        `;
        
        return giveawayEl;
    }

    // Open giveaway modal with details
    function openGiveawayModal(giveawayId) {
        const giveaway = giveaways.find(g => g.id === giveawayId);
        if (!giveaway) return;
        
        currentGiveawayId = giveawayId;
        
        // Determine status
        const now = new Date();
        const startDate = new Date(giveaway.start_date);
        const endDate = new Date(giveaway.end_date);
        let status, statusText, timeRemaining;
        
        if (now < startDate) {
            status = 'upcoming';
            statusText = 'Upcoming';
            timeRemaining = formatTimeDifference(now, startDate);
        } else if (now <= endDate) {
            status = 'active';
            statusText = 'Active';
            timeRemaining = formatTimeDifference(now, endDate);
        } else {
            status = 'ended';
            statusText = 'Ended';
            timeRemaining = 'Ended on ' + endDate.toLocaleDateString();
        }
        
        // Update left modal content
        document.getElementById('giveawayImage').src = giveaway.image_url || 'https://placehold.co/800x800';
        document.getElementById('giveawayTitle').textContent = giveaway.title;
        document.getElementById('entryCount').textContent = giveaway.entries;
        
        const timeLabel = document.getElementById('timeLabel');
        const endsIn = document.getElementById('endsIn');
        
        if (status === 'upcoming') {
            timeLabel.textContent = 'Starts in';
            endsIn.textContent = timeRemaining;
        } else if (status === 'ended') {
            timeLabel.textContent = 'Ended on';
            endsIn.textContent = endDate.toLocaleDateString();
        } else {
            timeLabel.textContent = 'Ends in';
            endsIn.textContent = timeRemaining;
        }
        
        document.getElementById('giveawayDescription').textContent = giveaway.description;
        
        // Update status badge
        const statusBadge = document.getElementById('giveawayStatusBadge');
        statusBadge.textContent = statusText;
        statusBadge.className = 'giveaway-status-badge';
        statusBadge.classList.add(`status-${status}`);
        
        // Update right modal content
        const rightModalTitle = document.getElementById('rightModalTitle');
        const rightModalContent = document.getElementById('rightModalContent');
        
        if (status === 'ended') {
            // Show winner information
            rightModalTitle.textContent = 'Winner';
            
            let winnerHTML = `
                <div class="winner-section">
                    <div class="winner-details">
                        <div class="winner-avatar">
                            ${giveaway.winner_name ? giveaway.winner_name.charAt(0).toUpperCase() : '<i class="fas fa-user"></i>'}
                        </div>
                        <div class="winner-info">
                            <div class="winner-name">${giveaway.winner_name || 'No winner selected'}</div>
                            <div class="winner-date">${giveaway.winner_date ? `Won on ${new Date(giveaway.winner_date).toLocaleDateString()}` : 'Check back later'}</div>
                        </div>
                    </div>
                </div>
            `;
            
            rightModalContent.innerHTML = winnerHTML;
        } else {
            // Show requirements
            rightModalTitle.textContent = 'Requirements';
            
            let requirementsHTML = '<ul class="requirements-list">';
            
            if (giveaway.requirements && giveaway.requirements.length > 0) {
                giveaway.requirements.forEach(req => {
                    requirementsHTML += `
                        <li class="requirement-item">
                            <i class="fas fa-check-circle requirement-icon"></i>
                            <span>${req}</span>
                        </li>
                    `;
                });
            } else {
                requirementsHTML += '<li class="requirement-item"><span>No special requirements</span></li>';
            }
            
            requirementsHTML += '</ul>';
            rightModalContent.innerHTML = requirementsHTML;
        }
        
        // Update enter button
        const enterBtn = document.getElementById('enterGiveawayBtn');
        if (status === 'ended') {
            enterBtn.innerHTML = '<i class="fas fa-trophy"></i> View Winner';
            enterBtn.className = 'btn btn-modal-primary';
            enterBtn.disabled = false;
        } else if (status === 'upcoming') {
            enterBtn.innerHTML = '<i class="fas fa-bell"></i> Notify Me';
            enterBtn.className = 'btn btn-modal-primary';
            enterBtn.disabled = false;
        } else {
            if (giveaway.has_entered) {
                enterBtn.innerHTML = '<i class="fas fa-check"></i> Already Entered';
                enterBtn.className = 'btn btn-modal-secondary';
                enterBtn.disabled = true;
            } else {
                enterBtn.innerHTML = '<i class="fas fa-ticket-alt"></i> Enter Giveaway';
                enterBtn.className = 'btn btn-modal-primary';
                enterBtn.disabled = false;
            }
        }
        
        // Open modal
        document.getElementById('giveawayModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Enter a giveaway
    function enterGiveaway(giveawayId) {
        enterGiveawayBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        enterGiveawayBtn.disabled = true;
        
        fetch('giveaway.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=enter_giveaway&giveaway_id=${giveawayId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess('You have successfully entered the giveaway! Good luck!');
                loadGiveaways();
                closeAllModals();
            } else {
                showError(data.error || 'Failed to enter giveaway');
                enterGiveawayBtn.innerHTML = '<i class="fas fa-ticket-alt"></i> Enter Giveaway';
                enterGiveawayBtn.disabled = false;
            }
        })
        .catch(error => {
            showError('Network error. Please try again.');
            enterGiveawayBtn.innerHTML = '<i class="fas fa-ticket-alt"></i> Enter Giveaway';
            enterGiveawayBtn.disabled = false;
        });
    }

    // Create new giveaway
    function createNewGiveaway() {
        const name = document.getElementById('giveawayName').value;
        const description = document.getElementById('giveawayDesc').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const image = document.getElementById('prizeImage').value || 'https://placehold.co/800x800';
        
        const requirements = [];
        document.querySelectorAll('#createRequirementsList .requirement-item input').forEach(input => {
            if (input.value) requirements.push(input.value);
        });
        
        const submitBtn = giveawayForm.querySelector('.btn-submit');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
        submitBtn.disabled = true;
        
        fetch('giveaway.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=create_giveaway&title=${encodeURIComponent(name)}&description=${encodeURIComponent(description)}&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&image_url=${encodeURIComponent(image)}&requirements=${encodeURIComponent(JSON.stringify(requirements))}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess('Giveaway created successfully!');
                giveawayForm.reset();
                createRequirementsList.innerHTML = '<div class="requirement-item"><input type="text" placeholder="Must be a registered user"><button type="button" class="btn-remove-requirement"><i class="fas fa-times"></i></button></div>';
                closeAllModals();
                loadGiveaways();
            } else {
                showError(data.error || 'Failed to create giveaway');
                submitBtn.innerHTML = 'Create Giveaway';
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            showError('Network error. Please try again.');
            submitBtn.innerHTML = 'Create Giveaway';
            submitBtn.disabled = false;
        });
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
        
        createRequirementsList.appendChild(requirementItem);
    }

    // Close all modals
    function closeAllModals() {
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    }

    // Format time difference
    function formatTimeDifference(now, futureDate) {
        const diff = futureDate - now;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        
        if (days > 0) {
            return `${days} day${days !== 1 ? 's' : ''} ${hours} hour${hours !== 1 ? 's' : ''}`;
        } else if (hours > 0) {
            return `${hours} hour${hours !== 1 ? 's' : ''}`;
        } else {
            return 'Less than an hour';
        }
    }

    // Debounce search input
    let debounceTimer;
    function debounceSearch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            loadGiveaways();
        }, 500);
    }

    // Show success message
    function showSuccess(message) {
        const toast = document.createElement('div');
        toast.className = 'toast toast-success';
        toast.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    // Show error message
    function showError(message) {
        const toast = document.createElement('div');
        toast.className = 'toast toast-error';
        toast.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    // Initialize the application
    init();
});