<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

require 'includes/db.php';

$user_id = $_SESSION['user_id'];

$sql = "SELECT latitude, longitude FROM geo_points WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$points = [];
while ($row = $result->fetch_assoc()) {
    $points[] = $row;
}

header('Content-Type: application/json');
echo json_encode($points);
?>
    