<?php
// Database connection settings
$servername = "127.0.0.1";
$username = "root";
$password = "root";
$dbname = "travel_app_db";
$port = 8889;

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Set common headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Function to create a slug from a title
function createSlug($title) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $title), '-'));
}

// Function to handle SQL prepare errors
function handleSqlError($conn, $stmt) {
    if ($stmt === false) {
        error_log('Prepare Error: ' . $conn->error);
        die(json_encode(["error" => "Database prepare error."]));
    }
}

// Function to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags($data));
}

// Function to insert or update locations
function saveLocations($conn, $travelId, $locations, $update = false) {
    $query = $update
        ? "UPDATE locations SET name = ?, rating = ?, is_done = ?, lat = ?, `long` = ? WHERE id = ? AND travel_id = ?"
        : "INSERT INTO locations (travel_id, name, rating, is_done, lat, `long`) VALUES (?, ?, ?, ?, ?, ?)";
        
    $stmt = $conn->prepare($query);
    handleSqlError($conn, $stmt);

    foreach ($locations as $location) {
        $name = $location['name'] ?? '';
        $rating = isset($location['rating']) ? (int)$location['rating'] : 0;
        $is_done = isset($location['is_done']) ? (bool)$location['is_done'] : false;
        $lat = isset($location['lat']) && is_numeric($location['lat']) ? $location['lat'] : 0;
        $long = isset($location['long']) && is_numeric($location['long']) ? $location['long'] : 0;

        if ($update) {
            $id = $location['id'] ?? 0;
            $stmt->bind_param("siddiii", $name, $rating, $is_done, $lat, $long, $id, $travelId);
        } else {
            $stmt->bind_param("isiddd", $travelId, $name, $rating, $is_done, $lat, $long);
        }

        if (!$stmt->execute()) {
            error_log(($update ? 'Update' : 'Insert') . ' Error: ' . $stmt->error);
        }
    }

    $stmt->close();
}

// Function to insert or update foods
function saveFoods($conn, $travelId, $foods, $update = false) {
    $query = $update
        ? "UPDATE foods SET title = ?, description = ?, rating = ?, is_done = ? WHERE id = ? AND travel_id = ?"
        : "INSERT INTO foods (travel_id, title, description, rating, is_done) VALUES (?, ?, ?, ?, ?)";
        
    $stmt = $conn->prepare($query);
    handleSqlError($conn, $stmt);

    foreach ($foods as $food) {
        $title = $food['title'] ?? '';
        $description = $food['description'] ?? '';
        $rating = (int)($food['rating'] ?? 0);
        $is_done = (int)($food['is_done'] ?? 0);

        if ($update) {
            $id = $food['id'] ?? 0;
            $stmt->bind_param("ssiiii", $title, $description, $rating, $is_done, $id, $travelId);
        } else {
            $stmt->bind_param("issiii", $travelId, $title, $description, $rating, $is_done);
        }

        if (!$stmt->execute()) {
            error_log(($update ? 'Update' : 'Insert') . ' Error: ' . $stmt->error);
        }
    }

    $stmt->close();
}

// Function to insert or update facts
function saveFacts($conn, $travelId, $facts, $update = false) {
    $query = $update
        ? "UPDATE facts SET title = ?, description = ?, is_done = ? WHERE id = ? AND travel_id = ?"
        : "INSERT INTO facts (travel_id, title, description, is_done) VALUES (?, ?, ?, ?)";
        
    $stmt = $conn->prepare($query);
    handleSqlError($conn, $stmt);

    foreach ($facts as $fact) {
        $title = $fact['title'] ?? '';
        $description = $fact['description'] ?? '';
        $is_done = (int)($fact['is_done'] ?? 0);

        if ($update) {
            $id = $fact['id'] ?? 0;
            $stmt->bind_param("ssiii", $title, $description, $is_done, $id, $travelId);
        } else {
            $stmt->bind_param("issi", $travelId, $title, $description, $is_done);
        }

        if (!$stmt->execute()) {
            error_log(($update ? 'Update' : 'Insert') . ' Error: ' . $stmt->error);
        }
    }

    $stmt->close();
}

// Function to handle image uploads
function handleImages($conn, $travelId, $files, $update = false) {
    $query = $update
        ? "INSERT INTO images (travel_id, image_url) VALUES (?, ?) ON DUPLICATE KEY UPDATE image_url = VALUES(image_url)"
        : "INSERT INTO images (travel_id, image_url) VALUES (?, ?)";
        
    $stmt = $conn->prepare($query);
    handleSqlError($conn, $stmt);

    for ($i = 0; $i < count($files['name']); $i++) {
        $imageUrl = 'uploads/' . basename($files['name'][$i]);
        if (move_uploaded_file($files['tmp_name'][$i], $imageUrl)) {
            $stmt->bind_param("is", $travelId, $imageUrl);

            if (!$stmt->execute()) {
                error_log(($update ? 'Update' : 'Insert') . ' Error: ' . $stmt->error);
            }
        } else {
            error_log("Error uploading image: " . $files['name'][$i]);
        }
    }

    $stmt->close();
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $data = [];
    if (isset($_GET['slug'])) {
        $slug = $conn->real_escape_string($_GET['slug']);
        $sql = "SELECT * FROM travels WHERE slug = '$slug'";
    } elseif (isset($_GET['locations'])) {
        if ($_GET['locations'] === 'all') {
            $sql = "SELECT * FROM locations";
        } else {
            $travel_id = intval($_GET['locations']);
            $sql = "SELECT * FROM locations WHERE travel_id = $travel_id";
        }
    } elseif (isset($_GET['foods'])) {
        $travel_id = intval($_GET['foods']);
        $sql = "SELECT * FROM foods WHERE travel_id = $travel_id";
    } elseif (isset($_GET['facts'])) {
        $travel_id = intval($_GET['facts']);
        $sql = "SELECT * FROM facts WHERE travel_id = $travel_id";
    } else {
        $sql = "SELECT * FROM travels";
    }

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
    } else {
        echo json_encode(["error" => "Query error: " . $conn->error]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle POST request to insert new travel data
    $postData = file_get_contents('php://input');
    $data = json_decode($postData, true);

    $title = sanitizeInput($data['title'] ?? '');
    $description = sanitizeInput($data['description'] ?? '');
    $date = sanitizeInput($data['date'] ?? '');
    $notes = sanitizeInput($data['notes'] ?? '');

    if (empty($title) || empty($description) || empty($date)) {
        echo json_encode(["error" => "Title, description, and date are required."]);
        exit;
    }

    $slug = createSlug($title);

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("INSERT INTO travels (title, description, date, notes, slug) VALUES (?, ?, ?, ?, ?)");
        handleSqlError($conn, $stmt);
        $stmt->bind_param("sssss", $title, $description, $date, $notes, $slug);
        if ($stmt->execute()) {
            $travelId = $conn->insert_id;

            // Insert associated data
            saveLocations($conn, $travelId, $data['locations'] ?? []);
            saveFoods($conn, $travelId, $data['foods'] ?? []);
            saveFacts($conn, $travelId, $data['facts'] ?? []);

            // Insert images if available
            if (isset($_FILES['images'])) {
                handleImages($conn, $travelId, $_FILES['images']);
            }

            $conn->commit();
            echo json_encode(["success" => "New record created successfully."]);
        } else {
            throw new Exception("Error inserting travel: " . $stmt->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["error" => $e->getMessage()]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    // Read the raw input from the PUT request
    $putData = json_decode(file_get_contents('php://input'), true);

    $travelId = intval($putData['id'] ?? 0);
    $title = sanitizeInput($putData['title'] ?? '');
    $description = sanitizeInput($putData['description'] ?? '');
    $date = sanitizeInput($putData['date'] ?? '');
    $notes = sanitizeInput($putData['notes'] ?? '');

    if ($travelId <= 0 || empty($title) || empty($description) || empty($date)) {
        echo json_encode(["error" => "Travel ID, title, description, and date are required."]);
        exit;
    }

    $slug = createSlug($title);

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("UPDATE travels SET title = ?, description = ?, date = ?, notes = ?, slug = ? WHERE id = ?");
        handleSqlError($conn, $stmt);
        $stmt->bind_param("sssssi", $title, $description, $date, $notes, $slug, $travelId);
        if ($stmt->execute()) {
            saveLocations($conn, $travelId, $putData['locations'] ?? [], true);
            saveFoods($conn, $travelId, $putData['foods'] ?? [], true);
            saveFacts($conn, $travelId, $putData['facts'] ?? [], true);

            if (isset($_FILES['images'])) {
                handleImages($conn, $travelId, $_FILES['images'], true);
            }

            $conn->commit();
            echo json_encode(["success" => "Record updated successfully."]);
        } else {
            throw new Exception("Error updating travel: " . $stmt->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["error" => $e->getMessage()]);
    }
}

$conn->close();