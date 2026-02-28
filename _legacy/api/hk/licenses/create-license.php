<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../db/connection.php';
require_once '../../../vendor/autoload.php';
require_once '../../../includes/get_user_info.php';
require_once '../../../includes/session.php';
require_once '../../../includes/logging.php';

requireAuth();

header('Content-Type: application/json');

// Capture POST data
$productName = $_POST['productName'] ?? '';
$duration = $_POST['duration'] ?? '';
$count = $_POST['count'] ?? '';
$durationType = $_POST['durationType'] ?? '';
$genby = $_SESSION['user_name'] ?? 'Admin';
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['user_email'] ?? null;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Validate inputs
if (empty($productName) || empty($duration) || empty($count) || empty($durationType)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
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

// Product details
$api_url = $product['api_url'];
$api_key = $product['apikey'];
$productType = $product['type'];
$license_identifier = $product['license-identifier'];
$license_level = $product['license-level']; 

// Generate license mask
$licensemask = "{$license_identifier}-" . generateRandomString(4) . '-' . generateRandomString(4) . '-' . generateRandomString(4);

// Encryption Class
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
            if ($byteValue > 255) {
                $byteValue = $byteValue % 256;
            }
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
            $byteLength = (int)substr($str, $startIndex, 1);
            $startIndex += 1;

            $byteValue = (int)substr($str, $startIndex, $byteLength);
            $decryptedBytes[$arrayIndex] = chr($byteValue);
            $index = $startIndex + $byteLength;
        }

        return $decryptedBytes;
    }
}

try {
    switch ($productType) {
        case 'keyauth':
            $url = "{$api_url}{$api_key}&type=add&format=JSON&owner={$genby}&mask={$license_identifier}-****-****-****&expiry={$duration}&amount={$count}&level={$license_level}";
            $client = new \GuzzleHttp\Client();

            try {
                $response = $client->request('GET', $url, ['headers' => ['Accept' => 'application/json']]);
                $responseBody = json_decode($response->getBody(), true);

                $keys = $responseBody['keys'] ?? [];
                if (empty($keys) && isset($responseBody['key'])) {
                    $keys = [$responseBody['key']];
                }

                // Insert licenses
                $licenseDetails = [];
                foreach ($keys as $license_key) {
                    $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
                    insertLicense($con, $user_id, $productName, $license_key, $duration, $durationType, $genby, $ip_address, 0, $expires_at);
                    
                    $licenseDetails[] = [
                        'key' => $license_key,
                        'expires' => $expires_at,
                        'duration' => formatDuration($duration, $durationType),
                        'product' => $productName
                    ];
                }

                echo json_encode([
                    'success' => true,
                    'message' => $responseBody['message'] ?? 'License successfully generated',
                    'licenses' => $licenseDetails
                ]);
                logAction($user_id, $genby, $user_email, $ip_address, "Generated License: " . implode(', ', $keys));
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => 'KeyAuth Error: ' . $e->getMessage()]);
            }
            break;

        case 'pytguard':
            $url = "{$api_url}create_license/{$licensemask}?expiry_days={$duration}";
            $client = new \GuzzleHttp\Client();

            try {
                $response = $client->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.361',
                        'x-access-key' => $api_key,
                    ],
                ]);

                $rawResponse = $response->getBody();
                if (stripos($rawResponse, 'successfully') !== false) {
                    $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
                    insertLicense($con, $user_id, $productName, $licensemask, $duration, $durationType, $genby, $ip_address, 0, $expires_at);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => trim($rawResponse),
                        'licenses' => [[
                            'key' => $licensemask,
                            'expires' => $expires_at,
                            'duration' => formatDuration($duration, $durationType),
                            'product' => $productName
                        ]]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'PytGuard Error: ' . trim($rawResponse)]);
                }
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => 'PytGuard Error: ' . $e->getMessage()]);
            }
            break;
            
            
case 'valorant':
            // API Configuration
            $url = "https://antivgc.com/api/licenses/generate";
            $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOjQ1LCJ1c2VybmFtZSI6ImRpc2NvcmQtYm90LWh3aWQtcmVzZXQiLCJyb2xlIjoicmVzZWxsZXIiLCJzZXJ2aWNlIjp0cnVlLCJjcmVhdGVkX2J5IjoiRmx4eGR6IiwiY3JlYXRlZF9hdCI6IjIwMjYtMDEtMjhUMDA6NTE6MzcuNzg0WiIsImlhdCI6MTc2OTU2MTQ5NywiZXhwIjoxODAxMDk3NDk3fQ.rtpu5gq0YSrfHlOJBHBIt8JGUPCHV0MwghOVhB9_6Do";

            // Prepare the payload based on your curl command
            // Note: We use the $count and $duration variables from your script input
            $payload = [
                'duration'       => (int)$duration,
                'quantity'       => (int)$count,
                'product'        => 'RLBMODS', // Hardcoded as per your curl, or use $productName if dynamic
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

                $responseBody = json_decode($response->getBody(), true);

                // Check if API reported success
                if (($responseBody['success'] ?? false) === true) {
                    
                    // The API likely returns an array of licenses in 'licenses' or 'keys'
                    $generatedData = $responseBody['licenses'] ?? $responseBody['keys'] ?? [];
                    
                    $licenseDetails = [];
                    
                    foreach ($generatedData as $item) {
                        // Handle if the API returns an object or just a string key
                        $keyString = is_array($item) ? ($item['license_key'] ?? $item['key']) : $item;

                        // Calculate expiry for local database record
                        $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration} days"));

                        // Save to your local database (Reseller_licenses table)
                        insertLicense($con, $user_id, $productName, $keyString, $duration, $durationType, $genby, $ip_address, 0, $expires_at);

                        // Prepare response for frontend
                        $licenseDetails[] = [
                            'key'      => $keyString,
                            'expires'  => $expires_at,
                            'duration' => formatDuration($duration, $durationType),
                            'product'  => $productName
                        ];
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Licenses generated successfully via AntiVGC',
                        'licenses' => $licenseDetails
                    ]);
                    
                    // Log the action
                    $keysString = implode(', ', array_column($licenseDetails, 'key'));
                    logAction($user_id, $genby, $user_email, $ip_address, "Generated Valorant License: " . $keysString);

                } else {
                    // API returned 200 OK, but success: false in body
                    echo json_encode([
                        'success' => false, 
                        'message' => 'API Failed: ' . ($responseBody['message'] ?? 'Unknown error')
                    ]);
                }

            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $msg = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
                echo json_encode(['success' => false, 'message' => 'Valorant API Request Error: ' . $msg]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => 'General Error: ' . $e->getMessage()]);
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

                    $licenseDetails = [];
                    foreach ($keys as $license_key) {
                        $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
                        insertLicense($con, $user_id, $productName, $license_key, $duration, $durationType, $genby, $ip_address, 0, $expires_at);
                        
                        $licenseDetails[] = [
                            'key' => $license_key,
                            'expires' => $expires_at,
                            'duration' => formatDuration($duration, $durationType),
                            'product' => $productName
                        ];
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => $responseBody['message'] ?? 'License successfully generated',
                        'licenses' => $licenseDetails
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => $responseBody['message'] ?? 'PrivateAuth API failed'
                    ]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No response from PrivateAuth API']);
            }
            break;
            
        case "stock":
            $con->begin_transaction();
            try{
                $stmt = $con->prepare("SELECT id, license_key FROM product_stock WHERE product_id = ? AND duration = ? AND duration_type = ? AND status = 'available' LIMIT 1 FOR UPDATE");
                $stmt->bind_param("iis", $product['id'],$duration, $duration_type);
                $stmt->execute();
                
                $stockItem = $stmt->get_result()->fetch_assoc();
                
                if (!$stockItem)
                {
                    throw new Exception("No available stock for this duration");
                    
                }
                
                $license_key = $stockItem['license_key'];
                $expires_at = date("Y-m-d H:i:s", strtotime("+$duration $durationType"));
                
                $update = $con->prepare("UPDATE product_stock SET status='sold', sold_at = NOW(), sold_to_user_id ? WHERE id = ?");
                $update->bind_param("ii", $user_id, $stockItem['id']);
                $update->execute();
                
                insertLicense($con, $user_id, $productName, $license_key, $duration, $durationType, $genby, $ip_address, 0, $expires_at);
                
                $con->commit();
            echo json_encode(['success' => true, 'message' => 'License pulled from stock', 'key' => $license_key]);
        } catch (Exception $e) {
            $con->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unsupported product type']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
}

// Helper functions
function insertLicense($con, $user_id, $product_name, $license_key, $duration, $duration_type, $generated_by, $ip_address, $cost, $expires_at) {
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

function generateRandomString($length) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $shuffled = str_shuffle($chars);
    return substr($shuffled, 0, $length);
}

function formatDuration($duration, $type) {
    switch($type) {
        case 'days': return $duration . ' day' . ($duration != 1 ? 's' : '');
        case 'weeks': return ($duration/7) . ' week' . (($duration/7) != 1 ? 's' : '');
        case 'months': return ($duration/30) . ' month' . (($duration/30) != 1 ? 's' : '');
        case 'lifetime': return 'Lifetime';
        default: return $duration . ' ' . $type;
    }
}