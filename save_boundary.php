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
    <title>GeoTracker Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/leaflet-geometryutil"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background: #0f0c29;
            color: white;
        }
        #map {
            height: 70vh;
            width: 100%;
        }
        .buttons, .results {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px;
        }
        button, input[type=text] {
            padding: 10px 20px;
            border-radius: 25px;
            border: none;
            font-weight: bold;
        }
        button {
            background: linear-gradient(to right, #11998e, #38ef7d);
            color: white;
            cursor: pointer;
        }
        input[type=text] {
            width: 200px;
            background: #fff;
            color: #333;
        }
        .card {
            background: #1e1e2f;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            min-width: 200px;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
        }
        .card strong {
            display: block;
            margin-bottom: 10px;
            color: #ffdd57;
        }
    </style>
</head>
<body>
<h2 style="text-align:center; margin:10px;">Welcome, <?= $_SESSION['user_name'] ?>!</h2>
<div id="map"></div>
<div class="buttons">
    <button onclick="getCurrentLocation()">📍 Auto Locate</button>
    <button onclick="enableManualMode()">🖱 Manual Mark</button>
    <button onclick="drawBoundary()">🔲 Draw Boundary</button>
    <input type="text" id="boundary_name" placeholder="Boundary Name">
    <button onclick="analyzeBuildings()">🏠 Analyze Buildings</button>
    <button onclick="analyzeAgriculture()">🌾 Analyze Agriculture</button>
</div>
<div class="results">
    <div class="card"><strong>Total Area</strong><span id="total_area">0</span> km²</div>
    <div class="card"><strong>Buildings Count</strong><span id="building_count">0</span></div>
    <div class="card"><strong>Building Area</strong><span id="building_area">0</span> km²</div>
    <div class="card"><strong>Agricultural Area</strong><span id="agri_area">0</span> km²</div>
    <div class="card"><strong>Empty Land</strong><span id="empty_area">0</span> km²</div>
</div>
<script>
let map = L.map('map').setView([11.41, 76.70], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

let markers = [], points = [], polygon = null;
let manualMode = false;

function getCurrentLocation() {
    navigator.geolocation.getCurrentPosition(pos => {
        const { latitude, longitude } = pos.coords;
        addPoint([latitude, longitude]);
    });
}

function enableManualMode() {
    manualMode = true;
    map.on('click', e => addPoint([e.latlng.lat, e.latlng.lng]));
    alert("Manual mode enabled. Click on the map to add points.");
}

function addPoint(latlng) {
    points.push(latlng);
    const marker = L.marker(latlng).addTo(map);
    markers.push(marker);
}

function drawBoundary() {
    if (points.length < 3) return alert("Mark at least 3 points.");

    if (polygon) map.removeLayer(polygon);
    const closed = [...points, points[0]];
    polygon = L.polygon(closed, { color: 'blue', fillOpacity: 0.4 }).addTo(map);
    map.fitBounds(polygon.getBounds());

    const area = (L.GeometryUtil.geodesicArea(closed) / 1e6).toFixed(3);
    document.getElementById('total_area').innerText = area;

    const boundaryName = document.getElementById('boundary_name').value.trim();
    if (!boundaryName) return alert("Please enter a boundary name before drawing.");

    const geojson = polygon.toGeoJSON();
    const geoStr = JSON.stringify(geojson);

    fetch('save_boundary.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            name: boundaryName,
            geojson: geoStr,
            total_area: area,
            building_area: document.getElementById('building_area').innerText || 0,
            agri_area: document.getElementById('agri_area').innerText || 0,
            empty_area: document.getElementById('empty_area').innerText || 0,
            building_count: document.getElementById('building_count').innerText || 0
        })
    })
    .then(res => res.text())
    .then(msg => {
        console.log("Boundary Saved:", msg);
        alert("Boundary saved to database!");
    })
    .catch(err => {
        console.error("Save failed:", err);
        alert("Failed to save boundary.");
    });
}

async function analyzeBuildings() {
    if (!polygon) return alert("Draw a boundary first.");
    const geo = polygon.toGeoJSON();
    const coords = geo.geometry.coordinates[0].map(c => `${c[1]} ${c[0]}`).join(" ");

    const query = `[out:json][timeout:25];(way["building"](poly:"${coords}"););out body;>;out skel qt;`;
    try {
        const response = await fetch("https://overpass-api.de/api/interpreter", {
            method: "POST",
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ data: query })
        });

        const text = await response.text();
        if (!text.startsWith('{')) return alert("Overpass error or blocked. Try again later.");

        const data = JSON.parse(text);
        let totalBuildingArea = 0;
        let buildingCount = 0;
        const nodes = {};

        data.elements.forEach(el => {
            if (el.type === "node") nodes[el.id] = [el.lat, el.lon];
        });

        data.elements.forEach(el => {
            if (el.type === "way" && el.nodes.length > 2) {
                const latlngs = el.nodes.map(id => nodes[id]).filter(n => n);
                if (latlngs.length >= 3) {
                    const area = L.GeometryUtil.geodesicArea(latlngs.map(p => [p[0], p[1]]));
                    totalBuildingArea += area;
                    buildingCount++;
                }
            }
        });

        const buildingKm2 = (totalBuildingArea / 1e6).toFixed(3);
        const totalArea = parseFloat(document.getElementById('total_area').innerText);
        const agriArea = parseFloat(document.getElementById('agri_area').innerText);
        const emptyLand = (totalArea - buildingKm2 - agriArea).toFixed(3);

        document.getElementById('building_area').innerText = buildingKm2;
        document.getElementById('building_count').innerText = buildingCount;
        document.getElementById('empty_area').innerText = emptyLand;

        alert(`Buildings Detected: ${buildingCount}\nArea: ${buildingKm2} km²`);
    } catch (err) {
        console.error("Error analyzing buildings:", err);
        alert("Failed to fetch building data.");
    }
}

function analyzeAgriculture() {
    const agriKm2 = (Math.random() * 1).toFixed(3);
    document.getElementById('agri_area').innerText = agriKm2;

    const totalArea = parseFloat(document.getElementById('total_area').innerText);
    const buildingKm2 = parseFloat(document.getElementById('building_area').innerText);
    const emptyLand = (totalArea - buildingKm2 - agriKm2).toFixed(3);
    document.getElementById('empty_area').innerText = emptyLand;
}
</script>
</body>
</html>
