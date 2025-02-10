<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Access-Control-Allow-Origin");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once 'db_connect.php'; // Include the database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from the request (assuming it's JSON)
    $data = json_decode(file_get_contents("php://input"));

    $username = isset($data->username) ? trim($data->username) : '';
    $email = isset($data->email) ? trim($data->email) : '';
    $password = isset($data->password) ? trim($data->password) : '';

    // Basic validation (add more robust validation later)
    if (empty($username) || empty($email) || empty($password)) {
        http_response_code(400); // Bad Request
        echo json_encode(array("message" => "All fields are required."));
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(array("message" => "Invalid email format."));
        exit();
    }

    // Check if username or email already exists
    $query = "SELECT id FROM users WHERE username = :username OR email = :email";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":username", $username);
    $stmt->bindParam(":email", $email);

    try {
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(array("message" => "Username or email already exists."));
            exit();
        }
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(array("message" => "Database error: " . $e->getMessage()));
        exit();
    }

    // Hash the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert the user into the database
    $query = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":username", $username);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":password", $password_hash);

    try {
        if ($stmt->execute()) {
            http_response_code(201); // Created
            echo json_encode(array("message" => "User registered successfully."));
        } else {
            http_response_code(500);
            echo json_encode(array("message" => "Failed to register user."));
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(array("message" => "Database error: " . $e->getMessage()));
    }

} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(array("message" => "Only POST requests are allowed."));
}
?>
