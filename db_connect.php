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
        $name = $conn->real_escape_string($location['name']);
        $lat = $conn->real_escape_string($location['lat']);
        $lng = $conn->real_escape_string($location['lng']);
        $rating = intval($location['rating']);
        $is_done = intval($location['is_done']);

        $sql = "INSERT INTO locations (travel_id, name, lat, lng, rating, is_done) 
                VALUES ($travelId, '$name', '$lat', '$lng', $rating, $is_done)";
        if (!$conn->query($sql)) {
            echo json_encode(["error" => "Error inserting location: " . $conn->error]);
            return false;
        }
    }
    return true;
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
            echo json_encode(["error" => "Error inserting food: " . $conn->error]);
            return false;
        }
    }
    return true;
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
            echo json_encode(["error" => "Error inserting fact: " . $conn->error]);
            return false;
        }
    }
    return true;
}

// Function to insert images
function insertImages($conn, $travelId, $files) {
    for ($i = 0; $i < count($files['name']); $i++) {
        $imageUrl = 'uploads/' . basename($files['name'][$i]);
        if (move_uploaded_file($files['tmp_name'][$i], $imageUrl)) {
            $sql = "INSERT INTO images (travel_id, image_url) VALUES ($travelId, '$imageUrl')";
            if (!$conn->query($sql)) {
                echo json_encode(["error" => "Error inserting image: " . $conn->error]);
                return false;
            }
        } else {
            echo json_encode(["error" => "Error uploading image."]);
            return false;
        }
    }
    return true;
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

    // Insert travel data
    $sql = "INSERT INTO travels (title, description, date, notes, slug) 
            VALUES ('$title', '$description', '$date', '$notes', '$slug')";
    if ($conn->query($sql) === TRUE) {
        $travelId = $conn->insert_id;

        // Insert associated data
        $locations = isset($data['locations']) ? $data['locations'] : [];
        $foods = isset($data['foods']) ? $data['foods'] : [];
        $facts = isset($data['facts']) ? $data['facts'] : [];

        $success = insertLocations($conn, $travelId, $locations) &&
                   insertFoods($conn, $travelId, $foods) &&
                   insertFacts($conn, $travelId, $facts);

        // Insert images
        if ($success && isset($_FILES['images'])) {
            $success = insertImages($conn, $travelId, $_FILES['images']);
        }

        if ($success) {
            echo json_encode(["success" => "New record created successfully."]);
        } else {
            echo json_encode(["error" => "Failed to insert some data."]);
        }
    } else {
        echo json_encode(["error" => "Error inserting travel: " . $conn->error]);
    }
}

$conn->close();
?>