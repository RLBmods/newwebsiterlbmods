// admin-dashboard.js - Admin dashboard functionality

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Quick action cards click handlers
    document.querySelectorAll('.quick-action-card').forEach(card => {
        card.addEventListener('click', function() {
            const action = this.querySelector('h3').textContent;
            console.log(`Action: ${action}`);
            // Add your specific action handlers here
            alert(`Action triggered: ${action}`);
        });
    });

    // Admin table row click handler (for details)
    document.querySelectorAll('.admin-table tbody tr').forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('.btn-admin')) {
                const userId = this.querySelector('td').textContent.trim();
                console.log(`View details for: ${userId}`);
                // Add your detail view logic here
            }
        });
    });

    // Notification bell
    document.querySelector('.notification-bell').addEventListener('click', function() {
        // Add notification dropdown toggle logic
        console.log('Notifications clicked');
    });
});
