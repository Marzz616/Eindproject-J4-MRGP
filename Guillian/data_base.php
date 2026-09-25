<?php
// Verbinding met de database
$host = 'mysql';
$db   = 'mydb';
$user = 'student';
$pass = 'veiligwachtwoord';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Databaseverbinding mislukt: ' . htmlspecialchars($e->getMessage()));
}