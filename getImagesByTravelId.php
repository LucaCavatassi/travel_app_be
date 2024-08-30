<?php
// Database connection settings
$servername = "gre.h.filess.io";
$username = "travelApp_biggerjack";
$password = "a5fe397f6bc80ceb53a9efdf16883c060ed35796";
$dbname = "travelApp";
$port = 3307;

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

header("Access-Control-Allow-Origin: *"); // Allow all origins (adjust as needed)
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Determine the request method
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Get travel_id from request
$travel_id = isset($_GET['travel_id']) ? intval($_GET['travel_id']) : 0;

if ($travel_id <= 0) {
    echo json_encode(["error" => "Invalid travel_id"]);
    exit;
}

function handleSqlError($conn, $stmt) {
    if ($stmt === false) {
        die(json_encode(["error" => "SQL Error: " . $conn->error]));
    }
}

function handleImages($conn, $travelId) {
    if (empty($_FILES['images']['name'][0])) {
        return; // No images to process
    }

    $images = $_FILES['images'];
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

switch ($requestMethod) {
    case 'GET':
        // Fetch images for the given travel_id
        $sql = "SELECT image_url FROM images WHERE travel_id = ?";
        $stmt = $conn->prepare($sql);
        handleSqlError($conn, $stmt);
        $stmt->bind_param("i", $travel_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $images = [];
        while ($row = $result->fetch_assoc()) {
            $images[] = $row['image_url'];
        }

        echo json_encode($images);
        $stmt->close();
        break;

    case 'POST':
        // Handle image uploads
        handleImages($conn, $travel_id);
        echo json_encode(["success" => "Images uploaded successfully"]);
        break;

    case 'DELETE':
        // Get image to delete
        $image = isset($_GET['image']) ? $_GET['image'] : '';

        if (empty($image)) {
            echo json_encode(["error" => "Image parameter is required"]);
            exit;
        }

        // Delete image record from database
        $sql = "DELETE FROM images WHERE travel_id = ? AND image_url = ?";
        $stmt = $conn->prepare($sql);
        handleSqlError($conn, $stmt);
        $stmt->bind_param("is", $travel_id, $image);

        if ($stmt->execute()) {
            // Delete image file from server
            $uploadDir = 'uploads/';
            $filePath = $uploadDir . $image;

            if (file_exists($filePath)) {
                unlink($filePath);
            }

            echo json_encode(["success" => "Image deleted successfully"]);
        } else {
            echo json_encode(["error" => "Failed to delete image"]);
        }

        $stmt->close();
        break;

    default:
        echo json_encode(["error" => "Unsupported request method"]);
        break;
}

$conn->close();
?>