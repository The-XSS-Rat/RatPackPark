<?php
$host = 'themeparkdb';
$dbname = 'themeparkdb';
$user = 'root';
$pass = 'root';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
