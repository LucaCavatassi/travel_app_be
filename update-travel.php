<?php
// Ensure that your PHP script can handle PUT requests
// This is a simple implementation; you might want to add more error handling and security checks
// Set common headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');
header("Content-Type: application/json");

// Database connection
$host = '127.0.0.1'; // Database host
$db = 'travel_app_db'; // Database name
$user = 'root'; // Database user
$pass = 'root'; // Database password
$port = 8889;

// Create a new PDO instance
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Handle PUT request
parse_str(file_get_contents("php://input"), $put_vars);

$travel_id = $put_vars['id'] ?? null;
$title = $put_vars['title'] ?? null;
$description = $put_vars['description'] ?? null;
$date = $put_vars['date'] ?? null;
$notes = $put_vars['notes'] ?? null;

// Check if locations, foods, and facts are set before decoding
$locations = isset($put_vars['locations']) ? json_decode($put_vars['locations'], true) : [];
$foods = isset($put_vars['foods']) ? json_decode($put_vars['foods'], true) : [];
$facts = isset($put_vars['facts']) ? json_decode($put_vars['facts'], true) : [];
$images = $_FILES['images'] ?? [];

// Validate required fields
if (empty($travel_id) || empty($title) || empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Required fields are missing.']);
    exit;
}

try {
    // Update travel details
    $stmt = $pdo->prepare("UPDATE travels SET title = ?, description = ?, date = ?, notes = ? WHERE id = ?");
    $stmt->execute([$title, $description, $date, $notes, $travel_id]);

    // Update locations
    $stmt = $pdo->prepare("DELETE FROM locations WHERE travel_id = ?");
    $stmt->execute([$travel_id]);

    foreach ($locations as $location) {
        $stmt = $pdo->prepare("INSERT INTO locations (travel_id, name, lat, long, rating, is_done) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$travel_id, $location['name'], $location['lat'], $location['long'], $location['rating'], $location['is_done']]);
    }

    // Update foods
    $stmt = $pdo->prepare("DELETE FROM foods WHERE travel_id = ?");
    $stmt->execute([$travel_id]);

    foreach ($foods as $food) {
        $stmt = $pdo->prepare("INSERT INTO foods (travel_id, title, description, rating, is_done) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$travel_id, $food['title'], $food['description'], $food['rating'], $food['is_done']]);
    }

    // Update facts
    $stmt = $pdo->prepare("DELETE FROM facts WHERE travel_id = ?");
    $stmt->execute([$travel_id]);

    foreach ($facts as $fact) {
        $stmt = $pdo->prepare("INSERT INTO facts (travel_id, title, description, is_done) VALUES (?, ?, ?, ?)");
        $stmt->execute([$travel_id, $fact['title'], $fact['description'], $fact['is_done']]);
    }

    // Update images
    if (!empty($images)) {
        // Remove existing images
        $stmt = $pdo->prepare("DELETE FROM images WHERE travel_id = ?");
        $stmt->execute([$travel_id]);

        // Add new images
        foreach ($images['tmp_name'] as $index => $tmp_name) {
            if ($images['error'][$index] === UPLOAD_ERR_OK) {
                $image_url = basename($images['name'][$index]);
                $upload_path = 'uploads/' . $image_url;

                // Move uploaded file
                move_uploaded_file($tmp_name, $upload_path);

                $stmt = $pdo->prepare("INSERT INTO images (travel_id, image_url) VALUES (?, ?)");
                $stmt->execute([$travel_id, $image_url]);
            }
        }
    }

    // Return success
    echo json_encode(['success' => true, 'message' => 'Travel details updated successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>