<?php
require_once __DIR__ . "/../config/bootstrap.php";

$method = $_SERVER["REQUEST_METHOD"];
$resource = $_GET["resource"] ?? "prices";

if ($method === "GET" && !isset($_GET["admin"])) {
    $categories = $pdo->query("SELECT * FROM price_categories ORDER BY sort_order, name")->fetchAll();
    $items = $pdo->query("SELECT * FROM prices WHERE is_active = 1 ORDER BY category_id, sort_order, name")->fetchAll();
    json_ok(["categories" => $categories, "prices" => $items]);
}

require_role(["admin"]);

if ($resource === "categories") {
    if ($method === "GET") {
        json_ok(["categories" => $pdo->query("SELECT * FROM price_categories ORDER BY sort_order, name")->fetchAll()]);
    }
    if ($method === "POST") {
        $b = get_json_body();
        $name = trim($b["name"] ?? "");
        if ($name === "") json_fail("Nom kerak.");
        $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), -1) FROM price_categories")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO price_categories (name, sort_order) VALUES (?, ?)");
        $stmt->execute([$name, $maxOrder + 1]);
        json_ok(["id" => (int)$pdo->lastInsertId()], 201);
    }
    if ($method === "PUT") {
        $b = get_json_body();
        $id = (int)($b["id"] ?? 0);
        if (!$id) json_fail("id kerak.");
        $pdo->prepare("UPDATE price_categories SET name = ? WHERE id = ?")->execute([trim($b["name"] ?? ""), $id]);
        json_ok();
    }
    if ($method === "DELETE") {
        $id = (int)($_GET["id"] ?? 0);
        if (!$id) json_fail("id kerak.");
        $pdo->prepare("DELETE FROM price_categories WHERE id = ?")->execute([$id]);
        json_ok();
    }
}

if ($resource === "prices") {
    if ($method === "POST") {
        $b = get_json_body();
        $category_id = (int)($b["category_id"] ?? 0);
        $name = trim($b["name"] ?? "");
        $price = (float)($b["price"] ?? 0);
        if (!$category_id || $name === "") json_fail("Kategoriya va nom kerak.");

        $maxOrder = (int)$pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM prices WHERE category_id = ?")
            ->execute([$category_id]);
        $stmtOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM prices WHERE category_id = ?");
        $stmtOrder->execute([$category_id]);
        $maxOrder = (int)$stmtOrder->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO prices (category_id, name, price, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$category_id, $name, $price, $maxOrder + 1]);
        json_ok(["id" => (int)$pdo->lastInsertId()], 201);
    }
    if ($method === "PUT") {
        $b = get_json_body();
        $id = (int)($b["id"] ?? 0);
        if (!$id) json_fail("id kerak.");
        $stmt = $pdo->prepare("UPDATE prices SET category_id = ?, name = ?, price = ?, is_active = ? WHERE id = ?");
        $stmt->execute([
            (int)($b["category_id"] ?? 0),
            trim($b["name"] ?? ""),
            (float)($b["price"] ?? 0),
            isset($b["is_active"]) ? (int)$b["is_active"] : 1,
            $id,
        ]);
        json_ok();
    }
    if ($method === "DELETE") {
        $id = (int)($_GET["id"] ?? 0);
        if (!$id) json_fail("id kerak.");
        $pdo->prepare("DELETE FROM prices WHERE id = ?")->execute([$id]);
        json_ok();
    }
}

json_fail("Noma'lum so'rov.", 404);
