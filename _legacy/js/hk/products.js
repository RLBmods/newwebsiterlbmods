document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const modal = document.getElementById('product-modal');
    const addProductBtn = document.getElementById('add-product-btn');
    const modalCloseBtns = document.querySelectorAll('.modal-close, .btn-cancel');
    const productForm = document.getElementById('product-form');
    const modalTitle = document.getElementById('modal-title');
    const productIdInput = document.getElementById('product-id');
    const imagePreview = document.getElementById('image-preview');
    const uploadImageBtn = document.getElementById('upload-image-btn');
    const productImage = document.getElementById('product-image');
    const productsTable = document.querySelector('.admin-table tbody');

    const filePreview = document.getElementById('file-preview');
    const uploadFileBtn = document.getElementById('upload-file-btn');
    const productFile = document.getElementById('product-file');

    // Initialize the page
    setupEventListeners();
    loadProducts();

    // Event Listeners Setup
    function setupEventListeners() {
        // Add product button
        addProductBtn.addEventListener('click', openAddProductModal);

        // Modal close buttons
        modalCloseBtns.forEach(btn => btn.addEventListener('click', closeModal));

        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });

        // Image upload handling
        uploadImageBtn.addEventListener('click', () => productImage.click());
        productImage.addEventListener('change', handleImageUpload);

        // Form submission
        productForm.addEventListener('submit', handleFormSubmit);

        // Edit/Delete buttons event delegation
        productsTable.addEventListener('click', handleTableActions);

        // File Uploading
        uploadFileBtn.addEventListener('click', () => productFile.click());
        productFile.addEventListener('change', handleFileUpload);
    }

    // Load products from API
    function loadProducts() {
        showLoading(true);
        
        fetch('/api/hk/product/list.php')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    renderProducts(data.products);
                } else {
                    throw new Error(data.error || 'Failed to load products');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Failed to load products: ' + error.message);
                renderProducts([]); // Render empty table on error
            })
            .finally(() => {
                showLoading(false);
            });
    }

    // Render products in the table
    function renderProducts(products) {
        const tbody = document.querySelector('.admin-table tbody');
        tbody.innerHTML = '';

        if (!products || products.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No products found</td></tr>';
            return;
        }

        products.forEach(product => {
            // Safely parse prices
            const price = parseFloat(product.price) || 0;
            const dailyPrice = parseFloat(product['daily_price']) || 0;
            const weeklyPrice = parseFloat(product['weekly_price']) || 0;
            const monthlyPrice = parseFloat(product['monthly_price']) || 0;
            const lifetimePrice = parseFloat(product['lifetime_price']) || 0;
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
            <td>${product.id}</td>
            <td>
                <div class="product-name-cell">
                    ${product.name || 'N/A'}
                </div>
            </td>
            <td>$${price.toFixed(2)}</td>
            <td>
                <span class="status-badge ${product.visibility ? 'status-active' : 'status-expired'}">
                    ${product.visibility ? 'Visible' : 'Hidden'}
                </span>
            </td>
            <td>
                <span class="status-badge ${statusClasses[product.status] || 'status-unknown'}">
                    ${statusMap[product.status] || 'Unknown'}
                </span>
            </td>
            <td>${product.type || 'N/A'}</td>
            <td>${formatDate(product.created_at)}</td>
            <td>
                <button class="btn-admin btn-edit edit-product" data-product-id="${product.id}">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-admin btn-delete delete-product" data-product-id="${product.id}">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
            tbody.appendChild(tr);
        });
    }

    const statusMap = {
        1: 'Undetected',
        2: 'Use at own risk',
        3: 'Testing',
        4: 'Updating',
        5: 'Offline',
        6: 'In Development'
    };

    const statusClasses = {
        1: 'status-undetected',
        2: 'status-risk',
        3: 'status-testing',
        4: 'status-updating',
        5: 'status-offline',
        6: 'status-development'
    };

    // Helper function to format date
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            });
        } catch (e) {
            return 'N/A';
        }
    }

    // Table Actions Handler
    function handleTableActions(e) {
        const editBtn = e.target.closest('.edit-product');
        const deleteBtn = e.target.closest('.delete-product');
        
        if (editBtn) {
            const productId = editBtn.getAttribute('data-product-id');
            openEditProductModal(productId);
        }
        
        if (deleteBtn) {
            const productId = deleteBtn.getAttribute('data-product-id');
            confirmDeleteProduct(productId);
        }
    }

    // Modal Functions
    function openAddProductModal() {
        resetForm();
        modalTitle.textContent = 'Add New Product';
        modal.style.display = 'flex';
    }

    function openEditProductModal(productId) {
        fetch(`/api/hk/product/read.php?id=${productId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    populateForm(data.product);
                    modalTitle.textContent = 'Edit Product';
                    modal.style.display = 'flex';
                } else {
                    throw new Error(data.error || 'Failed to load product data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Failed to load product: ' + error.message);
            });
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    function resetForm() {
        productForm.reset();
        productIdInput.value = '';
        imagePreview.innerHTML = '<i class="fas fa-image"></i><span>No image selected</span>';
        productImage.value = '';
        filePreview.innerHTML = '<i class="fas fa-file"></i><span>No file selected</span>';
        productFile.value = '';
    }

    function populateForm(product) {
        productIdInput.value = product.id;
        document.getElementById('product-name').value = product.name || '';
        document.getElementById('product-price').value = product.price || '';
        document.getElementById('product-description').value = product.description || '';
        document.getElementById('daily-price').value = product['daily_price'] || '';
        document.getElementById('weekly-price').value = product['weekly_price'] || '';
        document.getElementById('monthly-price').value = product['monthly_price'] || '';
        document.getElementById('lifetime-price').value = product['lifetime_price'] || '';
        document.getElementById('product-type').value = product.type || 'keyauth';
        document.getElementById('license-level').value = product['license-level'] || 1;
        document.getElementById('api-url').value = product.api_url || '';
        document.getElementById('apikey').value = product.apikey || '';
        document.getElementById('license-identifier').value = product['license-identifier'] || '';
        document.getElementById('tutorial-link').value = product.tutorial_link || '';
        document.getElementById('product-visibility').checked = product.visibility == 1;
        document.getElementById('reseller-can-sell').checked = product.reseller_can_sell == 1;
        document.getElementById('file-name').value = product.file_name || '';
        document.getElementById('download-url').value = product.download_url || '';
        document.getElementById('product-version').value = product.version || '1.0.0';
        document.getElementById('product-status').value = product.status || 1;

        
        // Set file preview
        if (product.file_name) {
            filePreview.innerHTML = `
                <i class="fas fa-file"></i>
                <span>${product.file_name}</span>
            `;
        } else {
            filePreview.innerHTML = '<i class="fas fa-file"></i><span>No file selected</span>';
        }

        // Set image preview with error handling
        if (product.image_url) {
            const img = new Image();
            img.onload = function() {
                imagePreview.innerHTML = `<img src="${product.image_url}" alt="Product Preview">`;
            };
            img.onerror = function() {
                imagePreview.innerHTML = '<i class="fas fa-image"></i><span>Image not found</span>';
            };
            img.src = product.image_url;
        } else {
            imagePreview.innerHTML = '<i class="fas fa-image"></i><span>No image selected</span>';
        }
    }

    function handleFileUpload() {
        const file = this.files[0];
        if (file) {
            filePreview.innerHTML = `
                <i class="fas fa-file"></i>
                <span>${file.name} (${formatFileSize(file.size)})</span>
            `;
            // Auto-fill the file name field
            document.getElementById('file-name').value = file.name;
        }
    }
    
    // Helper function to format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Image Handling
    function handleImageUpload() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.innerHTML = `<img src="${e.target.result}" alt="Product Preview">`;
            };
            reader.readAsDataURL(file);
        }
    }

    async function handleFormSubmit(e) {
        e.preventDefault();
        
        const submitBtn = productForm.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        try {
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            const productId = productIdInput.value;
            const endpoint = productId ? '/api/hk/product/update.php' : '/api/hk/product/create.php';
            
            // Use FormData for file uploads
            const formData = new FormData(productForm);
            
            // Add product ID if editing
            if (productId) {
                formData.append('id', productId);
            }
            
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
    
            const responseText = await response.text();
            
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Failed to parse JSON:', responseText);
                throw new Error('Invalid server response');
            }
    
            if (!data.success) {
                throw new Error(data.error || 'Failed to save product');
            }
    
            showNotification('success', data.message || 'Product saved successfully');
            closeModal();
            loadProducts(); // Refresh the product list
        } catch (error) {
            console.error('Error:', error);
            showNotification('error', error.message || 'Failed to save product');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }

    // Product Deletion
    function confirmDeleteProduct(productId) {
        if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
            deleteProduct(productId);
        }
    }

// Delete Product
async function deleteProduct(productId) {
    try {
        const response = await fetch('/api/hk/product/delete.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: productId }),
            credentials: 'include'
        });

        const responseText = await response.text();
        const data = JSON.parse(responseText);

        if (!data.success) throw new Error(data.error || 'Delete failed');
        
        showNotification('success', data.message);
        loadProducts();
        
    } catch (error) {
        console.error('Error:', error);
        showNotification('error', error.message);
    }
}

    // Loading indicator
    function showLoading(show) {
        let loader = document.getElementById('loading-indicator');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'loading-indicator';
            loader.style.position = 'fixed';
            loader.style.top = '0';
            loader.style.left = '0';
            loader.style.width = '100%';
            loader.style.height = '100%';
            loader.style.backgroundColor = 'rgba(0,0,0,0.5)';
            loader.style.zIndex = '9999';
            loader.style.display = 'flex';
            loader.style.justifyContent = 'center';
            loader.style.alignItems = 'center';
            
            const spinner = document.createElement('div');
            spinner.className = 'spinner';
            loader.appendChild(spinner);
            document.body.appendChild(loader);
        }
        loader.style.display = show ? 'flex' : 'none';
    }

    // Notification System
    function showNotification(type, message) {
        // Check if notification system is already loaded
        if (typeof window.showSystemNotification === 'function') {
            window.showSystemNotification(type, message);
            return;
        }

        // Fallback notification
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close">&times;</button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
        
        // Manual close
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        });
    }
});

// Add spinner CSS if not already present
if (!document.querySelector('style#spinner-style')) {
    const style = document.createElement('style');
    style.id = 'spinner-style';
    style.textContent = `
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px;
            border-radius: 5px;
            color: white;
            display: flex;
            align-items: center;
            z-index: 10000;
            transition: opacity 0.3s ease;
        }
        .notification-success {
            background-color: #28a745;
        }
        .notification-error {
            background-color: #dc3545;
        }
        .notification-close {
            margin-left: 10px;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
        }
        .fade-out {
            opacity: 0;
        }
    `;
    document.head.appendChild(style);
}