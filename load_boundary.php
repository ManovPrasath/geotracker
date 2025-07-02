<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

require 'includes/db.php';

$user_id = $_SESSION['user_id'];

$sql = "SELECT geojson FROM boundaries WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$features = [];

while ($row = $result->fetch_assoc()) {
    $geo = json_decode($row['geojson'], true);
    if ($geo) {
        $features[] = $geo;
    }
}

echo json_encode([
    "type" => "FeatureCollection",
    "features" => $features
]);
?>
