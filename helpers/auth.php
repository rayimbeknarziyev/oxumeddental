<?php
function current_user(): ?array {
    if (!isset($_SESSION["admin_id"])) return null;
    return [
        "id"       => (int)$_SESSION["admin_id"],
        "name"     => $_SESSION["admin_name"] ?? "",
        "email"    => $_SESSION["admin_email"] ?? "",
        "role"     => $_SESSION["admin_role"] ?? "",
        "position" => $_SESSION["admin_position"] ?? "",
    ];
}

function require_role(array $roles): array {
    $user = current_user();
    if (!$user || !in_array($user["role"], $roles, true)) {
        json_fail("Ruxsat yo'q. Tizimga kiring.", 401);
    }
    return $user;
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        json_fail("Ruxsat yo'q. Tizimga kiring.", 401);
    }
    return $user;
}

function current_patient(): ?array {
    if (!isset($_SESSION["patient_id"])) return null;
    return [
        "id"        => (int)$_SESSION["patient_id"],
        "full_name" => $_SESSION["patient_name"] ?? "",
        "phone"     => $_SESSION["patient_phone"] ?? "",
    ];
}

function require_patient(): array {
    $patient = current_patient();
    if (!$patient) {
        json_fail("Bu amal uchun avval tizimga kiring.", 401);
    }
    return $patient;
}
