<?php
require_once __DIR__ . "/../config/bootstrap.php";

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

if ($method === "POST" && $action === "login") {
    $body = get_json_body();
    $email = trim($body["email"] ?? "");
    $password = $body["password"] ?? "";

    if ($email === "" || $password === "") {
        json_fail("Email va parolni kiriting.");
    }

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();     

    if (!$user || !password_verify($password, $user["password"])) {
        json_fail("Email yoki parol noto'g'ri.", 401);
    }

    $_SESSION["admin_id"]       = $user["id"];
    $_SESSION["admin_name"]     = $user["full_name"];
    $_SESSION["admin_email"]    = $user["email"];
    $_SESSION["admin_role"]     = $user["role"];
    $_SESSION["admin_position"] = $user["position"] ?? "";

    json_ok(["user" => [
        "id"       => (int)$user["id"],
        "name"     => $user["full_name"],
        "email"    => $user["email"],
        "role"     => $user["role"],
        "position" => $user["position"],
    ]]);
}

if ($method === "POST" && $action === "logout") {
    $_SESSION = [];
    session_destroy();
    json_ok();
}

if ($method === "GET" && $action === "me") {
    $user = current_user();
    if (!$user) json_fail("Tizimga kirilmagan.", 401);
    json_ok(["user" => $user]);
}

if ($method === "POST" && $action === "update_profile") {
    $user = require_login();
    $b = get_json_body();
    $full_name = trim($b["full_name"] ?? "");
    $position  = trim($b["position"] ?? "");
    $password  = $b["password"] ?? "";
    $password2 = $b["password_confirm"] ?? "";

    if ($full_name === "") json_fail("F.I.Sh bo'sh bo'lishi mumkin emas.");
    if ($password !== "" && $password !== $password2) json_fail("Parollar mos emas.");

    if ($password !== "") {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE admins SET full_name = ?, position = ?, password = ? WHERE id = ?");
        $stmt->execute([$full_name, $position, $hash, $user["id"]]);
    } else {
        $stmt = $pdo->prepare("UPDATE admins SET full_name = ?, position = ? WHERE id = ?");
        $stmt->execute([$full_name, $position, $user["id"]]);
    }

    $_SESSION["admin_name"] = $full_name;
    $_SESSION["admin_position"] = $position;

    json_ok(["user" => current_user()]);
}

json_fail("Noma'lum action.", 404);
