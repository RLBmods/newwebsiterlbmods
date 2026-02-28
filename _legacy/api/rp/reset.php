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
$required = ['productName', 'licenseKey'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$productName = $input['productName'];
$licenseKey = $input['licenseKey'];

// Verify the license belongs to the reseller
$stmt = $con->prepare("SELECT * FROM reseller_licenses WHERE user_id = ? AND product_name = ? AND license_key = ?");
$stmt->bind_param("iss", $reseller['user_id'], $productName, $licenseKey);
$stmt->execute();
$license = $stmt->get_result()->fetch_assoc();

if (!$license) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'License not found or does not belong to you']);
    exit;
}

// Fetch product details
$stmt = $con->prepare("SELECT * FROM products WHERE name = ?");
$stmt->bind_param("s", $productName);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product name']);
    exit;
}

$productType = $product['type'];
$api_url = $product['api_url'];
$api_key = $product['apikey'];
$license_identifier = $product['license-identifier'];

try {
    switch ($productType) {
        case 'keyauth':
            $url = "{$api_url}{$api_key}&type=resetuser&user={$licenseKey}";
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url, [
                'headers' => ['Accept' => 'application/json'],
            ]);
            $responseBody = json_decode($response->getBody(), true);

            http_response_code(200);
            echo json_encode([
                'success' => $responseBody['success'] ?? false,
                'message' => $responseBody['message'] ?? 'Failed to reset HWID',
            ]);
            logAction($reseller['user_id'], $reseller['user_name'], $reseller['user_email'], $reseller['ip_address'], 
                "API: Reset HWID for license: $licenseKey");
            break;

        case 'pytguard':
            $url = "{$api_url}reset-api-key/{$licenseKey}";
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0',
                    'x-access-key' => $api_key,
                ],
            ]);
            $rawResponse = $response->getBody();
            $responseBody = json_decode($rawResponse, true);

            http_response_code(200);
            echo json_encode([
                'success' => $responseBody['success'] ?? false,
                'message' => $responseBody['message'] ?? 'Failed to reset HWID',
            ]);
            logAction($reseller['user_id'], $reseller['user_name'], $reseller['user_email'], $reseller['ip_address'], 
                "API: Reset HWID for PytGuard license: $licenseKey");
            break;

        case 'privateauth':
            $appNameEncrypted = Encryption::encrypt($license_identifier);
            $authorizationEncrypted = Encryption::encrypt("BYdh467rGrmXoQG9J3DuBwYtevSA0re");
            $keyEncrypted = Encryption::encrypt($licenseKey);
            $discordIdEncrypted = Encryption::encrypt('1140696195673641100');

            $url = "{$api_url}ResetHWID";
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
                throw new Exception('cURL Error: ' . curl_error($ch));
            }
            curl_close($ch);

            if ($response) {
                $decryptedResponse = Encryption::decrypt($response);
                $responseBody = json_decode($decryptedResponse, true);

                http_response_code(200);
                echo json_encode([
                    'success' => $responseBody['success'] ?? false,
                    'message' => $responseBody['message'] ?? 'Failed to reset HWID',
                ]);
                logAction($reseller['user_id'], $reseller['user_name'], $reseller['user_email'], $reseller['ip_address'], 
                    "API: Reset HWID for PrivateAuth license: $licenseKey");
            } else {
                throw new Exception('No response from the API');
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unsupported product type']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>