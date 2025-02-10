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
require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../db_connect.php';

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


// Define Product Type
$productType = new ObjectType([
    'name' => 'Product',
    'fields' => [
        'id' => ['type' => Type::nonNull(Type::int())],
        'name' => ['type' => Type::string()],
        'description' => ['type' => Type::string()],
        'price' => ['type' => Type::float()],
        'image_url' => ['type' => Type::string()],
    ],
]);

// Define Query Type
$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'products' => [
            'type' => Type::listOf($productType),
            'resolve' => function ($root, $args, $context) use ($conn, $userId) {
                $query = "SELECT * FROM products";
                $stmt = $conn->prepare($query);
                $stmt->execute();
                return $stmt->fetchAll();
            }
        ],
    ],
]);

// Define Mutation Type (for creating products later)
$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' => [
        'createProduct' => [
            'type' => $productType,
            'args' => [
                'name' => ['type' => Type::nonNull(Type::string())],
                'description' => ['type' => Type::nonNull(Type::string())],
                'price' => ['type' => Type::nonNull(Type::float())],
                'image' => ['type' => Type::string()], // Assuming image is a string (URL or base64)
            ],
            'resolve' => function ($root, $args, $context) use ($conn, $userId) {
                $name = $args['name'];
                $description = $args['description'];
                $price = $args['price'];
                $image = $args['image'];

                $query = "INSERT INTO products (name, description, price, image_url, user_id) VALUES (:name, :description, :price, :image, :user_id)";
                $stmt = $conn->prepare($query);
                $stmt->bindValue(':name', $name);
                $stmt->bindValue(':description', $description);
                $stmt->bindValue(':price', $price);
                $stmt->bindValue(':image', $image);
                $stmt->bindValue(':user_id', $userId);

                $stmt->execute();

                $productId = $conn->lastInsertId();

                $query = "SELECT * FROM products WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindValue(':id', $productId);
                $stmt->execute();

                return $stmt->fetch();
            }
        ],
    ],
]);


// Define Schema
$schema = new Schema([
    'query' => $queryType,
    'mutation' => $mutationType,
]);

// Parse incoming query and variables
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$query = $input['query'];
$variableValues = isset($input['variables']) ? $input['variables'] : null;

try {
    $result = GraphQL::executeQuery($schema, $query, null, null, $variableValues);
    $output = $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE);
} catch (\Exception $e) {
    $output = [
        'errors' => [
            [
                'message' => $e->getMessage()
            ]
        ]
    ];
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($output);
