<?php
require __DIR__ . '/bootstrap.php';

$file = 'repo/data/sessions.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $r = gh_read($file);
        echo json_encode(['data' => $r['content'] ?? []]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    rate_limit('sessions_put_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'));
    require_admin_key();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => '会话数据格式错误（应为数组）']);
        exit;
    }
    foreach ($input as $s) {
        if (!is_array($s) || !isset($s['id'], $s['station_id'], $s['status'])) {
            http_response_code(400);
            echo json_encode(['error' => '会话字段缺失（id/station_id/status）']);
            exit;
        }
    }
    try {
        gh_write($file, $input, 'admin update sessions');
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method not allowed']);
