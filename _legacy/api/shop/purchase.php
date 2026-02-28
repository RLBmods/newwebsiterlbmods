<?php
header('Content-Type: application/json');

require_once '../../db/connection.php';
require_once '../../vendor/autoload.php';
require_once '../../includes/get_user_info.php';
require_once '../../includes/session.php';
require_once '../../includes/logging.php';

requireAuth();

requireMember();

// Set error handler to catch all errors
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function generateRandomString($length) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

function refundBalance($con, $user_id, $amount) {
    $con->query("UPDATE usertable SET balance = balance + {$amount} WHERE id = {$user_id}");
}

class Encryption {
    public static function encrypt($str) {
        $str = mb_convert_encoding($str, 'UTF-8', 'auto');
        $encrypted = '';
        $length = strlen($str);
        $encrypted .= strlen($length) . $length;
        
        $indices = range(0, $length - 1);
        shuffle($indices);
        
        foreach ($indices as $index) {
            $encrypted .= strlen($index) . $index;
            $byte = ord($str[$index]) % 256;
            $encrypted .= strlen($byte) . $byte;
        }
        
        return $encrypted;
    }

    public static function decrypt($str) {
        $lenLength = (int)substr($str, 0, 1);
        $length = (int)substr($str, 1, $lenLength);
        $decrypted = str_repeat("\0", $length);
        $pos = 1 + $lenLength;
        
        while ($pos < strlen($str)) {
            $idxLen = (int)substr($str, $pos, 1);
            $idx = (int)substr($str, $pos + 1, $idxLen);
            $pos += 1 + $idxLen;
            
            $byteLen = (int)substr($str, $pos, 1);
            $byte = (int)substr($str, $pos + 1, $byteLen);
            $pos += 1 + $byteLen;
            
            $decrypted[$idx] = chr($byte);
        }
        
        return $decrypted;
    }
}

try {
    // Validate input
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $productId = $_POST['productId'] ?? null;
    $duration = $_POST['duration'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;
    $user_name = $_SESSION['user_name'] ?? null;
    $user_email = $_SESSION['user_email'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!$productId || !$duration || !$user_id) {
        throw new Exception('Missing required parameters');
    }

    // Check user balance
    $stmt = $con->prepare("SELECT balance, product_access FROM usertable WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        throw new Exception('User not found');
    }

    // Get product details
    $stmt = $con->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        throw new Exception('Product not found');
    }

    // Validate duration and get price
    $validDurations = ['daily', 'weekly', 'monthly', 'lifetime'];
    if (!in_array($duration, $validDurations)) {
        throw new Exception('Invalid duration selected');
    }

    $priceField = $duration . '_price';
    $price = $product[$priceField] ?? 0;
    $durationDays = match($duration) {
        'daily' => 1,
        'weekly' => 7,
        'monthly' => 30,
        'lifetime' => 365,
        default => throw new Exception('Invalid duration')
    };

    if ($price <= 0) {
        throw new Exception('Selected duration is not available for this product');
    }

    if ($user['balance'] < $price) {
        throw new Exception('Insufficient balance');
    }

    // Generate license based on product type
    $license_key = '';
    $productType = $product['type'];
    $api_url = $product['api_url'];
    $api_key = $product['apikey'];
    $license_identifier = $product['license-identifier'];
    $license_level = $product['license-level'];

    // Deduct balance first
    $newBalance = $user['balance'] - $price;
    $updateStmt = $con->prepare("UPDATE usertable SET balance = ? WHERE id = ?");
    $updateStmt->bind_param("di", $newBalance, $user_id);
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update balance');
    }

    switch ($productType) {
        case 'keyauth':
            $url = "{$api_url}{$api_key}&type=add&format=JSON&owner=SHOP-{$user_name}&mask={$license_identifier}-****-****-****&expiry={$durationDays}&amount=1&level={$license_level}";
            $client = new \GuzzleHttp\Client();
            try {
                $response = $client->request('GET', $url, ['headers' => ['Accept' => 'application/json']]);
                $responseBody = json_decode($response->getBody(), true);

                if (isset($responseBody['key'])) {
                    $license_key = $responseBody['key'];
                } elseif (isset($responseBody['keys'][0])) {
                    $license_key = $responseBody['keys'][0];
                } else {
                    refundBalance($con, $user_id, $price);
                    throw new Exception($responseBody['message'] ?? 'Failed to generate license');
                }
            } catch (\Exception $e) {
                refundBalance($con, $user_id, $price);
                throw new Exception('API Error: ' . 'Internal Error');
            }
            break;
            case "stock":
            $con->begin_transaction();
            try {
                // We use $duration (e.g., 'daily') and $durationDays (e.g., 1) 
                // to match your database's stock configuration.
                $stmt = $con->prepare("SELECT id, license_key FROM product_stock WHERE product_id = ? AND duration = ? AND status = 'available' LIMIT 1 FOR UPDATE");
                
                // Binding: product_id (i) and durationDays (i)
                $stmt->bind_param("ii", $product['id'], $durationDays);
                $stmt->execute();
                
                $stockItem = $stmt->get_result()->fetch_assoc();
                
                // ERROR: Show specific message if no stock is found
                if (!$stockItem) {
                    throw new Exception("Error: No available stock for the selected duration ({$duration}).");
                }
                
                $license_key = $stockItem['license_key'];
                
                // Update the stock item to mark it as sold
                $update = $con->prepare("UPDATE product_stock SET status = 'sold', sold_at = NOW(), sold_to_user_id = ? WHERE id = ?");
                $update->bind_param("ii", $user_id, $stockItem['id']);
                
                if (!$update->execute()) {
                    throw new Exception("Failed to update stock records.");
                }
                
                $con->commit();
                // Logic will now fall through to the global "Record purchase" section at the end of the file
                
            } catch (Exception $e) {
                $con->rollback();
                // CRITICAL: Refund the user since balance was deducted before entering the switch
                refundBalance($con, $user_id, $price);
                
                echo json_encode([
                    'success' => false, 
                    'message' => $e->getMessage()
                ]);
                exit; // Stop execution so the rest of the script doesn't run
            }
            break;
case 'valorant':
            // API Configuration
            $url = "https://antivgc.com/api/licenses/generate";
            $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOjQ1LCJ1c2VybmFtZSI6ImRpc2NvcmQtYm90LWh3aWQtcmVzZXQiLCJyb2xlIjoicmVzZWxsZXIiLCJzZXJ2aWNlIjp0cnVlLCJjcmVhdGVkX2J5IjoiRmx4eGR6IiwiY3JlYXRlZF9hdCI6IjIwMjYtMDEtMjhUMDA6NTE6MzcuNzg0WiIsImlhdCI6MTc2OTU2MTQ5NywiZXhwIjoxODAxMDk3NDk3fQ.rtpu5gq0YSrfHlOJBHBIt8JGUPCHV0MwghOVhB9_6Do";

            // Prepare payload
            $payload = [
                'duration'       => (int)$durationDays,
                'quantity'       => 1, // User purchases 1 key at a time
                'product'        => 'RLBMODS', 
                'application_id' => 6
            ];

            $client = new \GuzzleHttp\Client();

            try {
                $response = $client->request('POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json'
                    ],
                    'json' => $payload,
                    'verify' => false 
                ]);

                $body = json_decode($response->getBody(), true);

                // Validate API Success
                if (($body['success'] ?? false) === true) {
                    
                    // Key is usually inside 'licenses' or 'keys' array
                    $dataList = $body['licenses'] ?? $body['keys'] ?? [];
                    $firstItem = $dataList[0] ?? null;

                    // Extract the string key
                    if (is_array($firstItem)) {
                        $license_key = $firstItem['license_key'] ?? $firstItem['key'] ?? null;
                    } else {
                        $license_key = $firstItem;
                    }
                } else {
                    // API returned 200 but success: false
                    refundBalance($con, $user_id, $price);
                    throw new Exception('Valorant API Failed: ' . ($body['message'] ?? 'Unknown error'));
                }

                // Final check to ensure we actually got a string
                if (empty($license_key)) {
                    refundBalance($con, $user_id, $price);
                    throw new Exception('Valorant API succeeded but returned no key.');
                }

            } catch (\Exception $e) {
                // Catch Guzzle or Logic errors
                refundBalance($con, $user_id, $price);
                error_log("Valorant Purchase Error: " . $e->getMessage());
                throw new Exception('Failed to generate Valorant license. Balance refunded.');
            }
            break;
case 'pytguard':
    $licensemask = "{$license_identifier}-" . generateRandomString(4) . '-' . generateRandomString(4) . '-' . generateRandomString(4);
    $url = "{$api_url}create_license/{$licensemask}?expiry_days={$durationDays}";
    $client = new \GuzzleHttp\Client();

    try {
        $response = $client->request('GET', $url, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.361',
                'x-access-key' => $api_key,
            ],
            'timeout' => 30,
            'connect_timeout' => 15,
        ]);

        $rawResponse = (string) $response->getBody();
        error_log("PytGuard Response: " . $rawResponse); // Debug logging
        
        // Check for success - your reseller portal uses this simple check
        if (stripos($rawResponse, 'successfully') !== false) {
            $license_key = $licensemask;
        } else {
            // Try to see if it's a JSON response with success
            $jsonResponse = json_decode($rawResponse, true);
            if ($jsonResponse && isset($jsonResponse['success']) && $jsonResponse['success']) {
                $license_key = $licensemask;
            } else {
                refundBalance($con, $user_id, $price);
                throw new Exception('PytGuard Error: ' . trim($rawResponse));
            }
        }
    } catch (\Exception $e) {
        refundBalance($con, $user_id, $price);
        error_log("PytGuard Exception: " . $e->getMessage());
        
        // Check if it's a 403 error and provide better message
        if (strpos($e->getMessage(), '403') !== false) {
            throw new Exception('PytGuard API: Authentication failed (403 Forbidden). Please check your API key and ensure it has proper permissions.');
        }
        
        throw new Exception('PytGuard Error: ' . $e->getMessage());
    }
    break;


        case 'privateauth':
            $licensemask = "{$license_identifier}-" . generateRandomString(4) . '-' . generateRandomString(4) . '-' . generateRandomString(4);
            $appNameEncrypted = Encryption::encrypt($license_identifier);
            $authorizationEncrypted = Encryption::encrypt("BYdh467rGrmXoQG9J3DuBwYtevSA0re");
            $keysCountEncrypted = Encryption::encrypt("1");
            $keyDaysEncrypted = Encryption::encrypt((string)$durationDays);
            $memNameEncrypted = Encryption::encrypt($api_key);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "{$api_url}CreateKeys",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'AppName: ' . $appNameEncrypted,
                    'Authorization: ' . $authorizationEncrypted,
                    'KeysCount: ' . $keysCountEncrypted,
                    'KeyDays: ' . $keyDaysEncrypted,
                    'AppSecret: ' . $memNameEncrypted,
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                refundBalance($con, $user_id, $price);
                throw new Exception('API Error: ' . 'Internal Error2');
            }

            if (!$response) {
                refundBalance($con, $user_id, $price);
                throw new Exception('No response from API');
            }

            $decryptedResponse = Encryption::decrypt($response);
            $responseBody = json_decode($decryptedResponse, true);

            if (empty($responseBody['success']) || empty($responseBody['keys'])) {
                refundBalance($con, $user_id, $price);
                throw new Exception($responseBody['message'] ?? 'Failed to generate license');
            }

            $license_key = is_array($responseBody['keys']) ? $responseBody['keys'][0] : $responseBody['keys'];
            break;

        default:
            refundBalance($con, $user_id, $price);
            throw new Exception('Unsupported product type');
    }

    // Record purchase
    $expires_at = $durationDays === 9999 ? null : date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));
    $stmt = $con->prepare("INSERT INTO shop_purchases (user_id, product_id, license_key, duration, duration_days, price, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Fix: Create variables for bind_param to avoid reference issues
    $nullExpires = null;
    $bindParams = [
        $user_id, 
        $productId, 
        $license_key, 
        $duration, 
        $durationDays, 
        $price
    ];
    
    if ($expires_at === null) {
        $stmt->bind_param("iissid", ...$bindParams);
    } else {
        $bindParams[] = $expires_at;
        $stmt->bind_param("iissids", ...$bindParams);
    }

    if (!$stmt->execute()) {
        refundBalance($con, $user_id, $price);
        throw new Exception('Failed to record purchase');
    }

    // Update user's product access

$productName = $product['name'];

$currentAccess = $user['product_access'] ?? '';
$currentAccessArray = array_filter(array_map('trim', explode(',', $currentAccess)));

$productName = $product['name']; // Get the name from product table

if (!in_array($productName, $currentAccessArray)) {
    $currentAccessArray[] = $productName;
    $updatedAccess = implode(', ', $currentAccessArray);
    
    $accessStmt = $con->prepare("UPDATE usertable SET product_access = ? WHERE id = ?");
    $accessStmt->bind_param("si", $updatedAccess, $user_id);
    if (!$accessStmt->execute()) {
        error_log("Failed to update product access for user {$user_id}");
    }
}

// Update user role from 'member' to 'customer' if they don't already have another role
$currentRole = $user['role'] ?? 'member'; // Assuming the role column is called 'role'

// Only change role if current role is exactly 'member'
if ($currentRole === 'member') {
    $roleStmt = $con->prepare("UPDATE usertable SET role = 'customer' WHERE id = ? AND role = 'member'");
    $roleStmt->bind_param("i", $user_id);
    if (!$roleStmt->execute()) {
        error_log("Failed to update role for user {$user_id}");
    } else {
        // Log the role change
        logAction($user_id, $user_name, $user_email, $ip_address, "Role updated from member to customer after purchase");
    }
}

    // Log successful purchase
    logAction($user_id, $user_name, $user_email, $ip_address, "Purchased product {$product['name']} ({$duration})");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Purchase successful',
        'license_key' => $license_key,
        'expires_at' => $expires_at,
        'new_balance' => $newBalance
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Purchase error: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}