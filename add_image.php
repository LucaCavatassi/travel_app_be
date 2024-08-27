<?php

$servername = "127.0.0.1";
$username = "root";
$password = "root";
$dbname = "travel_app_db";
$port = 8889;

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Handle SQL errors
function handleSqlError($conn, $stmt) {
    if ($stmt === false) {
        error_log("SQL Error: " . $conn->error);
        echo json_encode(["error" => "SQL Error: " . $conn->error]);
        exit();
    }
}

// Handle image uploads
function handleImages($conn, $travelId) {
    if (empty($_FILES['images']['name'][0])) {
        return; // No images to process
    }

    $images = $_FILES['images'];
    $imageCount = count($images['name']);
    $uploadDir = 'uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    foreach ($images['name'] as $index => $imageName) {
        if ($images['error'][$index] === UPLOAD_ERR_OK) {
            $tmpName = $images['tmp_name'][$index];
            $fileName = 'image_' . time() . '_' . $index . '_' . basename($imageName);
            $imagePath = $uploadDir . $fileName;

            if (move_uploaded_file($tmpName, $imagePath)) {
                $stmt = $conn->prepare("INSERT INTO images (travel_id, image_url) VALUES (?, ?)");
                handleSqlError($conn, $stmt);
                $stmt->bind_param("is", $travelId, $fileName);

                if (!$stmt->execute()) {
                    error_log('Insert Image Error: ' . $stmt->error);
                }
                $stmt->close();
            } else {
                error_log("Error moving uploaded file: " . $imageName);
            }
        } else {
            error_log("Error uploading file: " . $images['name'][$index]);
        }
    }
}

// Handle the POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && isset($_FILES['images'])) {
        $travelId = (int)$_POST['id'];
        handleImages($conn, $travelId);
        echo json_encode(["success" => "Files uploaded successfully"]);
    } else {
        echo json_encode(["error" => "Invalid request"]);
    }
} else {
    echo json_encode(["error" => "Invalid request method"]);
}

$conn->close();
?>