<?php
$host = "localhost";
$dbname = "ecommerce_db";
$username = "root";
$password = ""; // XAMPP default is empty password

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
