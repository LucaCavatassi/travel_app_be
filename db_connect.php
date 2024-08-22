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
    die("Connection failed: " . $conn->connect_error);
}

// Set common headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Function to create a slug from a title
function createSlug($title) {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// Function to insert or update locations
function upsertLocations($conn, $travelId, $locations) {
    foreach ($locations as $location) {
        $name = isset($location['name']) ? $location['name'] : '';
        $rating = isset($location['rating']) ? (int)$location['rating'] : 0;
        $is_done = isset($location['is_done']) ? (bool)$location['is_done'] : false;
        $lat = isset($location['lat']) && is_numeric($location['lat']) ? $location['lat'] : 0;
        $long = isset($location['long']) && is_numeric($location['long']) ? $location['long'] : 0;

        if ($lat === null || $long === null) {
            error_log("Error: Latitude and/or longitude not provided for location: $name");
            continue; // Skip this location if lat or long are missing
        }

        // Prepare and execute the SQL statement
        $stmt = $conn->prepare("INSERT INTO locations (travel_id, name, rating, is_done, lat, `long`) 
            VALUES (?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                rating = VALUES(rating), 
                is_done = VALUES(is_done), 
                lat = VALUES(lat), 
                `long` = VALUES(`long`)");
        $stmt->bind_param("isiddd", $travelId, $name, $rating, $is_done, $lat, $long);

        if (!$stmt->execute()) {
            error_log('Insert/Update Error: ' . $stmt->error);
        }
    }
}

// Function to insert or update foods
function upsertFoods($conn, $travelId, $foods) {
    foreach ($foods as $food) {
        $title = $conn->real_escape_string($food['title']);
        $description = $conn->real_escape_string($food['description']);
        $rating = intval($food['rating']);
        $is_done = intval($food['is_done']);
        
        $sql = "INSERT INTO foods (travel_id, title, description, rating, is_done) 
                VALUES ($travelId, '$title', '$description', $rating, $is_done)
                ON DUPLICATE KEY UPDATE 
                    title = VALUES(title), 
                    description = VALUES(description), 
                    rating = VALUES(rating), 
                    is_done = VALUES(is_done)";
        if (!$conn->query($sql)) {
            throw new Exception("Error inserting/updating food: " . $conn->error);
        }
    }
}

// Function to insert or update facts
function upsertFacts($conn, $travelId, $facts) {
    foreach ($facts as $fact) {
        $title = $conn->real_escape_string($fact['title']);
        $description = $conn->real_escape_string($fact['description']);
        $is_done = intval($fact['is_done']);
        
        $sql = "INSERT INTO facts (travel_id, title, description, is_done) 
                VALUES ($travelId, '$title', '$description', $is_done)
                ON DUPLICATE KEY UPDATE 
                    title = VALUES(title), 
                    description = VALUES(description), 
                    is_done = VALUES(is_done)";
        if (!$conn->query($sql)) {
            throw new Exception("Error inserting/updating fact: " . $conn->error);
        }
    }
}

// Function to insert or update images
function upsertImages($conn, $travelId, $files) {
    for ($i = 0; $i < count($files['name']); $i++) {
        $imageUrl = 'uploads/' . basename($files['name'][$i]);
        if (move_uploaded_file($files['tmp_name'][$i], $imageUrl)) {
            $sql = "INSERT INTO images (travel_id, image_url) VALUES ($travelId, '$imageUrl')
                    ON DUPLICATE KEY UPDATE 
                        image_url = VALUES(image_url)";
            if (!$conn->query($sql)) {
                throw new Exception("Error inserting/updating image: " . $conn->error);
            }
        } else {
            throw new Exception("Error uploading image.");
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
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    echo json_encode($data);
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle POST request to insert new travel data
    $postData = file_get_contents('php://input');
    $data = json_decode($postData, true);

    $title = isset($data['title']) ? $conn->real_escape_string($data['title']) : '';
    $description = isset($data['description']) ? $conn->real_escape_string($data['description']) : '';
    $date = isset($data['date']) ? $conn->real_escape_string($data['date']) : '';
    $notes = isset($data['notes']) ? $conn->real_escape_string($data['notes']) : '';

    if (empty($title) || empty($description) || empty($date)) {
        echo json_encode(["error" => "Title, description, and date are required."]);
        exit;
    }

    $slug = createSlug($title);

    $conn->begin_transaction();

    try {
        $sql = "INSERT INTO travels (title, description, date, notes, slug) 
                VALUES ('$title', '$description', '$date', '$notes', '$slug')";
        if ($conn->query($sql) === TRUE) {
            $travelId = $conn->insert_id;

            $locations = isset($data['locations']) ? $data['locations'] : [];
            $foods = isset($data['foods']) ? $data['foods'] : [];
            $facts = isset($data['facts']) ? $data['facts'] : [];

            upsertLocations($conn, $travelId, $locations);
            upsertFoods($conn, $travelId, $foods);
            upsertFacts($conn, $travelId, $facts);

            if (isset($_FILES['images'])) {
                upsertImages($conn, $travelId, $_FILES['images']);
            }

            $conn->commit();
            echo json_encode(["success" => "New record created successfully."]);
        } else {
            throw new Exception("Error inserting travel: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["error" => $e->getMessage()]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    // Handle PUT request to update existing travel data
    parse_str(file_get_contents('php://input'), $putData);

    $travelId = isset($putData['id']) ? intval($putData['id']) : 0;
    $title = isset($putData['title']) ? $conn->real_escape_string($putData['title']) : '';
    $description = isset($putData['description']) ? $conn->real_escape_string($putData['description']) : '';
    $date = isset($putData['date']) ? $conn->real_escape_string($putData['date']) : '';
    $notes = isset($putData['notes']) ? $conn->real_escape_string($putData['notes']) : '';
    
    if (empty($travelId) || empty($title) || empty($description) || empty($date)) {
        echo json_encode(["error" => "ID, title, description, and date are required."]);
        exit;
    }

    $slug = createSlug($title);

    $conn->begin_transaction();

    try {
        $sql = "UPDATE travels 
                SET title='$title', description='$description', date='$date', notes='$notes', slug='$slug' 
                WHERE id=$travelId";
        if ($conn->query($sql) === TRUE) {
            $locations = isset($putData['locations']) ? $putData['locations'] : [];
            $foods = isset($putData['foods']) ? $putData['foods'] : [];
            $facts = isset($putData['facts']) ? $putData['facts'] : [];

            upsertLocations($conn, $travelId, $locations);
            upsertFoods($conn, $travelId, $foods);
            upsertFacts($conn, $travelId, $facts);

            if (isset($_FILES['images'])) {
                upsertImages($conn, $travelId, $_FILES['images']);
            }

            $conn->commit();
            echo json_encode(["success" => "Record updated successfully."]);
        } else {
            throw new Exception("Error updating travel: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// Close the connection
$conn->close();
?>