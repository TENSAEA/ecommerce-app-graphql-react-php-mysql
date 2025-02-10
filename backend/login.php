<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Access-Control-Allow-Origin");
header('Content-Type: application/json');

error_log("login.php: Script started"); // Initial log

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    error_log("login.php: OPTIONS request handled");
    exit;
}
require_once __DIR__ . '/../vendor/autoload.php';

include_once 'db_connect.php';
error_log("login.php: db_connect.php included");

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
error_log("login.php: JWT classes used");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("login.php: POST request received");

    $data = @json_decode(file_get_contents("php://input"), true); // Use @ to suppress warnings
    error_log("login.php: Raw POST data: " . file_get_contents("php://input")); // Log raw POST data

    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        $error_message = "Invalid JSON data: " . json_last_error_msg();
        error_log("login.php: " . $error_message);
        echo json_encode(array("message" => $error_message));
        exit;
    }

    $username = isset($data['username']) ? trim($data['username']) : '';
    $password = isset($data['password']) ? trim($data['password']) : '';

    error_log("login.php: Username: " . $username . ", Password: " . $password);

    if (empty($username) || empty($password)) {
        http_response_code(400);
        $error_message = "Username and password are required.";
        error_log("login.php: " . $error_message);
        echo json_encode(array("message" => $error_message));
        exit;
    }

    $query = "SELECT id, username, password, role FROM users WHERE username = :username";
    try {
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user) {
            if (password_verify($password, $user['password'])) {
                // Password is correct

                // JWT Payload
                $payload = array(
                    "iss" => "http://localhost:5173", // Replace with your domain
                    "aud" => "http://localhost:5173", // Replace with your domain
                    "iat" => time(), // Issued at timestamp
                    "nbf" => time(), // Not before timestamp
                    "exp" => time() + (60 * 60), // Expiration time (1 hour)
                    "data" => array(
                        "id" => $user['id'],
                        "username" => $user['username'],
                        "role" => $user['role']
                    )
                );

                // JWT Secret Key (keep this very secret and secure!)
                $secret_key = "my_secret_key"; // Replace with a strong, random key!

                // Generate JWT
                try {
                    $jwt = JWT::encode($payload, $secret_key, 'HS256');

                    http_response_code(200);
                    $response = array(
                        "message" => "Login successful.",
                        "jwt" => $jwt,
                        "user" => array(
                            "id" => $user['id'],
                            "username" => $user['username'],
                            "role" => $user['role']
                        )
                    );
                    error_log("login.php: Login successful, JWT generated");
                    echo json_encode($response);
                    exit;

                } catch (Exception $e) {
                    http_response_code(500);
                    $error_message = "JWT Encoding Error: " . $e->getMessage();
                    error_log("login.php: " . $error_message);
                    echo json_encode(array("message" => $error_message));
                    exit;
                }

            } else {
                http_response_code(401);
                $error_message = "Invalid credentials.";
                error_log("login.php: " . $error_message);
                echo json_encode(array("message" => $error_message));
                exit;
            }
        } else {
            http_response_code(401);
            $error_message = "Invalid credentials.";
            error_log("login.php: " . $error_message);
            echo json_encode(array("message" => $error_message));
            exit;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        $error_message = "Database error: " . $e->getMessage();
        error_log("login.php: " . $error_message);
        echo json_encode(array("message" => $error_message));
        exit;
    }

} else {
    http_response_code(405);
    $error_message = "Only POST requests are allowed.";
    error_log("login.php: " . $error_message);
    echo json_encode(array("message" => $error_message));
    exit;
}
?>
