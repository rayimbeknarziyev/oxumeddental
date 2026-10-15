<?php
require_once __DIR__ . "/../config/bootstrap.php";

const SLOT_START_HOUR = 8;
const SLOT_END_HOUR = 18;

function slot_list(): array {
    $slots = [];
    for ($h = SLOT_START_HOUR; $h < SLOT_END_HOUR; $h++) {
        $slots[] = sprintf("%02d:00:00", $h);
    }
    return $slots;
}

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

if ($method === "GET" && $action === "slots") {
    $doctorId = (int)($_GET["doctor_id"] ?? 0);
    $date = trim($_GET["date"] ?? "");
    if (!$doctorId || $date === "") json_fail("doctor_id va date kerak.");

    $stmt = $pdo->prepare(
        "SELECT appointment_time FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status != 'cancelled'"
    );
    $stmt->execute([$doctorId, $date]);
    $taken = array_map(fn($r) => substr($r["appointment_time"], 0, 5), $stmt->fetchAll());

    $today = date("Y-m-d");
    $nowHour = (int)date("H");

    $slots = array_map(function ($t) use ($taken, $date, $today, $nowHour) {
        $hh = substr($t, 0, 5);
        $isPast = ($date === $today && (int)substr($t, 0, 2) <= $nowHour);
        return ["time" => $hh, "busy" => in_array($hh, $taken, true) || $isPast];
    }, slot_list());

    json_ok(["slots" => $slots]);
}

if ($method === "POST" && $action === "book") {
    $b = get_json_body();

    $doctorId = (int)($b["doctor_id"] ?? 0);
    $date = trim($b["appointment_date"] ?? "");
    $time = trim($b["appointment_time"] ?? "");
    if (preg_match('/^\d{2}:\d{2}$/', $time)) $time .= ":00";
    $note = trim($b["note"] ?? "");
    $patientName = trim($b["patient_name"] ?? "");
    $patientPhone = preg_replace('/[^\d+]/', '', trim($b["patient_phone"] ?? ""));

    if (!$doctorId || $date === "" || $time === "") {
        json_fail("Shifokor, sana va vaqtni tanlang.");
    }
    if (strlen($patientPhone) < 9) {
        json_fail("Telefon raqamingizni to'liq kiriting.");
    }
    if (!in_array($time, slot_list(), true)) {
        json_fail("Noto'g'ri vaqt tanlandi.");
    }
    if ($date < date("Y-m-d")) {
        json_fail("O'tgan sanaga yozib bo'lmaydi.");
    }
    if ($date === date("Y-m-d") && (int)substr($time, 0, 2) <= (int)date("H")) {
        json_fail("Bu vaqt allaqachon o'tib ketgan, boshqa vaqtni tanlang.");
    }

    $doctorCheck = $pdo->prepare("SELECT id FROM doctors WHERE id = ? AND is_active = 1");
    $doctorCheck->execute([$doctorId]);
    if (!$doctorCheck->fetch()) json_fail("Shifokor topilmadi.");

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO appointments (doctor_id, appointment_date, appointment_time, status, patient_name, patient_phone, note)
             VALUES (?, ?, ?, 'pending', ?, ?, ?)"
        );
        $stmt->execute([$doctorId, $date, $time, $patientName ?: null, $patientPhone, $note ?: null]);
        $apptId = (int)$pdo->lastInsertId();

        json_ok(["id" => $apptId, "message" => "Siz muvaffaqiyatli yozildingiz. Tez orada operatorimiz qo'ng'iroq qilib tasdiqlaydi."], 201);
    } catch (PDOException $e) {
        if ($e->getCode() === "23000") {
            json_fail("Afsuski bu vaqt hozirgina band qilindi. Boshqa vaqtni tanlang.", 409);
        }
        json_fail("Xatolik: " . $e->getMessage(), 500);
    }
}

if ($method === "GET" && $action === "by_phone") {
    $phone = preg_replace('/[^\d+]/', '', trim($_GET["phone"] ?? ""));
    if (strlen($phone) < 9) json_fail("Telefon raqamni to'liq kiriting.");

    $digits = preg_replace('/\D/', '', $phone);
    $stmt = $pdo->prepare(
        "SELECT a.id, a.appointment_date, a.appointment_time, a.status, d.full_name AS doctor_name, d.position AS doctor_position
         FROM appointments a
         JOIN doctors d ON d.id = a.doctor_id
         WHERE REPLACE(REPLACE(REPLACE(a.patient_phone, '+', ''), ' ', ''), '-', '') LIKE ?
         ORDER BY a.appointment_date DESC, a.appointment_time DESC"
    );
    $stmt->execute(["%" . $digits . "%"]);
    json_ok(["appointments" => $stmt->fetchAll()]);
}

if ($method === "GET" && $action === "mine") {
    $patient = require_patient();
    $stmt = $pdo->prepare(
        "SELECT a.*, d.full_name AS doctor_name FROM appointments a
         JOIN doctors d ON d.id = a.doctor_id
         WHERE a.patient_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC"
    );
    $stmt->execute([$patient["id"]]);
    json_ok(["appointments" => $stmt->fetchAll()]);
}

if ($method === "DELETE" && $action === "cancel") {
    $patient = require_patient();
    $id = (int)($_GET["id"] ?? 0);
    if (!$id) json_fail("id kerak.");

    $stmt = $pdo->prepare("SELECT patient_id FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row || (int)$row["patient_id"] !== $patient["id"]) json_fail("Topilmadi.", 404);

    $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?")->execute([$id]);
    json_ok();
}

if ($method === "GET" && $action === "") {
    $user = require_role(["admin", "doctor"]);

    $where = ["a.status != 'cancelled'"];
    $params = [];

    if ($user["role"] === "doctor") {
        $where[] = "d.admin_id = ?";
        $params[] = $user["id"];
        $where[] = "a.status = 'confirmed'";
    } elseif (!empty($_GET["doctor_id"])) {
        $where[] = "a.doctor_id = ?";
        $params[] = (int)$_GET["doctor_id"];
    }
    if (!empty($_GET["date"])) {
        $where[] = "a.appointment_date = ?";
        $params[] = $_GET["date"];
    }

    $sql = "SELECT a.*, d.full_name AS doctor_name,
                   COALESCE(p.full_name, a.patient_name, 'Onlayn yozilish') AS patient_name,
                   COALESCE(p.phone, a.patient_phone) AS patient_phone
            FROM appointments a
            JOIN doctors d ON d.id = a.doctor_id
            LEFT JOIN patients p ON p.id = a.patient_id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY a.appointment_date, a.appointment_time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_ok(["appointments" => $stmt->fetchAll()]);
}

if ($method === "PUT" && $action === "status") {
    $user = require_role(["admin", "doctor"]);
    $b = get_json_body();
    $id = (int)($b["id"] ?? 0);
    $status = $b["status"] ?? "";
    if (!$id || !in_array($status, ["pending", "confirmed", "cancelled"], true)) {
        json_fail("id va to'g'ri status kerak.");
    }

    if ($user["role"] === "doctor") {
        $check = $pdo->prepare(
            "SELECT a.id FROM appointments a JOIN doctors d ON d.id = a.doctor_id WHERE a.id = ? AND d.admin_id = ?"
        );
        $check->execute([$id, $user["id"]]);
        if (!$check->fetch()) json_fail("Ruxsat yo'q.", 403);
    }

    $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?")->execute([$status, $id]);
    json_ok();
}

json_fail("Noma'lum so'rov.", 404);
