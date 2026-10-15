<?php
require_once __DIR__ . "/../config/bootstrap.php";

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
    $user = current_user();
    if ($user && $user["role"] === "admin") {
        $list = $pdo->query(
            "SELECT d.id, d.full_name, d.position, d.birth_year, d.experience_years, d.photo, d.sort_order, d.is_active, d.admin_id, a.full_name AS account_name
             FROM doctors d LEFT JOIN admins a ON a.id = d.admin_id
             ORDER BY d.sort_order, d.full_name"
        )->fetchAll();
        json_ok(["doctors" => $list]);
    }
    $list = $pdo->query("SELECT id, full_name, position, birth_year, experience_years, photo, sort_order FROM doctors WHERE is_active = 1 ORDER BY sort_order, full_name")->fetchAll();
    json_ok(["doctors" => $list]);
}

require_role(["admin"]);

if ($method === "POST") {
    $b = get_json_body();
    $full_name = trim($b["full_name"] ?? "");
    $position  = trim($b["position"] ?? "");
    $photo     = trim($b["photo"] ?? "");
    $admin_id  = !empty($b["admin_id"]) ? (int)$b["admin_id"] : null;
    $birth_year = !empty($b["birth_year"]) ? (int)$b["birth_year"] : null;
    $experience_years = isset($b["experience_years"]) && $b["experience_years"] !== "" ? (int)$b["experience_years"] : null;

    if ($full_name === "") json_fail("Ism kiritilishi shart.");

    $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), -1) FROM doctors")->fetchColumn();
    $stmt = $pdo->prepare("INSERT INTO doctors (admin_id, full_name, position, birth_year, experience_years, photo, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$admin_id, $full_name, $position, $birth_year, $experience_years, $photo ?: null, $maxOrder + 1]);

    json_ok(["id" => (int)$pdo->lastInsertId()], 201);
}

if ($method === "PUT") {
    $b = get_json_body();
    $id = (int)($b["id"] ?? 0);
    if (!$id) json_fail("id kerak.");

    $stmt = $pdo->prepare("UPDATE doctors SET full_name = ?, position = ?, birth_year = ?, experience_years = ?, photo = ?, admin_id = ?, is_active = ? WHERE id = ?");
    $stmt->execute([
        trim($b["full_name"] ?? ""),
        trim($b["position"] ?? ""),
        !empty($b["birth_year"]) ? (int)$b["birth_year"] : null,
        isset($b["experience_years"]) && $b["experience_years"] !== "" ? (int)$b["experience_years"] : null,
        trim($b["photo"] ?? "") ?: null,
        !empty($b["admin_id"]) ? (int)$b["admin_id"] : null,
        isset($b["is_active"]) ? (int)$b["is_active"] : 1,
        $id,
    ]);
    json_ok();
}

if ($method === "DELETE") {
    $id = (int)($_GET["id"] ?? 0);
    if (!$id) json_fail("id kerak.");
    $pdo->prepare("DELETE FROM doctors WHERE id = ?")->execute([$id]);
    json_ok();
}

json_fail("Noma'lum so'rov.", 404);
