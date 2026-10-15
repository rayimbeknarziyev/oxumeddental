<?php
require_once __DIR__ . "/../config/bootstrap.php";

$method = $_SERVER["REQUEST_METHOD"];

$user = require_role(["admin", "doctor", "accountant"]);

if ($method === "GET") {
    $list = $pdo->query("SELECT * FROM materials WHERE is_active = 1 ORDER BY name")->fetchAll();
    json_ok(["materials" => $list]);
}

require_role(["admin"]);

if ($method === "POST") {
    $b = get_json_body();
    $name = trim($b["name"] ?? "");
    if ($name === "") json_fail("Nom kerak.");
    $stmt = $pdo->prepare("INSERT INTO materials (name, unit, price, stock_qty) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $name,
        trim($b["unit"] ?? "dona"),
        (float)($b["price"] ?? 0),
        isset($b["stock_qty"]) && $b["stock_qty"] !== "" ? (float)$b["stock_qty"] : null,
    ]);
    json_ok(["id" => (int)$pdo->lastInsertId()], 201);
}

if ($method === "PUT") {
    $b = get_json_body();
    $id = (int)($b["id"] ?? 0);
    if (!$id) json_fail("id kerak.");
    $stmt = $pdo->prepare("UPDATE materials SET name = ?, unit = ?, price = ?, stock_qty = ?, is_active = ? WHERE id = ?");
    $stmt->execute([
        trim($b["name"] ?? ""),
        trim($b["unit"] ?? "dona"),
        (float)($b["price"] ?? 0),
        isset($b["stock_qty"]) && $b["stock_qty"] !== "" ? (float)$b["stock_qty"] : null,
        isset($b["is_active"]) ? (int)$b["is_active"] : 1,
        $id,
    ]);
    json_ok();
}

if ($method === "DELETE") {
    $id = (int)($_GET["id"] ?? 0);
    if (!$id) json_fail("id kerak.");
    $pdo->prepare("DELETE FROM materials WHERE id = ?")->execute([$id]);
    json_ok();
}

json_fail("Noma'lum so'rov.", 404);
