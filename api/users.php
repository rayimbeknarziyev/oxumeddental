<?php
require_once __DIR__ . "/../config/bootstrap.php";

$method = $_SERVER["REQUEST_METHOD"];
require_role(["admin"]);

if ($method === "GET") {
    $list = $pdo->query(
        "SELECT id, full_name, email, role, position, photo, is_active, created_at FROM admins ORDER BY role, full_name"
    )->fetchAll();
    json_ok(["users" => $list]);
}

if ($method === "POST") {
    $b = get_json_body();
    $full_name = trim($b["full_name"] ?? "");
    $email = trim($b["email"] ?? "");
    $password = $b["password"] ?? "";
    $role = $b["role"] ?? "doctor";

    if ($full_name === "" || $email === "" || $password === "") {
        json_fail("F.I.Sh, email va parol kerak.");
    }
    if (!in_array($role, ["admin", "doctor", "accountant"], true)) {
        json_fail("Noto'g'ri rol.");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO admins (full_name, email, password, role, position) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$full_name, $email, $hash, $role, trim($b["position"] ?? "") ?: null]);
        json_ok(["id" => (int)$pdo->lastInsertId()], 201);
    } catch (PDOException $e) {
        json_fail("Bu email allaqachon mavjud yoki xatolik: " . $e->getMessage());
    }
}

if ($method === "PUT") {
    $b = get_json_body();
    $id = (int)($b["id"] ?? 0);
    if (!$id) json_fail("id kerak.");

    if (!empty($b["password"])) {
        $hash = password_hash($b["password"], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "UPDATE admins SET full_name=?, email=?, role=?, position=?, is_active=?, password=? WHERE id=?"
        );
        $stmt->execute([
            trim($b["full_name"] ?? ""), trim($b["email"] ?? ""), $b["role"] ?? "doctor",
            trim($b["position"] ?? "") ?: null, isset($b["is_active"]) ? (int)$b["is_active"] : 1,
            $hash, $id,
        ]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE admins SET full_name=?, email=?, role=?, position=?, is_active=? WHERE id=?"
        );
        $stmt->execute([
            trim($b["full_name"] ?? ""), trim($b["email"] ?? ""), $b["role"] ?? "doctor",
            trim($b["position"] ?? "") ?: null, isset($b["is_active"]) ? (int)$b["is_active"] : 1,
            $id,
        ]);
    }
    json_ok();
}

if ($method === "DELETE") {
    $id = (int)($_GET["id"] ?? 0);
    if (!$id) json_fail("id kerak.");
    $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$id]);
    json_ok();
}

json_fail("Noma'lum so'rov.", 404);
