document.addEventListener('DOMContentLoaded', function() {
    const redeemForm = document.getElementById('redeemForm');
    const orderIdInput = document.getElementById('order-id');
    const orderResults = document.getElementById('orderResults');
    
    // Toast notification function
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
    
    if (redeemForm) {
        redeemForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const orderId = orderIdInput.value.trim();
            
            if (!orderId) {
                showToast('Please enter your Order ID', 'error');
                return;
            }
            
            // Validate format (XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX)
            if (!orderId.match(/^[A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{12}$/i)) {
                showToast('Please enter a valid Order ID in format XXXX-XXXX-XXXX-XXXX', 'error');
                return;
            }
            
            // Disable button during processing
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            // Make AJAX request
            fetch('redeem.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=redeem_order&order_id=${encodeURIComponent(orderId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showToast(data.message, 'success');
                    
                    // Update results section
                    document.getElementById('result-order-id').textContent = orderId;
                    document.getElementById('result-order-date').textContent = data.date;
                    document.getElementById('result-product-name').textContent = data.product;
                    document.getElementById('result-product-code').textContent = orderId;
                    
                    // Show results
                    orderResults.style.display = 'block';
                    
                    // Scroll to results
                    orderResults.scrollIntoView({ behavior: 'smooth' });
                    
                    // Clear form
                    redeemForm.reset();
                } else {
                    // Show error message
                    showToast(data.error, 'error');
                }
            })
            .catch(error => {
                showToast('Network error. Please try again.', 'error');
            })
            .finally(() => {
                // Reset button
                submitBtn.innerHTML = '<i class="fas fa-key"></i> Redeem Order';
                submitBtn.disabled = false;
            });
        });
    }
    
    // Auto-uppercase and format order ID
    if (orderIdInput) {
        orderIdInput.addEventListener('input', function() {
            // Convert to uppercase
            this.value = this.value.toUpperCase();
            
            // Auto-insert dashes
            if (this.value.length === 8 && !this.value.includes('-')) {
                this.value = this.value + '-';
            } else if (this.value.length === 13 && this.value.split('-').length === 2) {
                this.value = this.value + '-';
            } else if (this.value.length === 18 && this.value.split('-').length === 3) {
                this.value = this.value + '-';
            }
        });
    }
});