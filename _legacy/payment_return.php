<?php
// payment_return.php - Handles customer return after payment processing

// Start session to access transaction data if needed
session_start();

// Load configuration
require_once 'config.php'; // Contains your server key, profile ID, etc.

// Function to verify the signature of callback data
function verifyReturnSignature($data, $serverKey) {
    $signature = $data['signature'] ?? null;
    unset($data['signature']);
    
    // Filter empty values and sort fields
    $data = array_filter($data);
    ksort($data);
    
    // Generate query string
    $query = http_build_query($data);
    
    // Calculate expected signature
    $expectedSignature = hash_hmac('sha256', $query, $serverKey);
    
    return hash_equals($expectedSignature, $signature);
}

// Function to check payment status with PayTabs
function checkPaymentStatus($tranRef) {
    global $serverKey, $profileId;
    
    $url = 'https://secure.paytabs.com/payment/query';
    
    $payload = [
        'profile_id' => $profileId,
        'tran_ref' => $tranRef
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $serverKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Main processing
try {
    // Get all GET parameters (PayTabs returns data in query string)
    $returnData = $_GET;
    
    // Verify the signature for security
    if (!verifyReturnSignature($returnData, $serverKey)) {
        throw new Exception('Invalid payment signature - possible tampering detected');
    }
    
    // Extract important fields
    $tranRef = $returnData['tranRef'] ?? '';
    $cartId = $returnData['cartId'] ?? '';
    $status = $returnData['respStatus'] ?? '';
    $message = $returnData['respMessage'] ?? '';
    
    // For additional security, verify with PayTabs server
    $paymentStatus = checkPaymentStatus($tranRef);
    
    // Determine the payment result
    $isSuccess = ($status === 'A') && 
                (isset($paymentStatus['payment_result']['response_status']) && 
                 $paymentStatus['payment_result']['response_status'] === 'A');
    
    // Store the transaction in your database
    // You would replace this with your actual database code
    $transactionData = [
        'tran_ref' => $tranRef,
        'cart_id' => $cartId,
        'status' => $isSuccess ? 'success' : 'failed',
        'amount' => $paymentStatus['cart_amount'] ?? 0,
        'currency' => $paymentStatus['cart_currency'] ?? '',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // file_put_contents('transactions.log', json_encode($transactionData) . "\n", FILE_APPEND);
    
    // Display appropriate message to customer
    if ($isSuccess) {
        // Successful payment
        $pageTitle = "Payment Successful";
        $headerText = "Thank You For Your Payment!";
        $messageText = "Your payment has been processed successfully.";
        $transactionDetails = [
            'Transaction Reference' => $tranRef,
            'Order ID' => $cartId,
            'Amount' => ($paymentStatus['cart_amount'] ?? 0) . ' ' . ($paymentStatus['cart_currency'] ?? ''),
            'Date' => date('F j, Y, g:i a')
        ];
    } else {
        // Failed payment
        $pageTitle = "Payment Failed";
        $headerText = "Payment Processing Failed";
        $messageText = "We couldn't process your payment. " . htmlspecialchars($message);
        $transactionDetails = [
            'Transaction Reference' => $tranRef,
            'Order ID' => $cartId,
            'Reason' => htmlspecialchars($message)
        ];
    }
    
} catch (Exception $e) {
    // Handle errors gracefully
    $pageTitle = "Payment Processing Error";
    $headerText = "An Error Occurred";
    $messageText = "We encountered an issue processing your payment. Please contact support.";
    error_log('Payment return error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        h1 {
            color: <?php echo $isSuccess ? '#4CAF50' : '#F44336'; ?>;
            text-align: center;
        }
        .transaction-details {
            margin-top: 20px;
            padding: 15px;
            background-color: #fff;
            border-radius: 5px;
            border: 1px solid #eee;
        }
        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        .detail-label {
            font-weight: bold;
            width: 150px;
        }
        .actions {
            margin-top: 30px;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: <?php echo $isSuccess ? '#4CAF50' : '#F44336'; ?>;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 0 10px;
        }
        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($headerText); ?></h1>
        <p><?php echo $messageText; ?></p>
        
        <div class="transaction-details">
            <h3>Transaction Details</h3>
            <?php foreach ($transactionDetails as $label => $value): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php echo htmlspecialchars($label); ?>:</span>
                    <span><?php echo htmlspecialchars($value); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="actions">
            <?php if ($isSuccess): ?>
                <a href="/order-details.php?order=<?php echo urlencode($cartId); ?>" class="btn">View Order</a>
            <?php else: ?>
                <a href="/checkout.php?order=<?php echo urlencode($cartId); ?>" class="btn">Try Again</a>
            <?php endif; ?>
            <a href="/" class="btn">Return Home</a>
        </div>
    </div>
    
    <!-- You might want to include tracking/analytics here -->
    <script>
        // Track successful payment in analytics
        <?php if ($isSuccess): ?>
        if (typeof gtag !== 'undefined') {
            gtag('event', 'purchase', {
                transaction_id: '<?php echo $tranRef; ?>',
                value: <?php echo $transactionData['amount']; ?>,
                currency: '<?php echo $transactionData['currency']; ?>',
                items: [{
                    item_id: '<?php echo $cartId; ?>',
                    item_name: 'Order <?php echo $cartId; ?>'
                }]
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>