/**
 * TopUp System - Enhanced Version with Card Payment Integration
 * Features:
 * - Clear 4-step modal flow (Amount → Method → Summary → Payment)
 * - Card payment processing via PayTabs API
 * - Real-time payment status checking
 * - Transaction details modal
 * - Pending transaction management
 * - Better error handling
 * - Improved UI feedback
 */

class TopUpSystem {
    constructor() {
        // DOM Elements
        this.dom = {
            transactionsList: document.getElementById('transactionsList'),
            pagination: document.getElementById('pagination'),
            btnRefresh: document.querySelector('.btn-refresh'),
            addFundsModal: document.getElementById('addFundsModal'),
            btnAddFunds: document.querySelector('.btn-add-funds'),
            modalClose: document.querySelectorAll('.modal-close'),
            modalSteps: document.querySelectorAll('.modal-step'),
            btnContinue: document.querySelectorAll('.btn-continue'),
            btnBack: document.querySelectorAll('.btn-back'),
            amountOptions: document.querySelectorAll('.amount-option'),
            customAmountInput: document.getElementById('customAmount'),
            paymentMethods: document.querySelectorAll('.payment-method'),
            btnConfirm: document.querySelector('.btn-confirm-payment'),
            btnCopy: document.querySelectorAll('.btn-copy'),
            balanceAmount: document.querySelector('.balance-amount'),
            transactionModal: document.getElementById('transactionModal'),
            transactionDetails: document.getElementById('transactionDetails'),
            processingOverlay: document.getElementById('processingOverlay'),
            paymentSuccess: document.getElementById('paymentSuccess'),
            paymentFailed: document.getElementById('paymentFailed'),
            errorMessage: document.getElementById('errorMessage')
        };

        // State
        this.state = {
            currentPage: 1,
            transactionsPerPage: 7,
            selectedAmount: 0,
            selectedMethod: '',
            currentOrderId: '',
            paymentCheckInterval: null,
            countdownInterval: null,
            countdownTime: 15 * 60, // 15 minutes
            isProcessingPayment: false,
            transactionCache: null,
            lastRefresh: null,
            isCheckingPayments: false
        };

        this.cardPaymentState = {
            paymentWindow: null,
            checkInterval: null,
            attempts: 0,
            maxAttempts: 30
        };

        // Constants
        this.constants = {
            refreshInterval: 30000, // 30 seconds
            cacheTTL: 60000, // 1 minute
            pendingCheckInterval: 60000 // 1 minute
        };

        // Initialize
        this.init();
    }

    // Initialize the application
    init() {
        this.setupEventListeners();
        this.loadTransactionHistory();
        this.checkOldPendingPayments();
        
        // Set up periodic checks
        setInterval(() => this.checkOldPendingPayments(), this.constants.pendingCheckInterval);
        
        // Clean up on page unload
        window.addEventListener('beforeunload', () => this.cleanupIntervals());
    }

    // Set up all event listeners
    setupEventListeners() {
        const { dom } = this;
        
        // Add Funds Modal
        dom.btnAddFunds.addEventListener('click', () => this.openModal());
        
        // Modal close buttons
        dom.modalClose.forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.closest('.modal-overlay').id === 'addFundsModal') {
                    this.closeModal();
                } else {
                    this.closeTransactionModal();
                }
            });
        });
        
        // Modal overlay clicks
        dom.addFundsModal.addEventListener('click', (e) => {
            if (e.target === dom.addFundsModal) this.closeModal();
        });
        
        dom.transactionModal.addEventListener('click', (e) => {
            if (e.target === dom.transactionModal) this.closeTransactionModal();
        });

        // Amount Selection
        dom.amountOptions.forEach(option => {
            option.addEventListener('click', () => {
                if (option.classList.contains('custom-amount-option')) return;
                
                dom.amountOptions.forEach(opt => opt.classList.remove('active'));
                option.classList.add('active');
                dom.customAmountInput.value = '';
                this.state.selectedAmount = parseFloat(option.getAttribute('data-amount'));
            });
        });

        // Custom Amount Input
        dom.customAmountInput.addEventListener('focus', () => {
            dom.amountOptions.forEach(opt => opt.classList.remove('active'));
            dom.customAmountInput.closest('.amount-option').classList.add('active');
        });

        dom.customAmountInput.addEventListener('input', () => {
            this.state.selectedAmount = parseFloat(dom.customAmountInput.value) || 0;
        });

        document.querySelector('.custom-amount-option').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                dom.customAmountInput.focus();
            }
        });

        // Payment Method Selection
        dom.paymentMethods.forEach(method => {
            method.addEventListener('click', () => {
                dom.paymentMethods.forEach(m => m.classList.remove('active'));
                method.classList.add('active');
                this.state.selectedMethod = method.getAttribute('data-method');
            });
        });

        // Step Navigation
        dom.btnContinue.forEach(btn => {
            btn.addEventListener('click', () => {
                const nextStep = btn.getAttribute('data-next');
                if (this.validateStep(nextStep)) this.goToStep(nextStep);
            });
        });
        
        dom.btnBack.forEach(btn => {
            btn.addEventListener('click', () => {
                const prevStep = btn.getAttribute('data-prev');
                this.goToStep(prevStep);
            });
        });

        // Transaction History Actions
        dom.btnRefresh.addEventListener('click', () => this.refreshTransactions());

        // Confirm Payment
        if (dom.btnConfirm) {
            dom.btnConfirm.addEventListener('click', () => this.processPayment());
        }

        // Copy buttons
        dom.btnCopy.forEach(btn => {
            btn.addEventListener('click', () => this.copyToClipboard(btn));
        });
    }

    // Clean up intervals
    cleanupIntervals() {
        if (this.state.paymentCheckInterval) {
            clearInterval(this.state.paymentCheckInterval);
            this.state.paymentCheckInterval = null;
        }
        
        if (this.state.countdownInterval) {
            clearInterval(this.state.countdownInterval);
            this.state.countdownInterval = null;
        }
        
        if (this.cardPaymentState.checkInterval) {
            clearInterval(this.cardPaymentState.checkInterval);
            this.cardPaymentState.checkInterval = null;
        }
    }

    async loadTransactionHistory(page = 1) {
        // Check cache first
        if (this.state.transactionCache && 
            this.state.currentPage === page && 
            Date.now() - this.state.lastRefresh < this.constants.cacheTTL) {
            return;
        }
    
        this.dom.transactionsList.innerHTML = `
            <div class="transaction-loading">
                <i class="fas fa-spinner fa-spin"></i> Loading transactions...
            </div>
        `;
        
        try {
            const response = await fetch(`/api/topup/history.php?page=${page}`);
            
            // Handle empty responses
            if (response.status === 204 || response.headers.get('Content-Length') === '0') {
                this.dom.transactionsList.innerHTML = `
                    <div class="transaction-empty">
                        <i class="fas fa-info-circle"></i> No transactions found
                    </div>
                `;
                return;
            }
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.success) {
                this.state.transactionCache = data;
                this.state.currentPage = page;
                this.state.lastRefresh = Date.now();
                this.displayTransactions(data.transactions);
                this.setupPagination(data.total, data.pages, page);
            } else {
                throw new Error(data.error || 'Failed to load transaction history');
            }
        } catch (error) {
            console.error('Transaction history error:', error);
            this.dom.transactionsList.innerHTML = `
                <div class="transaction-error">
                    <i class="fas fa-exclamation-circle"></i> Couldn't load transactions
                    <button class="btn-retry">Retry</button>
                </div>
            `;
            
            // Add retry button functionality
            this.dom.transactionsList.querySelector('.btn-retry')?.addEventListener('click', () => {
                this.loadTransactionHistory(page);
            });
        }
    }

    // Display transactions
    displayTransactions(transactions) {
        this.dom.transactionsList.innerHTML = '';
        
        if (transactions.length === 0) {
            this.dom.transactionsList.classList.add('empty');
            this.dom.transactionsList.innerHTML = '<div>No transactions found</div>';
        } else {
            this.dom.transactionsList.classList.remove('empty');
            transactions.forEach(transaction => {
                this.dom.transactionsList.appendChild(this.createTransactionElement(transaction));
            });
        }
        
        // Fill empty space if less than transactionsPerPage
        const remainingSpace = this.state.transactionsPerPage - transactions.length;
        for (let i = 0; i < remainingSpace; i++) {
            const emptyEl = document.createElement('div');
            emptyEl.className = 'transaction empty-transaction';
            emptyEl.style.visibility = 'hidden';
            emptyEl.innerHTML = '<div></div><div></div><div></div><div></div><div></div>';
            this.dom.transactionsList.appendChild(emptyEl);
        }
    }

    // Create transaction element
    createTransactionElement(transaction) {
        const transactionEl = document.createElement('div');
        transactionEl.className = 'transaction';
        transactionEl.dataset.id = transaction.id;
        
        // Get status configuration
        const statusConfig = this.statusConfig[transaction.status] || this.statusConfig.pending;
        
        // Determine icon based on payment method
        let methodIcon = 'fas fa-question-circle';
        if (transaction.payment_method.includes('paypal')) methodIcon = 'fab fa-paypal';
        else if (transaction.payment_method.includes('card')) methodIcon = 'far fa-credit-card';
        else if (transaction.payment_method.includes('btc')) methodIcon = 'fab fa-bitcoin';
        else if (transaction.payment_method.includes('eth')) methodIcon = 'fab fa-ethereum';
        else if (transaction.payment_method.includes('ltc')) methodIcon = 'fas fa-coins';
        
        // Format date
        const date = new Date(transaction.created_at);
        const formattedDate = date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric'
        });
        
        transactionEl.innerHTML = `
            <div class="transaction-icon ${statusConfig.iconClass}">
                <i class="${statusConfig.icon}"></i>
            </div>
            <div class="transaction-amount ${statusConfig.amountClass}">
                $${parseFloat(transaction.amount).toFixed(2)}
            </div>
            <div class="transaction-method">
                <div class="method-icon">
                    <i class="${methodIcon}"></i>
                </div>
                <span>${this.formatPaymentMethod(transaction.payment_method)}</span>
            </div>
            <div class="transaction-status status-${transaction.status}">
                ${transaction.status.charAt(0).toUpperCase() + transaction.status.slice(1)}
            </div>
            <div class="transaction-date">
                ${formattedDate}
            </div>
        `;
        
        // Add click handler to view details
        transactionEl.addEventListener('click', () => {
            this.viewTransactionDetails(transaction.id);
        });
        
        return transactionEl;
    }

    // Format payment method for display
    formatPaymentMethod(method) {
        const methodMap = {
            'paypal': 'PayPal',
            'btc': 'Bitcoin',
            'eth': 'Ethereum',
            'ltc': 'Litecoin',
            'card': 'Credit/Debit Card',
            'crypto': 'Cryptocurrency'
        };
        
        return methodMap[method] || method.charAt(0).toUpperCase() + method.slice(1);
    }

    // Check for old pending payments
    async checkOldPendingPayments() {
        if (this.state.isCheckingPayments) return;
        this.state.isCheckingPayments = true;
        
        try {
            const response = await fetch('/api/topup/get_pending_payments.php');
            
            // Handle empty responses
            if (response.status === 204 || response.headers.get('Content-Length') === '0') {
                return;
            }
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data?.success && Array.isArray(data.pending_payments)) {
                const now = Date.now();
                for (const payment of data.pending_payments) {
                    const paymentDate = new Date(payment.created_at).getTime();
                    // Check if payment is older than 1 minute
                    if (now - paymentDate > 60000) {
                        await this.checkSinglePaymentStatus(
                            payment.order_id, 
                            payment.payment_method
                        );
                    }
                }
            }
        } catch (error) {
            if (error instanceof SyntaxError) {
                console.warn('Empty or invalid JSON response from pending payments API');
            } else {
                console.error('Error checking pending payments:', error);
            }
        } finally {
            this.state.isCheckingPayments = false;
        }
    }

    // Check status of a single payment
    async checkSinglePaymentStatus(orderId, paymentMethod) {
        try {
            // Determine endpoint based on payment method
            const endpoint = ['btc', 'eth', 'ltc', 'sol', 'paypal'].includes(paymentMethod)
                ? `/api/topup/status.php?order_id=${orderId}&source=sellsn`
                : `/api/topup/card/status.php?order_id=${orderId}`;

            const response = await fetch(endpoint);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.success) {
                if (data.status === 'completed' || data.status === 'failed' || data.status === 'expired') {
                    await this.updatePaymentStatus(orderId, data.status, data.current_balance);
                    
                    if (orderId === this.state.currentOrderId) {
                        this.updatePaymentUI(data.status, data.current_balance);
                    }
                }
                return data;
            }
            throw new Error(data.error || 'Error checking payment status');
        } catch (error) {
            console.error(`Error checking payment status (${paymentMethod}):`, error);
            throw error;
        }
    }

    // Update payment UI based on status
    updatePaymentUI(status, balance) {
        const statusIndicators = document.querySelectorAll('.payment-status .status-indicator');
        
        if (status === 'completed') {
            statusIndicators.forEach(el => {
                if (el) {
                    el.className = 'status-indicator success';
                    el.innerHTML = '<i class="fas fa-check-circle"></i> <span>Payment completed!</span>';
                }
            });
            
            this.updateUserBalance(balance);
            this.loadTransactionHistory();
            this.stopPaymentStatusCheck();
            this.stopCountdown();
            
            this.showPaymentSuccessModal({
                amount: this.state.selectedAmount,
                current_balance: balance,
                order_id: this.state.currentOrderId,
                payment_method: this.state.selectedMethod
            });
        } else if (status === 'failed' || status === 'expired') {
            statusIndicators.forEach(el => {
                if (el) {
                    el.className = 'status-indicator failed';
                    el.innerHTML = `<i class="fas fa-times-circle"></i> <span>Payment ${status}</span>`;
                }
            });
            
            this.stopPaymentStatusCheck();
            this.stopCountdown();
        }
    }

    // =============================
    // CARD PAYMENT METHODS
    // =============================
    
    async checkCardPaymentStatus() {
        this.cardPaymentState.attempts++;
        
        try {
            const response = await fetch(`/api/topup/card/status.php?order_id=${this.state.currentOrderId}`);
            const data = await response.json();
            
            if (data.success) {
                switch(data.data.status) {
                    case 'completed':
                        this.handleCardPaymentSuccess(data.data);
                        break;
                    case 'failed':
                        this.handlePaymentFailure(data.message || 'Payment failed');
                        break;
                    // Continue checking for pending status
                }
            } else {
                throw new Error(data.error || 'Status check failed');
            }
        } catch (error) {
            this.handlePaymentFailure('Connection error while checking status');
        }
        
        // Stop after max attempts
        if (this.cardPaymentState.attempts >= this.cardPaymentState.maxAttempts) {
            clearInterval(this.cardPaymentState.checkInterval);
            this.handlePaymentFailure('Payment verification timeout');
        }
    }
    
    handleCardPaymentSuccess(paymentData) {
        clearInterval(this.cardPaymentState.checkInterval);
        
        // Update balance display
        this.updateUserBalance(paymentData.current_balance);
        
        // Show success message
        this.showSuccess('Payment completed! Your balance has been updated.');
        
        // Refresh transactions
        this.loadTransactionHistory();
        
        // Close payment window if still open
        if (this.cardPaymentState.paymentWindow) {
            this.cardPaymentState.paymentWindow.close();
        }
        
        // Close modal after success
        setTimeout(() => this.closeModal(), 3000);
    }
    
    handlePaymentFailure(message) {
        clearInterval(this.cardPaymentState.checkInterval);
        this.showError(message);
        
        // Close payment window if still open
        if (this.cardPaymentState.paymentWindow) {
            this.cardPaymentState.paymentWindow.close();
        }
    }
    
    // =============================
    // UI METHODS
    // =============================
    
    showProcessing() {
        if (this.dom.processingOverlay) {
            this.dom.processingOverlay.style.display = 'flex';
        }
        if (this.dom.paymentSuccess) {
            this.dom.paymentSuccess.style.display = 'none';
        }
        if (this.dom.paymentFailed) {
            this.dom.paymentFailed.style.display = 'none';
        }
    }
    
    showSuccess(message) {
        if (this.dom.paymentSuccess) {
            this.dom.paymentSuccess.innerHTML = message || 'Payment completed!';
            this.dom.paymentSuccess.style.display = 'block';
        }
        
        setTimeout(() => {
            if (this.dom.processingOverlay) {
                this.dom.processingOverlay.style.display = 'none';
            }
            if (this.cardPaymentState.paymentWindow) {
                this.cardPaymentState.paymentWindow.close();
            }
        }, 3000);
    }
    
    showError(message) {
        if (this.dom.paymentFailed) {
            this.dom.errorMessage.textContent = message;
            this.dom.paymentFailed.style.display = 'block';
        }
        
        setTimeout(() => {
            if (this.dom.processingOverlay) {
                this.dom.processingOverlay.style.display = 'none';
            }
            if (this.cardPaymentState.paymentWindow) {
                this.cardPaymentState.paymentWindow.close();
            }
        }, 5000);
    }

    // Update payment status in database
    async updatePaymentStatus(orderId, status, currentBalance) {
        try {
            const response = await fetch('/api/topup/update_payment_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: orderId,
                    status: status,
                    current_balance: currentBalance
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Refresh transactions if we're on the transactions page
                if (window.location.pathname.includes('transactions')) {
                    this.loadTransactionHistory(this.state.currentPage);
                }
                // Update balance display if needed
                if (currentBalance && this.dom.balanceAmount) {
                    this.updateUserBalance(currentBalance);
                }
            }
        } catch (error) {
            console.error('Error updating payment status:', error);
        }
    }

    // Setup pagination
    setupPagination(total, totalPages, currentPage) {
        this.dom.pagination.innerHTML = '';
        
        if (totalPages <= 1) return;
        
        // Previous button
        const prevBtn = document.createElement('button');
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevBtn.className = 'pagination-btn';
        prevBtn.disabled = currentPage === 1;
        prevBtn.addEventListener('click', () => {
            this.loadTransactionHistory(currentPage - 1);
        });
        this.dom.pagination.appendChild(prevBtn);
        
        // Always show first page
        if (currentPage > 2) {
            const firstPage = document.createElement('button');
            firstPage.textContent = '1';
            firstPage.className = 'pagination-btn';
            firstPage.addEventListener('click', () => this.loadTransactionHistory(1));
            this.dom.pagination.appendChild(firstPage);
            
            if (currentPage > 3) {
                const ellipsis = document.createElement('span');
                ellipsis.textContent = '...';
                ellipsis.className = 'pagination-ellipsis';
                this.dom.pagination.appendChild(ellipsis);
            }
        }
        
        // Show pages around current page
        const startPage = Math.max(1, currentPage - 1);
        const endPage = Math.min(totalPages, currentPage + 1);
        
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.textContent = i;
            pageBtn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
            pageBtn.addEventListener('click', () => this.loadTransactionHistory(i));
            this.dom.pagination.appendChild(pageBtn);
        }
        
        // Always show last page
        if (currentPage < totalPages - 1) {
            if (currentPage < totalPages - 2) {
                const ellipsis = document.createElement('span');
                ellipsis.textContent = '...';
                ellipsis.className = 'pagination-ellipsis';
                this.dom.pagination.appendChild(ellipsis);
            }
            
            const lastPage = document.createElement('button');
            lastPage.textContent = totalPages;
            lastPage.className = 'pagination-btn';
            lastPage.addEventListener('click', () => this.loadTransactionHistory(totalPages));
            this.dom.pagination.appendChild(lastPage);
        }
        
        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextBtn.className = 'pagination-btn';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.addEventListener('click', () => {
            this.loadTransactionHistory(currentPage + 1);
        });
        this.dom.pagination.appendChild(nextBtn);
    }

    // Modal Functions
    openModal() {
        // First check if user has pending transactions
        this.checkForPendingTransactions()
            .then(hasPending => {
                if (hasPending) {
                    this.showError('You already have a pending transaction. Please complete or cancel it before creating a new one.');
                    return;
                }
                
                this.dom.addFundsModal.classList.add('active');
                document.body.style.overflow = 'hidden';
                this.resetModal();
            })
            .catch(error => {
                console.error('Error checking for pending transactions:', error);
                this.dom.addFundsModal.classList.add('active');
                document.body.style.overflow = 'hidden';
                this.resetModal();
            });
    }

    closeModal() {
        this.dom.addFundsModal.classList.remove('active');
        document.body.style.overflow = '';
        this.stopPaymentStatusCheck();
        this.stopCountdown();
        
        // Clean up card payment
        if (this.cardPaymentState.checkInterval) {
            clearInterval(this.cardPaymentState.checkInterval);
            this.cardPaymentState.checkInterval = null;
        }
        if (this.cardPaymentState.paymentWindow) {
            this.cardPaymentState.paymentWindow.close();
        }
    }

    openTransactionModal() {
        this.dom.transactionModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    closeTransactionModal() {
        this.dom.transactionModal.classList.remove('active');
        document.body.style.overflow = '';
    }

    resetModal() {
        this.goToStep(1);
        this.dom.amountOptions.forEach(opt => opt.classList.remove('active'));
        this.dom.customAmountInput.value = '';
        this.dom.paymentMethods.forEach(m => m.classList.remove('active'));
        document.querySelectorAll('.payment-form').forEach(form => form.style.display = 'none');
        
        // Initialize first payment method as active
        if (this.dom.paymentMethods.length > 0) {
            this.dom.paymentMethods[0].classList.add('active');
            this.state.selectedMethod = this.dom.paymentMethods[0].getAttribute('data-method');
        }
        
        // Initialize first amount option as active
        if (this.dom.amountOptions.length >= 3) {
            this.dom.amountOptions[2].classList.add('active');
            this.state.selectedAmount = parseFloat(this.dom.amountOptions[2].getAttribute('data-amount'));
        }
    }

    goToStep(step) {
        // Animate step transition
        const currentActive = document.querySelector('.modal-step.active');
        if (currentActive) {
            currentActive.style.opacity = '0';
            currentActive.style.transform = 'translateY(10px)';
            setTimeout(() => {
                currentActive.classList.remove('active');
                this.showStep(step);
            }, 200);
        } else {
            this.showStep(step);
        }
    }

    showStep(step) {
        const stepElement = document.querySelector(`.modal-step[data-step="${step}"]`);
        stepElement.classList.add('active');
        setTimeout(() => {
            stepElement.style.opacity = '1';
            stepElement.style.transform = 'translateY(0)';
        }, 10);
    }

    // Check if user has pending transactions
    async checkForPendingTransactions() {
        try {
            const response = await fetch('/api/topup/get_pending_payments.php');
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            return data.success && data.pending_payments.length > 0;
        } catch (error) {
            console.error('Error checking pending transactions:', error);
            return false;
        }
    }

    // Validation Functions
    validateStep(nextStep) {
        if (nextStep == "2" && !this.validateAmount()) return false;
        if (nextStep == "3" && !this.validatePaymentMethod()) return false;
        return true;
    }

    validateAmount() {
        const selectedAmountEl = document.querySelector('.amount-option.active');
        
        if (!selectedAmountEl) {
            this.showError('Please select or enter an amount');
            return false;
        }
        
        if (selectedAmountEl.classList.contains('custom-amount-option')) {
            const amount = parseFloat(this.dom.customAmountInput.value);
            if (isNaN(amount)) {
                this.showError('Please enter a valid amount');
                return false;
            }
            if (amount < 1 || amount > 1000) {
                this.showError('Amount must be between $1 and $1000');
                return false;
            }
        }
        
        return true;
    }

    validatePaymentMethod() {
        const selectedMethodEl = document.querySelector('.payment-method.active');
        if (!selectedMethodEl) {
            this.showError('Please select a payment method');
            return false;
        }
        return true;
    }

    // Process payment
    async processPayment() {
        if (this.state.isProcessingPayment) return;
        if (!this.validateAmount() || !this.validatePaymentMethod()) return;

        this.state.isProcessingPayment = true;
        this.dom.btnConfirm.disabled = true;
        this.dom.btnConfirm.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        try {
            const paymentData = {
                amount: this.state.selectedAmount,
                payment_method: this.state.selectedMethod,
                currency: 'USD'
            };

            // Determine endpoint based on payment method
            const endpoint = this.state.selectedMethod === 'card' 
                ? '/api/topup/card/create.php'
                : '/api/topup/create.php';

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(paymentData)
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Payment processing failed');
            }

            // Store transaction data
            this.state.currentOrderId = this.state.selectedMethod === 'card' 
                ? data.data.transactionId 
                : data.order_id;
                
            this.state.currentPaymentMethod = this.state.selectedMethod;

            // Proceed to payment step
            this.goToStep(4);
            
            // Show appropriate payment form
            this.showPaymentForm(data);

        } catch (error) {
            console.error('Payment processing error:', error);
            this.showError(error.message);
        } finally {
            this.state.isProcessingPayment = false;
            this.dom.btnConfirm.disabled = false;
            this.dom.btnConfirm.textContent = 'Confirm Payment';
        }
    }

    showPaymentForm(data) {
        // Hide all forms first
        document.querySelectorAll('.payment-form').forEach(f => f.style.display = 'none');
        
        const formId = `${this.state.selectedMethod}-form`;
        const paymentForm = document.getElementById(formId);
        if (!paymentForm) throw new Error('Payment form not found');
        
        paymentForm.style.display = 'block';
        
        // Update form with transaction details
        if (this.state.selectedMethod === 'card') {
            // Update amount display
            const amountEl = paymentForm.querySelector('.amount-value');
            if (amountEl) amountEl.textContent = `$${this.state.selectedAmount.toFixed(2)}`;
            
            // Set up payment button
            paymentForm.querySelector('.btn-proceed-card').onclick = () => {
                this.showProcessing();
                this.cardPaymentState.paymentWindow = window.open(
                    data.data.redirectUrl,
                    '_blank'
                );
                
                // Start status checking
                this.cardPaymentState.checkInterval = setInterval(() => {
                    this.checkCardPaymentStatus();
                }, 2000);
            };
        } else if (this.state.selectedMethod === 'paypal') {
            // Existing paypal code
        } else { 
            // Existing crypto code
        }
        
        // Common handlers
        paymentForm.querySelector('.btn-cancel-order').onclick = () => {
            this.cancelOrder(this.state.currentOrderId);
        };
    }

    // Update payment form summary
    updatePaymentFormSummary(amount, method) {
        const methodNames = {
            'btc': 'Bitcoin',
            'eth': 'Ethereum', 
            'ltc': 'Litecoin',
            'paypal': 'PayPal',
            'card': 'Credit/Debit Card'
        };
        
        const forms = document.querySelectorAll('.payment-form');
        forms.forEach(form => {
            const amountEl = form.querySelector('.amount-value');
            const methodEl = form.querySelector('.method-value');
            
            if (amountEl) amountEl.textContent = `$${amount.toFixed(2)}`;
            if (methodEl) methodEl.textContent = methodNames[method] || method;
        });
    }

    // Cancel order
    async cancelOrder(orderId) {
        if (!confirm('Are you sure you want to cancel order #' + orderId + '?')) {
            return;
        }
    
        // UI feedback
        const cancelButtons = document.querySelectorAll('.btn-cancel-order');
        cancelButtons.forEach(btn => {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
        });
    
        try {
            // Create fresh connection
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout
    
            const response = await fetch('/api/topup/cancel_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ order_id: orderId }),
                signal: controller.signal
            });
    
            clearTimeout(timeoutId);
    
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }
    
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.loadTransactionHistory();
                this.closeModal();
            } else {
                throw new Error(data.error || "Unknown error occurred");
            }
        } catch (error) {
            console.error('Cancel error:', error);
            const message = error.name === 'AbortError' 
                ? "Request timed out. Please try again."
                : error.message;
                
            this.showError(message);
        } finally {
            // Reset UI
            cancelButtons.forEach(btn => {
                btn.innerHTML = '<i class="fas fa-times"></i> Cancel';
                btn.disabled = false;
            });
        }
    }

    // View transaction details
    async viewTransactionDetails(transactionId) {
        try {
            const response = await fetch(`/api/topup/transaction_details.php?id=${transactionId}`);
            const data = await response.json();
            
            if (data.success) {
                const modal = this.dom.transactionModal;
                const content = this.dom.transactionDetails;
                
                // Format the transaction details
                let html = `
                    <div class="transaction-details-summary">
                        <div class="detail-row">
                            <span>Order ID:</span>
                            <span>${data.transaction.order_id}</span>
                        </div>
                        <div class="detail-row">
                            <span>Amount:</span>
                            <span>$${parseFloat(data.transaction.amount).toFixed(2)}</span>
                        </div>
                        <div class="detail-row">
                            <span>Payment Method:</span>
                            <span>${this.formatPaymentMethod(data.transaction.payment_method)}</span>
                        </div>
                        <div class="detail-row">
                            <span>Status:</span>
                            <span class="status-${data.transaction.status}">
                                ${data.transaction.status.charAt(0).toUpperCase() + data.transaction.status.slice(1)}
                            </span>
                        </div>
                        <div class="detail-row">
                            <span>Date:</span>
                            <span>${new Date(data.transaction.created_at).toLocaleString()}</span>
                        </div>
                `;
                
                // Add expiration time if pending
                if (data.transaction.status === 'pending') {
                    html += `
                        <div class="detail-row">
                            <span>Expires:</span>
                            <span>${this.formatTimeRemaining(data.transaction.expires_at)}</span>
                        </div>
                    `;
                }
                
                html += `</div>`;
                
                // Add payment-specific details
                if (data.transaction.status === 'pending') {
                    if (['btc', 'eth', 'ltc'].includes(data.transaction.payment_method)) {
                        html += `
                            <div class="payment-instructions">
                                <p>Send the exact amount of <strong>${data.transaction.amount} ${data.transaction.payment_method.toUpperCase()}</strong> to:</p>
                                <div class="address-container">
                                    <code>${data.transaction.crypto_address}</code>
                                    <button class="btn-copy"><i class="fas fa-copy"></i></button>
                                </div>
                            </div>
                            
                            <div class="payment-status">
                                <div class="status-indicator pending">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <span>Waiting for payment...</span>
                                </div>
                                <div class="timer">
                                    <i class="fas fa-clock"></i>
                                    <span>Time remaining: <span class="time-remaining">${this.formatTimeRemaining(data.transaction.expires_at)}</span></span>
                                </div>
                            </div>
                            
                            <div class="payment-actions">
                                <button class="btn-cancel-order">Cancel Order</button>
                                <button class="btn-check-status">Check Status</button>
                            </div>
                        `;
                    } else if (data.transaction.payment_method === 'paypal') {
                        html += `
                            <div class="payment-instructions">
                                <p><strong>Send payment to:</strong></p>
                                <p class="paypal-email">${data.transaction.paypal_email}</p>
                                <p><strong>Note:</strong> ${data.transaction.paypal_note}</p>
                            </div>
                            
                            <div class="payment-status">
                                <div class="status-indicator pending">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <span>Waiting for payment confirmation...</span>
                                </div>
                                <div class="timer">
                                    <i class="fas fa-clock"></i>
                                    <span>Time remaining: <span class="time-remaining">${this.formatTimeRemaining(data.transaction.expires_at)}</span></span>
                                </div>
                            </div>
                            
                            <div class="payment-actions">
                                <button class="btn-cancel-order">Cancel Order</button>
                                <a href="${data.transaction.checkout_url}" target="_blank" class="btn-paypal">
                                    <i class="fab fa-paypal"></i> Complete Payment on PayPal
                                </a>
                            </div>
                        `;
                    } else if (data.transaction.payment_method === 'card') {
                        html += `
                            <div class="payment-instructions">
                                <p>Your card payment is being processed. Please check the payment status.</p>
                            </div>
                            
                            <div class="payment-actions">
                                <button class="btn-cancel-order">Cancel Order</button>
                                <button class="btn-check-status">Check Status</button>
                            </div>
                        `;
                    }
                }
                
                content.innerHTML = html;
                
                // Cancel Order Button
                const cancelButton = content.querySelector('.btn-cancel-order');
                if (cancelButton) {
                    cancelButton.addEventListener('click', () => {
                        this.cancelOrder(data.transaction.order_id);
                    });
                }

                // Check Status Button
                const checkStatusButton = content.querySelector('.btn-check-status');
                if (checkStatusButton) {
                    checkStatusButton.addEventListener('click', () => {
                        this.checkPaymentStatus(data.transaction.order_id);
                    });
                }

                // Copy Button
                const copyButton = content.querySelector('.btn-copy');
                if (copyButton) {
                    copyButton.addEventListener('click', (e) => {
                        const container = e.target.closest('.address-container');
                        if (container) {
                            const codeElement = container.querySelector('code');
                            if (codeElement && codeElement.textContent) {
                                this.copyToClipboard(codeElement.textContent.trim());
                            }
                        }
                    });
                }

                // Show the modal
                this.openTransactionModal();
                
            } else {
                throw new Error(data.error || 'Failed to load transaction details');
            }
        } catch (error) {
            this.showError('Error loading transaction details: ' + error.message);
        }
    }

    // Start countdown timer
    startCountdown() {
        this.state.countdownTime = 15 * 60; // Reset to 15 minutes
        this.updateCountdownDisplay();
        
        this.stopCountdown();
        this.state.countdownInterval = setInterval(() => {
            this.state.countdownTime--;
            this.updateCountdownDisplay();
            
            if (this.state.countdownTime <= 0) {
                this.stopCountdown();
                this.showError('Payment time has expired. Please start over.');
            }
        }, 1000);
    }
    
    // Update countdown display
    updateCountdownDisplay() {
        const minutes = Math.floor(this.state.countdownTime / 60);
        const seconds = this.state.countdownTime % 60;
        document.querySelectorAll('.time-remaining').forEach(el => {
            if (el) el.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        });
    }
    
    // Stop countdown timer
    stopCountdown() {
        if (this.state.countdownInterval) {
            clearInterval(this.state.countdownInterval);
            this.state.countdownInterval = null;
        }
    }

    // Start checking payment status
    startPaymentStatusCheck() {
        this.stopPaymentStatusCheck(); // Clear any existing checks
        
        // Determine check interval based on payment method
        const interval = this.state.currentPaymentMethod === 'card' ? 5000 : 30000;
        
        this.state.paymentCheckInterval = setInterval(() => {
            this.checkPaymentStatus();
        }, interval);
    }

    // Stop checking payment status
    stopPaymentStatusCheck() {
        if (this.state.paymentCheckInterval) {
            clearInterval(this.state.paymentCheckInterval);
            this.state.paymentCheckInterval = null;
        }
    }

    // Check payment status with API
    async checkPaymentStatus() {
        try {
            const endpoint = this.state.currentPaymentMethod === 'card'
                ? `/api/topup/card/status.php?order_id=${this.state.currentOrderId}`
                : `/api/topup/status.php?order_id=${this.state.currentOrderId}`;
            
            const response = await fetch(endpoint);
            const data = await response.json();
            
            if (data.success) {
                switch(data.status) {
                    case 'completed':
                        this.handlePaymentSuccess(data);
                        break;
                    case 'failed':
                        this.handlePaymentFailure(data.error || 'Payment failed');
                        break;
                    // Continue checking for other statuses
                }
            } else {
                throw new Error(data.error || 'Status check failed');
            }
        } catch (error) {
            console.error('Status check error:', error);
        }
    }
    
    handlePaymentSuccess(data) {
        this.stopPaymentStatusCheck();
        this.updateUserBalance(data.current_balance);
        this.loadTransactionHistory();
        this.showSuccess('Payment completed!');
    }

    // Show payment success modal
    showPaymentSuccessModal(data) {
        const successModal = document.createElement('div');
        successModal.className = 'payment-success-modal';
        successModal.innerHTML = `
            <div class="modal-overlay active">
                <div class="modal-container success">
                    <div class="modal-header">
                        <h3>Payment Successful</h3>
                    </div>
                    <div class="modal-body">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4>$${data.amount.toFixed(2)} has been added to your balance</h4>
                        <p>Your new balance is $${data.current_balance.toFixed(2)}</p>
                        
                        <div class="transaction-details">
                            <div class="detail-row">
                                <span>Order ID:</span>
                                <span>${data.order_id}</span>
                            </div>
                            <div class="detail-row">
                                <span>Payment Method:</span>
                                <span>${data.payment_method}</span>
                            </div>
                            <div class="detail-row">
                                <span>Status:</span>
                                <span class="status-completed">Completed</span>
                            </div>
                        </div>
                        
                        <button class="btn-close-success">Close</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(successModal);
        
        // Close modal handler
        successModal.querySelector('.btn-close-success').addEventListener('click', () => {
            document.body.removeChild(successModal);
            this.closeModal();
        });
    }
    
    // Format time remaining
    formatTimeRemaining(expiresAt) {
        const now = new Date();
        const expires = new Date(expiresAt);
        const diff = Math.max(0, (expires - now) / 1000); // in seconds
        
        const minutes = Math.floor(diff / 60);
        const seconds = Math.floor(diff % 60);
        
        return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }

    // Copy to clipboard
    copyToClipboard(btn) {
        const parent = btn.closest('.address-container') || btn.closest('.crypto-address-container');
        const codeElement = parent ? parent.querySelector('code') : null;
        const text = codeElement ? codeElement.textContent : '';
        
        if (!text) return;
        
        navigator.clipboard.writeText(text).then(() => {
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy:', err);
        });
    }

    // Update user balance display
    updateUserBalance(balance) {
        if (this.dom.balanceAmount) {
            const formattedBalance = isNaN(balance) ? '0.00' : parseFloat(balance).toFixed(2);
            this.dom.balanceAmount.textContent = `$${formattedBalance}`;
        }
    }

    // Refresh transactions
    refreshTransactions() {
        this.dom.btnRefresh.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        this.state.transactionCache = null; // Force refresh
        this.loadTransactionHistory(this.state.currentPage);
        setTimeout(() => {
            this.dom.btnRefresh.innerHTML = '<i class="fas fa-sync-alt"></i>';
        }, 1000);
    }

    // Show error message
    showError(message) {
        const errorEl = document.createElement('div');
        errorEl.className = 'error-message';
        errorEl.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        
        // Remove any existing error messages
        document.querySelectorAll('.error-message').forEach(el => el.remove());
        
        // Add to modal body or main content
        const modalBody = document.querySelector('.modal-body') || document.querySelector('.content-area-wrapper');
        if (modalBody) modalBody.prepend(errorEl);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            errorEl.remove();
        }, 5000);
    }

    // Show success message
    showSuccess(message) {
        const successEl = document.createElement('div');
        successEl.className = 'success-message';
        successEl.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
        
        // Remove any existing success messages
        document.querySelectorAll('.success-message').forEach(el => el.remove());
        
        // Add to modal body or main content
        const modalBody = document.querySelector('.modal-body') || document.querySelector('.content-area-wrapper');
        if (modalBody) modalBody.prepend(successEl);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            successEl.remove();
        }, 5000);
    }
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new TopUpSystem();
});