<?php
require_once '../../db/connection.php';
require_once '../../vendor/autoload.php';
require_once 'auth.php';
require_once '../../includes/logging.php';

// Authenticate reseller
$reseller = authenticateReseller($con);

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Validate required fields
$required = ['productName', 'duration', 'count', 'durationType'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$productName = $input['productName'];
$duration = (int)$input['duration'];
$count = (int)$input['count'];
$durationType = $input['durationType'];
$ip_address = $reseller['ip_address'];

// Calculate total cost (you'll need to implement your pricing logic)
$stmt = $con->prepare("SELECT price_per_unit FROM products WHERE name = ? AND reseller_can_sell = 1");
$stmt->bind_param("s", $productName);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product not available for reselling']);
    exit;
}

$pricePerUnit = (float)$product['price_per_unit'];
$totalCost = $pricePerUnit * $count * $duration;

// Check reseller balance
if ($reseller['balance'] < $totalCost) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
    exit;
}

// Fetch product details for API
$stmt = $con->prepare("SELECT * FROM products WHERE name = ?");
$stmt->bind_param("s", $productName);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product name']);
    exit;
}

$api_url = $product['api_url'];
$api_key = $product['apikey'];
$productType = $product['type'];
$license_identifier = $product['license-identifier'];
$license_level = $product['license-level'];

// Generate license mask
$licensemask = "{$license_identifier}-" . generateRandomString(4) . '-' . generateRandomString(4) . '-' . generateRandomString(4);

try {
    // Deduct balance first
    $newBalance = $reseller['balance'] - $totalCost;
    $updateStmt = $con->prepare("UPDATE usertable SET balance = ? WHERE id = ?");
    $updateStmt->bind_param("di", $newBalance, $reseller['user_id']);
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update balance');
    }

    switch ($productType) {
        case 'keyauth':
            $url = "{$api_url}{$api_key}&type=add&format=JSON&owner={$reseller['user_name']}&mask={$license_identifier}-****-****-****&expiry={$duration}&amount={$count}&level={$license_level}";
            $client = new \GuzzleHttp\Client();

            $response = $client->request('GET', $url, ['headers' => ['Accept' => 'application/json']]);
            $responseBody = json_decode($response->getBody(), true);

            $keys = $responseBody['keys'] ?? [];
            if (empty($keys) && isset($responseBody['key'])) {
                $keys = [$responseBody['key']];
            }

            // Insert licenses
            foreach ($keys as $license_key) {
                insertLicense($con, $reseller['user_id'], $productName, $license_key, $duration, $durationType, $reseller['user_name'], $ip_address, ($totalCost / $count), $duration);
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => $responseBody['message'] ?? 'License successfully generated',
                'keys' => $keys,
                'newBalance' => $newBalance
            ]);
            logAction($reseller['user_id'], $reseller['user_name'], $reseller['user_email'], $ip_address, "API: Generated License: " . implode(', ', $keys));
            break;

        case 'pytguard':
            $url = "{$api_url}create_license/{$licensemask}?expiry_days={$duration}";
            $client = new \GuzzleHttp\Client();

            $response = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0',
                    'x-access-key' => $api_key,
                ],
            ]);

            $rawResponse = $response->getBody();
            if (stripos($rawResponse, 'successfully') !== false) {
                insertLicense($con, $reseller['user_id'], $productName, $licensemask, $duration, $durationType, $reseller['user_name'], $ip_address, $totalCost, $duration);
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => trim($rawResponse),
                    'key' => $licensemask,
                    'newBalance' => $newBalance
                ]);
                logAction($reseller['user_id'], $reseller['user_name'], $reseller['user_email'], $ip_address, "API: Generated PytGuard License: $licensemask");
            } else {
                refundBalance($con, $reseller['user_id'], $totalCost);
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'PytGuard Error: ' . trim($rawResponse)]);
            }
            break;

        case 'privateauth':
            $appNameEncrypted = Encryption::encrypt($license_identifier);
            $authorizationEncrypted = Encryption::encrypt("BYdh467rGrmXoQG9J3DuBwYtevSA0re");
            $keysCountEncrypted = Encryption::encrypt((string)$count);
            $keyDaysEncrypted = Encryption::encrypt((string)$duration);
            $memNameEncrypted = Encryption::encrypt($api_key);

            $url = "{$api_url}CreateKeys";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'AppName: ' . $appNameEncrypted,
                'Authorization: ' . $authorizationEncrypted,
                'KeysCount: ' . $keysCountEncrypted,
                'KeyDays: ' . $keyDaysEncrypted,
                'AppSecret: ' . $memNameEncrypted,
                'Content-Type: application/json',
            ]);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                refundBalance($con, $reseller['user_id'], $totalCost);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'PrivateAuth cURL Error: ' . $curlError]);
                break;
            }

            if ($response) {
                $decryptedResponse = Encryption::decrypt($response);
                $responseBody = json_decode($decryptedResponse, true);

                if (isset($responseBody['success']) && $responseBody['success']) {
                    $keys = $responseBody['keys'] ?? [];
                    if (!is_array($keys)) {
                        $keys = [$keys];
                    }

                    foreach ($keys as $license_key) {
                        insertLicense($con, $reseller['user_id'], $productName, $license_key, $duration, $durationType, $reseller['user_name'], $ip_address, ($totalCost / $count), $duration);
                    }

                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => $responseBody['message'] ?? 'License successfully generated',
                        'key' => count($keys) === 1 ? $keys[0] : $keys,
                        'newBalance' => $newBalance
                    ]);
                    logAction($reseller['user_id'], $reseller['user_name'], $reseller['user_email'], $ip_address, "API: Generated PrivateAuth License: " . implode(', ', $keys));
                } else {
                    refundBalance($con, $reseller['user_id'], $totalCost);
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => $responseBody['message'] ?? 'PrivateAuth API failed'
                    ]);
                }
            } else {
                refundBalance($con, $reseller['user_id'], $totalCost);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'No response from PrivateAuth API']);
            }
            break;

        default:
            refundBalance($con, $reseller['user_id'], $totalCost);
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unsupported product type']);
            break;
    }
} catch (Exception $e) {
    refundBalance($con, $reseller['user_id'], $totalCost);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
}

// Helper functions
function insertLicense($con, $user_id, $product_name, $license_key, $duration, $duration_type, $generated_by, $ip_address, $cost, $duration_days) {
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
    $stmt = $con->prepare("INSERT INTO reseller_licenses 
        (user_id, product_name, license_key, duration, duration_type, generated_by, ip_address, cost, expires_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("ississsds", 
        $user_id, 
        $product_name, 
        $license_key, 
        $duration, 
        $duration_type, 
        $generated_by, 
        $ip_address, 
        $cost, 
        $expires_at);
    $stmt->execute();
}

function refundBalance($con, $user_id, $amount) {
    $con->query("UPDATE usertable SET balance = balance + {$amount} WHERE id = {$user_id}");
}

function generateRandomString($length) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $shuffled = str_shuffle($chars);
    return substr($shuffled, 0, $length);
}
?>