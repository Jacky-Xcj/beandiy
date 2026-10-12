<?php
require __DIR__ . '/bootstrap.php';

$file = 'repo/data/settings.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $r = gh_read($file);
        // 空/缺失时返回 {} 而非 []，保证前端按对象处理
        echo json_encode(['data' => $r['content'] ?? new stdClass(), 'writable' => write_enabled()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    rate_limit('settings_put_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'));
    require_admin_key();
    require_write_access();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => '设置数据格式错误（应为对象）']);
        exit;
    }
    try {
        gh_write($file, $input, 'admin update settings');
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method not allowed']);
