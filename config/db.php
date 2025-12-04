<?php
$host = "localhost";   // o "localhost"
$port = "3306";        // normalmente 3306
$dbname = "consejeria_db";
$user = "root";        // el mismo que usas en HeidiSQL
$pass = "";            // la contraseña de ese usuario (vacío si no tiene)

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
