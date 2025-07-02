<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO geo_points (user_id, latitude, longitude) VALUES (?, ?, ?)");
    $stmt->bind_param("idd", $user_id, $lat, $lng);
    $stmt->execute();
    echo "Success";
}
