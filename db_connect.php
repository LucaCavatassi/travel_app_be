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
    global $conn; // Assuming you have a global $conn for database connection
    // Convert the title to lowercase and replace spaces with hyphens
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

    // Check if the slug already exists in the database
    $stmt = $conn->prepare("SELECT COUNT(*) FROM travels WHERE slug = ?");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    // If the slug exists, append a unique identifier
    if ($count > 0) {
        $slug .= '-' . uniqid();
    }

    return $slug;
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
    foreach ($locations as $location) {
        $name = $location['name'] ?? '';
        $rating = (int)($location['rating'] ?? 0);
        $is_done = (int)($location['is_done'] ?? 0);
        $lat = (float)($location['lat'] ?? 0);
        $long = (float)($location['long'] ?? 0);
        $id = (int)($location['id'] ?? 0);

        if ($update && $id > 0) {
            // Update existing location
            $stmt = $conn->prepare("UPDATE locations SET name = ?, rating = ?, is_done = ?, lat = ?, `long` = ? WHERE id = ? AND travel_id = ?");
            if (!$stmt) {
                throw new Exception('Prepare Statement Error (Locations Update): ' . $conn->error);
            }
            $stmt->bind_param("siddiii", $name, $rating, $is_done, $lat, $long, $id, $travelId);
        } else {
            // Insert new location
            $stmt = $conn->prepare("INSERT INTO locations (travel_id, name, rating, is_done, lat, `long`) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception('Prepare Statement Error (Locations Insert): ' . $conn->error);
            }
            $stmt->bind_param("isiddd", $travelId, $name, $rating, $is_done, $lat, $long);
        }

        if (!$stmt->execute()) {
            throw new Exception('Execute Statement Error (Locations): ' . $stmt->error);
        }
        $stmt->close();
    }
}
function saveFoods($conn, $travelId, $foods, $update = false) {
    foreach ($foods as $food) {
        $title = $food['title'] ?? '';
        $description = $food['description'] ?? '';
        $rating = (int)($food['rating'] ?? 0);
        $is_done = (int)($food['is_done'] ?? 0);
        $id = (int)($food['id'] ?? 0);

        if ($update && $id > 0) {
            // Update existing food
            $stmt = $conn->prepare("UPDATE foods SET title = ?, description = ?, rating = ?, is_done = ? WHERE id = ? AND travel_id = ?");
            if (!$stmt) {
                throw new Exception('Prepare Statement Error (Foods Update): ' . $conn->error);
            }
            $stmt->bind_param("ssiiii", $title, $description, $rating, $is_done, $id, $travelId);
        } else {
            // Insert new food
            $stmt = $conn->prepare("INSERT INTO foods (travel_id, title, description, rating, is_done) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception('Prepare Statement Error (Foods Insert): ' . $conn->error);
            }
            $stmt->bind_param("issii", $travelId, $title, $description, $rating, $is_done);
        }

        if (!$stmt->execute()) {
            throw new Exception('Execute Statement Error (Foods): ' . $stmt->error);
        }
        $stmt->close();
    }
}

// Function to insert or update facts
function saveFacts($conn, $travelId, $facts, $update = false) {
    foreach ($facts as $fact) {
        $title = $fact['title'] ?? '';
        $description = $fact['description'] ?? '';
        $is_done = (int)($fact['is_done'] ?? 0);
        $id = (int)($fact['id'] ?? 0);

        if ($update && $id > 0) {
            // Update existing fact
            $stmt = $conn->prepare("UPDATE facts SET title = ?, description = ?, is_done = ? WHERE id = ? AND travel_id = ?");
            if (!$stmt) {
                throw new Exception('Prepare Statement Error (Facts Update): ' . $conn->error);
            }
            $stmt->bind_param("ssiii", $title, $description, $is_done, $id, $travelId);
        } else {
            // Insert new fact
            $stmt = $conn->prepare("INSERT INTO facts (travel_id, title, description, is_done) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception('Prepare Statement Error (Facts Insert): ' . $conn->error);
            }
            $stmt->bind_param("issi", $travelId, $title, $description, $is_done);
        }

        if (!$stmt->execute()) {
            throw new Exception('Execute Statement Error (Facts): ' . $stmt->error);
        }
        $stmt->close();
    }
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


// Function to get PUT data
function getPutData() {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        return $data;
    }

    error_log('JSON Decode Error: ' . json_last_error_msg());
    return [];
}

function updateTravel($conn, $data) {
    error_log('Raw PUT Data: ' . print_r($data, true));

    // Ensure 'id' is present
    if (!isset($data['id']) || empty($data['id'])) {
        error_log('ID not found in PUT data.');
        echo json_encode(["success" => false, "message" => "ID not found"]);
        return;
    }

    $travelId = (int)$data['id'];
    error_log('Received travelId: ' . $travelId);

    // Ensure 'title' is present
    if (!isset($data['title']) || empty($data['title'])) {
        error_log('Title not found in PUT data.');
        echo json_encode(["success" => false, "message" => "Title not found"]);
        return;
    }

    $title = $data['title'];
    $slug = createSlug($title);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update travel details including slug
        $stmt = $conn->prepare("UPDATE travels SET title = ?, description = ?, date = ?, notes = ?, slug = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Prepare Statement Error: ' . $conn->error);
        }

        $stmt->bind_param("sssssi", $title, $data['description'], $data['date'], $data['notes'], $slug, $travelId);

        if (!$stmt->execute()) {
            throw new Exception('Execute Statement Error (Travels Update): ' . $stmt->error);
        }
        $stmt->close();

        // Update locations
        saveLocations($conn, $travelId, $data['locations'] ?? [], true);

        // Update foods
        saveFoods($conn, $travelId, $data['foods'] ?? [], true);

        // Update facts
        saveFacts($conn, $travelId, $data['facts'] ?? [], true);
        

        // If all updates succeed, commit the transaction
        $conn->commit();

        echo json_encode(["success" => true, "slug" => $slug]);

    } catch (Exception $e) {
        // Rollback transaction if any of the updates fail
        $conn->rollback();

        // Log the error and return a failure message
        error_log('Transaction failed: ' . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Update failed. Transaction rolled back."]);
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
    }elseif (isset($_GET['images'])) {
        $travel_id = (int)$_GET['images'];
        $sql = "SELECT * FROM images WHERE travel_id = $travel_id";}    
    else {
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

}elseif($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = getPutData();
    updateTravel($conn, $data);
}elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
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