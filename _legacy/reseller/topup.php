<?php
// Enable output buffering
ob_start();

// Include required files
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../db/connection.php';
require_once '../includes/logging.php';
require_once '../includes/get_user_info.php';

// Ensure the user is authenticated
requireAuth();
requireReseller();

// Get user balance
$user_id = $_SESSION['user_id'];
$balance_query = "SELECT balance FROM usertable WHERE id = ?";
$stmt = $con->prepare($balance_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$balance_result = $stmt->get_result();
$user_data = $balance_result->fetch_assoc();
$user_balance = $user_data['balance'] ?? 0;

// Get transaction history
$transactions = [];
$stmt = $con->prepare("SELECT * FROM payment_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['amount'] = (float)$row['amount'];
    $transactions[] = $row;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name;?> • Balance Topup</title>
    <link rel="stylesheet" href="https://panel.rlbmods.com/css/style.css">
    <link rel="stylesheet" href="https://panel.rlbmods.com/css/topup.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://panel.rlbmods.com/js/profile.js" defer></script>
    <script src="https://panel.rlbmods.com/js/heartbeat.js" defer></script>
    <script src="https://panel.rlbmods.com/js/notify.js" defer></script>
    <!--<script src="js/qrcode.min.js"></script>-->
</head>
<body>
    <div class="sidebar-overlay"></div>
    <!-- Sidebar -->
    <?php include_once('../blades/sidebar/reseller-sidebar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <div class="breadcrumbs">
                    <a href="/" class="breadcrumb-item"><i class="fas fa-home"></i></a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-current">Balance Topup</span>
                </div>
            </div>
            
            <!-- Balance Display -->
            <div class="header-right">
                <div class="user-balance-container">
                    <i class="fas fa-wallet"></i>
                    <span class="user-balance">$<?php echo number_format($user_balance, 2); ?></span>
                </div>
            </div>
            
            <!-- Notifications -->
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
            <!-- Banner -->
            <div class="topup-banner gaming-hero">
                <div class="hero-overlay"></div>
                <div class="banner-content">
                    <div class="reseller-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <h1>Account Balance</h1>
                    <p class="reseller-subtitle">Add funds to your account securely</p>
                </div>
            </div>
            
            <!-- Resume Payment Section -->
            <div class="resume-payment-section" id="resumePaymentSection" style="display: none;">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Incomplete Payment</h2>
                </div>
                
                <div class="resume-payment-card">
                    <div class="resume-payment-content">
                        <div class="resume-payment-icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="resume-payment-info">
                            <h4>Payment In Progress</h4>
                            <p>You have an incomplete payment that needs attention.</p>
                            <div class="resume-payment-details">
                                <span class="resume-amount" id="resumeAmount">$0.00</span>
                                <span class="resume-method" id="resumeMethod">BTC</span>
                                <span class="resume-status pending" id="resumeStatus">Pending</span>
                            </div>
                        </div>
                        <button class="btn-resume-payment" id="btnResumePayment">
                            <i class="fas fa-play-circle"></i> Resume Payment
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Two Column Layout -->
            <div class="topup-layout">
                <!-- Left Column - Transaction History -->
                <div class="transaction-history">
                    <div class="section-header">
                        <h2><i class="fas fa-history"></i> Transaction History</h2>
                        <button class="btn-refresh" id="refreshHistory">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    
                    <div class="transactions-container">
                        <div class="transaction-header">
                            <span>Amount</span>
                            <span>Payment Method</span>
                            <span>Status</span>
                            <span>Date</span>
                        </div>
                        
                        <div class="transactions-list" id="transactionsList">
                            <?php if (empty($transactions)): ?>
                                <div class="empty-transaction">
                                    <div>No transactions found</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <div class="transaction" data-order-id="<?= htmlspecialchars($tx['order_id']) ?>" data-transaction-id="<?= $tx['id'] ?>">
                                        <div class="transaction-icon <?= $tx['status'] ?>">
                                            <?php if ($tx['payment_method'] === 'btc'): ?>
                                                <i class="fab fa-bitcoin"></i>
                                            <?php elseif ($tx['payment_method'] === 'eth'): ?>
                                                <i class="fab fa-ethereum"></i>
                                            <?php elseif ($tx['payment_method'] === 'ltc'): ?>
                                                <i class="fas fa-coins"></i>
                                            <?php elseif ($tx['payment_method'] === 'sol'): ?>
                                                <i class="fas fa-bolt"></i>
                                            <?php elseif ($tx['payment_method'] === 'usdt'): ?>
                                                <i class="fas fa-dollar-sign"></i>
                                            <?php elseif ($tx['payment_method'] === 'card'): ?>
                                                <i class="fas fa-credit-card"></i>
                                            <?php else: ?>
                                                <i class="fas fa-money-bill-wave"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="transaction-amount <?= $tx['status'] === 'completed' ? 'positive' : ($tx['status'] === 'pending' ? 'pending' : 'negative') ?>">
                                            $<?= number_format($tx['amount'], 2) ?>
                                        </div>
                                        <div class="transaction-method">
                                            <?= strtoupper($tx['payment_method']) ?>
                                        </div>
                                        <div class="transaction-status status-<?= $tx['status'] ?>">
                                            <?= ucfirst($tx['status']) ?>
                                        </div>
                                        <div class="transaction-date">
                                            <?= date('M j, Y', strtotime($tx['created_at'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="pagination-container">
                        <div class="pagination" id="pagination">
                            <!-- Pagination will be loaded here by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Balance Box -->
                <div class="balance-summary">
                    <div class="balance-box">
                        <div class="balance-content">
                            <i class="fas fa-wallet"></i>
                            <div class="balance-info">
                                <span class="balance-label">Available Balance</span>
                                <span class="balance-amount">$<?= number_format($user_balance, 2) ?></span>
                            </div>
                        </div>
                        <button class="btn-add-funds" id="addFundsBtn">
                            <i class="fas fa-plus"></i> Add Funds
                        </button>
                    </div>
                    <div class="balance-help">
                        <div class="balance-help-header">
                            <i class="fas fa-question-circle"></i>
                            <h4>Need Help?</h4>
                        </div>
                        
                        <div class="help-item">
                            <h5><i class="fas fa-exclamation-circle"></i> Payment Issues</h5>
                            <p>If your payment doesn't process, please check your payment details and try again.</p>
                        </div>
                        
                        <div class="help-item">
                            <h5><i class="fas fa-clock"></i> Processing Time</h5>
                            <p>Crypto payments may take up to 30 minutes to confirm on the blockchain.</p>
                        </div>

                        <div class="help-item">
                            <h5><i class="fas fa-redo"></i> Resume Payment</h5>
                            <p>Click on any transaction in the history to view its details and resume pending payments.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Add Funds Modal -->
        <div class="modal-overlay" id="addFundsModal">
            <div class="modal-container">
                <div class="modal-header">
                    <h3>Add Funds</h3>
                    <button class="modal-close">&times;</button>
                </div>
                
                <div class="modal-body">
                    <!-- Step 1: Amount Selection -->
                    <div class="modal-step active" data-step="1">
                        <h4>Select Amount</h4>
                        <div class="amount-options">
                            <button class="amount-option" data-amount="10">$10</button>
                            <button class="amount-option" data-amount="25">$25</button>
                            <button class="amount-option" data-amount="50">$50</button>
                            <button class="amount-option" data-amount="100">$100</button>
                            <button class="amount-option" data-amount="250">$250</button>
                            <div class="amount-option custom-amount-option">
                                <span>$</span>
                                <input type="number" id="customAmount" min="5" placeholder="Custom" class="custom-amount-input">
                            </div>
                        </div>
                        
                        <div class="modal-actions">
                            <button class="btn-continue" data-next="2">Continue</button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Payment Method -->
                    <div class="modal-step" data-step="2">
                        <h4>Select Payment Method</h4>
                        <div class="payment-methods">
                            <div class="payment-method" data-method="card">
                                <div class="method-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="method-info">
                                    <div class="method-name">Credit/Debit Card</div>
                                    <div class="method-desc">Visa, Mastercard, etc.</div>
                                </div>
                            </div>
                            <div class="payment-method" data-method="paypalfnf">
                                <div class="method-icon">
                                    <i class="fab fa-paypal"></i>
                                </div>
                                <div class="method-info">
                                    <div class="method-name">PayPal</div>
                                    <div class="method-desc"></div>
                                </div>
                            </div>
                            <div class="payment-method" data-method="btc">
                                <div class="method-icon">
                                    <i class="fab fa-bitcoin"></i>
                                </div>
                                <div class="method-info">
                                    <div class="method-name">Bitcoin</div>
                                    <div class="method-desc">BTC</div>
                                </div>
                            </div>
                            
                            <div class="payment-method" data-method="eth">
                                <div class="method-icon">
                                    <i class="fab fa-ethereum"></i>
                                </div>
                                <div class="method-info">
                                    <div class="method-name">Ethereum</div>
                                    <div class="method-desc">ETH</div>
                                </div>
                            </div>
                            
                            <div class="payment-method" data-method="ltc">
                                <div class="method-icon">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <div class="method-info">
                                    <div class="method-name">Litecoin</div>
                                    <div class="method-desc">LTC</div>
                                </div>
                            </div>
                            
                            <div class="payment-method" data-method="sol">
                                <div class="method-icon">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <div class="method-info">
                                    <div class="method-name">Solana</div>
                                    <div class="method-desc">SOL</div>
                                </div>
                            </div>
                            
                            <div class="payment-method" data-method="usdt">
                                <div class="method-icon">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="method-info">
                                    <div class="method-name">USDT</div>
                                    <div class="method-desc">Tether</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-actions">
                            <button class="btn-back" data-prev="1">Back</button>
                            <button class="btn-continue" data-next="3">Proceed to Checkout</button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Payment Details -->
                    <div class="modal-step" data-step="3">
                        <h4>Complete Payment</h4>
                        
                        <div class="payment-status-container">
                            <div id="paymentStatus">
                                <i class="fas fa-spinner fa-pulse payment-status-icon pending"></i>
                                <h4 class="payment-status-title">Waiting for Payment</h4>
                                <p class="payment-status-subtitle">Please complete your payment to continue</p>
                            </div>
                        </div>
                        
                        <div class="payment-details">
                            <div class="payment-detail-row">
                                <span class="payment-detail-label">Amount:</span>
                                <span class="payment-detail-value">$<span id="modalAmount">0.00</span></span>
                            </div>
                            <div class="payment-detail-row">
                                <span class="payment-detail-label">Payment Method:</span>
                                <span class="payment-detail-value"><span id="modalMethod">BTC</span></span>
                            </div>
                            <div class="payment-detail-row">
                                <span class="payment-detail-label">Order ID:</span>
                                <span class="payment-detail-value"><span id="modalOrderId"></span></span>
                            </div>
                            <div class="payment-detail-row">
                                <span class="payment-detail-label">Status:</span>
                                <span class="payment-detail-value"><span id="modalStatus">Pending</span></span>
                            </div>
                        </div>
                        
                        <!-- PayPal Payment Section -->
                        <div id="paypalPaymentSection" style="display: none;">
                            <div class="payment-instructions">
                                <p><strong>PayPal Payment Instructions</strong></p>
                                <p>Please send your payment to the following PayPal address:</p>
                                
                                <div class="paypal-email-container">
                                    <label>PayPal Email:</label>
                                    <div class="paypal-email-value">
                                        <code id="paypalEmail">mreagle13337@gmail.com</code>
                                        <button class="copy-btn" onclick="copyToClipboard('paypalEmail', 'PayPal email')">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="paypal-note-container">
                                    <label>Payment Note (Important):</label>
                                    <div class="paypal-note-value">
                                        <code id="paypalNote"></code>
                                        <button class="copy-btn" onclick="copyToClipboard('paypalNote', 'payment note')">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                </div>
                                
                                <p class="note-important">
                                    <i class="fas fa-exclamation-circle"></i>
                                    You MUST include the payment note for your transaction to be processed
                                </p>
                            </div>
                        </div>

                        <div id="cardPaymentSection" style="display: none;">
                            <div class="payment-instructions">
                                <p><strong>Card Payment Instructions</strong></p>
                                <p>Click the button below to complete your payment through our secure payment gateway.</p>
                                
                                <div class="card-redirect-container">
                                    <button id="cardRedirectBtn" class="btn-primary">
                                        <i class="fas fa-credit-card"></i>
                                        Complete Payment Now
                                    </button>
                                </div>
                                
                                <div class="card-security-info">
                                    <i class="fas fa-lock"></i>
                                    <span>Your payment details are encrypted and secure</span>
                                </div>
                            </div>
                        </div>

                        <!-- Crypto Payment Section -->
                        <div id="cryptoPaymentSection">
                            <div class="qr-code-container" id="qrCodeContainer"></div>
                            
                            <div class="crypto-address-container">
                                <code id="cryptoAddress" class="crypto-address"></code>
                                <button class="copy-btn" id="copyAddressBtn">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                            
                            <div class="payment-instructions">
                                <p><strong>Send exactly <span id="cryptoAmount">0.00000000 BTC</span> to the address above</strong></p>
                                <p class="mb-0 small">Your balance will update automatically after network confirmations</p>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="#" id="blockExplorerLink" target="_blank" class="btn btn-outline-light btn-sm">
                                    <i class="fas fa-external-link-alt me-1"></i> View on Block Explorer
                                </a>
                            </div>
                            
                            <div id="confirmationProgress" class="mt-3" style="display: none;">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>Network Confirmations:</span>
                                    <span id="confirmationsCount">0/3</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-success" id="confirmationsBar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- No back button in step 3 -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include_once('../blades/footer/footer.php'); ?>
    </main>

    <script>
const DEBUG_MODE = <?= DEBUG_MODE ? 'true' : 'false' ?>;

function debugLog(message, data = null) {
    if (!DEBUG_MODE) return;
    
    const timestamp = new Date().toISOString();
    let logMessage = `[${timestamp}] ${message}`;
    
    if (data !== null) {
        logMessage += ` - ${JSON.stringify(data, null, 2)}`;
    }
    
    //console.log(logMessage);
    
    // Also send to server for logging
    fetch('../api/debug_log.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            message: message,
            data: data,
            timestamp: timestamp,
            page: 'topup'
        })
    }).catch(err => {
        console.error('Failed to send debug log:', err);
    });
}

const CONFIG = {
    debug: <?= DEBUG_MODE ? 'true' : 'false' ?>,
    clientKey: '<?= PAYTABS_CLIENT_KEY ?>'
};

const state = {
    paymentWindow: null,
    checkInterval: null,
    attempts: 0,
    maxAttempts: 30
};

// Initialize Notyf for notifications
const notyf = new Notyf({
    duration: 5000,
    position: {
        x: 'right',
        y: 'top',
    },
    types: [
        {
            type: 'success',
            background: '#28a745',
            icon: {
                className: 'fas fa-check-circle',
                tagName: 'i',
                color: 'white'
            }
        },
        {
            type: 'error',
            background: '#dc3545',
            icon: {
                className: 'fas fa-exclamation-circle',
                tagName: 'i',
                color: 'white'
            }
        }
    ]
});

// Payment variables
let statusCheckInterval;
let currentCryptoAddress = '';
let currentPaymentMethod = '';
let currentOrderId = '';
let currentAmount = 0;
let pendingTransaction = null;
let currentResumeTransaction = null;

// Crypto rates cache
let cryptoRatesCache = {
    data: null,
    timestamp: 0,
    ttl: 300000 // 5 minutes in milliseconds
};

// DOM Elements
const addFundsModal = document.getElementById('addFundsModal');
const addFundsBtn = document.getElementById('addFundsBtn');
const modalClose = document.querySelector('.modal-close');
const modalSteps = document.querySelectorAll('.modal-step');
const btnContinue = document.querySelectorAll('.btn-continue');
const btnBack = document.querySelectorAll('.btn-back');
const amountOptions = document.querySelectorAll('.amount-option');
const customAmountInput = document.getElementById('customAmount');
const paymentMethods = document.querySelectorAll('.payment-method');
const refreshHistoryBtn = document.getElementById('refreshHistory');
const copyAddressBtn = document.getElementById('copyAddressBtn');
const cryptoAddress = document.getElementById('cryptoAddress');
const cryptoAmount = document.getElementById('cryptoAmount');
const modalAmount = document.getElementById('modalAmount');
const modalMethod = document.getElementById('modalMethod');
const modalOrderId = document.getElementById('modalOrderId');
const modalStatus = document.getElementById('modalStatus');
const blockExplorerLink = document.getElementById('blockExplorerLink');
const qrCodeContainer = document.getElementById('qrCodeContainer');
const confirmationProgress = document.getElementById('confirmationProgress');
const transactionsList = document.getElementById('transactionsList');
const resumePaymentSection = document.getElementById('resumePaymentSection');
const btnResumePayment = document.getElementById('btnResumePayment');

// Payment sections
const cryptoSection = document.getElementById('cryptoPaymentSection');
const paypalSection = document.getElementById('paypalPaymentSection');
const cardSection = document.getElementById('cardPaymentSection');

// Function to fetch crypto rates from CoinGecko
async function fetchCryptoRates() {
    const now = Date.now();
    
    // Return cached data if still valid
    if (cryptoRatesCache.data && (now - cryptoRatesCache.timestamp) < cryptoRatesCache.ttl) {
        return cryptoRatesCache.data;
    }
    
    const response = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,litecoin,solana,tether&vs_currencies=usd');
    
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const rates = await response.json();
    
    // Update cache
    cryptoRatesCache.data = rates;
    cryptoRatesCache.timestamp = now;
    
    return rates;
}

// Get crypto ID for CoinGecko API
function getCryptoId(method) {
    const cryptoMap = {
        'btc': 'bitcoin',
        'eth': 'ethereum',
        'ltc': 'litecoin',
        'sol': 'solana',
        'usdt': 'tether'
    };
    return cryptoMap[method] || 'bitcoin';
}

// Calculate crypto amount using CoinGecko API
async function calculateCryptoAmount(usdAmount, cryptoMethod) {
    const rates = await fetchCryptoRates();
    const cryptoId = getCryptoId(cryptoMethod);
    
    if (!rates || !rates[cryptoId] || !rates[cryptoId].usd) {
        throw new Error(`Rate not available for ${cryptoMethod}`);
    }
    
    const rate = rates[cryptoId].usd;
    const cryptoAmount = parseFloat(usdAmount) / rate;
    
    // Format based on currency
    const decimals = cryptoMethod === 'btc' || cryptoMethod === 'ltc' ? 8 : 6;
    return cryptoAmount.toFixed(decimals);
}

// Get crypto price using CoinGecko API
async function getCryptoPrice(crypto) {
    const rates = await fetchCryptoRates();
    const cryptoId = getCryptoId(crypto);
    
    if (!rates || !rates[cryptoId] || !rates[cryptoId].usd) {
        throw new Error(`Rate not available for ${crypto}`);
    }
    
    return rates[cryptoId].usd;
}

// Initialize the page
function init() {
    setupEventListeners();
    checkPendingTransactions();
    setupTransactionClickEvents();
}

// Set up all event listeners
function setupEventListeners() {
    // Add Funds Modal
    addFundsBtn.addEventListener('click', openModal);
    modalClose.addEventListener('click', closeModal);
    addFundsModal.addEventListener('click', function(e) {
        if (e.target === addFundsModal) closeModal();
    });

    // Amount Selection
    amountOptions.forEach(option => {
        option.addEventListener('click', function() {
            if (this.classList.contains('custom-amount-option')) return;
            
            document.querySelectorAll('.amount-option').forEach(opt => {
                opt.classList.remove('active');
            });
            this.classList.add('active');
            customAmountInput.value = '';
        });
    });

    // Custom Amount Input
    customAmountInput.addEventListener('focus', function() {
        document.querySelectorAll('.amount-option').forEach(opt => {
            opt.classList.remove('active');
        });
        this.closest('.amount-option').classList.add('active');
    });

    document.querySelector('.custom-amount-option').addEventListener('click', function(e) {
        if (e.target === this) {
            this.querySelector('input').focus();
        }
    });

    // Payment Method Selection
    paymentMethods.forEach(method => {
        method.addEventListener('click', function() {
            paymentMethods.forEach(m => m.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Step Navigation - Remove back button functionality for step 3
    btnContinue.forEach(btn => {
        btn.addEventListener('click', function() {
            const nextStep = this.getAttribute('data-next');
            if (validateStep(nextStep)) goToStep(nextStep);
        });
    });
    
    // Only allow back navigation for steps 1 and 2
    btnBack.forEach(btn => {
        btn.addEventListener('click', function() {
            const prevStep = this.getAttribute('data-prev');
            if (prevStep !== '3') { // Don't allow back from step 3
                goToStep(prevStep);
            }
        });
    });

    // Transaction History Refresh
    refreshHistoryBtn.addEventListener('click', refreshTransactions);

    // Copy Crypto Address
    copyAddressBtn.addEventListener('click', copyCryptoAddress);
}

// Transaction Click Events
function setupTransactionClickEvents() {
    // Add click handlers to all transactions
    document.querySelectorAll('.transaction').forEach(transaction => {
        transaction.style.cursor = 'pointer';
        transaction.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const transactionId = this.getAttribute('data-transaction-id');
            const orderId = this.getAttribute('data-order-id');
            
            if (transactionId) {
                loadAndShowTransaction(transactionId);
            } else {
                console.error('No transaction ID found');
                notyf.error('Could not load transaction details');
            }
        });
    });
}

async function loadAndShowTransaction(transactionId) {
    try {
        console.log('Loading transaction:', transactionId);
        
        const response = await fetch(`../api/topup/transaction_details.php?id=${transactionId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        console.log('Transaction response:', responseText);
        
        // Clean the response
        const cleanedResponse = cleanJSONResponse(responseText);
        
        let data;
        try {
            data = JSON.parse(cleanedResponse);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            // Try to extract JSON
            const jsonMatch = responseText.match(/\{.*\}/s);
            if (jsonMatch) {
                data = JSON.parse(jsonMatch[0]);
            } else {
                throw new Error('Could not parse server response');
            }
        }
        
        if (data.success && data.transaction) {
            // Open modal and show transaction details
            showTransactionInModal(data.transaction);
        } else {
            throw new Error(data.error || 'Transaction not found');
        }
    } catch (error) {
        console.error('Error loading transaction:', error);
        notyf.error('Error loading transaction: ' + error.message);
    }
}

function cleanJSONResponse(responseText) {
    // Remove PHP warnings and notices
    let cleaned = responseText
        .replace(/<br\s*\/?>/gi, '')
        .replace(/<b>.*?<\/b>/g, '')
        .replace(/Warning:.*? in .*? on line \d+<br\s*\/?>/gi, '')
        .replace(/Notice:.*? in .*? on line \d+<br\s*\/?>/gi, '')
        .trim();
    
    // Find the first { and last } to extract JSON
    const firstBrace = cleaned.indexOf('{');
    const lastBrace = cleaned.lastIndexOf('}');
    
    if (firstBrace !== -1 && lastBrace !== -1) {
        cleaned = cleaned.substring(firstBrace, lastBrace + 1);
    }
    
    return cleaned;
}

function showTransactionInModal(transaction) {
    console.log('Showing transaction in modal:', transaction);
    
    // Open the main payment modal
    openModal();
    
    // Set amount from transaction
    const amountOptions = document.querySelectorAll('.amount-option');
    amountOptions.forEach(opt => opt.classList.remove('active'));
    
    // Try to find matching amount option
    let foundMatch = false;
    amountOptions.forEach(opt => {
        if (parseFloat(opt.getAttribute('data-amount')) === parseFloat(transaction.amount)) {
            opt.classList.add('active');
            foundMatch = true;
        }
    });
    
    // If no match, use custom amount
    if (!foundMatch) {
        const customOption = document.querySelector('.custom-amount-option');
        const customInput = document.getElementById('customAmount');
        if (customOption && customInput) {
            customOption.classList.add('active');
            customInput.value = transaction.amount;
        }
    }
    
    // Set payment method
    const paymentMethods = document.querySelectorAll('.payment-method');
    paymentMethods.forEach(method => {
        method.classList.remove('active');
        if (method.getAttribute('data-method') === transaction.payment_method) {
            method.classList.add('active');
        }
    });
    
    // Store transaction for reference
    currentResumeTransaction = transaction;
    
    // Go directly to payment details step (step 3)
    goToStep(3);
    
    // Load and display the existing payment details
    displayExistingPaymentDetails(transaction);
}

// Resume Payment Functions
async function checkPendingTransactions() {
    try {
        const response = await fetch('../api/topup/get_pending_transaction.php');
        const data = await response.json();
        
        if (data.success && data.transaction) {
            pendingTransaction = data.transaction;
            showResumePaymentSection(data.transaction);
        } else {
            hideResumePaymentSection();
        }
    } catch (error) {
        console.error('Error checking pending transactions:', error);
        hideResumePaymentSection();
    }
}

function showResumePaymentSection(transaction) {
    const resumeAmount = document.getElementById('resumeAmount');
    const resumeMethod = document.getElementById('resumeMethod');
    const resumeStatus = document.getElementById('resumeStatus');
    
    if (!resumePaymentSection || !resumeAmount || !resumeMethod || !resumeStatus || !btnResumePayment) return;
    
    // Update content
    resumeAmount.textContent = `$${parseFloat(transaction.amount).toFixed(2)}`;
    resumeMethod.textContent = transaction.payment_method.toUpperCase();
    resumeStatus.textContent = transaction.status.charAt(0).toUpperCase() + transaction.status.slice(1);
    resumeStatus.className = `resume-status ${transaction.status}`;
    
    // Update button based on status
    if (transaction.status === 'pending') {
        btnResumePayment.innerHTML = '<i class="fas fa-play-circle"></i> Resume Payment';
        btnResumePayment.onclick = () => showTransactionInModal(transaction);
    }
    
    // Show section
    resumePaymentSection.style.display = 'block';
}

function hideResumePaymentSection() {
    if (resumePaymentSection) {
        resumePaymentSection.style.display = 'none';
    }
}

async function displayExistingPaymentDetails(transaction) {
    console.log('Displaying existing payment details:', transaction);
    
    // Update modal display with transaction data
    modalAmount.textContent = parseFloat(transaction.amount).toFixed(2);
    modalMethod.textContent = transaction.payment_method.toUpperCase();
    modalOrderId.textContent = transaction.order_id;
    
    // Show/hide payment sections based on status
    const isCompleted = transaction.status === 'completed';
    const isCrypto = ['btc', 'eth', 'ltc', 'sol', 'usdt'].includes(transaction.payment_method);
    const isPayPal = transaction.payment_method === 'paypalfnf';
    const isCard = transaction.payment_method === 'card';
    
    // Hide all payment sections for completed transactions
    if (isCompleted) {
        if (cryptoSection) cryptoSection.style.display = 'none';
        if (paypalSection) paypalSection.style.display = 'none';
        if (cardSection) cardSection.style.display = 'none';
    } else {
        // Show appropriate payment section for pending/failed transactions
        if (cryptoSection) cryptoSection.style.display = isCrypto ? 'block' : 'none';
        if (paypalSection) paypalSection.style.display = isPayPal ? 'block' : 'none';
        if (cardSection) cardSection.style.display = isCard ? 'block' : 'none';
        
        // Display crypto payment details for pending transactions
        if (isCrypto && transaction.crypto_address && !isCompleted) {
            cryptoAddress.textContent = transaction.crypto_address;
            currentCryptoAddress = transaction.crypto_address;
            
            // Calculate crypto amount using API
            try {
                const cryptoAmountValue = await calculateCryptoAmount(transaction.amount, transaction.payment_method);
                cryptoAmount.textContent = `${cryptoAmountValue} ${transaction.payment_method.toUpperCase()}`;
            } catch (error) {
                console.error('Error calculating crypto amount:', error);
                notyf.error('Error calculating crypto amount. Please try again.');
                return;
            }
            
            // Generate QR code
            generateQRCode(transaction.crypto_address);
            
            // Set block explorer link
            setBlockExplorerLink(transaction.crypto_address, transaction.payment_method);
        }
        
        // Display PayPal details for pending transactions
        if (isPayPal && !isCompleted) {
            const paypalEmail = document.getElementById('paypalEmail');
            const paypalNote = document.getElementById('paypalNote');
            
            if (paypalEmail) paypalEmail.textContent = 'mreagle13337@gmail.com';
            if (paypalNote) paypalNote.textContent = transaction.order_id;
        }
    }
    
    // Update payment status display
    updatePaymentStatusDisplay(transaction.status);
    
    // Start status checking for this transaction if it's pending
    if (transaction.order_id && transaction.status === 'pending') {
        currentOrderId = transaction.order_id;
        currentPaymentMethod = transaction.payment_method;
        startStatusChecks(currentOrderId);
    }
}

function generateQRCode(address) {
    qrCodeContainer.innerHTML = '';
    
    try {
        // Try to use QRCode library if available
        if (typeof QRCode !== 'undefined') {
            new QRCode(qrCodeContainer, {
                text: address,
                width: 180,
                height: 180,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        } else {
            // Fallback to image
            qrCodeContainer.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(address)}" class="img-fluid" alt="Payment QR Code">`;
        }
    } catch (e) {
        console.error('QR code generation failed:', e);
        qrCodeContainer.innerHTML = '<div class="qr-error">QR Code Unavailable</div>';
    }
}

function setBlockExplorerLink(address, method) {
    const explorerLinks = {
        'btc': `https://blockchair.com/bitcoin/address/${address}`,
        'eth': `https://etherscan.io/address/${address}`,
        'ltc': `https://blockchair.com/litecoin/address/${address}`,
        'sol': `https://explorer.solana.com/address/${address}`,
        'usdt': `https://tronscan.org/#/address/${address}`
    };
    
    blockExplorerLink.href = explorerLinks[method] || '#';
}

// Modal Functions
function openModal() {
    addFundsModal.classList.add('active');
    document.body.style.overflow = 'hidden';
    resetModal();
}

function closeModal() {
    addFundsModal.classList.remove('active');
    document.body.style.overflow = '';
    if (statusCheckInterval) {
        clearInterval(statusCheckInterval);
        statusCheckInterval = null;
    }
    
    // Clear resume state
    currentResumeTransaction = null;
    
    // Refresh pending transactions check
    checkPendingTransactions();
}

function resetModal() {
    goToStep(1);
    document.querySelectorAll('.amount-option').forEach(opt => opt.classList.remove('active'));
    customAmountInput.value = '';
    paymentMethods.forEach(m => m.classList.remove('active'));
    paymentMethods[0].classList.add('active');
}

function goToStep(step) {
    modalSteps.forEach(s => s.classList.remove('active'));
    document.querySelector(`.modal-step[data-step="${step}"]`).classList.add('active');
    
    // Hide back button in step 3
    const backButtons = document.querySelectorAll('.btn-back');
    backButtons.forEach(btn => {
        if (step === '3') {
            btn.style.display = 'none';
        } else {
            btn.style.display = 'inline-block';
        }
    });
    
    // If going to step 3, initialize payment (unless resuming)
    if (step === '3' && !currentResumeTransaction) {
        initializePayment();
    }
}

// Validation Functions
function validateStep(nextStep) {
    if (nextStep == "2" && !validateAmount()) return false;
    if (nextStep == "3" && !validatePaymentMethod()) return false;
    return true;
}

function validateAmount() {
    const selectedAmount = document.querySelector('.amount-option.active');
    const amount = customAmountInput.value;
    
    if (!selectedAmount) {
        notyf.error('Please select or enter an amount');
        return false;
    }
    
    if (selectedAmount.classList.contains('custom-amount-option') && (!amount || parseFloat(amount) < 0 || parseFloat(amount) > 1000)) {
        notyf.error('Amount must be between $5 and $1000');
        return false;
    }
    
    return true;
}

function validatePaymentMethod() {
    const selectedMethod = document.querySelector('.payment-method.active');
    if (!selectedMethod) {
        notyf.error('Please select a payment method');
        return false;
    }
    return true;
}

async function initializePayment() {
    // Get selected amount
    let amount = 0;
    const selectedAmount = document.querySelector('.amount-option.active');
    
    if (selectedAmount.classList.contains('custom-amount-option')) {
        amount = parseFloat(customAmountInput.value) || 0;
    } else if (selectedAmount) {
        amount = parseFloat(selectedAmount.getAttribute('data-amount'));
    }
    
    // Get selected method
    const selectedMethod = document.querySelector('.payment-method.active');
    const method = selectedMethod ? selectedMethod.getAttribute('data-method') : 'btc';
    
    // Store for later use
    currentAmount = amount;
    currentPaymentMethod = method;
    
    // Update modal display
    modalAmount.textContent = amount.toFixed(2);
    modalMethod.textContent = method.toUpperCase();
    
    try {
        let apiEndpoint, requestData;
        
        // For card payments, use the card payment handler
        if (method === 'card') {
            apiEndpoint = '../api/topup/card/payment_handler.php';
            requestData = {
                action: 'create_payment_page',
                amount: amount,
                currency: 'USD',
                productDescription: `Balance Top-Up ($${amount})`
            };
        } else {
            // For other payment methods
            apiEndpoint = '../api/topup/create.php';
            requestData = {
                amount: amount,
                paymentMethod: method
            };
        }
        
        const response = await fetch(apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Client-Key': '<?= PAYTABS_CLIENT_KEY ?>'
            },
            body: JSON.stringify(requestData)
        });

        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || 'Payment failed');
        }

        if (result.success) {
            await setupPaymentDetails(result.data, amount, method);
        } else {
            throw new Error(result.error || 'Unknown error');
        }
    } catch (error) {
        console.error('Payment error:', error);
        notyf.error("Error: " + error.message);
        goToStep(1);
    }
}

async function setupPaymentDetails(data, amount, method) {
    console.log('Payment details received:', {data, amount, method});
    
    currentOrderId = data.order_id || data.transactionId;
    currentCryptoAddress = data.payment_address || '';
    
    // Set basic info
    modalOrderId.textContent = currentOrderId;
    
    // Handle different payment methods
    const isCrypto = ['btc', 'eth', 'ltc', 'sol', 'usdt'].includes(method);
    const isPayPal = method === 'paypalfnf';
    const isCard = method === 'card';
    
    console.log('Payment method detection:', {isCrypto, isPayPal, isCard});
    
    // Show/hide appropriate sections
    if (cryptoSection) cryptoSection.style.display = isCrypto ? 'block' : 'none';
    if (paypalSection) paypalSection.style.display = isPayPal ? 'block' : 'none';
    if (cardSection) cardSection.style.display = isCard ? 'block' : 'none';
    
    if (isCrypto && data.payment_address) {
        console.log('Setting up crypto payment');
        // Display crypto address
        cryptoAddress.textContent = data.payment_address;
        
        // Calculate crypto amount using API
        try {
            const cryptoAmountValue = await calculateCryptoAmount(amount, method);
            cryptoAmount.textContent = `${cryptoAmountValue} ${method.toUpperCase()}`;
        } catch (error) {
            console.error('Error calculating crypto amount:', error);
            notyf.error('Error calculating crypto amount. Please try again.');
            return;
        }
        
        // Generate QR code
        qrCodeContainer.innerHTML = '';
        try {
            new QRCode(qrCodeContainer, {
                text: data.payment_address,
                width: 180,
                height: 180,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        } catch (e) {
            // Fallback to image if QRCode fails
            qrCodeContainer.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(data.payment_address)}" class="img-fluid" alt="Payment QR Code">`;
        }
        
        // Set block explorer link
        const explorerLinks = {
            btc: `https://blockchair.com/bitcoin/address/${data.payment_address}`,
            eth: `https://etherscan.io/address/${data.payment_address}`,
            ltc: `https://blockchair.com/litecoin/address/${data.payment_address}`,
            sol: `https://explorer.solana.com/address/${data.payment_address}`,
            usdt: `https://tronscan.org/#/address/${data.payment_address}`
        };
        
        blockExplorerLink.href = explorerLinks[method] || '#';
    }
    
    if (isPayPal) {
        console.log('Setting up PayPal payment');
        // Update PayPal section with email and note
        const paypalEmailElement = document.getElementById('paypalEmail');
        const paypalNoteElement = document.getElementById('paypalNote');
        
        if (paypalEmailElement) {
            paypalEmailElement.textContent = data.paypal_email || 'mreagle13337@gmail.com';
        }
        
        if (paypalNoteElement) {
            paypalNoteElement.textContent = data.paypal_note || data.order_id || currentOrderId;
        }
    }
    
    if (isCard && data.redirectUrl) {
        // Update Card section elements
        const cardAmountElement = document.getElementById('cardAmount');
        const cardOrderIdElement = document.getElementById('cardOrderId');
        
        if (cardAmountElement) {
            cardAmountElement.textContent = amount.toFixed(2);
        }
        
        if (cardOrderIdElement) {
            cardOrderIdElement.textContent = currentOrderId;
        }
        
        // Add event listener to redirect button
        const cardRedirectBtn = document.getElementById('cardRedirectBtn');
        if (cardRedirectBtn) {
            cardRedirectBtn.onclick = function() {
                // Open payment page in a new window
                const paymentWindow = window.open(data.redirectUrl, '_blank', 'width=600,height=700,scrollbars=yes');
                
                // Start checking payment status after a short delay
                setTimeout(() => {
                    checkPaymentStatus();
                    statusCheckInterval = setInterval(checkPaymentStatus, 5000);
                }, 3000);
            };
        }
    }
    
    // Start status checks for all payment methods
    startStatusChecks(currentOrderId);
}

function updatePaymentStatusDisplay(status) {
    const statusElement = document.getElementById('paymentStatus');
    if (!statusElement) return;
    
    let statusIcon, statusTitle, statusSubtitle;
    
    switch(status) {
        case 'completed':
            statusIcon = '<i class="fas fa-check-circle payment-status-icon completed"></i>';
            statusTitle = 'Payment Completed';
            statusSubtitle = 'Your payment has been successfully processed';
            break;
        case 'failed':
        case 'expired':
            statusIcon = '<i class="fas fa-times-circle payment-status-icon failed"></i>';
            statusTitle = 'Payment Failed';
            statusSubtitle = 'This payment could not be completed';
            break;
        default:
            statusIcon = '<i class="fas fa-spinner fa-pulse payment-status-icon pending"></i>';
            statusTitle = 'Payment In Progress';
            statusSubtitle = 'Please complete your payment';
    }
    
    statusElement.innerHTML = `${statusIcon}<h4 class="payment-status-title">${statusTitle}</h4><p class="payment-status-subtitle">${statusSubtitle}</p>`;
    modalStatus.textContent = status.charAt(0).toUpperCase() + status.slice(1);
}

function startStatusChecks(orderId) {
    // Clear any existing interval
    if (statusCheckInterval) {
        clearInterval(statusCheckInterval);
    }
    
    currentOrderId = orderId;
    
    // First immediate check
    checkPaymentStatus();
    
    // Then check every 15 seconds for first 5 minutes, then every 60 seconds
    let checks = 0;
    statusCheckInterval = setInterval(() => {
        checks++;
        checkPaymentStatus();
        
        // After 20 checks (5 minutes at 15s intervals), switch to 60s interval
        if (checks === 20) {
            clearInterval(statusCheckInterval);
            statusCheckInterval = setInterval(checkPaymentStatus, 60000);
        }
    }, 15000);
}

async function checkPaymentStatus() {
    if (!currentOrderId) return;
    
    try {
        let statusEndpoint;
        
        // For card payments, use the card status endpoint
        if (currentPaymentMethod === 'card') {
            statusEndpoint = '../api/topup/card/payment_handler.php?action=check_payment_status';
        } else {
            statusEndpoint = `../api/topup/status.php?order_id=${currentOrderId}`;
        }
        
        const response = await fetch(statusEndpoint, {
            headers: { 'Client-Key': '<?= PAYTABS_CLIENT_KEY ?>' }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Status check failed');
        }
        
        // Handle the response
        const statusData = currentPaymentMethod === 'card' ? data.data : data;
        
        if (!statusData) {
            throw new Error('Invalid status data received');
        }
        
        updatePaymentStatus(statusData);
        
        // If payment completed, refresh balance and history
        if (statusData.status === 'completed') {
            // Get updated balance from server
            const balanceResponse = await fetch('../api/user/balance.php');
            if (balanceResponse.ok) {
                const balanceData = await balanceResponse.json();
                if (balanceData.success) {
                    updateUserBalance(balanceData.balance);
                }
            }
            
            refreshTransactions();
            
            // Show success message
            notyf.success('Payment completed successfully! Your balance has been updated.');
            
            // Close modal after successful payment
            setTimeout(() => {
                closeModal();
            }, 3000);
            
            // Stop checking status
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
                statusCheckInterval = null;
            }
            
            // Refresh pending transactions section
            checkPendingTransactions();
        }
    } catch (error) {
        console.error('Status check error:', error);
        notyf.error('Error checking payment status: ' + error.message);
    }
}

function updatePaymentStatus(statusData) {
    const statusElement = document.getElementById('paymentStatus');
    const confirmationProgress = document.getElementById('confirmationProgress');
    
    // Update status display
    let statusIcon, statusClass, statusTitle, statusSubtitle;
    switch(statusData.status) {
        case 'completed':
            statusIcon = '<i class="fas fa-check-circle payment-status-icon completed"></i>';
            statusClass = 'status-paid';
            statusTitle = 'Payment Completed';
            statusSubtitle = 'Your payment has been successfully processed';
            modalStatus.textContent = 'Completed';
            break;
        case 'failed':
            statusIcon = '<i class="fas fa-times-circle payment-status-icon failed"></i>';
            statusClass = 'status-failed';
            statusTitle = 'Payment Failed';
            statusSubtitle = statusData.error || 'Payment was not completed';
            modalStatus.textContent = 'Failed';
            break;
        case 'expired':
            statusIcon = '<i class="fas fa-clock payment-status-icon failed"></i>';
            statusClass = 'status-failed';
            statusTitle = 'Payment Expired';
            statusSubtitle = 'The payment window has closed';
            modalStatus.textContent = 'Expired';
            break;
        default:
            statusIcon = '<i class="fas fa-spinner fa-pulse payment-status-icon pending"></i>';
            statusClass = 'status-pending';
            statusTitle = 'Waiting for Payment';
            statusSubtitle = 'Please complete your payment';
            modalStatus.textContent = 'Pending';
    }
    
    statusElement.innerHTML = `${statusIcon}<h4 class="payment-status-title">${statusTitle}</h4><p class="payment-status-subtitle">${statusSubtitle}</p>`;
    
    // Update confirmations progress if available
    if (statusData.confirmations !== null && statusData.confirmations !== undefined) {
        const confirmations = parseInt(statusData.confirmations) || 0;
        const maxConfirmations = 3;
        
        if (confirmations > 0) {
            confirmationProgress.style.display = 'block';
            const percentage = Math.min(100, (confirmations / maxConfirmations) * 100);
            document.getElementById('confirmationsBar').style.width = `${percentage}%`;
            document.getElementById('confirmationsCount').textContent = `${confirmations}/${maxConfirmations}`;
        }
    }
    
    // If payment completed, stop checking status
    if (statusData.status === 'completed' || statusData.status === 'failed' || statusData.status === 'expired') {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
            statusCheckInterval = null;
        }
    }
}

function updateUserBalance(newBalance) {
    // Update balance display
    document.querySelector('.balance-amount').textContent = '$' + parseFloat(newBalance).toFixed(2);
    document.querySelector('.user-balance').textContent = '$' + parseFloat(newBalance).toFixed(2);
}

async function refreshTransactions() {
    refreshHistoryBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const response = await fetch('../api/topup/history.php');
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                renderTransactionHistory(data.transactions);
                notyf.success('Transaction history refreshed');
                
                // Re-setup transaction click events after refresh
                setTimeout(setupTransactionClickEvents, 100);
            }
        }
    } catch (error) {
        console.error('Failed to load transaction history:', error);
        notyf.error('Failed to refresh transactions');
    } finally {
        refreshHistoryBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
    }
}

function renderTransactionHistory(transactions) {
    transactionsList.innerHTML = '';
    
    if (!transactions || transactions.length === 0) {
        transactionsList.innerHTML = '<div class="empty-transaction"><div>No transactions found</div></div>';
        return;
    }
    
    transactions.forEach(tx => {
        const transactionEl = document.createElement('div');
        transactionEl.className = 'transaction';
        transactionEl.setAttribute('data-order-id', tx.order_id);
        transactionEl.setAttribute('data-transaction-id', tx.id);
        
        const iconClass = tx.status === 'completed' ? 'success' : 
                         tx.status === 'pending' ? 'pending' : 'failed';
        
        let methodIcon = 'fas fa-money-bill-wave';
        if (tx.payment_method === 'btc') methodIcon = 'fab fa-bitcoin';
        else if (tx.payment_method === 'eth') methodIcon = 'fab fa-ethereum';
        else if (tx.payment_method === 'ltc') methodIcon = 'fas fa-coins';
        else if (tx.payment_method === 'sol') methodIcon = 'fas fa-bolt';
        else if (tx.payment_method === 'usdt') methodIcon = 'fas fa-dollar-sign';
        
        transactionEl.innerHTML = `
            <div class="transaction-icon ${iconClass}">
                <i class="${methodIcon}"></i>
            </div>
            <div class="transaction-amount ${tx.status === 'completed' ? 'positive' : (tx.status === 'pending' ? 'pending' : 'negative')}">
                $${parseFloat(tx.amount).toFixed(2)}
            </div>
            <div class="transaction-method">
                ${tx.payment_method ? tx.payment_method.toUpperCase() : ''}
            </div>
            <div class="transaction-status status-${tx.status}">
                ${tx.status ? tx.status.charAt(0).toUpperCase() + tx.status.slice(1) : 'Pending'}
            </div>
            <div class="transaction-date">
                ${tx.created_at ? new Date(tx.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : ''}
            </div>
        `;
        
        transactionsList.appendChild(transactionEl);
    });
}

// Copy to clipboard function
function copyToClipboard(elementId, elementName) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const textToCopy = element.textContent;
    
    navigator.clipboard.writeText(textToCopy).then(() => {
        notyf.success(`${elementName.charAt(0).toUpperCase() + elementName.slice(1)} copied to clipboard`);
    }).catch(err => {
        console.error('Failed to copy: ', err);
        notyf.error('Failed to copy to clipboard');
    });
}

function copyCryptoAddress() {
    if (!currentCryptoAddress) return;
    
    navigator.clipboard.writeText(currentCryptoAddress).then(() => {
        const originalText = copyAddressBtn.innerHTML;
        copyAddressBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        
        setTimeout(() => {
            copyAddressBtn.innerHTML = originalText;
        }, 2000);
        
        notyf.success('Address copied to clipboard');
    });
}

// Initialize the application
init();
</script>
</body>
</html>