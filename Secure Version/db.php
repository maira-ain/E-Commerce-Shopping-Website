<?php

declare(strict_types=1);

$host = '127.0.0.1';
$dbname = 'shopeasy_db';
$username = 'root';
$password = '';
$port = 3307;

$conn = mysqli_connect($host, $username, $password, $dbname, $port);

if (!$conn) {
    die('Database connection failed.');
}

mysqli_set_charset($conn, 'utf8mb4');
?>