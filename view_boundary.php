<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Saved Boundaries</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background: #f0f2f5;
            color: #333;
        }
        header {
            background-color: #4A90E2;
            padding: 15px 20px;
            color: white;
            text-align: center;
            font-size: 24px;
        }
        .container {
            padding: 20px;
            max-width: 1200px;
            margin: auto;
        }
        #map {
            height: 60vh;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }
        th {
            background-color: #f9fafb;
            color: #555;
        }
        tr:hover {
            background-color: #f1f3f5;
        }
        button {
            background-color: #4A90E2;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #3a78c2;
        }
    </style>
</head>
<body>
<header>📍 My Saved Boundaries</header>
<div class="container">
    <div id="map"></div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Total Area (km²)</th>
                <th>Buildings</th>
                <th>Building Area</th>
                <th>Agriculture</th>
                <th>Empty Land</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $uid = $_SESSION['user_id'];
            $result = $conn->query("SELECT * FROM boundaries WHERE user_id = $uid ORDER BY created_at DESC");
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . $row['area_km2'] . "</td>";
                echo "<td>" . $row['building_count'] . "</td>";
                echo "<td>" . $row['building_area'] . "</td>";
                echo "<td>" . $row['agri_area'] . "</td>";
                echo "<td>" . $row['empty_area'] . "</td>";
                echo "<td>" . $row['created_at'] . "</td>";
                echo "<td><button onclick='loadBoundary(" . json_encode($row['geojson']) . ")'>View</button></td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>
<script>
let map = L.map('map').setView([11.41, 76.70], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Map data © OpenStreetMap contributors'
}).addTo(map);

let currentPolygon = null;
function loadBoundary(geojsonString) {
    const geo = JSON.parse(geojsonString);
    if (currentPolygon) map.removeLayer(currentPolygon);
    currentPolygon = L.geoJSON(geo, { style: { color: '#e74c3c', fillOpacity: 0.4 }}).addTo(map);
    map.fitBounds(currentPolygon.getBounds());
}
</script>
</body>
</html>
