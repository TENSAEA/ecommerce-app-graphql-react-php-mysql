<?php

$host = "localhost"; // Or your specific host (e.g., 127.0.0.1)
$dbname = "ecommerce"; // Your database name
$username = "root"; // Your MySQL username
$password = "316213"; // Your MySQL password (leave blank if you didn't set one)

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // set the default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    //echo "Connected successfully"; // Remove this in production
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die(); // Stop script execution if connection fails
}
