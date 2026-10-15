<?php
$host     = "thomas.proxy.rlwy.net";
$port     = "44322";
$dbname   = "railway";
$username = "root";
$password = "QEdoOFLywGDehODUiwWzxHPkUXmfXaZu";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["error" => "DB ulanmadi: " . $e->getMessage()]);
    exit;
}