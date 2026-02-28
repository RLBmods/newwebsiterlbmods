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

// Assign product-specific values
$api_url = $product['api_url'];
$api_key = $product['apikey'];
$productType = $product['type'];
$license_identifier = $product['license-identifier'];

// Helper function to format expiration
function formatExpiration($totalSeconds)
{
    if ($totalSeconds <= 0) return 'Expired';

    $days = floor($totalSeconds / 86400); // Convert seconds to days

    if ($days <= 1) return "$days Day";
    if ($days < 7) return "$days Days";
    if ($days < 30) return floor($days / 7) . " Week(s)";
    if ($days < 365) return floor($days / 30) . " Month(s)";
    if ($days < 1825) return floor($days / 365) . " Year(s)";

    return 'Lifetime'; // Anything over 5 years
}

function formatexpirationforpytguard($expiry_date) {
    if (empty($expiry_date) || $expiry_date == 'null') {
        return "Activate the key first";
    }

$timestampToUse = $timestampVariable ?? 'now';
$current_date = new DateTime($timestampToUse);
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
    } elseif ($interval->m > 0) {
        return $interval->m . " month" . ($interval->m > 1 ? "s" : "") . " left";
    } elseif ($days_left >= 7) {
        $weeks_left = floor($days_left / 7);
        return $weeks_left . " week" . ($weeks_left > 1 ? "s" : "") . " left";
    } else {
        return $days_left . " day" . ($days_left > 1 ? "s" : "") . " left";
    }
}

// Encryption Class for PrivateAuth
class Encryption
{
    public static function encrypt($str)
    {
        $str = mb_convert_encoding($str, 'UTF-8', 'auto');
        return self::encryptBytes($str);
    }

    public static function decrypt($bytes)
    {
        return self::decryptBytes($bytes);
    }

    public static function encryptBytes($str)
    {
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

    public static function decryptBytes($str)
    {
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
            $url = "{$api_url}{$api_key}&type=fetchallkeys&format=JSON";
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url, [
                'headers' => ['Accept' => 'application/json'],
            ]);

            $responseBody = json_decode($response->getBody(), true);

            if ($responseBody['success'] && !empty($responseBody['keys'])) {
                $licenses = array_map(function ($license) {
                    return [
                        'key' => $license['key'] ?? 'N/A',
                        'expires' => formatExpiration($license['expires'] ?? 0),
                        'status' => $license['status'] ?? 'N/A',
                        'genby' => $license['genby'] ?? 'N/A',
                        'gendate' => gmdate("Y-m-d H:i:s", $license['gendate'] ?? 0),
                        'activation_date' => isset($license['usedate']) ? gmdate("Y-m-d H:i:s", $license['usedate']) : 'None'
                    ];
                }, $responseBody['keys']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Fetched licenses successfully',
                    'licenses' => $licenses,
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Currently no licenses available.',
                ]);
            }
            break;
            
            case 'stock':
    // Admin View: Fetch every key associated with this product
    // We use COLLATE utf8mb4_general_ci to fix the "Illegal mix of collations" error
    $stmt = $con->prepare("
        SELECT 
            ps.license_key as `key`,
            ps.status as stock_status,
            ps.added_at as gendate,
            rl.generated_at as redeemed_at,
            rl.generated_by as sold_by,
            rl.duration as dur_val,
            rl.duration_type as dur_type,
            rl.expires_at,
            u.name as owner_name
        FROM product_stock ps
        LEFT JOIN reseller_licenses rl ON (ps.license_key COLLATE utf8mb4_general_ci = rl.license_key COLLATE utf8mb4_general_ci)
        LEFT JOIN usertable u ON rl.user_id = u.id
        WHERE ps.product_id = (
            SELECT id FROM products 
            WHERE name COLLATE utf8mb4_general_ci = ? 
            LIMIT 1
        )
        ORDER BY ps.added_at DESC
    ");
    
    $stmt->bind_param("s", $productName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $licenses = [];
    while ($row = $result->fetch_assoc()) {
        $status = $row['stock_status'];
        $formattedExpires = 'N/A';

        if ($row['expires_at']) {
            $expireTimestamp = strtotime($row['expires_at']);
            if ($expireTimestamp < time()) {
                $status = 'Expired';
                $formattedExpires = 'Expired';
            } else {
                $secondsLeft = $expireTimestamp - time();
                $formattedExpires = formatExpiration($secondsLeft, $row['dur_type'] ?? 'days');
            }
        }

        $licenses[] = [
            'key' => $row['key'],
            'status' => ucfirst($status),
            'duration' => $row['dur_val'] ? $row['dur_val'] . ' ' . ucfirst($row['dur_type']) : 'Not Sold',
            'gendate' => date("Y-m-d H:i", strtotime($row['gendate'])),
            'activation_date' => $row['redeemed_at'] ? date("Y-m-d H:i", strtotime($row['redeemed_at'])) : 'Pending',
            'expires' => $formattedExpires,
            'genby' => $row['sold_by'] ?? 'System',
            'owner' => $row['owner_name'] ?? 'Available'
        ];
    }

    echo json_encode([
        'success' => true,
        'licenses' => $licenses,
        'count' => count($licenses)
    ]);
    break;

case 'valorant':
            // API Configuration
            $url = "https://antivgc.com/api/licenses/list?application_id=6&limit=50";
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

                $responseBody = json_decode($response->getBody(), true);
                $licensesData = $responseBody['licenses'] ?? [];

                if (($responseBody['success'] ?? false) === true) {
                    $licenses = array_map(function ($license) {
                        
                        // Formatting helper for dates
                        $formatDate = function($dateStr) {
                            return (!empty($dateStr)) ? date("Y-m-d H:i:s", strtotime($dateStr)) : 'N/A';
                        };

                        // --- STATUS LOGIC ---
                        if (empty($license['activated_at'])) {
                            // If never activated, force status to Inactive (Fresh Key)
                            $status = 'Inactive';
                        } elseif (isset($license['active']) && $license['active'] == 0) {
                            // If explicitly disabled by admin/system
                            $status = 'Disabled';
                        } else {
                            // Otherwise, it is Active
                            $status = 'Active';
                        }

                        // Override status if Expired
                        // Note: Depending on your API, a fresh key (Inactive) might have an expiration date in the future, 
                        // but if the date is in the past, it's definitely expired.
                        if (!empty($license['expires_at']) && strtotime($license['expires_at']) < time()) {
                            $status = 'Expired';
                        }
                        // --------------------

                        // Calculate "Time Left" string
                        $expiresDisplay = 'Lifetime';
                        if (!empty($license['expires_at'])) {
                            $expTime = strtotime($license['expires_at']);
                            $now = time();
                            if ($expTime < $now) {
                                $expiresDisplay = 'Expired';
                            } else {
                                $daysLeft = floor(($expTime - $now) / (60 * 60 * 24));
                                if ($daysLeft > 365) {
                                    $expiresDisplay = "Lifetime (>1 Year)";
                                } elseif ($daysLeft > 0) {
                                    $expiresDisplay = $daysLeft . " Days Left";
                                } else {
                                    $expiresDisplay = "Less than 1 Day";
                                }
                            }
                        }

                        return [
                            'key'             => $license['license_key'] ?? 'N/A',
                            'expires'         => $expiresDisplay, 
                            'status'          => $status,
                            'genby'           => 'ID: ' . ($license['created_by'] ?? 'System'),
                            'gendate'         => $formatDate($license['created_at'] ?? ''),
                            'activation_date' => (!empty($license['activated_at'])) ? $formatDate($license['activated_at']) : 'None'
                        ];
                    }, $licensesData);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Fetched ' . count($licenses) . ' licenses successfully',
                        'licenses' => $licenses,
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => 'No licenses found.',
                        'licenses' => []
                    ]);
                }

            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $msg = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
                echo json_encode(['success' => false, 'message' => 'API Request Error: ' . $msg]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => 'General Error: ' . $e->getMessage()]);
            }
            break;
        case 'pytguard':
            if (empty($license_identifier)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'License identifier is required.',
                ]);
                exit;
            }
        
            $url = "{$api_url}get_licenses?format={$license_identifier}";

            try {
                $client = new \GuzzleHttp\Client();
                $response = $client->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.361',
                        'x-access-key' => $api_key,
                    ],
                ]);
        
                $responseBody = json_decode($response->getBody(), true);

                if (is_array($responseBody) && !empty($responseBody)) {
                    $formattedLicenses = array_map(function ($license) {
                        return [
                            'key' => $license['api_key'],
                            'expires' => formatexpirationforpytguard($license['expiry']),
                            'status' => isset($license['sid']) ? 'Active' : 'Inactive',
                            'genby' => 'System',
                            'gendate' => isset($license['gendate']) ? gmdate("Y-m-d H:i:s", $license['gendate']) : 'N/A',
                            'activation_date' => isset($license['expiry']) ? 'Activated' : 'None'
                        ];
                    }, $responseBody);
                
                    echo json_encode([
                        'success' => true,
                        'licenses' => $formattedLicenses,
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No licenses found.',
                    ]);
                }
            } catch (\Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error retrieving licenses: ' . $e->getMessage(),
                ]);
            }
            break;

        case 'privateauth':
            $authorizationEncrypted = Encryption::encrypt("BYdh467rGrmXoQG9J3DuBwYtevSA0re");
            
            // Get all keys
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "{$api_url}GetKeys",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'AppName: ' . $license_identifier,
                    'Authorization: ' . $authorizationEncrypted,
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!$response || $httpCode !== 200) {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch keys from API']);
                exit;
            }

            $keys = json_decode($response, true);
            if (!is_array($keys)) {
                echo json_encode(['success' => false, 'message' => 'Invalid keys format from API']);
                exit;
            }

            $licenses = [];
            $batchSize = 20;
            $timeout = 5;
            
            foreach (array_chunk($keys, $batchSize) as $keyBatch) {
                $multiHandle = curl_multi_init();
                $handles = [];
                
                foreach ($keyBatch as $key) {
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => "{$api_url}GetStatus",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => $timeout,
                        CURLOPT_HTTPHEADER => [
                            'AppName: ' . Encryption::encrypt($license_identifier),
                            'Authorization: ' . $authorizationEncrypted,
                            'KeyString: ' . Encryption::encrypt($key),
                            'Content-Type: application/json',
                        ],
                    ]);
                    curl_multi_add_handle($multiHandle, $ch);
                    $handles[] = ['handle' => $ch, 'key' => $key];
                }
                
                $startTime = time();
                $running = null;
                do {
                    $status = curl_multi_exec($multiHandle, $running);
                    if ($running) {
                        curl_multi_select($multiHandle, 0.5);
                    }
                    
                    if (time() - $startTime > ($timeout * 2)) {
                        break;
                    }
                } while ($running && $status === CURLM_OK);
                
                foreach ($handles as $h) {
                    $response = curl_multi_getcontent($h['handle']);
                    
                    $defaultData = [
                        'key' => $h['key'],
                        'expires' => 'N/A',
                        'status' => 'N/A',
                        'genby' => 'System',
                        'gendate' => 'N/A',
                        'activation_date' => 'None'
                    ];
                    
                    if ($response) {
                        try {
                            $decrypted = Encryption::decrypt($response);
                            $data = json_decode($decrypted, true);
                            
                            if ($data && isset($data['success'])) {
                                $days = $data['daysRemaining'] ?? 0;
                                $startDate = $data['startDate'] ?? 'None';
                                $expiryDate = $data['expiryDate'] ?? 'None';
                                
                                $status = 'Active';
                                if ($startDate === 'None' || $startDate === 'N/A') {
                                    $status = 'Unused';
                                } elseif ($days <= 0) {
                                    $status = 'Expired';
                                } elseif (isset($data['message']) && stripos($data['message'], 'expired') !== false) {
                                    $status = 'Expired';
                                }
                                
                                if ($status === 'Unused') {
                                    $formattedExpires = $days;
                                } elseif ($status === 'Expired') {
                                    $formattedExpires = 'Expired';
                                } else {
                                    if ($days <= 1) {
                                        $formattedExpires = "1 Day";
                                    } elseif ($days < 7) {
                                        $formattedExpires = "$days Days";
                                    } elseif ($days < 30) {
                                        $weeks = floor($days / 7);
                                        $formattedExpires = "$weeks Week" . ($weeks > 1 ? 's' : '');
                                    } elseif ($days < 365) {
                                        $months = floor($days / 30);
                                        $formattedExpires = "$months Month" . ($months > 1 ? 's' : '');
                                    } else {
                                        $formattedExpires = 'Lifetime';
                                    }
                                }
                                
                                $licenses[] = [
                                    'key' => $h['key'],
                                    'expires' => $formattedExpires,
                                    'status' => $status,
                                    'genby' => 'System',
                                    'gendate' => $startDate,
                                    'activation_date' => $startDate
                                ];
                                continue;
                            }
                        } catch (Exception $e) {
                            error_log("Error processing key {$h['key']}: " . $e->getMessage());
                        }
                    }
                    
                    $licenses[] = $defaultData;
                    curl_multi_remove_handle($multiHandle, $h['handle']);
                    curl_close($h['handle']);
                }
                
                curl_multi_close($multiHandle);
                usleep(200000);
            }

            echo json_encode([
                'success' => true,
                'message' => "Fetched " . count($licenses) . " licenses",
                'licenses' => $licenses
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unsupported product type']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}