<?php
function json_ok($data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(["success" => true] + (is_array($data) ? $data : ["data" => $data]), JSON_UNESCAPED_UNICODE);
    exit;
}

function json_fail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(["success" => false, "error" => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
