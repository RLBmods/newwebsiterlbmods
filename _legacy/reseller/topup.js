document.addEventListener('DOMContentLoaded', function() {
    // Notification system
    const notyf = new Notyf({
        duration: 5000,
        position: { x: 'right', y: 'top' },
        types: [
            {
                type: 'info',
                background: '#3498db',
                icon: {
                    className: 'fas fa-info-circle',
                    tagName: 'i',
                    color: '#fff'
                }
            },
            {
                type: 'success',
                background: '#07bc0c',
                icon: {
                    className: 'fas fa-check-circle',
                    tagName: 'i',
                    color: '#fff'
                }
            },
            {
                type: 'warning',
                background: '#f1c40f',
                icon: {
                    className: 'fas fa-exclamation-circle',
                    tagName: 'i',
                    color: '#fff'
                }
            },
            {
                type: 'error',
                background: '#e74c3c',
                icon: {
                    className: 'fas fa-times-circle',
                    tagName: 'i',
                    color: '#fff'
                }
            }
        ]
    });

    class TopUpSystem {
        constructor() {
            this.notificationInterval = null;
            this.pendingCheckInterval = null;
            this.currentTransactionId = null;
            this.currentGateway = null;
            this.currentPage = 1;
            this.paymentWindow = null;
            this.paymentCheckInterval = null;
            this.currentOrderId = null;
            
            this.elements = {
                transactionsList: document.getElementById('transactionsList'),
                pagination: document.getElementById('pagination'),
                btnRefresh: document.querySelector('.btn-refresh'),
                addFundsModal: document.getElementById('addFundsModal'),
                btnAddFunds: document.querySelector('.btn-add-funds'),
                modalClose: document.querySelector('.modal-close'),
                modalSteps: document.querySelectorAll('.modal-step'),
                btnContinue: document.querySelectorAll('.btn-continue'),
                btnBack: document.querySelectorAll('.btn-back'),
                amountOptions: document.querySelectorAll('.amount-option'),
                customAmountInput: document.getElementById('customAmount'),
                paymentMethods: document.querySelectorAll('.payment-method'),
                paymentForms: document.querySelectorAll('.payment-form'),
                amountValue: document.querySelector('.amount-value'),
                methodValue: document.querySelector('.method-value'),
                totalValue: document.querySelector('.total-value'),
                btnConfirm: document.getElementById('btnConfirmPayment'),
                btnCopy: document.querySelector('.btn-copy'),
                balanceAmount: document.querySelector('.balance-amount'),
                cryptoAddress: document.getElementById('cryptoAddress'),
                cryptoAmount: document.getElementById('cryptoAmount'),
                cryptoCurrency: document.getElementById('cryptoCurrency'),
                transactionDetailsModal: document.getElementById('transactionDetailsModal'),
                transactionDetailsContent: document.getElementById('transactionDetailsContent'),
                paymentStatusModal: document.getElementById('paymentStatusModal'),
                paymentStatusContent: document.getElementById('paymentStatusContent'),
                paymentExpiry: document.getElementById('paymentExpiry')
            };
            
            this.init();
        }

        init() {
            console.log('[TopUp] Initializing topup system...');
            this.setupEventListeners();
            this.loadTransactions();
            this.loadBalance();
            this.checkOldPendingPayments();
            this.setupPendingPaymentsPolling();
        }

        setupEventListeners() {
            console.log('[TopUp] Setting up event listeners');
            
            // Add Funds Modal
            this.elements.btnAddFunds.addEventListener('click', () => this.openModal());
            this.elements.modalClose.addEventListener('click', () => this.closeModal());
            this.elements.addFundsModal.addEventListener('click', (e) => {
                if (e.target === this.elements.addFundsModal) this.closeModal();
            });

            // Amount Selection
            this.elements.amountOptions.forEach(option => {
                option.addEventListener('click', function() {
                    if (this.classList.contains('custom-amount-option')) return;
                    
                    document.querySelectorAll('.amount-option').forEach(opt => {
                        opt.classList.remove('active');
                    });
                    this.classList.add('active');
                    this.elements.customAmountInput.value = '';
                    this.updateSummary();
                }.bind(this));
            });

            // Custom Amount Input
            this.elements.customAmountInput.addEventListener('focus', function() {
                document.querySelectorAll('.amount-option').forEach(opt => {
                    opt.classList.remove('active');
                });
                this.closest('.amount-option').classList.add('active');
            });

            this.elements.customAmountInput.addEventListener('input', () => this.updateSummary());

            document.querySelector('.custom-amount-option').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.querySelector('input').focus();
                }
            });

            // Payment Method Selection
            this.elements.paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    this.elements.paymentMethods.forEach(m => m.classList.remove('active'));
                    this.classList.add('active');
                    this.updateSummary();
                    
                    const methodType = this.getAttribute('data-method');
                    this.elements.paymentForms.forEach(form => form.style.display = 'none');
                    document.getElementById(`${methodType}-form`).style.display = 'block';
                });
            });

            // Step Navigation
            this.elements.btnContinue.forEach(btn => {
                btn.addEventListener('click', function() {
                    const nextStep = this.getAttribute('data-next');
                    if (this.validateStep(nextStep)) this.goToStep(nextStep);
                }.bind(this));
            });
            
            this.elements.btnBack.forEach(btn => {
                btn.addEventListener('click', function() {
                    const prevStep = this.getAttribute('data-prev');
                    this.goToStep(prevStep);
                }.bind(this));
            });

            // Transaction History Actions
            this.elements.btnRefresh.addEventListener('click', () => this.loadTransactions());

            // Confirm Payment
            if (this.elements.btnConfirm) {
                this.elements.btnConfirm.addEventListener('click', () => this.processPayment());
            }

            // Copy Crypto Address
            if (this.elements.btnCopy) {
                this.elements.btnCopy.addEventListener('click', () => this.copyCryptoAddress());
            }

            // Transaction click handler
            document.addEventListener('click', (e) => {
                if (e.target.closest('.transaction') || e.target.closest('.transaction *')) {
                    const transactionEl = e.target.closest('.transaction');
                    if (transactionEl) {
                        const transactionId = transactionEl.dataset.id;
                        const gateway = transactionEl.dataset.gateway;
                        this.showTransactionDetails(transactionId, gateway);
                    }
                }
            });

            // Modal close buttons
            document.querySelectorAll('.modal-close').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.closeAllModals();
                });
            });

            // Modal overlays
            document.querySelectorAll('.modal-overlay').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        this.closeAllModals();
                    }
                });
            });
        }

        // Payment Processing
        async processPayment() {
            const amount = parseFloat(this.elements.amountValue.textContent.replace('$', ''));
            const method = document.querySelector('.payment-method.active').getAttribute('data-method');
            
            try {
                this.elements.btnConfirm.disabled = true;
                this.elements.btnConfirm.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Payment...';
                
                console.log('[TopUp] Processing payment:', { amount, method });
                
                const response = await this.processNowPaymentsPayment(amount, method);
                
                console.log('[TopUp] Payment response:', response);
                
                if (response.success) {
                    if (response.data.payment_address) {
                        // Show crypto payment details
                        this.displayCryptoPayment(response.data);
                        this.goToStep(3);
                        notyf.success('Payment created! Send the exact amount to the address below.');
                    } else if (response.data.checkout_url) {
                        // Redirect to payment page
                        window.open(response.data.checkout_url, '_blank');
                        notyf.success('Redirecting to payment gateway...');
                        this.closeModal();
                    } else {
                        throw new Error('No payment method returned from gateway');
                    }
                } else {
                    throw new Error(response.error || 'Payment creation failed');
                }
            } catch (error) {
                console.error('[TopUp] Payment Error:', error);
                notyf.error(error.message);
            } finally {
                this.elements.btnConfirm.disabled = false;
                this.elements.btnConfirm.innerHTML = 'Confirm Payment';
            }
        }

        // Process NowPayments Payment
        async processNowPaymentsPayment(amount, method) {
            console.log('[TopUp] Creating NowPayments payment...');
            
            const response = await fetch('/api/topup/create.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    amount: amount,
                    paymentMethod: method,
                    quantity: 1,
                    note: 'Balance topup'
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        }

        // Display Crypto Payment Details
        displayCryptoPayment(paymentData) {
            console.log('[TopUp] Displaying crypto payment:', paymentData);
            
            // Update payment details in step 3
            this.elements.cryptoAddress.textContent = paymentData.payment_address;
            this.elements.cryptoAmount.textContent = paymentData.payment_amount;
            this.elements.cryptoCurrency.textContent = paymentData.payment_currency.toUpperCase();
            
            // Store payment data for status checking
            this.currentOrderId = paymentData.order_id;
            this.currentPaymentId = paymentData.payment_id;
            
            // Set up status checking
            this.startPaymentStatusChecking();
            
            // Show expiration time if available
            if (paymentData.expiration_estimate_date) {
                const expiryDate = new Date(paymentData.expiration_estimate_date);
                const now = new Date();
                const minutesLeft = Math.round((expiryDate - now) / (1000 * 60));
                
                if (this.elements.paymentExpiry) {
                    this.elements.paymentExpiry.textContent = `Expires in: ${minutesLeft} minutes`;
                }
            }
        }

        // Start checking payment status
        startPaymentStatusChecking() {
            console.log('[TopUp] Starting payment status checking...');
            
            // Check status every 10 seconds
            this.paymentCheckInterval = setInterval(async () => {
                try {
                    const status = await this.checkNowPaymentsStatus(this.currentPaymentId);
                    console.log('[TopUp] Payment status:', status);
                    
                    if (status === 'finished' || status === 'confirmed') {
                        // Payment completed
                        clearInterval(this.paymentCheckInterval);
                        this.showPaymentSuccess();
                    } else if (status === 'failed' || status === 'expired') {
                        // Payment failed
                        clearInterval(this.paymentCheckInterval);
                        this.showPaymentFailed(status);
                    }
                    // Continue checking for other statuses
                } catch (error) {
                    console.error('[TopUp] Status check error:', error);
                }
            }, 10000); // Check every 10 seconds
        }

        // Check NowPayments Status
        async checkNowPaymentsStatus(paymentId) {
            const response = await fetch(`/api/topup/status.php?payment_id=${paymentId}`);
            const data = await response.json();
            
            if (data.success) {
                return data.status;
            } else {
                throw new Error(data.error || 'Status check failed');
            }
        }

        // Show Payment Success
        showPaymentSuccess() {
            notyf.success('Payment completed! Your balance has been updated.');
            this.closeModal();
            this.loadTransactions();
            this.loadBalance();
        }

        // Show Payment Failed
        showPaymentFailed(status) {
            const message = status === 'expired' ? 'Payment expired' : 'Payment failed';
            notyf.error(message);
        }

        // Load Transactions
        async loadTransactions(page = 1) {
            try {
                this.elements.btnRefresh.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                const response = await fetch(`/api/topup/history.php?page=${page}`);
                const data = await response.json();
                
                if (data.success) {
                    this.renderTransactions(data.transactions);
                    this.renderPagination(data.total, data.pages, data.current_page);
                } else {
                    throw new Error(data.error || 'Failed to load transactions');
                }
            } catch (error) {
                notyf.error(error.message);
                console.error('Transaction Error:', error);
            } finally {
                this.elements.btnRefresh.innerHTML = '<i class="fas fa-sync-alt"></i>';
            }
        }

        // Load Balance
        async loadBalance() {
            try {
                const response = await fetch('/api/topup/balance.php');
                const data = await response.json();
                
                if (data.success) {
                    this.elements.balanceAmount.textContent = `$${data.balance.toFixed(2)}`;
                }
            } catch (error) {
                console.error('Balance Error:', error);
            }
        }

        // Render Transactions
        renderTransactions(transactions) {
            this.elements.transactionsList.innerHTML = '';
            
            if (transactions.length === 0) {
                this.elements.transactionsList.classList.add('empty');
                this.elements.transactionsList.innerHTML = '<div>No transactions found</div>';
                return;
            }
            
            this.elements.transactionsList.classList.remove('empty');
            
            transactions.forEach(tx => {
                const txEl = document.createElement('div');
                txEl.className = 'transaction';
                txEl.dataset.id = tx.id;
                txEl.dataset.gateway = tx.gateway || 'nowpayments';
                
                const iconClass = tx.status === 'completed' ? 'success' : 
                                 tx.status === 'pending' ? 'pending' : 'failed';
                
                let icon;
                if (tx.payment_method === 'paypal') {
                    icon = 'fab fa-paypal';
                } else if (tx.payment_method === 'card') {
                    icon = 'far fa-credit-card';
                } else {
                    icon = 'fab fa-bitcoin';
                }
                
                txEl.innerHTML = `
                    <div class="transaction-icon ${iconClass}">
                        <i class="${icon}"></i>
                    </div>
                    <div class="transaction-amount positive">
                        +$${parseFloat(tx.amount).toFixed(2)}
                    </div>
                    <div class="transaction-method">
                        <div class="method-icon">
                            <i class="${icon}"></i>
                        </div>
                        <span>${tx.payment_method ? tx.payment_method.toUpperCase() : 'N/A'}</span>
                    </div>
                    <div class="transaction-status status-${tx.status}">
                        ${tx.status.charAt(0).toUpperCase() + tx.status.slice(1)}
                    </div>
                    <div class="transaction-date">
                        ${new Date(tx.created_at).toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric' 
                        })}
                    </div>
                `;
                
                this.elements.transactionsList.appendChild(txEl);
            });
        }

        // Render Pagination
        renderPagination(total, pages, current) {
            this.elements.pagination.innerHTML = '';
            this.currentPage = current;
            
            // Previous Button
            const prevBtn = document.createElement('button');
            prevBtn.className = 'pagination-button';
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevBtn.disabled = this.currentPage === 1;
            prevBtn.addEventListener('click', () => {
                if (this.currentPage > 1) this.loadTransactions(this.currentPage - 1);
            });
            this.elements.pagination.appendChild(prevBtn);
            
            // Page Buttons
            for (let i = 1; i <= pages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.className = `pagination-button ${i === this.currentPage ? 'active' : ''}`;
                pageBtn.textContent = i;
                pageBtn.addEventListener('click', () => this.loadTransactions(i));
                this.elements.pagination.appendChild(pageBtn);
            }
            
            // Next Button
            const nextBtn = document.createElement('button');
            nextBtn.className = 'pagination-button';
            nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextBtn.disabled = this.currentPage === pages;
            nextBtn.addEventListener('click', () => {
                if (this.currentPage < pages) this.loadTransactions(this.currentPage + 1);
            });
            this.elements.pagination.appendChild(nextBtn);
        }

        // Modal Functions
        openModal() {
            this.elements.addFundsModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            this.resetModal();
        }

        closeModal() {
            this.elements.addFundsModal.classList.remove('active');
            document.body.style.overflow = '';
            clearInterval(this.paymentCheckInterval);
            if (this.paymentWindow) this.paymentWindow.close();
        }

        resetModal() {
            this.goToStep(1);
            document.querySelectorAll('.amount-option').forEach(opt => opt.classList.remove('active'));
            this.elements.customAmountInput.value = '';
            this.elements.paymentMethods.forEach(m => m.classList.remove('active'));
            this.elements.paymentForms.forEach(form => form.style.display = 'none');
            document.getElementById('btc-form').style.display = 'block';
            this.elements.paymentMethods[0].classList.add('active');
            this.updateSummary();
        }

        goToStep(step) {
            this.elements.modalSteps.forEach(s => s.classList.remove('active'));
            document.querySelector(`.modal-step[data-step="${step}"]`).classList.add('active');
        }

        // Validation
        validateStep(nextStep) {
            if (nextStep == "2" && !this.validateAmount()) return false;
            if (nextStep == "3" && !this.validatePaymentMethod()) return false;
            return true;
        }

        validateAmount() {
            const selectedAmount = document.querySelector('.amount-option.active');
            const amount = this.elements.customAmountInput.value;
            
            if (!selectedAmount) {
                notyf.error('Please select or enter an amount');
                return false;
            }
            
            if (selectedAmount.classList.contains('custom-amount-option') && (!amount || parseFloat(amount) < 1 || parseFloat(amount) > 1000)) {
                notyf.error('Amount must be between $1 and $1000');
                return false;
            }
            
            return true;
        }

        validatePaymentMethod() {
            const selectedMethod = document.querySelector('.payment-method.active');
            if (!selectedMethod) {
                notyf.error('Please select a payment method');
                return false;
            }
            return true;
        }

        // Update Summary
        updateSummary() {
            let amount = 0;
            const selectedAmount = document.querySelector('.amount-option.active');
            
            if (selectedAmount && selectedAmount.classList.contains('custom-amount-option')) {
                amount = parseFloat(this.elements.customAmountInput.value) || 0;
            } else if (selectedAmount) {
                amount = parseFloat(selectedAmount.getAttribute('data-amount'));
            }
            
            const selectedMethod = document.querySelector('.payment-method.active');
            const methodName = selectedMethod ? selectedMethod.querySelector('.method-name').textContent : '';
            
            this.elements.amountValue.textContent = `$${amount.toFixed(2)}`;
            this.elements.methodValue.textContent = methodName;
            this.elements.totalValue.textContent = `$${amount.toFixed(2)}`;
        }

        // Helper Functions
        copyCryptoAddress() {
            const cryptoAddress = this.elements.cryptoAddress.textContent;
            if (!cryptoAddress || cryptoAddress === 'Waiting for address...') return;
            
            navigator.clipboard.writeText(cryptoAddress);
            notyf.success('Address copied to clipboard!');
        }

        closeAllModals() {
            document.querySelectorAll('.modal-overlay').forEach(modal => {
                modal.classList.remove('active');
            });
            document.body.style.overflow = '';
        }

        // Placeholder methods for existing functionality
        async checkOldPendingPayments() {
            // Implementation for checking pending payments
        }

        setupPendingPaymentsPolling() {
            // Implementation for polling
        }

        async showTransactionDetails(transactionId, gateway) {
            // Implementation for showing transaction details
        }

        async checkPaymentStatus(transactionId, gateway) {
            // Implementation for checking payment status
        }
    }

    // Initialize the system
    const topUpSystem = new TopUpSystem();
});