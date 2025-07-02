<?php
$host = 'localhost';
$username = 'root';
$password = ''; // Default XAMPP password is blank
$database = 'geotracker';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
