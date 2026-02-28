// apiConfig.js - Centralized API configuration
const API_BASE = 'https://s.compilecrew.xyz/api/topup';

export const API_ENDPOINTS = {
    PENDING_PAYMENTS: `${API_BASE}/get_pending_payments.php`,
    TRANSACTION_HISTORY: `${API_BASE}/history.php`,
    USER_BALANCE: `${API_BASE}/balance.php`,
    TRANSACTION_DETAILS: `${API_BASE}/get_transaction_details.php`,
    SELLSN_STATUS: `${API_BASE}/gateway/sellsn/check_status.php`,
    PAYTOP_STATUS: `${API_BASE}/gateway/paytop/check_status.php`,
    SELLSN_CREATE: `${API_BASE}/gateway/sellsn/create.php`,
    PAYTOP_CREATE: `${API_BASE}/gateway/paytop/create.php`
};