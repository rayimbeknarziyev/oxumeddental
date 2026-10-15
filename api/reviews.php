<?php
require_once __DIR__ . "/../config/bootstrap.php";

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET" && !isset($_GET["admin"])) {
    $list = $pdo->query(
        "SELECT * FROM reviews WHERE is_approved = 1 ORDER BY sort_order DESC, id DESC"
    )->fetchAll();
    json_ok(["reviews" => $list]);
}

if ($method === "POST" && !isset($_GET["admin"])) {
    $patient = require_patient();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE patient_id = ? AND review_date = CURDATE()");
    $stmt->execute([$patient["id"]]);
    if ((int)$stmt->fetchColumn() > 0) {
        json_fail("Kuniga faqat 1 marta sharh qoldirish mumkin. Ertaga qayta urinib ko'ring.", 429);
    }

    $b = get_json_body();
    $text = trim($b["review_text"] ?? "");
    if ($text === "") json_fail("Sharh matnini kiriting.");

    $stmt = $pdo->prepare(
        "INSERT INTO reviews (patient_id, author, review_text, review_date, rating, is_approved) VALUES (?, ?, ?, CURDATE(), ?, 0)"
    );
    $stmt->execute([$patient["id"], $patient["full_name"], $text, (int)($b["rating"] ?? 5)]);
    json_ok(["id" => (int)$pdo->lastInsertId(), "message" => "Rahmat! Sharhingiz tekshiruvdan so'ng chiqadi."], 201);
}

require_role(["admin"]);

if ($method === "GET") {
    json_ok(["reviews" => $pdo->query("SELECT * FROM reviews ORDER BY sort_order DESC, id DESC")->fetchAll()]);
}

if ($method === "PUT") {
    $b = get_json_body();
    $id = (int)($b["id"] ?? 0);
    if (!$id) json_fail("id kerak.");
    $stmt = $pdo->prepare(
        "UPDATE reviews SET author=?, review_text=?, review_date=?, rating=?, is_approved=? WHERE id=?"
    );
    $stmt->execute([
        trim($b["author"] ?? ""), trim($b["review_text"] ?? ""),
        $b["review_date"] ?? null, (int)($b["rating"] ?? 5),
        isset($b["is_approved"]) ? (int)$b["is_approved"] : 1, $id,
    ]);
    json_ok();
}

if ($method === "DELETE") {
    $id = (int)($_GET["id"] ?? 0);
    if (!$id) json_fail("id kerak.");
    $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$id]);
    json_ok();
}

json_fail("Noma'lum so'rov.", 404);
