<?php

require_once '../../../db/connection.php';
require_once '../../../vendor/autoload.php';
require_once '../../../includes/get_user_info.php';
require_once '../../../includes/session.php';
require_once '../../../includes/logging.php';

header('Content-Type: application/json');

// Capture the product name & license key from the request
$productName = $_POST['productName'] ?? '';
$licenseKey = $_POST['licenseKey'] ?? '';

if (empty($productName)) {
    echo json_encode(['success' => false, 'message' => 'Missing product name']);
    exit;
}

if (empty($licenseKey)) {
    echo json_encode(['success' => false, 'message' => 'Missing license key']);
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

$productType = $product['type'];
$api_url = $product['api_url'];
$api_key = $product['apikey'];
$license_identifier = $product['license-identifier'];

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
            // KeyAuth Reset HWID API
            $url = "{$api_url}{$api_key}&type=resetuser&user={$licenseKey}";

            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url, [
                'headers' => ['Accept' => 'application/json'],
            ]);

            $responseBody = json_decode($response->getBody(), true);

            echo json_encode([
                'success' => $responseBody['success'] ?? false,
                'message' => $responseBody['message'] ?? 'Failed to reset HWID',
            ]);
            break;

        case 'pytguard':
            // PytGuard Reset HWID API
            $url = "{$api_url}reset-api-key/{$licenseKey}";

            try {
                $client = new \GuzzleHttp\Client();
                $response = $client->request('POST', $url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.361',
                        'x-access-key' => $api_key,
                    ],
                ]);

                $rawResponse = $response->getBody();
                $responseBody = json_decode($rawResponse, true);

                echo json_encode([
                    'success' => $responseBody['success'] ?? false,
                    'message' => $responseBody['message'] ?? 'Failed to reset HWID',
                ]);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error: ' . $e->getResponse()->getBody()->getContents(),
                ]);
            }
            break;
            
            case 'stock':
        echo json_encode([
            'success' => false, 
            'message' => 'This product does not require a manual HWID reset.'
        ]);
        break;
            
case 'valorant':
            // API Configuration
            $url = "https://antivgc.com/api/licenses/reset";
            $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6ImRpc2NvcmQtYm90LWh3aWQtcmVzZXQiLCJyb2xlIjoicmVzZWxsZXIiLCJzZXJ2aWNlIjp0cnVlLCJjcmVhdGVkX2J5IjoiRmx4eGR6IiwiY3JlYXRlZF9hdCI6IjIwMjUtMTItMjhUMjE6NDQ6MjYuMTQ2WiIsImlhdCI6MTc2Njk1ODI2NiwiZXhwIjoxNzk4NDk0MjY2fQ.0NEg3LWL0DlrsFk6Y5NwCHrqcSwmE8v4ep0-0hf9KhU";

            $client = new \GuzzleHttp\Client();

            try {
                $response = $client->request('POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json'
                    ],
                    'json' => [
                        'license_key' => $licenseKey
                    ],
                    'verify' => false // Disable SSL verification (same as previous requests)
                ]);

                $responseBody = json_decode($response->getBody(), true);

                // Determine success based on API response
                $isSuccess = $responseBody['success'] ?? ($response->getStatusCode() === 200);
                $message = $responseBody['message'] ?? 'HWID Reset processed.';

                echo json_encode([
                    'success' => $isSuccess,
                    'message' => $message,
                ]);

            } catch (\GuzzleHttp\Exception\RequestException $e) {
                // Handle API errors (400, 401, 500, etc.)
                $errorContent = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
                
                // Try to extract a clean message from JSON error response
                $jsonError = json_decode($errorContent, true);
                $cleanMessage = $jsonError['message'] ?? $errorContent;

                echo json_encode([
                    'success' => false, 
                    'message' => 'API Error: ' . $cleanMessage
                ]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
            }
            break;

            case 'privateauth':
    // Encrypt headers for the request
    $appNameEncrypted = Encryption::encrypt($license_identifier);
    $authorizationEncrypted = Encryption::encrypt("BYdh467rGrmXoQG9J3DuBwYtevSA0re");
    $keyEncrypted = Encryption::encrypt($licenseKey);
    $discordIdEncrypted = Encryption::encrypt('1140696195673641100');

    // Debugging: Log the encrypted headers
    error_log("AppName Encrypted: " . $appNameEncrypted);
    error_log("Authorization Encrypted: " . $authorizationEncrypted);
    error_log("KeyString Encrypted: " . $keyEncrypted);
    error_log("DiscordId Encrypted: " . $discordIdEncrypted);

    // Define the API URL
    $url = "{$api_url}ResetHWID";

    // Make the API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'AppName: ' . $appNameEncrypted,
        'Authorization: ' . $authorizationEncrypted,
        'KeyString: ' . $keyEncrypted,
        'DiscordId: ' . $discordIdEncrypted,
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(['success' => false, 'message' => 'cURL Error: ' . curl_error($ch)]);
        curl_close($ch);
        exit;
    }

    curl_close($ch);

    // Decrypt and process the response
    if ($response) {
        error_log("Raw Response: " . $response); // Log raw response for debugging
        $decryptedResponse = Encryption::decrypt($response);
        error_log("Decrypted Response: " . $decryptedResponse); // Log decrypted response
        $responseBody = json_decode($decryptedResponse, true);

        // Ensure the responseBody is an array and handle missing keys
        if (is_array($responseBody)) {
            echo json_encode([
                'success' => $responseBody['success'] ?? false,
                'message' => $responseBody['message'] ?? 'Failed to reset HWID',
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid response from the API']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No response from the API']);
    }
    break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unsupported product type']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>