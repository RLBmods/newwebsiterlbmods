<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../db/connection.php';
require_once '../../vendor/autoload.php';
require_once '../../includes/get_user_info.php';
require_once '../../includes/session.php';
require_once '../../includes/logging.php';

requireAuth();

header('Content-Type: application/json');

// Get authenticated user info
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['user_name'] ?? 'Unknown';
$user_email = $_SESSION['user_email'] ?? null;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Capture product name from the request
$productName = $_GET['productName'] ?? '';

if (empty($productName)) {
    echo json_encode(['success' => false, 'message' => 'Missing product name']);
    exit;
}

// Fetch product details from the database
$stmt = $con->prepare("SELECT * FROM products WHERE name = ?");
$stmt->bind_param("s", $productName);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Invalid product name']);
    exit;
}

// Check if reseller can sell this product
if (isset($product['reseller_can_sell']) && $product['reseller_can_sell'] == 0) {
    echo json_encode(['success' => false, 'message' => 'You are not authorized to generate licenses for this product']);
    exit;
}

// Assign product-specific values
$api_url = $product['api_url'];
$api_key = $product['apikey'];
$productType = $product['type'];
$license_identifier = $product['license-identifier'];

// Improved expiration formatting with proper units
function formatExpiration($totalSeconds, $durationType = 'days') {
    if ($totalSeconds <= 0) return 'Expired';
    
    $days = floor($totalSeconds / 86400);
    
    if ($durationType === 'lifetime') {
        return 'Lifetime';
    }
    
    // For unactivated licenses, show original duration
    if ($days > 365 * 5) { // More than 5 years
        return 'Lifetime';
    }
    
    if ($days <= 1) return "1 Day";
    if ($days < 7) return "$days Days";
    if ($days < 30) {
        $weeks = floor($days / 7);
        return $weeks == 1 ? "1 Week" : "$weeks Weeks";
    }
    if ($days < 365) {
        $months = floor($days / 30);
        return $months == 1 ? "1 Month" : "$months Months";
    }
    
    $years = floor($days / 365);
    return $years == 1 ? "1 Year" : "$years Years";
}

function formatOriginalDuration($duration, $durationType) {
    if ($durationType === 'lifetime') {
        return 'Lifetime';
    }
    
    $unit = ucfirst($durationType);
    if ($duration > 1) {
        $unit .= 's'; // Pluralize
    }
    
    return "$duration $unit";
}

function formatexpirationforpytguard($expiry_date) {
    if (empty($expiry_date) || $expiry_date == 'null') {
        return "Not Activated";
    }

    $current_date = new DateTime(null, new DateTimeZone('UTC'));
    $expiry_date = new DateTime($expiry_date, new DateTimeZone('UTC'));

    if ($current_date > $expiry_date) {
        return "Expired";
    }

    $interval = $current_date->diff($expiry_date);
    $days_left = $interval->days;

    if ($interval->y > 3) {
        return "Lifetime";
    }
    if ($interval->y > 0) {
        return $interval->y . " year" . ($interval->y > 1 ? "s" : "") . " left";
    }
    if ($interval->m > 0) {
        return $interval->m . " month" . ($interval->m > 1 ? "s" : "") . " left";
    }
    if ($days_left >= 7) {
        return floor($days_left / 7) . " week" . (floor($days_left / 7) > 1 ? "s" : "") . " left";
    }
    return $days_left . " day" . ($days_left > 1 ? "s" : "") . " left";
}

// Encryption Class (same as admin area)
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
    // First get all licenses this user has generated from our database
    $dbStmt = $con->prepare("SELECT license_key, duration, duration_type FROM reseller_licenses WHERE user_id = ? AND product_name = ?");
    $dbStmt->bind_param("is", $user_id, $productName);
    $dbStmt->execute();
    $userLicenses = $dbStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $userLicenseKeys = array_column($userLicenses, 'license_key');

    if (empty($userLicenseKeys)) {
        echo json_encode(['success' => true, 'licenses' => [], 'message' => 'No licenses generated for this product']);
        exit;
    }

    $licenses = [];
    
    switch ($productType) {
case 'valorant':
            // Configuration
            $url = "https://antivgc.com/api/licenses/list?application_id=6&limit=100";
            $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOjQ1LCJ1c2VybmFtZSI6ImRpc2NvcmQtYm90LWh3aWQtcmVzZXQiLCJyb2xlIjoicmVzZWxsZXIiLCJzZXJ2aWNlIjp0cnVlLCJjcmVhdGVkX2J5IjoiRmx4eGR6IiwiY3JlYXRlZF9hdCI6IjIwMjYtMDEtMjhUMDA6NTE6MzcuNzg0WiIsImlhdCI6MTc2OTU2MTQ5NywiZXhwIjoxODAxMDk3NDk3fQ.rtpu5gq0YSrfHlOJBHBIt8JGUPCHV0MwghOVhB9_6Do";

            $client = new \GuzzleHttp\Client();

            try {
                $response = $client->request('GET', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept'        => 'application/json',
                    ],
                    'verify' => false,
                ]);

                $body = json_decode($response->getBody(), true);
                
                // The API usually returns data in 'licenses', 'data', or the root array
                $remoteKeys = $body['licenses'] ?? $body['data'] ?? [];

                if (is_array($remoteKeys)) {
                    foreach ($remoteKeys as $item) {
                        // Normalize the key string
                        $keyString = $item['license_key'] ?? $item['key'] ?? $item['license'] ?? '';

                        // FILTER: Only process keys that exist in the User's Local Database
                        if (!empty($keyString) && in_array($keyString, $userLicenseKeys)) {
                            
                            // Get local duration info from the DB array we fetched earlier
                            $dbLicense = current(array_filter($userLicenses, function($l) use ($keyString) {
                                return $l['license_key'] === $keyString;
                            }));
                            
                            $duration = $dbLicense['duration'] ?? 1;
                            $durationType = $dbLicense['duration_type'] ?? 'days';

                            // --- Determine Status & Expiration ---
                            $status = 'Active';
                            $expirationText = '';
                            $activationDate = 'None';
                            
                            // 1. Check Activation
                            $activatedAt = $item['activated_at'] ?? null;
                            
                            if (empty($activatedAt)) {
                                $status = 'Unused';
                                $activationDate = 'None';
                                // If unused, show the original duration (e.g., "30 Days")
                                $expirationText = formatOriginalDuration($duration, $durationType);
                            } else {
                                $activationDate = gmdate("Y-m-d H:i:s", strtotime($activatedAt));
                                
                                // 2. Check Expiration
                                $expiresAt = $item['expires_at'] ?? null;
                                if ($expiresAt) {
                                    $expTimestamp = strtotime($expiresAt);
                                    if ($expTimestamp < time()) {
                                        $status = 'Expired';
                                        $expirationText = 'Expired';
                                    } else {
                                        // Calculate time remaining
                                        $secondsLeft = $expTimestamp - time();
                                        $expirationText = formatExpiration($secondsLeft, $durationType);
                                    }
                                }
                            }

                            // 3. Check Manual Ban/Disable flag from API
                            if (isset($item['active']) && $item['active'] == 0) {
                                $status = 'Disabled';
                            }

                            // Add to the final list
                            $licenses[] = [
                                'key'             => $keyString,
                                'expires'         => $expirationText,
                                'status'          => $status,
                                'genby'           => $username, // From local session
                                'gendate'         => isset($item['created_at']) ? gmdate("Y-m-d H:i:s", strtotime($item['created_at'])) : 'N/A',
                                'activation_date' => $activationDate,
                                'duration'        => $duration . ' ' . ucfirst($durationType) . ($duration > 1 ? 's' : '')
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                // If API fails, fall back to showing local DB data with a warning status
                error_log("Valorant API Fetch Error: " . $e->getMessage());
                foreach ($userLicenses as $l) {
                    $licenses[] = [
                        'key' => $l['license_key'],
                        'expires' => 'API Error',
                        'status' => 'Unknown',
                        'genby' => $username,
                        'gendate' => 'N/A',
                        'activation_date' => 'N/A',
                        'duration' => $l['duration'] . ' ' . ucfirst($l['duration_type'])
                    ];
                }
            }
            break;
    
    case 'stock':
    // Use the $userLicenses array already fetched at the start of the file
    foreach ($userLicenses as $license) {
        $duration = $license['duration'] ?? 1;
        $durationType = $license['duration_type'] ?? 'days';
        
        $licenses[] = [
            'key' => $license['license_key'],
            'expires' => 'Managed by Software', // Or use your formatOriginalDuration helper
            'status' => 'Active',
            'genby' => $username,
            'gendate' => 'N/A', // Pull from DB if you add a created_at column
            'activation_date' => 'None',
            'duration' => formatOriginalDuration($duration, $durationType)
        ];
    }

    echo json_encode([
        'success' => true,
        'licenses' => $licenses,
        'count' => count($licenses)
    ]);
    exit;
        case 'keyauth':
            $url = "{$api_url}{$api_key}&type=fetchallkeys&format=JSON";
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url, ['headers' => ['Accept' => 'application/json']]);
            $responseBody = json_decode($response->getBody(), true);

            if ($responseBody['success'] && !empty($responseBody['keys'])) {
                foreach ($responseBody['keys'] as $license) {
                    if (in_array($license['key'], $userLicenseKeys)) {
                        // Find matching license in our database to get original duration
                        $dbLicense = current(array_filter($userLicenses, function($l) use ($license) {
                            return $l['license_key'] === $license['key'];
                        }));
                        
                        $duration = $dbLicense['duration'] ?? 1;
                        $durationType = $dbLicense['duration_type'] ?? 'days';
                        
                        // For unactivated licenses, show original duration
                        if (empty($license['usedate'])) {
                            $expirationText = formatOriginalDuration($duration, $durationType);
                        } else {
                            $expirationText = formatExpiration($license['expires'] ?? 0, $durationType);
                        }
                        
                        $licenses[] = [
                            'key' => $license['key'],
                            'expires' => $expirationText,
                            'status' => $license['status'] ?? 'N/A',
                            'genby' => $username,
                            'gendate' => gmdate("Y-m-d H:i:s", $license['gendate'] ?? 0),
                            'activation_date' => isset($license['usedate']) ? gmdate("Y-m-d H:i:s", $license['usedate']) : 'None',
                            'duration' => $duration . ' ' . ucfirst($durationType) . ($duration > 1 ? 's' : '')
                        ];
                    }
                }
            }
            break;

        case 'pytguard':
            if (empty($license_identifier)) {
                echo json_encode(['success' => false, 'message' => 'License identifier is required.']);
                exit;
            }
            
            $url = "{$api_url}get_licenses?format={$license_identifier}";
            $client = new \GuzzleHttp\Client();
            
            try {
                $response = $client->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.361',
                        'x-access-key' => $api_key,
                    ],
                ]);
                
                $responseBody = json_decode($response->getBody(), true);
                
                if (is_array($responseBody) && !empty($responseBody)) {
                    foreach ($responseBody as $license) {
                        if (in_array($license['api_key'], $userLicenseKeys)) {
                            // Find matching license in our database
                            $dbLicense = current(array_filter($userLicenses, function($l) use ($license) {
                                return $l['license_key'] === $license['api_key'];
                            }));
                            
                            $duration = $dbLicense['duration'] ?? 1;
                            $durationType = $dbLicense['duration_type'] ?? 'days';
                            
                            $licenses[] = [
                                'key' => $license['api_key'],
                                'expires' => formatexpirationforpytguard($license['expiry']),
                                'status' => isset($license['sid']) ? 'Active' : 'Inactive',
                                'genby' => $username,
                                'gendate' => gmdate("Y-m-d H:i:s", $license['gendate'] ?? 0),
                                'activation_date' => isset($license['expiry']) ? 'Activated' : 'None',
                                'duration' => $duration . ' ' . ucfirst($durationType) . ($duration > 1 ? 's' : '')
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("Pytguard API error: " . $e->getMessage());
            }
            break;

        case 'privateauth':
            $authorizationEncrypted = Encryption::encrypt("BYdh467rGrmXoQG9J3DuBwYtevSA0re");
            $url = "{$api_url}GetKeys";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'AppName: ' . $license_identifier,
                'Authorization: ' . $authorizationEncrypted,
                'Content-Type: application/json',
            ]);
            $response = curl_exec($ch);
            
            if (!curl_errno($ch)) {
                $apiKeys = json_decode($response, true);
                if (is_array($apiKeys)) {
                    foreach ($apiKeys as $key) {
                        if (in_array($key, $userLicenseKeys)) {
                            // Find matching license in our database
                            $dbLicense = current(array_filter($userLicenses, function($l) use ($key) {
                                return $l['license_key'] === $key;
                            }));
                            
                            $duration = $dbLicense['duration'] ?? 1;
                            $durationType = $dbLicense['duration_type'] ?? 'days';
                            
                            // Get status for the key
                            $statusCh = curl_init();
                            curl_setopt($statusCh, CURLOPT_URL, "{$api_url}GetStatus");
                            curl_setopt($statusCh, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($statusCh, CURLOPT_HTTPHEADER, [
                                'AppName: ' . Encryption::encrypt($license_identifier),
                                'Authorization: ' . $authorizationEncrypted,
                                'KeyString: ' . Encryption::encrypt($key),
                                'Content-Type: application/json',
                            ]);
                            $statusResponse = curl_exec($statusCh);
                            
                            $statusData = [];
                            if ($statusResponse) {
                                $decrypted = Encryption::decrypt($statusResponse);
                                $statusData = json_decode($decrypted, true);
                            }
                            curl_close($statusCh);
                            
                            $days = $statusData['daysRemaining'] ?? 0;
                            $status = 'Active';
                            if ($days <= 0) {
                                $status = 'Expired';
                            } elseif (empty($statusData['startDate']) || $statusData['startDate'] === 'None') {
                                $status = 'Unused';
                            }
                            
                            if ($status === 'Unused') {
                                $expirationText = formatOriginalDuration($duration, $durationType);
                            } else {
                                $expirationText = formatExpiration($days * 86400, $durationType);
                            }
                            
                            $licenses[] = [
                                'key' => $key,
                                'expires' => $expirationText,
                                'status' => $status,
                                'genby' => $username,
                                'gendate' => gmdate("Y-m-d H:i:s", $license['gendate'] ?? 0),
                                'activation_date' => $statusData['startDate'] ?? 'None',
                                'duration' => $duration . ' ' . ucfirst($durationType) . ($duration > 1 ? 's' : '')
                            ];
                        }
                    }
                }
            }
            curl_close($ch);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unsupported product type']);
            exit;
    }

    // Log the action
    logAction($user_id, $username, $user_email, $ip_address, 
        "Fetched user licenses for product: $productName");

    echo json_encode([
        'success' => true,
        'licenses' => $licenses,
        'count' => count($licenses)
    ]);

} catch (Exception $e) {
    error_log("License fetch error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}