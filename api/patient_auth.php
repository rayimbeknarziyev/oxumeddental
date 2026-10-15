<?php
require_once __DIR__ . "/../config/bootstrap.php";

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

function normalize_phone(string $phone): string {
    $digits = preg_replace("/[^0-9+]/", "", $phone);
    return $digits;
}

if ($method === "POST" && $action === "register") {
    $b = get_json_body();
    $full_name = trim($b["full_name"] ?? "");
    $phone = normalize_phone($b["phone"] ?? "");
    $password = $b["password"] ?? "";

    if ($full_name === "" || $phone === "" || $password === "") {
        json_fail("F.I.Sh, telefon va parolni kiriting.");
    }
    if (strlen($password) < 6) {
        json_fail("Parol kamida 6 belgidan iborat bo'lishi kerak.");
    }

    $stmt = $pdo->prepare("SELECT id FROM patients WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        json_fail("Bu telefon raqami bilan foydalanuvchi allaqachon ro'yxatdan o'tgan.");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO patients (full_name, phone, password) VALUES (?, ?, ?)");
    $stmt->execute([$full_name, $phone, $hash]);
    $id = (int)$pdo->lastInsertId();

    $_SESSION["patient_id"] = $id;
    $_SESSION["patient_name"] = $full_name;
    $_SESSION["patient_phone"] = $phone;

    json_ok(["patient" => ["id" => $id, "full_name" => $full_name, "phone" => $phone]], 201);
}

if ($method === "POST" && $action === "login") {
    $b = get_json_body();
    $phone = normalize_phone($b["phone"] ?? "");
    $password = $b["password"] ?? "";

    if ($phone === "" || $password === "") {
        json_fail("Telefon va parolni kiriting.");
    }

    $stmt = $pdo->prepare("SELECT * FROM patients WHERE phone = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$phone]);
    $patient = $stmt->fetch();

    if (!$patient || !password_verify($password, $patient["password"])) {
        json_fail("Telefon yoki parol noto'g'ri.", 401);
    }

    $_SESSION["patient_id"] = $patient["id"];
    $_SESSION["patient_name"] = $patient["full_name"];
    $_SESSION["patient_phone"] = $patient["phone"];

    json_ok(["patient" => ["id" => (int)$patient["id"], "full_name" => $patient["full_name"], "phone" => $patient["phone"]]]);
}

if ($method === "POST" && $action === "logout") {
    unset($_SESSION["patient_id"], $_SESSION["patient_name"], $_SESSION["patient_phone"]);
    json_ok();
}

if ($method === "GET" && $action === "me") {
    $patient = current_patient();
    if (!$patient) json_fail("Tizimga kirilmagan.", 401);
    json_ok(["patient" => $patient]);
}

json_fail("Noma'lum action.", 404);
