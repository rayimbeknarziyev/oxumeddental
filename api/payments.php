<?php
require_once __DIR__ . "/../config/bootstrap.php";

$method = $_SERVER["REQUEST_METHOD"];
$user = require_role(["admin", "accountant"]);

if ($method === "GET") {
    $rows = $pdo->query(
        "SELECT p.*, a.full_name AS author FROM payments p
         LEFT JOIN admins a ON a.id = p.created_by
         ORDER BY p.created_at DESC"
    )->fetchAll();
    json_ok(["payments" => $rows]);
}

if ($method === "POST") {
    $b = get_json_body();
    $type = ($b["type"] ?? "") === "expense" ? "expense" : "income";
    $amount = (float)($b["amount"] ?? 0);
    if ($amount <= 0) json_fail("Summani kiriting.");

    $stmt = $pdo->prepare(
        "INSERT INTO payments (type, amount, description, created_by, created_at) VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$type, $amount, trim($b["description"] ?? "") ?: null, $user["id"]]);
    json_ok(["id" => (int)$pdo->lastInsertId()], 201);
}

if ($method === "DELETE") {
    $id = (int)($_GET["id"] ?? 0);
    if (!$id) json_fail("id kerak.");
    $pdo->prepare("DELETE FROM payments WHERE id = ?")->execute([$id]);
    json_ok();
}

json_fail("Noma'lum so'rov.", 404);
