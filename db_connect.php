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
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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
        $rating = (int)($location['rating'] ?? 0);
        $is_done = (bool)($location['is_done'] ?? false);
        $lat = (float)($location['lat'] ?? 0);
        $long = (float)($location['long'] ?? 0);

        if ($update) {
            $id = (int)($location['id'] ?? 0);
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
            $id = (int)($food['id'] ?? 0);
            $stmt->bind_param("ssiiii", $title, $description, $rating, $is_done, $id, $travelId);
        } else {
            $stmt->bind_param("issii", $travelId, $title, $description, $rating, $is_done);
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
            $id = (int)($fact['id'] ?? 0);
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
            $travel_id = (int)$_GET['locations'];
            $sql = "SELECT * FROM locations WHERE travel_id = $travel_id";
        }
    } elseif (isset($_GET['foods'])) {
        $travel_id = (int)$_GET['foods'];
        $sql = "SELECT * FROM foods WHERE travel_id = $travel_id";
    } elseif (isset($_GET['facts'])) {
        $travel_id = (int)$_GET['facts'];
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
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $date = $_POST['date'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if (empty($title) || empty($description) || empty($date)) {
        echo json_encode(["error" => "Title, description, and date are required."]);
        exit;
    }

    $title = sanitizeInput($title);
    $description = sanitizeInput($description);
    $date = sanitizeInput($date);
    $notes = sanitizeInput($notes);
    $slug = createSlug($title);

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("INSERT INTO travels (title, description, date, notes, slug) VALUES (?, ?, ?, ?, ?)");
        handleSqlError($conn, $stmt);

        $stmt->bind_param("sssss", $title, $description, $date, $notes, $slug);

        if ($stmt->execute()) {
            $travelId = $stmt->insert_id;

            // Handle Locations
            if (!empty($_POST['locations'])) {
                $locations = json_decode($_POST['locations'], true);
                saveLocations($conn, $travelId, $locations);
            }

            // Handle Foods
            if (!empty($_POST['foods'])) {
                $foods = json_decode($_POST['foods'], true);
                saveFoods($conn, $travelId, $foods);
            }

            // Handle Facts
            if (!empty($_POST['facts'])) {
                $facts = json_decode($_POST['facts'], true);
                saveFacts($conn, $travelId, $facts);
            }

            // Handle Images
            handleImages($conn, $travelId);

            $conn->commit();
            echo json_encode(["success" => "New travel entry created successfully.", "slug" => $slug]);
        } else {
            $conn->rollback();
            echo json_encode(["error" => "Failed to insert travel entry."]);
        }

        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["error" => "Transaction failed: " . $e->getMessage()]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    // Get raw POST data
    $rawData = file_get_contents("php://input");

    // Decode JSON data
    $data = json_decode($rawData, true);

    // Check for JSON decoding errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["error" => "Invalid JSON format"]);
        exit;
    }

    // Extract fields from decoded data
    $travelId = $data['id'] ?? 0;
    $title = $data['title'] ?? '';
    $description = $data['description'] ?? '';
    $date = $data['date'] ?? '';
    $notes = $data['notes'] ?? '';

    // Validate required fields
    if (empty($travelId) || empty($title) || empty($description) || empty($date)) {
        echo json_encode(["error" => "ID, title, description, and date are required."]);
        exit;
    }

    // Sanitize input
    $travelId = (int)$travelId;
    $title = sanitizeInput($title);
    $description = sanitizeInput($description);
    $date = sanitizeInput($date);
    $notes = sanitizeInput($notes);
    $slug = createSlug($title);

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Prepare and execute update statement
        $stmt = $conn->prepare("UPDATE travels SET title = ?, description = ?, date = ?, notes = ?, slug = ? WHERE id = ?");
        handleSqlError($conn, $stmt);

        $stmt->bind_param("sssssi", $title, $description, $date, $notes, $slug, $travelId);

        if ($stmt->execute()) {
            // Handle Locations
            if (!empty($data['locations'])) {
                $locations = $data['locations'];
                saveLocations($conn, $travelId, $locations, true);
            }

            // Handle Foods
            if (!empty($data['foods'])) {
                $foods = $data['foods'];
                saveFoods($conn, $travelId, $foods, true);
            }

            // Handle Facts
            if (!empty($data['facts'])) {
                $facts = $data['facts'];
                saveFacts($conn, $travelId, $facts, true);
            }

            // Commit transaction
            $conn->commit();
            echo json_encode(["success" => "Travel entry updated successfully."]);
        } else {
            // Rollback transaction on failure
            $conn->rollback();
            echo json_encode(["error" => "Failed to update travel entry."]);
        }

        $stmt->close();
    } catch (Exception $e) {
        // Rollback transaction on exception
        $conn->rollback();
        echo json_encode(["error" => "Transaction failed: " . $e->getMessage()]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    // Handle DELETE request to remove a travel entry
    parse_str(file_get_contents("php://input"), $_DELETE);
    $travelId = (int)($_DELETE['id'] ?? 0);

    if (empty($travelId)) {
        echo json_encode(["error" => "Travel ID is required."]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM travels WHERE id = ?");
    handleSqlError($conn, $stmt);

    $stmt->bind_param("i", $travelId);

    if ($stmt->execute()) {
        echo json_encode(["success" => "Travel entry deleted successfully."]);
    } else {
        echo json_encode(["error" => "Failed to delete travel entry."]);
    }

    $stmt->close();
}

$conn->close();
?>