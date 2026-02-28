// redeem.js - Updated version with better form handling
document.addEventListener('DOMContentLoaded', function() {
    const redeemForm = document.querySelector('.redeem-form');
    const orderIdInput = document.getElementById('order-id');
    const redeemBtn = document.getElementById('redeem-btn');
    const redeemResult = document.getElementById('redeem-result');
    const redeemError = document.getElementById('redeem-error');
    const productList = document.getElementById('product-list');
    const errorMessage = document.getElementById('error-message');
    
    // Make sure we found all elements
    if (!redeemForm || !orderIdInput || !redeemBtn || !redeemResult || !redeemError || !productList || !errorMessage) {
        console.error('One or more required elements not found!');
        return;
    }

    // Handle button click directly (as well as form submission)
    redeemBtn.addEventListener('click', function(e) {
        e.preventDefault();
        processRedeem();
    });

    // Also handle form submission
    redeemForm.addEventListener('submit', function(e) {
        e.preventDefault();
        processRedeem();
    });
    
    function processRedeem() {
        const orderId = orderIdInput.value.trim();
        
        if (!orderId) {
            showError("Please enter your order ID");
            return;
        }
        
        // Validate order ID format (RLB- followed by 8 digits)
        const orderIdRegex = /^RLB-\d{8}$/;
        if (!orderIdRegex.test(orderId)) {
            showError("Invalid order ID format. Please use RLB- followed by 8 digits (e.g. RLB-12345678)");
            return;
        }
        
        // Show loading state
        redeemBtn.classList.add('loading');
        redeemBtn.disabled = true;
        
        // Simulate API call with timeout
        setTimeout(() => {
            // In a real app, you would make an actual API call here
            // For demo purposes, we'll randomly succeed or fail
            const isSuccess = Math.random() > 0.3; // 70% chance of success
            
            if (isSuccess) {
                // Success case
                redeemResult.style.display = 'block';
                redeemError.style.display = 'none';
                
                // Populate product list
                productList.innerHTML = '';
                const sampleProducts = [
                    { name: "Premium Cheat Package", license: "1 Month" },
                    { name: "Aimbot Module", license: "Lifetime" },
                    { name: "Wallhack Module", license: "Lifetime" },
                    { name: "Customer Role", license: "Permanent" }
                ];
                
                sampleProducts.forEach(product => {
                    const li = document.createElement('li');
                    li.innerHTML = `
                        <span>${product.name}</span>
                        <span>${product.license}</span>
                    `;
                    productList.appendChild(li);
                });
            } else {
                // Error case
                showError("The order ID you entered could not be found or has already been redeemed.");
            }
            
            // Reset loading state
            redeemBtn.classList.remove('loading');
            redeemBtn.disabled = false;
            
            // Scroll to result
            const resultElement = isSuccess ? redeemResult : redeemError;
            resultElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 1500);
    }
    
    function showError(message) {
        errorMessage.textContent = message;
        redeemError.style.display = 'block';
        redeemResult.style.display = 'none';
    }
    
    // Close error message when clicking the input
    orderIdInput.addEventListener('focus', function() {
        redeemError.style.display = 'none';
    });
});