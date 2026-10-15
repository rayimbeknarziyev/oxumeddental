<?php
require_once __DIR__ . "/../config/bootstrap.php";

$method = $_SERVER["REQUEST_METHOD"];
$user = require_role(["admin", "doctor"]);

if ($method === "GET") {
    if (isset($_GET["id"])) {
        $id = (int)$_GET["id"];
        $stmt = $pdo->prepare(
            "SELECT r.*, a.full_name AS doctor_name
             FROM doctor_reports r
             JOIN admins a ON a.id = r.doctor_id
             WHERE r.id = ?"
        );
        $stmt->execute([$id]);
        $report = $stmt->fetch();
        if (!$report) json_fail("Topilmadi.", 404);

        if ($user["role"] === "doctor" && (int)$report["doctor_id"] !== $user["id"]) {
            json_fail("Ruxsat yo'q.", 403);
        }

        $itemsStmt = $pdo->prepare("SELECT * FROM doctor_report_items WHERE report_id = ?");
        $itemsStmt->execute([$id]);
        $report["items"] = $itemsStmt->fetchAll();

        json_ok(["report" => $report]);
    }

    $where = [];
    $params = [];

    if ($user["role"] === "doctor") {
        $where[] = "r.doctor_id = ?";
        $params[] = $user["id"];
    } elseif (!empty($_GET["doctor_id"])) {
        $where[] = "r.doctor_id = ?";
        $params[] = (int)$_GET["doctor_id"];
    }

    if (!empty($_GET["date_from"])) {
        $where[] = "r.report_date >= ?";
        $params[] = $_GET["date_from"];
    }
    if (!empty($_GET["date_to"])) {
        $where[] = "r.report_date <= ?";
        $params[] = $_GET["date_to"];
    }

    $sql = "SELECT r.*, a.full_name AS doctor_name
            FROM doctor_reports r
            JOIN admins a ON a.id = r.doctor_id";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY r.report_date DESC, r.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_ok(["reports" => $stmt->fetchAll()]);
}

if ($method === "POST") {
    if ($user["role"] !== "doctor") json_fail("Faqat shifokor hisobot kiritadi.", 403);

    $b = get_json_body();
    $reportDate = trim($b["report_date"] ?? date("Y-m-d"));
    $items = $b["items"] ?? [];

    if (!is_array($items) || count($items) === 0) {
        json_fail("Kamida bitta xizmat yoki material kiritilishi kerak.");
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO doctor_reports (doctor_id, patient_name, report_date, note, total_amount) VALUES (?, ?, ?, ?, 0)"
        );
        $stmt->execute([$user["id"], trim($b["patient_name"] ?? "") ?: null, $reportDate, trim($b["note"] ?? "") ?: null]);
        $reportId = (int)$pdo->lastInsertId();

        $total = 0;
        $itemStmt = $pdo->prepare(
            "INSERT INTO doctor_report_items (report_id, item_type, item_id, item_name, quantity, unit_price, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($items as $it) {
            $type = ($it["item_type"] ?? "") === "material" ? "material" : "service";
            $qty = (float)($it["quantity"] ?? 1);
            $unitPrice = (float)($it["unit_price"] ?? 0);
            $lineTotal = $qty * $unitPrice;
            $total += $lineTotal;

            $itemStmt->execute([
                $reportId,
                $type,
                (int)($it["item_id"] ?? 0),
                trim($it["name"] ?? ""),
                $qty,
                $unitPrice,
                $lineTotal,
            ]);
        }

        $pdo->prepare("UPDATE doctor_reports SET total_amount = ? WHERE id = ?")->execute([$total, $reportId]);
        $pdo->commit();

        json_ok(["id" => $reportId, "total_amount" => $total], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_fail("Xatolik: " . $e->getMessage(), 500);
    }
}

if ($method === "DELETE") {
    $id = (int)($_GET["id"] ?? 0);
    if (!$id) json_fail("id kerak.");

    $stmt = $pdo->prepare("SELECT doctor_id FROM doctor_reports WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_fail("Topilmadi.", 404);

    if ($user["role"] !== "admin" && (int)$row["doctor_id"] !== $user["id"]) {
        json_fail("Ruxsat yo'q.", 403);
    }

    $pdo->prepare("DELETE FROM doctor_reports WHERE id = ?")->execute([$id]);
    json_ok();
}

json_fail("Noma'lum so'rov.", 404);
