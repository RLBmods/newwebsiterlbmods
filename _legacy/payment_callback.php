<?php
// Verify callback signature
function verifySignature($serverKey) {
    $signatureFields = $_POST;
    $requestSignature = $signatureFields['signature'] ?? null;
    unset($signatureFields['signature']);
    
    // Filter empty values
    $signatureFields = array_filter($signatureFields);
    
    // Sort fields alphabetically
    ksort($signatureFields);
    
    // Generate query string
    $query = http_build_query($signatureFields);
    
    // Calculate signature
    $signature = hash_hmac('sha256', $query, $serverKey);
    
    return hash_equals($signature, $requestSignature);
}

$serverKey = 'S2J92J6HTZ-JLJZZZNLZD-9HLHBRDBZ6';
if (!verifySignature($serverKey)) {
    http_response_code(400);
    die('Invalid signature');
}

// Process payment result
$tranRef = $_POST['tranRef'] ?? '';
$status = $_POST['respStatus'] ?? '';
$cartId = $_POST['cartId'] ?? '';

if ($status === 'A') {
    // Payment approved
    file_put_contents('payments.log', 
        date('Y-m-d H:i:s') . " APPROVED - TranRef: $tranRef, CartID: $cartId\n", 
        FILE_APPEND);
    // Update your database, send confirmation email, etc.
} else {
    // Payment declined
    file_put_contents('payments.log', 
        date('Y-m-d H:i:s') . " DECLINED - TranRef: $tranRef, CartID: $cartId\n", 
        FILE_APPEND);
}

// Return success response
http_response_code(200);
echo 'OK';
?>