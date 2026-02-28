document.addEventListener('DOMContentLoaded', function() {
    // Initialize Notyf for notifications
    const notyf = new Notyf({
        duration: 3000,
        position: { x: 'right', y: 'bottom' },
        dismissible: true
    });

    // Current selected product
    let currentProduct = null;
    let currentProductId = null;
    let userBalance = parseFloat(document.querySelector('.user-balance').textContent.replace('$', '')) || 0;
    
    // Purchase history pagination
    let currentPurchaseHistoryPage = 1;
    const purchasesPerPage = 7;

    // Modal elements
    const modalOverlay = document.querySelector('.product-modal-overlay');
    const modal = document.querySelector('.product-modal');
    const closeBtn = document.querySelector('.modal-close-btn');
    const productsGrid = document.querySelector('.products-grid');
    
    // Product modal elements
    const modalImage = document.getElementById('modal-product-image');
    const modalTitle = document.getElementById('modal-product-title');
    const modalCurrentPrice = document.getElementById('modal-current-price');
    //const modalOriginalPrice = document.getElementById('modal-original-price');
    const modalDescription = document.getElementById('modal-product-description');
    const modalFeatures = document.getElementById('modal-product-features');
    const durationOptions = document.querySelectorAll('.duration-option');
    const ratingCount = document.querySelector('.rating-count');
    
    // Buy now button
    const buyNowBtn = document.querySelector('.btn-buy-now');

    // License modal elements
    const licenseModal = document.querySelector('.license-modal-overlay');
    const licenseKeyDisplay = document.getElementById('license-key-display');
    const licenseExpiryInfo = document.getElementById('license-expiry-info');

    // Initialize the page
    fetchProducts();
    fetchPurchaseHistory();
    setupEventListeners();

    function setupEventListeners() {
        // Close modal
        closeBtn.addEventListener('click', closeModal);
        modalOverlay.addEventListener('click', function(e) {
            if (e.target === modalOverlay) {
                closeModal();
            }
        });

        // Duration selection
        durationOptions.forEach(option => {
            option.addEventListener('click', function() {
                if (this.classList.contains('disabled')) return;
                
                // Remove active class from all options
                durationOptions.forEach(opt => opt.classList.remove('active'));
                
                // Add active class to clicked option
                this.classList.add('active');
                
                // Update displayed price
                if (currentProduct) {
                    modalCurrentPrice.textContent = this.querySelector('.price').textContent;
                }
            });
        });

        // Buy now button handler
        buyNowBtn.addEventListener('click', function() {
            if (!currentProductId || !currentProduct) {
                notyf.error('Please select a product and duration');
                return;
            }

            const activeDuration = document.querySelector('.duration-option.active');
            if (!activeDuration) {
                notyf.error('Please select a duration');
                return;
            }

            const duration = activeDuration.dataset.duration;
            purchaseProduct(currentProductId, duration);
        });

        // Refresh purchase history
        document.querySelector('.btn-refresh-transactions').addEventListener('click', refreshPurchaseHistory);

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modalOverlay.classList.contains('active')) {
                closeModal();
            }
            if (e.key === 'Escape' && licenseModal.style.display === 'flex') {
                licenseModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    }

    // Function to fetch products from API
    function fetchProducts() {
        fetch('../api/shop/products.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderProducts(data.products);
                } else {
                    notyf.error(data.message || 'Failed to load products');
                    productsGrid.innerHTML = '<div class="error-message">Failed to load products. Please try again later.</div>';
                }
            })
            .catch(error => {
                console.error('Error fetching products:', error);
                notyf.error('Error loading products');
                productsGrid.innerHTML = '<div class="error-message">Error loading products. Please check your connection.</div>';
            });
    }

    // Function to render products
// Function to render products
function renderProducts(products) {
    if (!products || products.length === 0) {
        productsGrid.innerHTML = '<div class="no-products">No products available at this time.</div>';
        return;
    }

    productsGrid.innerHTML = '';

    products.forEach(product => {
        // Check prices in order: daily -> weekly -> monthly -> lifetime
        const dailyPrice = parseFloat(product.daily_price) || 0;
        const weeklyPrice = parseFloat(product.weekly_price) || 0;
        const monthlyPrice = parseFloat(product.monthly_price) || 0;
        const lifetimePrice = parseFloat(product.lifetime_price) || 0;
        
        let displayPrice = 0;
        let priceText = 'Not Available';
        let durationType = '';
        
        // Check in order of priority
        if (dailyPrice > 0) {
            displayPrice = dailyPrice;
            priceText = `$${dailyPrice.toFixed(2)}`;
            durationType = 'Daily';
        } else if (weeklyPrice > 0) {
            displayPrice = weeklyPrice;
            priceText = `$${weeklyPrice.toFixed(2)}`;
            durationType = 'Weekly';
        } else if (monthlyPrice > 0) {
            displayPrice = monthlyPrice;
            priceText = `$${monthlyPrice.toFixed(2)}`;
            durationType = 'Monthly';
        } else if (lifetimePrice > 0) {
            displayPrice = lifetimePrice;
            priceText = `$${lifetimePrice.toFixed(2)}`;
            durationType = 'Lifetime';
        }

        const productCard = document.createElement('div');
        productCard.className = 'product-card';
        productCard.dataset.product = product.name.toLowerCase().replace(/\s+/g, '-');
        productCard.dataset.productId = product.id;
        
        // Add badges based on product properties
        productCard.innerHTML = `
        ${product.is_best_seller ? '<div class="product-badge">BEST SELLER</div>' : ''}
        ${product.is_new ? '<div class="product-badge">NEW</div>' : ''}
            <div class="product-image">
                <img src="${product.image_url || 'https://placehold.co/300x200/1a1a1a/6a3ee7?text=Product'}" alt="${product.name}">
            </div>
            <div class="product-info">
                <h3 class="product-title">${product.name}</h3>
                <div class="product-price">
                    <span class="current-price">${priceText}</span>
                    ${durationType ? `<span class="price-duration">${durationType}</span>` : ''}
                </div>
                <div class="product-rating">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star-half-alt"></i>
                    <span class="rating-count">(${Math.floor(Math.random() * 100) + 20})</span>
                </div>
            </div>
            <button class="btn-view-product">View Product</button>
        `;

        // Only enable the button if at least one duration is available
        const viewButton = productCard.querySelector('.btn-view-product');
        if (displayPrice <= 0) {
            viewButton.disabled = true;
            viewButton.classList.add('disabled');
            viewButton.textContent = 'Not Available';
        }

        viewButton.addEventListener('click', () => {
            if (displayPrice <= 0) return;
            currentProductId = product.id;
            fetchProductDetails(product.id);
        });

        productsGrid.appendChild(productCard);
    });
}
    // Function to fetch product details
    function fetchProductDetails(productId) {
        fetch(`../api/shop/product.php?id=${productId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentProduct = data.product;
                    updateModal(data.product);
                    modalOverlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    notyf.error(data.message || 'Failed to load product details');
                }
            })
            .catch(error => {
                console.error('Error fetching product details:', error);
                notyf.error('Error loading product details');
            });
    }

    // Update modal with product data
    function updateModal(product) {
        modal.setAttribute('data-product-id', product.id);
        modalImage.src = product.image_url || 'https://placehold.co/300x200/1a1a1a/6a3ee7?text=Product';
        modalImage.alt = product.name;
        modalTitle.textContent = product.name;
        modalDescription.textContent = product.description || 'No description available';
        
        // Update features list (using default features since they're not in your API response)
        modalFeatures.innerHTML = '';
        const defaultFeatures = [
            'Undetected by anti-cheat systems',
            'Regular updates',
            'Premium support',
            'Easy to use interface'
        ];
        
        defaultFeatures.forEach(feature => {
            const li = document.createElement('li');
            li.innerHTML = `<i class="fas fa-check"></i> ${feature}`;
            modalFeatures.appendChild(li);
        });
        
        // Update duration options with prices
        durationOptions.forEach(option => {
            const duration = option.dataset.duration;
            const price = parseFloat(product[`${duration}_price`]) || 0;
            option.querySelector('.price').textContent = `$${price.toFixed(2)}`;
            option.dataset.price = price.toFixed(2);
            
            // Disable if price is 0 or not available
            if (price <= 0) {
                option.disabled = true;
                option.classList.add('disabled');
            } else {
                option.disabled = false;
                option.classList.remove('disabled');
            }
        });
        
        // Set first available duration as active
        let activeSet = false;
        durationOptions.forEach(option => {
            if (!activeSet && !option.disabled) {
                option.classList.add('active');
                modalCurrentPrice.textContent = `$${option.dataset.price}`;
                activeSet = true;
            } else {
                option.classList.remove('active');
            }
        });
        
        // Hide original price if not applicable
        const originalPrice = parseFloat(product.price) || 0;
        const minPrice = Math.min(
            parseFloat(product.daily_price) || Infinity,
            parseFloat(product.weekly_price) || Infinity,
            parseFloat(product.monthly_price) || Infinity,
            parseFloat(product.lifetime_price) || Infinity
        );
        
        //if (originalPrice > minPrice) {
        //    modalOriginalPrice.textContent = `$${originalPrice.toFixed(2)}`;
        //    modalOriginalPrice.style.display = 'inline';
        //} else {
        //    modalOriginalPrice.style.display = 'none';
        //}
        
        // Set random rating count
        ratingCount.textContent = `(${Math.floor(Math.random() * 100) + 20})`;
    }

    // Function to handle product purchase
    function purchaseProduct(productId, duration) {
        const loadingText = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        const originalText = buyNowBtn.innerHTML;
        buyNowBtn.innerHTML = loadingText;
        buyNowBtn.disabled = true;
    
        // Create form data
        const formData = new FormData();
        formData.append('productId', productId);
        formData.append('duration', duration);
    
        fetch('../api/shop/purchase.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(async response => {
            // First check if the response is JSON
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }
            
            // If not JSON, read as text to see what's wrong
            const text = await response.text();
            try {
                // Try to parse as JSON anyway (some APIs might not set content-type properly)
                return JSON.parse(text);
            } catch (e) {
                // If still not JSON, throw an error with the response text
                throw new Error(text || 'Unknown error occurred');
            }
        })
        .then(data => {
            buyNowBtn.innerHTML = originalText;
            buyNowBtn.disabled = false;
    
            if (data.success) {
                notyf.success('Purchase successful!');
                // Update balance display
                userBalance = data.new_balance;
                document.querySelector('.user-balance').textContent = `$${data.new_balance.toFixed(2)}`;
                
                // Show license key to user
                showLicenseModal(data.license_key, data.expires_at);
                closeModal();
                
                // Refresh purchase history
                fetchPurchaseHistory();
            } else {
                notyf.error(data.message || 'Purchase failed');
                // If balance was deducted but API returned error, we might need to refund
                if (data.new_balance !== undefined) {
                    document.querySelector('.user-balance').textContent = `$${data.new_balance.toFixed(2)}`;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            buyNowBtn.innerHTML = originalText;
            buyNowBtn.disabled = false;
            
            // More specific error message
            const errorMsg = error.message.includes('<') 
                ? 'Server error occurred. Please try again later.' 
                : error.message;
                
            notyf.error(errorMsg || 'An error occurred during purchase');
        });
    }

    // Function to show license modal
    function showLicenseModal(licenseKey, expiresAt) {
        licenseKeyDisplay.value = licenseKey;
        licenseExpiryInfo.textContent = expiresAt 
            ? `Expires on: ${new Date(expiresAt).toLocaleString()}` 
            : 'Lifetime license';
        
        licenseModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Setup close handlers
        licenseModal.querySelector('.modal-close-btn').addEventListener('click', () => {
            licenseModal.style.display = 'none';
            document.body.style.overflow = '';
        });

        licenseModal.querySelector('.btn-modal-close').addEventListener('click', () => {
            licenseModal.style.display = 'none';
            document.body.style.overflow = '';
        });

        licenseModal.addEventListener('click', (e) => {
            if (e.target === licenseModal) {
                licenseModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });

        // Copy license button
        licenseModal.querySelector('.copy-license-btn').addEventListener('click', () => {
            navigator.clipboard.writeText(licenseKey)
                .then(() => {
                    const btn = licenseModal.querySelector('.copy-license-btn');
                    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    setTimeout(() => {
                        btn.innerHTML = '<i class="fas fa-copy"></i>';
                    }, 2000);
                });
        });
    }

    // Close modal function
    function closeModal() {
        modalOverlay.classList.remove('active');
        document.body.style.overflow = ''; // Re-enable scrolling
    }

    // Purchase History Functions
    function fetchPurchaseHistory(page = 1) {
        const purchaseHistoryList = document.getElementById('purchaseHistoryList');
        purchaseHistoryList.innerHTML = '<div class="transaction-loading"><i class="fas fa-spinner fa-spin"></i> Loading purchase history...</div>';
        
        fetch(`/api/shop/purchases.php?page=${page}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayPurchaseHistory(data.purchases);
                    setupPurchaseHistoryPagination(data.total, data.pages, page);
                    currentPurchaseHistoryPage = page;
                } else {
                    notyf.error('Failed to load purchase history: ' + (data.message || 'Unknown error'));
                    purchaseHistoryList.innerHTML = '<div class="transaction-error">Error loading purchase history</div>';
                }
            })
            .catch(error => {
                console.error('Error fetching purchase history:', error);
                notyf.error('Error loading purchase history');
                purchaseHistoryList.innerHTML = '<div class="transaction-error">Error loading purchase history</div>';
            });
    }

    function displayPurchaseHistory(purchases) {
        const purchaseHistoryList = document.getElementById('purchaseHistoryList');
        purchaseHistoryList.innerHTML = '';
        
        if (purchases.length === 0) {
            purchaseHistoryList.innerHTML = '<div class="no-transactions">No purchases found</div>';
            return;
        }
        
        purchases.forEach(purchase => {
            const purchaseEl = document.createElement('div');
            purchaseEl.className = 'transaction';
            
            const date = new Date(purchase.purchase_date);
            const formattedDate = date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            purchaseEl.innerHTML = `
                <div class="transaction-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="transaction-product">
                    ${purchase.product_name}
                </div>
                <div class="license-key">
                    ${purchase.license_key || 'N/A'}
                </div>
                <div class="transaction-duration">
                    ${purchase.duration_display}
                </div>
                <div class="transaction-price">
                    $${parseFloat(purchase.price).toFixed(2)}
                </div>
                <div class="transaction-date">
                    ${formattedDate}
                </div>
            `;
            
            purchaseHistoryList.appendChild(purchaseEl);
        });
        
        // Fill empty space if less than purchasesPerPage transactions
        const remainingSpace = purchasesPerPage - purchases.length;
        for (let i = 0; i < remainingSpace; i++) {
            const emptyEl = document.createElement('div');
            emptyEl.className = 'transaction empty-transaction';
            emptyEl.style.visibility = 'hidden';
            emptyEl.innerHTML = '<div></div><div></div><div></div><div></div><div></div><div></div>';
            purchaseHistoryList.appendChild(emptyEl);
        }
    }

    function setupPurchaseHistoryPagination(total, totalPages, currentPage) {
        const pagination = document.getElementById('purchaseHistoryPagination');
        pagination.innerHTML = '';
        
        if (totalPages <= 1) return;
        
        // Previous button
        const prevBtn = document.createElement('button');
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevBtn.className = 'pagination-btn';
        prevBtn.disabled = currentPage === 1;
        prevBtn.addEventListener('click', () => {
            fetchPurchaseHistory(currentPage - 1);
        });
        pagination.appendChild(prevBtn);
        
        // Always show first page
        if (currentPage > 2) {
            const firstPage = document.createElement('button');
            firstPage.textContent = '1';
            firstPage.className = 'pagination-btn';
            firstPage.addEventListener('click', () => fetchPurchaseHistory(1));
            pagination.appendChild(firstPage);
            
            if (currentPage > 3) {
                const ellipsis = document.createElement('span');
                ellipsis.textContent = '...';
                ellipsis.className = 'pagination-ellipsis';
                pagination.appendChild(ellipsis);
            }
        }
        
        // Show pages around current page
        const startPage = Math.max(1, currentPage - 1);
        const endPage = Math.min(totalPages, currentPage + 1);
        
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.textContent = i;
            pageBtn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
            pageBtn.addEventListener('click', () => fetchPurchaseHistory(i));
            pagination.appendChild(pageBtn);
        }
        
        // Always show last page
        if (currentPage < totalPages - 1) {
            if (currentPage < totalPages - 2) {
                const ellipsis = document.createElement('span');
                ellipsis.textContent = '...';
                ellipsis.className = 'pagination-ellipsis';
                pagination.appendChild(ellipsis);
            }
            
            const lastPage = document.createElement('button');
            lastPage.textContent = totalPages;
            lastPage.className = 'pagination-btn';
            lastPage.addEventListener('click', () => fetchPurchaseHistory(totalPages));
            pagination.appendChild(lastPage);
        }
        
        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextBtn.className = 'pagination-btn';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.addEventListener('click', () => {
            fetchPurchaseHistory(currentPage + 1);
        });
        pagination.appendChild(nextBtn);
    }

    function refreshPurchaseHistory() {
        const btn = document.querySelector('.btn-refresh-transactions');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        fetchPurchaseHistory(currentPurchaseHistoryPage);
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        }, 1000);
    }
});