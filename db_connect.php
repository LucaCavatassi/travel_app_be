<?php
$servername = "127.0.0.1";  // Use the provided IP address for DB_HOST
$username = "root";  // Database username
$password = "root";  // Database password
$dbname = "travel_app_db";  // Database name
$port = 8889;  // Use the provided DB_PORT

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Example query to fetch data
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $sql = "SELECT * FROM travels";
    $result = $conn->query($sql);

    $data = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    // Return data as JSON
    header("Access-Control-Allow-Origin: *");  // Allows requests from any origin
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");  // Allows specific methods
    header("Access-Control-Allow-Headers: Content-Type");
    header('Content-Type: application/json');
    echo json_encode($data);
}

$conn->close();
?>