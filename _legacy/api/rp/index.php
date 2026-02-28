<?php
/**
 * RESTful Reseller API - RLBMODS
 * THE COMPLETE GOLDEN STANDARD IMPLEMENTATION
 */

header('Content-Type: application/json');
require_once '../../db/connection.php';
require_once '../../vendor/autoload.php';

/**
 * Standardized Response Helper
 */
function sendResponse($statusCode, $data = null) {
    http_response_code($statusCode);
    if ($statusCode >= 400) {
        echo json_encode(['error' => ['code' => $statusCode, 'message' => $data]]);
    } else {
        echo json_encode($data);
    }
    exit;
}

// 1. Force HTTPS
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// 2. Initial Parameter Check
if (!isset($_GET['reseller_token']) || !isset($_GET['type'])) {
    sendResponse(400, 'Bad Request: Missing reseller_token or type');
}

$token = trim($_GET['reseller_token']);
$action = strtolower(trim($_GET['type']));

// 3. Authentication (401)
$stmt = $con->prepare("SELECT u.id, u.name, u.balance, u.email, u.discount_override FROM reseller_tokens rt 
                      JOIN usertable u ON rt.user_id = u.id 
                      WHERE rt.token = ? AND rt.expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$auth_res = $stmt->get_result();

if ($auth_res->num_rows === 0) {
    sendResponse(401, 'Unauthorized: Invalid or expired token');
}

$reseller = $auth_res->fetch_assoc();
$reseller_id = (int)$reseller['id'];
$reseller_balance = (float)$reseller['balance'];
$reseller_discount_override = (float)$reseller['discount_override'];
$reseller_name = $reseller['name'];

// 4. Action Router
switch ($action) {

    case 'fetchbalance':
        sendResponse(200, ['balance' => number_format($reseller_balance, 2, '.', '')]);
        break;

    case 'fetchdiscount':
        $loyalty = calculateLoyaltyDiscount($con, $reseller_id);
        $total = $loyalty + $reseller_discount_override;
        sendResponse(200, [
            'loyalty_discount' => $loyalty . "%",
            'manual_override' => $reseller_discount_override . "%",
            'total_discount' => $total . "%"
        ]);
        break;

    case 'fetchallkeys':
        $stmt = $con->prepare("SELECT license_key, product_name, expires_at FROM reseller_licenses WHERE user_id = ?");
        $stmt->bind_param("i", $reseller_id);
        $stmt->execute();
        sendResponse(200, ['keys' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'fetchbyproduct':
        if (!isset($_GET['product'])) sendResponse(400, 'Product name required');
        $stmt = $con->prepare("SELECT license_key, expires_at FROM reseller_licenses WHERE user_id = ? AND product_name = ?");
        $stmt->bind_param("is", $reseller_id, $_GET['product']);
        $stmt->execute();
        sendResponse(200, ['keys' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'fetchallproducts':
        $stmt = $con->prepare("SELECT name FROM products WHERE reseller_can_sell = 1");
        $stmt->execute();
        sendResponse(200, ['products' => array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'name')]);
        break;

    case 'fetchdetails':
        $stmt = $con->prepare("SELECT license_key, product_name, duration, duration_type, created_at, expires_at FROM reseller_licenses WHERE user_id = ?");
        $stmt->bind_param("i", $reseller_id);
        $stmt->execute();
        sendResponse(200, ['licenses' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'createlicense':
        if (!isset($_GET['product'], $_GET['duration'], $_GET['duration_type'], $_GET['count'])) {
            sendResponse(400, 'Missing parameters: product, duration, duration_type, count');
        }

        $p_name = $_GET['product'];
        $dur = (int)$_GET['duration'];
        $dur_type = $_GET['duration_type'];
        $count = (int)$_GET['count'];

        $stmt = $con->prepare("SELECT * FROM products WHERE name = ? AND reseller_can_sell = 1");
        $stmt->bind_param("s", $p_name);
        $stmt->execute();
        $p_data = $stmt->get_result()->fetch_assoc();

        if (!$p_data) sendResponse(404, 'Product not found');

        $prices = ['daily' => 'daily_price', 'days' => 'daily_price', 'weekly' => 'weekly_price', 'week' => 'weekly_price', 'monthly' => 'monthly_price', 'month' => 'monthly_price', 'lifetime' => 'lifetime_price'];
        $field = $prices[$dur_type] ?? null;
        $unit_price = ($field) ? (float)$p_data[$field] : 0;

        if ($unit_price <= 0) sendResponse(400, 'Invalid duration price');

        $discount = (calculateLoyaltyDiscount($con, $reseller_id) + $reseller_discount_override) / 100;
        $total_cost = ($unit_price * $count * ($dur_type === 'lifetime' ? 1 : $dur)) * (1 - $discount);

        if ($reseller_balance < $total_cost) sendResponse(402, 'Insufficient balance');

        try {
            $con->query("UPDATE usertable SET balance = balance - $total_cost WHERE id = $reseller_id");
            $generated_keys = [];

            if ($p_data['type'] === 'stock') {
                $con->begin_transaction();
                $stmt = $con->prepare("SELECT id, license_key FROM product_stock WHERE product_id = ? AND duration = ? AND duration_type = ? AND status = 'available' LIMIT ? FOR UPDATE");
                $stmt->bind_param("iisi", $p_data['id'], $dur, $dur_type, $count);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows < $count) throw new Exception("Out of stock");
                while ($row = $res->fetch_assoc()) {
                    $con->query("UPDATE product_stock SET status = 'sold', sold_at = NOW(), sold_to_user_id = $reseller_id WHERE id = {$row['id']}");
                    insertLicense($con, $reseller_id, $p_name, $row['license_key'], $dur, $dur_type, $reseller_name, ($total_cost/$count));
                    $generated_keys[] = $row['license_key'];
                }
                $con->commit();
            } elseif ($p_data['type'] === 'keyauth') {
                $url = "{$p_data['api_url']}{$p_data['apikey']}&type=add&format=JSON&owner={$reseller_name}&mask={$p_data['license-identifier']}-****-****-****&expiry={$dur}&amount={$count}&level={$p_data['license-level']}";
                $resp = json_decode((new \GuzzleHttp\Client())->request('GET', $url)->getBody(), true);
                $keys = $resp['keys'] ?? (isset($resp['key']) ? [$resp['key']] : []);
                foreach ($keys as $k) {
                    insertLicense($con, $reseller_id, $p_name, $k, $dur, $dur_type, $reseller_name, ($total_cost/$count));
                    $generated_keys[] = $k;
                }
            } elseif ($p_data['type'] === 'pytguard') {
                $client = new \GuzzleHttp\Client();
                for ($i = 0; $i < $count; $i++) {
                    $k = "{$p_data['license-identifier']}-" . generateRandomString(4) . '-' . generateRandomString(4);
                    $client->request('GET', "{$p_data['api_url']}create_license/{$k}?expiry_days={$dur}", ['headers' => ['x-access-key' => $p_data['apikey']]]);
                    insertLicense($con, $reseller_id, $p_name, $k, $dur, $dur_type, $reseller_name, ($total_cost/$count));
                    $generated_keys[] = $k;
                }
            } 
            elseif ($p_data['type'] === 'privateauth') {
                require_once '../../api/rp/encryption.php';
                $client = new \GuzzleHttp\Client();
                $response = $client->request('GET', "{$p_data['api_url']}CreateKeys", [
                    'headers' => [
                        'AppName' => Encryption::encrypt($p_data['license-identifier']),
                        'Authorization' => Encryption::encrypt("BYdh467rGrmXoQG9J3DuBwYtevSA0re"),
                        'KeysCount' => Encryption::encrypt((string)$count),
                        'KeyDays' => Encryption::encrypt((string)$dur),
                        'AppSecret' => Encryption::encrypt($p_data['apikey']),
                    ]
                ]);
                $res = json_decode(Encryption::decrypt($response->getBody()->getContents()), true);
                if (!$res['success']) throw new Exception($res['message']);
                foreach ((array)$res['keys'] as $k) {
                    insertLicense($con, $reseller_id, $p_name, $k, $dur, $dur_type, $reseller_name, ($total_cost/$count));
                    $generated_keys[] = $k;
                }
            } elseif ($p_data['type'] === 'valorant') {
                // Configuration
                $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOjQ1LCJ1c2VybmFtZSI6ImRpc2NvcmQtYm90LWh3aWQtcmVzZXQiLCJyb2xlIjoicmVzZWxsZXIiLCJzZXJ2aWNlIjp0cnVlLCJjcmVhdGVkX2J5IjoiRmx4eGR6IiwiY3JlYXRlZF9hdCI6IjIwMjYtMDEtMjhUMDA6NTE6MzcuNzg0WiIsImlhdCI6MTc2OTU2MTQ5NywiZXhwIjoxODAxMDk3NDk3fQ.rtpu5gq0YSrfHlOJBHBIt8JGUPCHV0MwghOVhB9_6Do";
                
                $client = new \GuzzleHttp\Client();
                $response = $client->request('POST', "https://antivgc.com/api/licenses/generate", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json'
                    ],
                    'json' => [
                        'duration'       => (int)$dur,
                        'quantity'       => (int)$count,
                        'product'        => 'RLBMODS', // Or use $p_name if matches exactly
                        'application_id' => 6
                    ],
                    'verify' => false
                ]);

                $body = json_decode($response->getBody(), true);

                if (!($body['success'] ?? false)) {
                    throw new Exception("Valorant API Error: " . ($body['message'] ?? 'Unknown error'));
                }

                // Handle API returning 'licenses' array or 'keys' array
                $dataList = $body['licenses'] ?? $body['keys'] ?? [];

                foreach ($dataList as $item) {
                    // Extract string key if it's an object
                    $k = is_array($item) ? ($item['license_key'] ?? $item['key']) : $item;
                    
                    // Insert into local DB
                    insertLicense($con, $reseller_id, $p_name, $k, $dur, $dur_type, $reseller_name, ($total_cost/$count));
                    $generated_keys[] = $k;
                }
            }
            
            sendResponse(201, ['keys' => $generated_keys]);
        } catch (Exception $e) {
            if ($con->in_transaction) $con->rollback();
            $con->query("UPDATE usertable SET balance = balance + $total_cost WHERE id = $reseller_id");
            sendResponse(500, $e->getMessage());
        }
        break;

    case 'resethwid':
        if (!isset($_GET['license_key'], $_GET['product'])) sendResponse(400, 'Missing license_key or product');
        $license_key = trim($_GET['license_key']);
        $p_name = trim($_GET['product']);

        $stmt = $con->prepare("SELECT id FROM reseller_licenses WHERE user_id = ? AND license_key = ?");
        $stmt->bind_param("is", $reseller_id, $license_key);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) sendResponse(404, 'License ownership not verified');

        $stmt = $con->prepare("SELECT * FROM products WHERE name = ?");
        $stmt->bind_param("s", $p_name);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();

        try {
            $client = new \GuzzleHttp\Client();
            if ($p['type'] === 'keyauth') {
                $client->request('POST', "{$p['api_url']}{$p['apikey']}&type=resetuser&user={$license_key}");
            } elseif ($p['type'] === 'pytguard') {
                $client->request('POST', "{$p['api_url']}reset-api-key/{$license_key}", ['headers' => ['x-access-key' => $p['apikey']]]);
            } elseif ($p['type'] === 'valorant') {
                $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6ImRpc2NvcmQtYm90LWh3aWQtcmVzZXQiLCJyb2xlIjoicmVzZWxsZXIiLCJzZXJ2aWNlIjp0cnVlLCJjcmVhdGVkX2J5IjoiRmx4eGR6IiwiY3JlYXRlZF9hdCI6IjIwMjUtMTItMjhUMjE6NDQ6MjYuMTQ2WiIsImlhdCI6MTc2Njk1ODI2NiwiZXhwIjoxNzk4NDk0MjY2fQ.0NEg3LWL0DlrsFk6Y5NwCHrqcSwmE8v4ep0-0hf9KhU";
                
                $client->request('POST', "https://antivgc.com/api/licenses/reset", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json'
                    ],
                    'json' => [
                        'license_key' => $license_key
                    ],
                    'verify' => false
                ]);
            } elseif ($p['type'] === 'privateauth') {
                require_once '../../api/rp/encryption.php';
                $client->request('GET', "{$p['api_url']}ResetHWID", [
                    'headers' => [
                        'AppName' => Encryption::encrypt($p['license-identifier']),
                        'Authorization' => Encryption::encrypt("BYdh467rGrmXoQG9J3DuBwYtevSA0re"),
                        'KeyString' => Encryption::encrypt($license_key),
                        'DiscordId' => Encryption::encrypt('1140696195673641100')
                    ]
                ]);
            }
            sendResponse(200, ['status' => 'Reset successful']);
        } catch (Exception $e) {
            sendResponse(500, 'Reset failed: ' . $e->getMessage());
        }
        break;

    default:
        sendResponse(400, "Invalid action: $action");
        break;
}

/**
 * HELPERS
 */
function calculateLoyaltyDiscount($con, $u_id) {
    $stmt = $con->prepare("SELECT COUNT(*) as total FROM reseller_licenses WHERE user_id = ?");
    $stmt->bind_param("i", $u_id);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    if ($total >= 500) return 20;
    if ($total >= 200) return 15;
    if ($total >= 100) return 10;
    if ($total >= 50) return 5;
    return 0;
}

function insertLicense($con, $u_id, $p_name, $l_key, $dur, $dur_t, $gen_by, $cost) {
    $days = (int)$dur;
    if ($dur_t === 'week' || $dur_t === 'weekly') $days = $dur * 7;
    if ($dur_t === 'month' || $dur_t === 'monthly') $days = $dur * 30;
    if ($dur_t === 'lifetime') $days = 3650;
    $exp = date('Y-m-d H:i:s', strtotime("+$days days"));
    $stmt = $con->prepare("INSERT INTO reseller_licenses (user_id, product_name, license_key, duration, duration_type, generated_by, ip_address, cost, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("ississsds", $u_id, $p_name, $l_key, $dur, $dur_t, $gen_by, $ip, $cost, $exp);
    $stmt->execute();
}

function generateRandomString($length) {
    return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
}