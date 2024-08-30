<?php

$servername = "gre.h.filess.io";
$username = "travelApp_biggerjack";
$password = "a5fe397f6bc80ceb53a9efdf16883c060ed35796";
$dbname = "travelApp";
$port = 3307;

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

function removeImage($conn, $imageName) {

    $imagePath = 'uploads/' . $imageName;

    // Check if the file exists before trying to delete it
    if (file_exists($imagePath)) {
        if (unlink($imagePath)) {
            echo "File successfully deleted: $imagePath\n";
        } else {
            echo "Error deleting file: $imagePath\n";
        }
    } else {
        echo "File does not exist: $imagePath. Proceeding to delete database record.\n";
    }

    // Proceed to delete the record from the database even if the file does not exist
    $stmt = $conn->prepare("DELETE FROM images WHERE image_url = ?");
    if (!$stmt) {
        echo "SQL prepare error: " . $conn->error . "\n";
        return;
    }
    $stmt->bind_param("s", $imageName);

    if (!$stmt->execute()) {
        echo 'Delete Image Error: ' . $stmt->error;
    } else {
        echo "Database record deleted for image: $imageName\n";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['image'])) {
    $imageName = $_POST['image'];
    echo "Received image name: $imageName\n";
    // Assuming $conn is your database connection, make sure it's available here.
    removeImage($conn, $imageName);
} else {
    echo "No image name received or incorrect request method.\n";
}
?>