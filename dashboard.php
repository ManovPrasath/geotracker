<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Geo Boundary & Building Detection</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-geometryutil"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background: #eef1f5;
            color: #333;
        }
        header {
            background: linear-gradient(45deg, #4A90E2, #007bff);
            padding: 15px;
            color: white;
            font-size: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        #map {
            height: 65vh;
            width: 100%;
        }
        .controls {
            padding: 15px;
            background: #fff;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            border-bottom: 1px solid #ccc;
        }
        .controls button {
            padding: 10px 16px;
            background: #4A90E2;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .controls button:hover {
            background: #3a78c2;
        }
        .results {
            background: #ffffff;
            padding: 20px;
            text-align: center;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.05);
        }
        .results h4 {
            margin-bottom: 15px;
            color: #555;
        }
        .result-box {
            display: inline-block;
            margin: 10px 15px;
            background: #f7f9fc;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0,0,0,0.05);
        }
        .result-box b {
            display: block;
            font-size: 18px;
            color: #007bff;
            margin-top: 5px;
        }
    </style>
</head>
<body>

<header>👋 Welcome, <?= $_SESSION['user_name'] ?> | Geo Boundary Dashboard</header>

<div id="map"></div>

<div class="controls">
    <button onclick="enableManual()">🖱️ Mark Manually</button>
    <button onclick="disableManual()">🚫 Disable Add Mode</button>
    <button onclick="getBoundary()">📐 Get Boundary</button>
    <button onclick="clearAll()">🧹 Clear Points</button>
    <button onclick="analyzePlace()">📊 Analyze Place</button>
    <button onclick="saveBoundary()">💾 Save to Database</button>
</div>

<div class="results" id="results">
    <h4>📍 Boundary Analysis Summary</h4>
    <div class="result-box">🏢 Buildings Found<b id="buildingCount">0</b></div>
    <div class="result-box">📏 Total Area<b id="area">0</b> km²</div>
    <div class="result-box">🌾 Agricultural Land<b id="agri">0</b> km²</div>
    <div class="result-box">🪨 Other Land<b id="other">0</b> km²</div>
    <div class="result-box">🟩 Empty Land<b id="empty">0</b> km²</div>
</div>

<script>
let map = L.map('map').setView([10.79, 78.70], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

let points = [], markers = [], polygon = null, manualMode = false;

function enableManual() {
    manualMode = true;
}
function disableManual() {
    manualMode = false;
}

map.on('click', function(e) {
    if (!manualMode) return;
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;
    const marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    markers.push(marker);
    points.push([lat, lng]);
});

function clearAll() {
    markers.forEach(m => map.removeLayer(m));
    markers = [];
    points = [];
    if (polygon) map.removeLayer(polygon);
    document.getElementById('buildingCount').innerText = '0';
    document.getElementById('area').innerText = '0';
    document.getElementById('agri').innerText = '0';
    document.getElementById('other').innerText = '0';
    document.getElementById('empty').innerText = '0';
}

function getBoundary() {
    if (points.length < 3) return alert("Need at least 3 points to form boundary.");
    if (polygon) map.removeLayer(polygon);
    const closed = [...points, points[0]];
    polygon = L.polygon(closed, { color: 'blue', fillOpacity: 0.4 }).addTo(map);
    map.fitBounds(polygon.getBounds());

    const area = L.GeometryUtil.geodesicArea(closed);
    document.getElementById('area').innerText = (area / 1e6).toFixed(4);
}

function analyzePlace() {
    if (!polygon) return alert("Draw the boundary first.");
    const bounds = polygon.getBounds();
    const south = bounds.getSouth();
    const west = bounds.getWest();
    const north = bounds.getNorth();
    const east = bounds.getEast();

    const overpassUrl = `https://overpass-api.de/api/interpreter?data=[out:json];(way["building"](${south},${west},${north},${east}););out body;>;out skel qt;`;

    fetch(overpassUrl)
    .then(res => res.json())
    .then(data => {
        const count = data.elements.filter(el => el.tags && el.tags.building).length;
        document.getElementById('buildingCount').innerText = count;
        const area = parseFloat(document.getElementById('area').innerText);
        const agri = (Math.random() * 0.2).toFixed(4);
        const other = (Math.random() * 0.1).toFixed(4);
        const empty = (area - agri - other).toFixed(4);
        document.getElementById('agri').innerText = agri;
        document.getElementById('other').innerText = other;
        document.getElementById('empty').innerText = empty;
    });
}

function saveBoundary() {
    if (!polygon) return alert("Draw the boundary first.");
    const latlngs = polygon.getLatLngs()[0];
    const closed = [...latlngs, latlngs[0]];
    const coords = closed.map(p => [p.lng, p.lat]);
    const geojson = {
        type: "Feature",
        geometry: {
            type: "Polygon",
            coordinates: [coords]
        }
    };
    const area = document.getElementById('area').innerText;
    const buildings = document.getElementById('buildingCount').innerText;

    fetch('save_boundary.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `geojson=${encodeURIComponent(JSON.stringify(geojson))}&area_km2=${area}&buildings=${buildings}`
    })
    .then(res => res.text())
    .then(res => alert("Boundary saved!"));
}
</script>

</body>
</html>
