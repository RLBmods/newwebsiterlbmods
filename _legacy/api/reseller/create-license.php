<?php
require_once '../../db/connection.php';
require_once '../../vendor/autoload.php';
require_once '../../includes/get_user_info.php';
require_once '../../includes/session.php';
require_once '../../includes/logging.php';

requireAuth();
requireReseller();

header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to calculate loyalty discount
function calculateLoyaltyDiscount($con, $user_id) {
    $query = "SELECT COUNT(*) as total_purchases FROM reseller_licenses WHERE user_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $totalPurchases = $data['total_purchases'] ?? 0;
    
    if ($totalPurchases >= 500) return 20;
    if ($totalPurchases >= 200) return 15;
    if ($totalPurchases >= 100) return 10;
    if ($totalPurchases >= 50) return 5;
    return 0;
}

// Encryption Class for PrivateAuth
class Encryption {
    public static function encrypt($str) {
        $str = mb_convert_encoding($str, 'UTF-8', 'auto');
        return self::encryptBytes($str);
    }
    public static function decrypt($bytes) {
        return self::decryptBytes($bytes);
    }
    public static function encryptBytes($str) {
        $encryptedString = '';
        $length = strlen($str);
        $encryptedString .= strlen($length) . $length;
        $indices = range(0, $length - 1);
        shuffle($indices);
        foreach ($indices as $index) {
            $encryptedString .= strlen($index) . $index;
            $byteValue = ord($str[$index]);
            if ($byteValue > 255) $byteValue = $byteValue % 256;
            $encryptedString .= strlen($byteValue) . $byteValue;
        }
        return $encryptedString;
    }
    public static function decryptBytes($str) {
        $lenLength = (int)substr($str, 0, 1);
        $bytesLength = (int)substr($str, 1, $lenLength);
        $decryptedBytes = str_repeat("\0", $bytesLength);
        $index = 1 + $lenLength;
        while ($index < strlen($str)) {
            $indexLength = (int)substr($str, $index, 1);
            $startIndex = $index + 1;
            $arrayIndex = (int)substr($str, $startIndex, $indexLength);
            $startIndex += $indexLength;
            $byteLength = (int)substr($str, $startIndex, $byteLength);
            $startIndex += 1;
            $byteValue = (int)substr($str, $startIndex, $byteLength);
            $decryptedBytes[$arrayIndex] = chr($byteValue);
            $index = $startIndex + $byteLength;
        }
        return $decryptedBytes;
    }
}

// Capture POST data
$productName = $_POST['productName'] ?? '';
$duration = $_POST['duration'] ?? '';
$count = (int)($_POST['count'] ?? 1);
$durationType = $_POST['durationType'] ?? '';
$totalCostOriginal = (float)($_POST['totalCost'] ?? 0); 
$user_id = $_SESSION['user_id'] ?? null;
$genby = $_SESSION['user_name'] ?? 'Unknown';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (empty($productName) || empty($duration) || $count < 1) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid required fields']);
    exit;
}

// Fetch user data (name, email)
$stmt = $con->prepare("SELECT balance, discount_override, name, email FROM usertable WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User account not found']);
    exit;
}

// Combined Discount Logic (Loyalty + Override)
$loyaltyDiscount = calculateLoyaltyDiscount($con, $user_id);
$discountOverride = (float)($user['discount_override'] ?? 0);
$totalDiscountPercent = $loyaltyDiscount + $discountOverride;

$discountedCostTotal = $totalCostOriginal * (1 - ($totalDiscountPercent / 100));
$costPerKey = $discountedCostTotal / $count;

if ($user['balance'] < $discountedCostTotal) {
    echo json_encode(['success' => false, 'message' => 'Insufficient balance. Required: $' . number_format($discountedCostTotal, 2)]);
    exit;
}

// Fetch product details
$stmt = $con->prepare("SELECT * FROM products WHERE name = ?");
$stmt->bind_param("s", $productName);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Invalid product name']);
    exit;
}

$api_url = $product['api_url'];
$api_key = $product['apikey'];
$productType = $product['type'];
$license_identifier = $product['license-identifier'];

// Fetch Reseller Branding Level

try {
    // Deduct balance initially
    $newBalance = $user['balance'] - $discountedCostTotal;
    $updateStmt = $con->prepare("UPDATE usertable SET balance = ? WHERE id = ?");
    $updateStmt->bind_param("di", $newBalance, $user_id);
    if (!$updateStmt->execute()) throw new Exception('Failed to update balance');

    $keys = [];

    switch ($productType) {
        case 'stock':
            $con->begin_transaction();
            try {
                $stmt = $con->prepare("SELECT id, license_key FROM product_stock WHERE product_id = ? AND duration = ? AND status = 'available' LIMIT ? FOR UPDATE");
                $stmt->bind_param("iii", $product['id'], $duration, $count);
                $stmt->execute();
                $stockResult = $stmt->get_result();
                
                if ($stockResult->num_rows < $count) throw new Exception("This duration is currently out of stock");

                $updateStock = $con->prepare("UPDATE product_stock SET status = 'sold', sold_at = NOW(), sold_to_user_id = ? WHERE id = ?");
                while ($row = $stockResult->fetch_assoc()) {
                    $keys[] = $row['license_key'];
                    $updateStock->bind_param("ii", $user_id, $row['id']);
                    $updateStock->execute();
                    insertLicense($con, $user_id, $productName, $row['license_key'], $duration, $duration, $genby, $ip_address, $costPerKey, $duration);
                }
                $con->commit();
            } catch (Exception $e) {
                $con->rollback();
                throw $e;
            }
            break;

        case 'keyauth':
            // KeyAuth handles bulk generation in one request via 'amount' parameter
            $url = "{$api_url}{$api_key}&type=add&format=JSON&owner={$genby}&mask={$license_identifier}-****-****-****&expiry={$duration}&amount={$count}&level={$license_level}";
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url, ['headers' => ['Accept' => 'application/json']]);
            $body = json_decode($response->getBody(), true);
            
            $generatedKeys = $body['keys'] ?? (isset($body['key']) ? [$body['key']] : []);
            if (empty($generatedKeys)) throw new Exception($body['message'] ?? 'KeyAuth Error');

            foreach ($generatedKeys as $k) {
                $keys[] = $k;
                insertLicense($con, $user_id, $productName, $k, $duration, 'days', $genby, $ip_address, $costPerKey, $duration);
            }
            break;
case 'valorant':
            // Configuration
            $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOjQ1LCJ1c2VybmFtZSI6ImRpc2NvcmQtYm90LWh3aWQtcmVzZXQiLCJyb2xlIjoicmVzZWxsZXIiLCJzZXJ2aWNlIjp0cnVlLCJjcmVhdGVkX2J5IjoiRmx4eGR6IiwiY3JlYXRlZF9hdCI6IjIwMjYtMDEtMjhUMDA6NTE6MzcuNzg0WiIsImlhdCI6MTc2OTU2MTQ5NywiZXhwIjoxODAxMDk3NDk3fQ.rtpu5gq0YSrfHlOJBHBIt8JGUPCHV0MwghOVhB9_6Do";
            $url = "https://antivgc.com/api/licenses/generate";

            $client = new \GuzzleHttp\Client();

            try {
                $response = $client->request('POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json'
                    ],
                    'json' => [
                        'duration'       => (int)$duration,
                        'quantity'       => (int)$count,
                        'product'        => 'RLBMODS', // Hardcoded based on your curl command
                        'application_id' => 6
                    ],
                    'verify' => false
                ]);

                $body = json_decode($response->getBody(), true);

                // Check API Success
                if (($body['success'] ?? false) === true) {
                    
                    // Handle response keys (can be in 'licenses' or 'keys')
                    $dataList = $body['licenses'] ?? $body['keys'] ?? [];
                    
                    if (empty($dataList)) {
                        throw new Exception("API successful but returned no keys.");
                    }

                    foreach ($dataList as $item) {
                        // Extract string key if object
                        $k = is_array($item) ? ($item['license_key'] ?? $item['key']) : $item;
                        
                        // Add to keys array for JSON response
                        $keys[] = $k;
                        
                        // Insert into DB
                        insertLicense($con, $user_id, $productName, $k, $duration, 'days', $genby, $ip_address, $costPerKey, $duration);
                    }

                } else {
                    // API returned 200, but success is false
                    throw new Exception($body['message'] ?? 'Valorant API returned unsuccessful status');
                }

            } catch (\GuzzleHttp\Exception\RequestException $e) {
                // Handle HTTP errors (400, 500, etc)
                $msg = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
                throw new Exception("Valorant API Request Error: " . $msg);
            }
            break;
        case 'pytguard':
            $client = new \GuzzleHttp\Client();
            // Loop for PytGuard because the API typically creates one license at a time
            for ($i = 0; $i < $count; $i++) {
                $currentMask = "{$license_identifier}-" . generateRandomString(4) . '-' . generateRandomString(4) . '-' . generateRandomString(4);
                $url = "{$api_url}create_license/{$currentMask}?expiry_days={$duration}";
                
                $response = $client->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.361',
                        'x-access-key' => $api_key,
                    ],
                ]);
                
                $rawResponse = (string)$response->getBody();
                if (stripos($rawResponse, 'successfully') !== false) {
                    $keys[] = $currentMask;
                    insertLicense($con, $user_id, $productName, $currentMask, $duration, 'days', $genby, $ip_address, $costPerKey, $duration);
                } else {
                    throw new Exception('PytGuard Error on key '.($i+1).': ' . trim($rawResponse));
                }
            }
            break;

        case 'privateauth':
            // PrivateAuth handles bulk generation via 'KeysCount' header
            $url = "{$api_url}CreateKeys";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'AppName: ' . Encryption::encrypt($license_identifier),
                'Authorization: ' . Encryption::encrypt("BYdh467rGrmXoQG9J3DuBwYtevSA0re"),
                'KeysCount: ' . Encryption::encrypt((string)$count),
                'KeyDays: ' . Encryption::encrypt((string)$duration),
                'AppSecret: ' . Encryption::encrypt($api_key),
                'Content-Type: application/json',
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $body = json_decode(Encryption::decrypt($response), true);
            if (!isset($body['success']) || !$body['success']) throw new Exception($body['message'] ?? 'PrivateAuth failed');
            
            $generatedKeys = (array)$body['keys'];
            foreach ($generatedKeys as $k) {
                $keys[] = $k;
                insertLicense($con, $user_id, $productName, $k, $duration, 'days', $genby, $ip_address, $costPerKey, $duration);
            }
            break;

        default:
            throw new Exception('Unsupported product type');
    }

    logAction($user_id, $genby, $user['email'], $ip_address, "Generated ($count): " . implode(', ', $keys));
    
    echo json_encode([
        'success' => true,
        'keys' => $keys,
        'newBalance' => $newBalance,
        'discountApplied' => $totalDiscountPercent,
        'discountedPrice' => $discountedCostTotal
    ]);

} catch (Exception $e) {
    // Refund balance on failure
    $con->query("UPDATE usertable SET balance = balance + {$discountedCostTotal} WHERE id = {$user_id}");
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Helpers
function insertLicense($con, $user_id, $product_name, $license_key, $duration, $duration_type, $generated_by, $ip_address, $cost, $duration_days) {
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
    $stmt = $con->prepare("INSERT INTO reseller_licenses (user_id, product_name, license_key, duration, duration_type, generated_by, ip_address, cost, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississsds", $user_id, $product_name, $license_key, $duration, $duration_type, $generated_by, $ip_address, $cost, $expires_at);
    $stmt->execute();
}

function generateRandomString($length) {
    return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, $length);
}