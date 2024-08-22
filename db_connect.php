<?php
// Database connection settings
$servername = "127.0.0.1";  // Database host
$username = "root";  // Database username
$password = "root";  // Database password
$dbname = "travel_app_db";  // Database name
$port = 8889;  // Database port

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set common headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Function to create a slug from a title
function createSlug($title) {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}


// Function to insert locations
function insertLocations($conn, $travelId, $locations) {
    foreach ($locations as $location) {
        $name = isset($location['name']) ? $location['name'] : '';
        $rating = isset($location['rating']) ? (int)$location['rating'] : 0;
        $is_done = isset($location['is_done']) ? (bool)$location['is_done'] : false;
        $lat = isset($location['lat']) && is_numeric($location['lat']) ? $location['lat'] : 0;
        $long = isset($location['long']) && is_numeric($location['long']) ? $location['long'] : 0;

        // Ensure lat and long are not null
        if ($lat === null || $long === null) {
            error_log("Error: Latitude and/or longitude not provided for location: $name");
            continue; // Skip this location if lat or long are missing
        }

        // Prepare and execute the SQL statement
        $stmt = $conn->prepare("INSERT INTO locations (travel_id, name, rating, is_done, lat, `long`) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiddd", $travelId, $name, $rating, $is_done, $lat, $long);

        if (!$stmt->execute()) {
            error_log('Insert Error: ' . $stmt->error);
        }
    }
}

// Function to insert foods
function insertFoods($conn, $travelId, $foods) {
    foreach ($foods as $food) {
        $title = $conn->real_escape_string($food['title']);
        $description = $conn->real_escape_string($food['description']);
        $rating = intval($food['rating']);
        $is_done = intval($food['is_done']);
        
        $sql = "INSERT INTO foods (travel_id, title, description, rating, is_done) 
                VALUES ($travelId, '$title', '$description', $rating, $is_done)";
        if (!$conn->query($sql)) {
            throw new Exception("Error inserting food: " . $conn->error);
        }
    }
}

// Function to insert facts
function insertFacts($conn, $travelId, $facts) {
    foreach ($facts as $fact) {
        $title = $conn->real_escape_string($fact['title']);
        $description = $conn->real_escape_string($fact['description']);
        $is_done = intval($fact['is_done']);
        
        $sql = "INSERT INTO facts (travel_id, title, description, is_done) 
                VALUES ($travelId, '$title', '$description', $is_done)";
        if (!$conn->query($sql)) {
            throw new Exception("Error inserting fact: " . $conn->error);
        }
    }
}

// Function to insert images
function insertImages($conn, $travelId, $files) {
    for ($i = 0; $i < count($files['name']); $i++) {
        $imageUrl = 'uploads/' . basename($files['name'][$i]);
        if (move_uploaded_file($files['tmp_name'][$i], $imageUrl)) {
            $sql = "INSERT INTO images (travel_id, image_url) VALUES ($travelId, '$imageUrl')";
            if (!$conn->query($sql)) {
                throw new Exception("Error inserting image: " . $conn->error);
            }
        } else {
            throw new Exception("Error uploading image.");
        }
    }
}

// Handle GET and POST requests
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Fetch travel data
    $data = [];

    if (isset($_GET['slug'])) {
        $slug = $conn->real_escape_string($_GET['slug']);
        $sql = "SELECT * FROM travels WHERE slug = '$slug'";
    } elseif (isset($_GET['locations'])) {
        if ($_GET['locations'] === 'all') {
            $sql = "SELECT * FROM locations";  // Fetch all locations
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
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    echo json_encode($data);
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle POST request to insert new travel data
    // Get the raw POST data
    $postData = file_get_contents('php://input');
    $data = json_decode($postData, true);

    // Validate required fields
    $title = isset($data['title']) ? $conn->real_escape_string($data['title']) : '';
    $description = isset($data['description']) ? $conn->real_escape_string($data['description']) : '';
    $date = isset($data['date']) ? $conn->real_escape_string($data['date']) : '';
    $notes = isset($data['notes']) ? $conn->real_escape_string($data['notes']) : '';

    if (empty($title) || empty($description) || empty($date)) {
        echo json_encode(["error" => "Title, description, and date are required."]);
        exit;
    }

    // Create slug from title
    $slug = createSlug($title);

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Insert travel data
        $sql = "INSERT INTO travels (title, description, date, notes, slug) 
                VALUES ('$title', '$description', '$date', '$notes', '$slug')";
        if ($conn->query($sql) === TRUE) {
            $travelId = $conn->insert_id;

            // Insert associated data
            $locations = isset($data['locations']) ? $data['locations'] : [];
            $foods = isset($data['foods']) ? $data['foods'] : [];
            $facts = isset($data['facts']) ? $data['facts'] : [];

            insertLocations($conn, $travelId, $locations);
            insertFoods($conn, $travelId, $foods);
            insertFacts($conn, $travelId, $facts);

            // Insert images if available
            if (isset($_FILES['images'])) {
                insertImages($conn, $travelId, $_FILES['images']);
            }

            // Commit transaction
            $conn->commit();
            echo json_encode(["success" => "New record created successfully."]);
        } else {
            throw new Exception("Error inserting travel: " . $conn->error);
        }
    } catch (Exception $e) {
        // Rollback transaction in case of error
        $conn->rollback();
        echo json_encode(["error" => $e->getMessage()]);
    }
}

$conn->close();
?>