<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept, Authorization");
header("Access-Control-Allow-Credentials: true");

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\GraphQL;
use GraphQL\Error\DebugFlag;
use GraphQL\Type\Schema;  // Add this line
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$secret_key = "my_secret_key";
// Include the Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';
include_once 'db_connect.php';

// Debugging: Log all headers received by the server
error_log("All HTTP Headers: " . json_encode(getallheaders()));

// Attempt to retrieve the Authorization header
$authHeader = null;
if (function_exists('getallheaders')) {
    $allHeaders = getallheaders();
    // Check for both 'Authorization' and 'authorization'
    $authHeader = isset($allHeaders['Authorization']) ? $allHeaders['Authorization'] : (isset($allHeaders['authorization']) ? $allHeaders['authorization'] : null);
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['Authorization'])) {
    $authHeader = $_SERVER['Authorization'];
}

// Log the retrieved Authorization header
error_log("Authorization Header: " . $authHeader);

// Extract the JWT from the Authorization header
$jwt = null;
if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $jwt = trim($matches[1]);
    error_log("Extracted JWT: " . $jwt);
} else {
    error_log("No valid Bearer token found in Authorization header");
    http_response_code(401);
    echo json_encode(['errors' => [['message' => 'Unauthorized: JWT missing or invalid format']]]);
    exit;
}

try {
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $userId = $decoded->data->id;
    error_log("GraphQL: JWT decoded successfully, User ID: " . $userId);
    // You can now use $userId for authorization checks in your GraphQL resolvers
} catch (\Exception $e) {
    http_response_code(401);
    error_log("GraphQL: JWT decode error: " . $e->getMessage());
    echo json_encode(['errors' => [['message' => 'Unauthorized: Invalid JWT: '. $e->getMessage()]]]);
    exit;
}
// Verify JWT
try {
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $userId = $decoded->data->id;
    error_log("GraphQL: JWT decoded successfully, User ID: " . $userId);
    // You can now use $userId for authorization checks in your GraphQL resolvers
} catch (\Exception $e) {
    http_response_code(401);
    error_log("GraphQL: JWT decode error: " . $e->getMessage());
    echo json_encode(['errors' => [['message' => 'Unauthorized: Invalid JWT: '. $e->getMessage()]]]);
    exit;
}



// Fetch cart items for the user
$query = "SELECT c.product_id, c.quantity, p.name, p.price, p.image_url, p.description
          FROM carts c
          INNER JOIN products p ON c.product_id = p.id
          WHERE c.user_id = :user_id"; //Join the tables to show full needed information
$stmt = $conn->prepare($query);
$stmt->bindParam(":user_id", $userId);
$stmt->execute();
$cartItems = $stmt->fetchAll();

http_response_code(200);
echo json_encode($cartItems);
?>