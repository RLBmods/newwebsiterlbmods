document.addEventListener('DOMContentLoaded', function() {

    // DOM Elements
    const productTabs = document.querySelectorAll('.tab-btn');
    const actionTabs = document.querySelectorAll('.action-tab-btn');
    const createLicenseBtn = document.querySelector('.btn-create-license');
    const resetHwidBtn = document.querySelector('.btn-reset-hwid');
    const licenseModal = document.getElementById('licenseModal');
    const closeModalBtn = document.querySelector('.close-modal');
    const modalCloseBtn = document.querySelector('.btn-modal-close');
    const durationSelect = document.querySelector('.duration-select');
    const licenseAmount = document.querySelector('.license-amount');
    const costDisplay = document.querySelector('.cost-amount');
    const licenseTableBody = document.querySelector('.license-table tbody');
    const licenseListContainer = document.querySelector('.license-list');
    const createdLicenseContainer = document.querySelector('.created-license');
    const searchInput = document.querySelector('.search-box input');
    const searchBtn = document.querySelector('.search-btn');

    // Pagination variables
    let currentPage = 1;
    const licensesPerPage = 14;
    let currentProduct = 'temp-spoofer';
    let currentSearchTerm = '';

    // Load licenses for a product with pagination
    function loadLicenses(product, searchTerm = '', page = 1) {
        currentProduct = product;
        currentSearchTerm = searchTerm;
        currentPage = page;
        
        licenseTableBody.innerHTML = ''; // Clear existing rows
        
        let licenses = productLicenses[product] || [];
        
        // Filter licenses if search term exists
        if (searchTerm) {
            licenses = licenses.filter(license => 
                license.license.toLowerCase().includes(searchTerm.toLowerCase()) ||
                license.generatedBy.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }
        
        // Calculate pagination
        const totalLicenses = licenses.length;
        const totalPages = Math.ceil(totalLicenses / licensesPerPage);
        const startIndex = (page - 1) * licensesPerPage;
        const endIndex = Math.min(startIndex + licensesPerPage, totalLicenses);
        const paginatedLicenses = licenses.slice(startIndex, endIndex);
        
        if (paginatedLicenses.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-secondary)">
                    No licenses found for this product
                </td>
            `;
            licenseTableBody.appendChild(row);
        } else {
            paginatedLicenses.forEach(license => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${license.license}</td>
                    <td>${license.duration}</td>
                    <td><span class="status-badge ${license.status}">${license.status.charAt(0).toUpperCase() + license.status.slice(1)}</span></td>
                    <td>${license.generatedBy}</td>
                    <td>${license.genDate}</td>
                    <td>${license.actDate || 'Not activated'}</td>
                `;
                licenseTableBody.appendChild(row);
            });
        }
        
        // Update pagination controls
        updatePaginationControls(totalLicenses, page, totalPages);
    }

    // Update pagination controls
    function updatePaginationControls(totalLicenses, currentPage, totalPages) {
        let paginationContainer = document.querySelector('.pagination-container');
        
        if (!paginationContainer) {
            paginationContainer = document.createElement('div');
            paginationContainer.className = 'pagination-container';
            document.querySelector('.license-table-container').appendChild(paginationContainer);
        }
        
        paginationContainer.innerHTML = '';
        
        if (totalLicenses > licensesPerPage) {
            const paginationDiv = document.createElement('div');
            paginationDiv.className = 'pagination';
            
            // Previous button
            const prevBtn = document.createElement('button');
            prevBtn.className = 'pagination-btn pagination-nav';
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevBtn.disabled = currentPage === 1;
            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    loadLicenses(currentProduct, currentSearchTerm, currentPage - 1);
                }
            });
            paginationDiv.appendChild(prevBtn);
            
            // First page button (if needed)
            if (currentPage > 3 && totalPages > 5) {
                const firstPageBtn = document.createElement('button');
                firstPageBtn.className = 'pagination-btn';
                firstPageBtn.textContent = '1';
                firstPageBtn.addEventListener('click', () => {
                    loadLicenses(currentProduct, currentSearchTerm, 1);
                });
                paginationDiv.appendChild(firstPageBtn);
                
                if (currentPage > 4) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'pagination-ellipsis';
                    ellipsis.textContent = '...';
                    paginationDiv.appendChild(ellipsis);
                }
            }
            
            // Page numbers
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);
            
            if (endPage - startPage < 4 && startPage > 1) {
                startPage = Math.max(1, endPage - 4);
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
                pageBtn.textContent = i;
                pageBtn.addEventListener('click', () => {
                    loadLicenses(currentProduct, currentSearchTerm, i);
                });
                
                // Add pulse animation to current page
                if (i === currentPage) {
                    const pulse = document.createElement('span');
                    pulse.className = 'pagination-pulse';
                    pageBtn.appendChild(pulse);
                }
                
                paginationDiv.appendChild(pageBtn);
            }
            
            // Last page button (if needed)
            if (endPage < totalPages - 1) {
                if (endPage < totalPages - 2) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'pagination-ellipsis';
                    ellipsis.textContent = '...';
                    paginationDiv.appendChild(ellipsis);
                }
                
                const lastPageBtn = document.createElement('button');
                lastPageBtn.className = 'pagination-btn';
                lastPageBtn.textContent = totalPages;
                lastPageBtn.addEventListener('click', () => {
                    loadLicenses(currentProduct, currentSearchTerm, totalPages);
                });
                paginationDiv.appendChild(lastPageBtn);
            }
            
            // Next button
            const nextBtn = document.createElement('button');
            nextBtn.className = 'pagination-btn pagination-nav';
            nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    loadLicenses(currentProduct, currentSearchTerm, currentPage + 1);
                }
            });
            paginationDiv.appendChild(nextBtn);
            
            // License count with icon
            const licenseCount = document.createElement('div');
            //licenseCount.className = 'license-count';
            const startLicense = (currentPage - 1) * licensesPerPage + 1;
            const endLicense = Math.min(currentPage * licensesPerPage, totalLicenses);
            
            //const countIcon = document.createElement('i');
            //countIcon.className = 'fas fa-key';
            
            const countText = document.createElement('span');
            //countText.textContent = ` Total ${totalLicenses} licenses`;
            
            //licenseCount.appendChild(countIcon);
            licenseCount.appendChild(countText);
            
            paginationContainer.appendChild(licenseCount);
            paginationContainer.appendChild(paginationDiv);
        }
    }

    // Generate random license key
    function generateLicenseKey(product) {
        const prefix = {
            'temp-spoofer': 'RLB-TS',
            'fortnite': 'RLB-FN',
            'bo6': 'RLB-BO',
            'rust': 'RLB-RS'
        }[product] || 'RLB-XX';
        
        const randomPart1 = Math.floor(1000 + Math.random() * 9000);
        const randomPart2 = Math.random().toString(36).substr(2, 4).toUpperCase();
        const randomPart3 = Math.random().toString(36).substr(2, 4).toUpperCase();
        
        return `${prefix}-${randomPart1}-${randomPart2}-${randomPart3}`;
    }

    // Product Tab Switching
    productTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const product = this.getAttribute('data-product');
            
            // Update active tab
            productTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Load licenses for selected product
            loadLicenses(product);
        });
    });

    // Initialize with first product
    loadLicenses('temp-spoofer');

    // Action Tab Switching
    actionTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            actionTabs.forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.action-tab-content').forEach(c => c.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById(`${tabId}-tab`).classList.add('active');
        });
    });
    
    // Calculate License Cost
    function calculateCost() {
        const duration = parseInt(durationSelect.value);
        const amount = parseInt(licenseAmount.value);
        const pricePerDay = 0.50; // Example price
        const total = (duration * pricePerDay * amount).toFixed(2);
        costDisplay.textContent = `$${total}`;
    }
    
    durationSelect.addEventListener('change', calculateCost);
    licenseAmount.addEventListener('input', function() {
        // Clamp value between min and max
        if (this.value > 50) this.value = 50;
        if (this.value < 1) this.value = 1;
        calculateCost();
    });
    
    // Initialize cost
    calculateCost();
    
    // Create License
    if (createLicenseBtn) {
        createLicenseBtn.addEventListener('click', function() {
            const amount = parseInt(licenseAmount.value);
            const duration = durationSelect.options[durationSelect.selectedIndex].text;
            const activeProduct = document.querySelector('.tab-btn.active').getAttribute('data-product');
            
            // Generate licenses
            const licenses = [];
            for (let i = 0; i < amount; i++) {
                const licenseKey = generateLicenseKey(activeProduct);
                licenses.push(licenseKey);
                
                // Add these licenses to the product's list
                const newLicense = {
                    license: licenseKey,
                    duration: duration,
                    status: 'active',
                    generatedBy: 'Reseller123',
                    genDate: new Date().toISOString().split('T')[0],
                    actDate: ''
                };
                
                if (!productLicenses[activeProduct]) {
                    productLicenses[activeProduct] = [];
                }
                productLicenses[activeProduct].unshift(newLicense);
            }
            
            // Show modal with all licenses in a single list
            licenseModal.style.display = 'flex';
            
            // Clear previous content
            licenseListContainer.innerHTML = '';
            licenseListContainer.style.display = 'block';
            createdLicenseContainer.style.display = 'none';
            
            // Add all licenses to the list
            licenses.forEach(licenseKey => {
                const licenseItem = document.createElement('div');
                licenseItem.className = 'license-item';
                licenseItem.innerHTML = `
                    <span>${licenseKey}</span>
                    <button class="copy-license-btn">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                `;
                licenseListContainer.appendChild(licenseItem);
            });
            
            // Add copy all button
            const copyAllBtn = document.createElement('button');
            copyAllBtn.className = 'btn-secondary btn-copy-all';
            copyAllBtn.innerHTML = '<i class="fas fa-copy"></i> Copy All Licenses';
            licenseListContainer.appendChild(copyAllBtn);
            
            // Add event listener for the new copy all button
            copyAllBtn.addEventListener('click', function() {
                const licenseText = licenses.join('\n');
                navigator.clipboard.writeText(licenseText)
                    .then(() => {
                        const originalText = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-check"></i> Copied All!';
                        setTimeout(() => {
                            this.innerHTML = originalText;
                        }, 2000);
                    });
            });
            
            // Reload licenses to show the new ones in the table
            loadLicenses(activeProduct);
        });
    }
    
    // Reset HWID
    if (resetHwidBtn) {
        resetHwidBtn.addEventListener('click', function() {
            const licenseKey = document.querySelector('.license-input').value;
            if (!licenseKey) {
                alert('Please enter a license key');
                return;
            }
            
            // Find the license in any product
            let licenseFound = false;
            for (const product in productLicenses) {
                const licenseIndex = productLicenses[product].findIndex(l => l.license === licenseKey);
                if (licenseIndex !== -1) {
                    licenseFound = true;
                    // In a real app, you would call your API to reset HWID
                    alert(`HWID reset for license: ${licenseKey}`);
                    break;
                }
            }
            
            if (!licenseFound) {
                alert('License not found');
            }
        });
    }

    // API Key Functionality - Updated version
function fetchApiKey() {
    const apiKeyTextarea = document.getElementById('apiKeyTextarea');
    apiKeyTextarea.placeholder = 'Loading API key...';
    apiKeyTextarea.classList.add('blurred');
    
    console.log('Fetching API key...'); // Debug log
    
    $.ajax({
        url: 'https://s.compilecrew.xyz/api/reseller/get_token.php',
        method: 'GET',
        xhrFields: {
            withCredentials: true
        },
        crossDomain: true,
        dataType: 'json',
        success: function(response) {
            console.log('API Key Response:', response);
            if (response && response.success && response.token) {
                apiKeyTextarea.value = response.token;
                apiKeyTextarea.placeholder = 'API key loaded';
                apiKeyTextarea.classList.remove('blurred');
            } else {
                apiKeyTextarea.value = '';
                apiKeyTextarea.placeholder = response?.message || 'No API key found';
                notyf.error(response?.message || 'No API key found');
            }
        },
        error: function(xhr, status, error) {
            console.error('API Key Error:', xhr.responseText, status, error);
            apiKeyTextarea.value = '';
            apiKeyTextarea.placeholder = 'Error loading API key';
            notyf.error('Failed to load API key: ' + (xhr.responseJSON?.message || error));
        }
    });
}

// Regenerate API Key - Updated version
document.getElementById('regenerateApiBtn').addEventListener('click', function() {
    const btn = this;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Regenerating...';
    btn.disabled = true;
    
    console.log('Regenerating API key...'); // Debug log
    
    $.ajax({
        url: 'https://s.compilecrew.xyz/api/reseller/generate_token.php',
        method: 'POST',
        xhrFields: {
            withCredentials: true
        },
        crossDomain: true,
        dataType: 'json',
        success: function(response) {
            console.log('Regenerate Response:', response);
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Regenerate';
            btn.disabled = false;
            
            if (response && response.success && response.token) {
                // Update the textarea
                const apiKeyTextarea = document.getElementById('apiKeyTextarea');
                apiKeyTextarea.value = response.token;
                apiKeyTextarea.placeholder = 'API key regenerated';
                apiKeyTextarea.classList.remove('blurred');
                
                // Show the new key in the modal
                document.getElementById('newApiKeyValue').value = response.token;
                document.getElementById('apiKeyModal').style.display = 'flex';
                
                notyf.success('API key regenerated successfully');
            } else {
                notyf.error(response?.message || 'Failed to regenerate API key');
            }
        },
        error: function(xhr, status, error) {
            console.error('Regenerate Error:', xhr.responseText, status, error);
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Regenerate';
            btn.disabled = false;
            notyf.error('Error regenerating API key: ' + (xhr.responseJSON?.message || error));
        }
    });
});

// Initialize API key on page load
$(document).ready(function() {
    fetchApiKey();
});
    
    // Search functionality
    if (searchInput && searchBtn) {
        const performSearch = () => {
            const searchTerm = searchInput.value.trim();
            const activeProduct = document.querySelector('.tab-btn.active').getAttribute('data-product');
            loadLicenses(activeProduct, searchTerm, 1); // Reset to page 1 when searching
        };
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
        
        searchBtn.addEventListener('click', performSearch);
    }
    
    // Modal Controls
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function() {
            licenseModal.style.display = 'none';
        });
    }
    
    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', function() {
            licenseModal.style.display = 'none';
        });
    }
    
    window.addEventListener('click', function(event) {
        if (event.target === licenseModal) {
            licenseModal.style.display = 'none';
        }
    });
    
    // Copy License (delegated event for dynamic elements)
    document.addEventListener('click', function(e) {
        if (e.target.closest('.copy-license-btn')) {
            const btn = e.target.closest('.copy-license-btn');
            const licenseValue = btn.closest('.created-license') ? 
                btn.closest('.created-license').querySelector('.license-value').textContent :
                btn.closest('.license-item').querySelector('span').textContent;
            
            navigator.clipboard.writeText(licenseValue.trim())
                .then(() => {
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                    }, 2000);
                })
                .catch(err => {
                    console.error('Failed to copy text: ', err);
                });
        }
    });
});